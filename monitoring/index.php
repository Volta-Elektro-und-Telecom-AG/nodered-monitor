<?php
// --- System Metrics ---
// CPU usage
$cpuLoad = sys_getloadavg()[0];

// Memory usage
$memInfo = file_get_contents("/proc/meminfo");
preg_match("/MemTotal:\s+(\d+)/", $memInfo, $totalMem);
preg_match("/MemAvailable:\s+(\d+)/", $memInfo, $availableMem);
$usedMem = $totalMem[1] - $availableMem[1];

// Disk usage
$diskTotal = disk_total_space("/");
$diskFree = disk_free_space("/");
$diskUsed = $diskTotal - $diskFree;

// Temperature
$temp = exec("vcgencmd measure_temp");

// --- Dynamic Navbar ---
// Scan current directory for subdirectories
$dirs = array_filter(glob('*'), 'is_dir');
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Raspberry Pi Health Monitor</title>
    <style>
        body { 
            font-family: Arial, sans-serif; 
            margin: 0; 
            padding: 0; 
            background: #f5f5f5; 
            color: #333; 
        }
        header {
            background: #2c3e50; 
            padding: 15px 20px; 
            color: #fff;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        header h1 { margin: 0; font-size: 1.5em; }
        nav a {
            color: #fff; 
            text-decoration: none; 
            margin-left: 15px; 
            padding: 5px 10px; 
            border-radius: 4px; 
            transition: background 0.3s;
        }
        nav a:hover { background: #34495e; }
        main { padding: 20px; }
        .metric {
            background: #fff;
            padding: 15px;
            margin-bottom: 10px;
            border-radius: 6px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }
        .metric strong { display: inline-block; width: 120px; }
    </style>
</head>
<body>
    <header>
        <h1>Raspberry Pi Health</h1>
        <nav>
            <?php foreach ($dirs as $dir): ?>
                <?php if (file_exists("$dir/index.php")): ?>
                    <a href="<?= "$dir/index.php" ?>"><?= ucfirst($dir) ?></a>
                <?php endif; ?>
            <?php endforeach; ?>
        </nav>
    </header>

    <main>
        <div class="metric"><strong>CPU Load:</strong> <?= $cpuLoad ?>%</div>
        <div class="metric"><strong>Memory Usage:</strong> <?= round($usedMem / 1024) ?> MB / <?= round($totalMem[1] / 1024) ?> MB</div>
        <div class="metric"><strong>Disk Usage:</strong> <?= round($diskUsed / 1024 / 1024 / 1024, 2) ?> GB / <?= round($diskTotal / 1024 / 1024 / 1024, 2) ?> GB</div>
        <div class="metric"><strong>Temperature:</strong> <?= $temp ?></div>
    </main>
</body>
</html>
