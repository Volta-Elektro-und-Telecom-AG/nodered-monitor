<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

$dir = __DIR__;
$files = array_diff(scandir($dir), ['.', '..', 'index.php', 'pingfails.json']);
$topChartsFiles = [];
$otherChartsFiles = [];

// Unterteile Topcharts (24h/7d) und andere Logs
foreach ($files as $file) {
    if (!is_file($dir.'/'.$file) || !preg_match('/\.log$/', $file)) continue;
    if (preg_match('/(24h|7d)\.log$/', $file)) {
        $topChartsFiles[] = $file;
    } else {
        $otherChartsFiles[] = $file;
    }
}

// Pingfails einlesen
$pingfails = [];
$pfFile = $dir.'/pingfails.json';
if (is_file($pfFile)) {
    $pfJson = @file_get_contents($pfFile);
    if ($pfJson !== false) $pingfails = json_decode($pfJson, true) ?: [];
}
?>
<!DOCTYPE html>
<html lang="de">
<head>
<meta charset="UTF-8">
<title>VOLTA Monitoring</title>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<style>
* { font-family: Arial; }
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
    <img style="max-height:50px;" src="https://raw.githubusercontent.com/Volta-Elektro-und-Telecom-AG/nodered-monitor/refs/heads/main/volta_logo.webp" alt="Volta Logo">
</div>

<div style="margin: 10px 0;">
    <label for="dateSelect">Datum wählen:</label>
    <input type="date" id="dateSelect" value="<?php echo date('Y-m-d'); ?>">
</div>

<div class="container">
    <div class="status">
        <?php if ($pingfails): ?>
            <h2>Status Pingfails</h2>
            <table>
                <tr><th>Host</th><th>Fehlerzahl</th></tr>
                <?php foreach ($pingfails as $host => $fails): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($host); ?></td>
                        <td class="<?php echo $fails>0?'bad':'good'; ?>">
                            <?php echo (int)$fails; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </table>
        <?php endif; ?>
    </div>

    <div class="topcharts">
        <?php foreach ($topChartsFiles as $file): ?>
            <div>
                <h3><?php echo htmlspecialchars($file); ?></h3>
                <canvas id="chart_<?php echo md5($file); ?>" width="700" height="300"></canvas>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<hr>

<?php if ($otherChartsFiles): ?>
    <label for="logSelect">Wähle eine Log-Datei:</label>
    <select id="logSelect">
        <option value="">-- bitte auswählen --</option>
        <?php foreach ($otherChartsFiles as $file): ?>
            <option value="<?php echo htmlspecialchars($file); ?>"><?php echo htmlspecialchars($file); ?></option>
        <?php endforeach; ?>
    </select>

    <h2 id="chartTitle"></h2>
    <canvas id="chartCanvas" width="900" height="400"></canvas>
<?php endif; ?>

<script>
function renderChart(canvasId, cfgs) {
    if (!cfgs.length) return null;
    const ctx = document.getElementById(canvasId).getContext('2d');
    const datasets = cfgs.map(c=>({
        label: c.label,
        data: c.data,
        borderColor: c.color,
        backgroundColor: 'rgba(0,0,0,0)',
        tension: 0.2,
        fill: false
    }));
    return new Chart(ctx, {
        type: 'line',
        data: { labels: cfgs[0].labels, datasets },
        options: {
            responsive: true,
            interaction: { mode: 'nearest', intersect: false },
            scales: {
                x: { title: { display:true, text:'Zeit (UTC+2)' } },
                y: { title: { display:true, text:'Ping (ms)' }, beginAtZero:true }
            }
        }
    });
}

let currentCharts = {};
const today = document.getElementById('dateSelect').value;

// Topcharts direkt laden
<?php foreach ($topChartsFiles as $file): ?>
fetch('data.php?file=<?php echo urlencode($file); ?>&date=' + today)
    .then(res=>res.json())
    .then(data=>{
        currentCharts["<?php echo md5($file); ?>"] = renderChart("chart_<?php echo md5($file); ?>", data);
    });
<?php endforeach; ?>

// Datepicker Event
document.getElementById('dateSelect').addEventListener('change', function() {
    const dateStr = this.value;

    <?php foreach ($topChartsFiles as $file): ?>
    fetch('data.php?file=<?php echo urlencode($file); ?>&date=' + dateStr)
        .then(res=>res.json())
        .then(data=>{
            if(currentCharts["<?php echo md5($file); ?>"]) currentCharts["<?php echo md5($file); ?>"].destroy();
            currentCharts["<?php echo md5($file); ?>"] = renderChart("chart_<?php echo md5($file); ?>", data);
        });
    <?php endforeach; ?>

    // Detail-Chart neu laden falls ausgewählt
    const select = document.getElementById('logSelect');
    if(select.value) loadOtherChart(select.value, dateStr);
});

// Other Charts per Ajax laden
function loadOtherChart(file, dateStr) {
    fetch('data.php?file=' + encodeURIComponent(file) + '&date=' + dateStr)
        .then(res=>res.json())
        .then(data=>{
            if(currentCharts["detail"]) currentCharts["detail"].destroy();
            currentCharts["detail"] = renderChart('chartCanvas', data);
            document.getElementById('chartTitle').textContent = file;
        });
}

const logSelect = document.getElementById('logSelect');
if(logSelect){
    logSelect.addEventListener('change', function() {
        const file = this.value;
        const dateStr = document.getElementById('dateSelect').value;
        if(file) loadOtherChart(file, dateStr);
    });
}
</script>
</body>
</html>

