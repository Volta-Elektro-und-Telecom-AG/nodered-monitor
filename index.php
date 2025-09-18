<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

$dir = __DIR__;
$files = @array_diff(scandir($dir), ['.', '..', 'index.php', 'pingfails.json']);

// --- Hilfsfunktion: Logdatei einlesen und zusammenführen ---
function loadLogFile($path) {
    $content = @file_get_contents($path);
    if ($content === false) return null;

    $lines = preg_split('/\r\n|\r|\n/', trim($content));
    $merged = null;

    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '') continue;

        $decoded = @json_decode($line, true);
        if ($decoded === null) continue;

        // Format: [{"series":["host1",..],"data":[[...],[...]]}]
        if (isset($decoded[0])) {
            if ($merged === null) {
                $merged = $decoded[0]; // Erste Zeile übernehmen
            } else {
                if (isset($decoded[0]['data'])) {
                    foreach ($decoded[0]['data'] as $i => $seriesData) {
                        if (!isset($merged['data'][$i])) $merged['data'][$i] = [];
                        $merged['data'][$i] = array_merge($merged['data'][$i], $seriesData);
                    }
                }
            }
        }
    }

    return $merged ? [$merged] : null;
}

// Pingfails einlesen
$pingfails = [];
$pfFile = $dir . '/pingfails.json';
if (is_file($pfFile)) {
    $pfJson = @file_get_contents($pfFile);
    if ($pfJson !== false) {
        $pingfails = @json_decode($pfJson, true) ?: [];
    }
}

// Farbpalette für Hosts
$palette = ['#e6194b','#3cb44b','#ffe119','#4363d8','#f58231','#911eb4',
            '#46f0f0','#f032e6','#bcf60c','#fabebe','#008080','#e6beff',
            '#9a6324','#fffac8','#800000','#aaffc3','#808000','#ffd8b1',
            '#000075','#808080'];
$hostColors = [];
function assignColor($host, &$hostColors, $palette) {
    if (isset($hostColors[$host])) return $hostColors[$host];
    $color = $palette[count($hostColors) % count($palette)];
    $hostColors[$host] = $color;
    return $color;
}

$topCharts = [];
$otherCharts = [];

foreach ($files as $file) {
    $path = $dir . '/' . $file;
    if (!is_file($path)) continue;

    $data = loadLogFile($path);
    if ($data === null) continue;

    // === Mehr-Host-Logs (24h / 7d) ===
    if (preg_match('/(24h|7d)\.log$/', $file)) {
        if (isset($data[0]['series']) && isset($data[0]['data'])) {
            $series = $data[0]['series'];
            $allData = $data[0]['data'];
            $graphConfig = [];
            foreach ($series as $i => $host) {
                $labels = [];
                $values = [];
                foreach ($allData[$i] as $point) {
                    $time = round($point['x'] / 1000);
                    $labels[] = $time;
                    $values[] = $point['y'];
                }
                $graphConfig[] = [
                    'label' => $host,
                    'labels' => $labels,
                    'data' => $values,
                    'color' => assignColor($host, $hostColors, $palette)
                ];
            }
            $topCharts[$file] = $graphConfig;
        }
    } else {
        // === Einzel-Host-Logs (firewall.log → Host = firewall) ===
        $host = pathinfo($file, PATHINFO_FILENAME);
        $labels = [];
        $values = [];
        foreach ($data[0]['data'][0] as $point) {
            $time = round($point['x'] / 1000);
            $labels[] = $time;
            $values[] = $point['y'];
        }
        $otherCharts[$file] = [[
            'label' => $host,
            'labels' => $labels,
            'data' => $values,
            'color' => assignColor($host, $hostColors, $palette)
        ]];
    }
}
?>
<!DOCTYPE html>
<html lang="de">
<head>
<meta charset="UTF-8">
<title>VOLTA Monitoring</title>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<style>
* {font-family: arial; }
.container { display: flex; gap: 30px; align-items: flex-start; }
.status { flex: 1; }
.topcharts { flex: 2; display: flex; gap:20px; flex-wrap:wrap; }
canvas { background: #fff; border: 1px solid #ccc; }
table { border-collapse: collapse; margin-bottom: 20px; }
th, td { border: 1px solid #ccc; padding: 6px 10px; }
th { background: #f0f0f0; }
.bad { color: red; font-weight: bold; }
.good { color: green; }
.header { display:flex; justify-content:space-between; align-items:center;}
</style>
</head>
<body>
<div class="header">
        <h1>Ping Monitoring</h1>
        <img style="max-height:50px;" src="https://raw.githubusercontent.com/Volta-Elektro-und-Telecom-AG/nodered-monitor/refs/heads/main/volta_logo.webp" alt="Volta Elektro und Telecom AG">
</div>

<!-- Datepicker -->
<div style="margin: 10px 0;">
    <label for="dateSelect">Datum wählen:</label>
    <input type="date" id="dateSelect" value="<?php echo date('Y-m-d'); ?>">
</div>

<div class="container">
    <div class="status">
        <?php if ($pingfails): ?>
            <h2>Status Pingfails</h2>
            <table>
                <tr>
                    <th>Host</th>
                    <th>Fehlerzahl</th>
                </tr>
                <?php foreach ($pingfails as $host => $fails): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($host); ?></td>
                        <td class="<?php echo $fails > 0 ? 'bad' : 'good'; ?>">
                            <?php echo (int)$fails; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </table>
        <?php endif; ?>
    </div>

    <div class="topcharts">
        <?php foreach ($topCharts as $fname => $config): ?>
            <div>
                <h3><?php echo htmlspecialchars($fname); ?></h3>
                <canvas id="chart_<?php echo md5($fname); ?>" width="400" height="300"></canvas>
                <script>
                window.topChartData = window.topChartData || {};
                window.topChartData["<?php echo md5($fname); ?>"] = <?php echo json_encode($config); ?>;
                </script>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<hr>

<?php if ($otherCharts): ?>
    <label for="logSelect">Wähle eine Log-Datei:</label>
    <select id="logSelect">
        <option value="">-- bitte auswählen --</option>
        <?php foreach ($otherCharts as $file => $config): ?>
            <option value="<?php echo htmlspecialchars($file); ?>">
                <?php echo htmlspecialchars($file); ?>
            </option>
            <script>
            window.graphData = window.graphData || {};
            window.graphData["<?php echo addslashes($file); ?>"] = <?php echo json_encode($config); ?>;
            </script>
        <?php endforeach; ?>
    </select>

    <h2 id="chartTitle"></h2>
    <canvas id="chartCanvas" width="900" height="400"></canvas>
<?php endif; ?>

<script>
function filterByDate(cfgs, dateStr) {
    const dayStart = new Date(dateStr + "T00:00:00").getTime() / 1000;
    const dayEnd = new Date(dateStr + "T23:59:59").getTime() / 1000;

    return cfgs.map(c => {
        const filtered = c.labels.map((ts, i) => ({
            ts: ts,
            val: c.data[i]
        })).filter(p => p.ts >= dayStart && p.ts <= dayEnd);

        return {
            label: c.label,
            labels: filtered.map(p => new Date(p.ts * 1000).toLocaleTimeString("de-DE")),
            data: filtered.map(p => p.val),
            color: c.color
        };
    });
}

function renderChart(canvasId, cfgs, dateStr) {
    const filteredCfgs = filterByDate(cfgs, dateStr);
    if (!filteredCfgs.length) return null;

    const ctx = document.getElementById(canvasId).getContext('2d');
    const datasets = filteredCfgs.map(c => ({
        label: c.label,
        data: c.data,
        borderColor: c.color,
        backgroundColor: 'rgba(0,0,0,0)',
        tension: 0.2,
        fill: false
    }));

    return new Chart(ctx, {
        type: 'line',
        data: { labels: filteredCfgs[0].labels, datasets },
        options: {
            responsive: true,
            interaction: { mode: 'nearest', intersect: false },
            scales: {
                x: { title: { display: true, text: 'Zeit' } },
                y: { title: { display: true, text: 'Ping (ms)' }, beginAtZero: true }
            }
        }
    });
}

let currentCharts = {};

// Initial Charts zeichnen (heute)
const today = document.getElementById('dateSelect').value;
if (window.topChartData) {
    Object.keys(window.topChartData).forEach(key => {
        currentCharts[key] = renderChart("chart_" + key, window.topChartData[key], today);
    });
}

// Datepicker Event
document.getElementById('dateSelect').addEventListener('change', function() {
    const dateStr = this.value;
    // Top-Charts neu
    if (window.topChartData) {
        Object.keys(window.topChartData).forEach(key => {
            if (currentCharts[key]) currentCharts[key].destroy();
            currentCharts[key] = renderChart("chart_" + key, window.topChartData[key], dateStr);
        });
    }
    // Detail-Chart neu
    const file = document.getElementById('logSelect') ? document.getElementById('logSelect').value : "";
    if (file && window.graphData[file]) {
        if (currentCharts["detail"]) currentCharts["detail"].destroy();
        currentCharts["detail"] = renderChart("chartCanvas", window.graphData[file], dateStr);
        document.getElementById('chartTitle').textContent = file;
    }
});

// Detail-Dropdown Event
if (document.getElementById('logSelect')) {
    document.getElementById('logSelect').addEventListener('change', function() {
        const file = this.value;
        const dateStr = document.getElementById('dateSelect').value;
        if (!file || !window.graphData[file]) return;
        if (currentCharts["detail"]) currentCharts["detail"].destroy();
        currentCharts["detail"] = renderChart("chartCanvas", window.graphData[file], dateStr);
        document.getElementById('chartTitle').textContent = file;
    });
}
</script>
</body>
</html>
