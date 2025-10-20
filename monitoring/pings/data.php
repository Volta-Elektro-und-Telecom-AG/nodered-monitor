<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

ini_set('memory_limit', '1024M');
set_time_limit(0);                // Kein Zeitlimit
ignore_user_abort(true);           // Keep running if browser disconnects

// Zeitzone auf UTC+2
date_default_timezone_set("Europe/Berlin");

$dir = __DIR__;
$file = basename($_GET['file'] ?? '');
$dateStr = $_GET['date'] ?? date('Y-m-d');

if (!$file || !preg_match('/\.log$/', $file)) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid file']);
    exit;
}

$path = $dir . '/' . $file;
if (!is_file($path)) {
    http_response_code(404);
    echo json_encode(['error' => 'File not found']);
    exit;
}

// Farbpalette
$palette = ['#e6194b','#3cb44b','#ffe119','#4363d8','#f58231','#911eb4',
            '#46f0f0','#f032e6','#bcf60c','#fabebe','#008080','#e6beff',
            '#9a6324','#fffac8','#800000','#aaffc3','#808000','#ffd8b1',
            '#000075','#808080'];
$colorIndex = 0;

// Funktion: Logdatei einlesen (zeilenweise, schonend für Pi)
function loadLogFile($path) {
    $handle = fopen($path, "rb");
    if (!$handle) return null;

    // Disable read buffering to prevent PHP from reading the whole file at once
    stream_set_read_buffer($handle, 0);

    $merged = null;
    $lineNumber = 0;

    while (!feof($handle)) {
        $line = fgets($handle);
        if ($line === false) break;

        $line = trim($line);
        if ($line === '') continue;

        $decoded = @json_decode($line, true);
        if ($decoded === null) continue;

        if (isset($decoded[0])) {
            if ($merged === null) {
                $merged = $decoded[0];
            } else {
                foreach ($decoded[0]['data'] as $i => $seriesData) {
                    if (!isset($merged['data'][$i])) $merged['data'][$i] = [];
                    $merged['data'][$i] = array_merge($merged['data'][$i], $seriesData);
                }
            }
        }

        // CPU Überhitzungsschutz alle 100 Zeilen
        if (++$lineNumber % 100 === 0) {
            gc_collect_cycles();
            usleep(50000);
        }
    }

    fclose($handle);
    return $merged ? [$merged] : null;
}

$data = loadLogFile($path);
if ($data === null) {
    http_response_code(500);
    echo json_encode(['error' => 'Could not parse file']);
    exit;
}

// Zeitfenster für Datumauswahl
$dayStart = strtotime($dateStr . ' 00:00:00');
$dayEnd   = strtotime($dateStr . ' 23:59:59');

// Sonderfälle 24h/7d
if (preg_match('/24h\.log$/', $file)) {
    $dayStart = 0;
    $dayEnd   = time(); 
}
if (preg_match('/7d\.log$/', $file)) {
    $dayStart = 0; 
    $dayEnd   = time();  
}

$result = [];
if (isset($data[0]['series']) && isset($data[0]['data'])) {
    foreach ($data[0]['series'] as $i => $host) {
        $points = []; // temporär für Sortierung

        $prevY = null;
        $bufferTime = null;
        $bufferY = null;
        $interval = 300; // 5 Minuten für Value-Änderungen
        $aggBucket = [];
        $aggBucketStart = null;

        foreach ($data[0]['data'][$i] as $point) {
            $time = round($point['x'] / 1000);
            if ($time < $dayStart || $time > $dayEnd) continue;
            $y = $point['y'];

            if ($prevY === null) {
                $points[] = ['time' => $time, 'value' => $y];
                $prevY = $y;
                continue;
            }

            if ($y === $prevY) {
                // gleiche Werte → buffer für first/last
                $bufferTime = $time;
                $bufferY = $y;

                // Bucket fertigstellen, falls offen
                if (!empty($aggBucket)) {
                    $maxVal = max($aggBucket);
                    $points[] = ['time' => $aggBucketStart, 'value' => $maxVal];
                    $aggBucket = [];
                    $aggBucketStart = null;
                }
            } else {
                // Wertänderung → Punkt in 5-Minuten-Buckets
                if ($aggBucketStart === null) $aggBucketStart = $time;
                $aggBucket[] = $y;

                if ($time - $aggBucketStart >= $interval) {
                    $maxVal = max($aggBucket);
                    $points[] = ['time' => $aggBucketStart, 'value' => $maxVal];
                    $aggBucketStart = $time;
                    $aggBucket = [$y];
                }

                // vorherigen buffer hinzufügen (letzter Punkt konstant)
                if ($bufferTime !== null) {
                    $points[] = ['time' => $bufferTime, 'value' => $bufferY];
                    $bufferTime = null;
                    $bufferY = null;
                }

                $prevY = $y;
            }
        }

        // Letzter buffer Punkt hinzufügen
        if ($bufferTime !== null) {
            $points[] = ['time' => $bufferTime, 'value' => $bufferY];
        }

        if (!empty($aggBucket)) {
            $maxVal = max($aggBucket);
            $points[] = ['time' => $aggBucketStart, 'value' => $maxVal];
        }

        // Punkte nach Zeit sortieren
        usort($points, function($a,$b){ return $a['time'] <=> $b['time']; });

        // Arrays für Chart.js
        $labels = [];
        $values = [];
        foreach ($points as $p) {
            $labels[] = date("H:i", $p['time']);
            $values[] = $p['value'];
        }

        $result[] = [
            'label' => $host,
            'labels' => $labels,
            'data' => $values,
            'color' => $palette[$colorIndex++ % count($palette)]
        ];
    }
}

header('Content-Type: application/json');
echo json_encode($result);
