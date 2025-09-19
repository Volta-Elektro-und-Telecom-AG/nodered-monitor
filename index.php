<?php
// Alle Unterordner im aktuellen Verzeichnis holen
$dirs = array_filter(glob('*'), 'is_dir');
?>

<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <title>Volta Dienste</title>
    <style>
        body {
            display: flex;
            flex-direction: column;
            justify-content: flex-start;
            align-items: center;
            min-height: 100vh;
            margin: 0;
            font-family: Arial, sans-serif;
            background: #f0f0f0;
        }
        header {
            margin: 40px 0 20px 0;
            text-align: center;
        }
        header img {
            max-width: 250px;
            height: auto;
        }
        .container {
            text-align: center;
            width: 100%;
        }
        .folder-button {
            display: block;
            width: 200px;
            margin: 10px auto;
            padding: 15px;
            font-size: 16px;
            text-decoration: none;
            color: #fff;
            background: #3498db;
            border-radius: 6px;
            transition: background 0.3s;
        }
        .folder-button:hover {
            background: #2980b9;
        }
    </style>
</head>
<body>
    <header>
        <img src="/volta_logo.webp" alt="Volta Logo">
    </header>
    <div class="container">
        <h1>Volta Dienste </h1>
        <?php foreach ($dirs as $dir): ?>
            <?php if (file_exists("$dir/index.php")): ?>
                <a class="folder-button" href="<?= "$dir/index.php" ?>"><?= ucfirst($dir) ?></a>
            <?php endif; ?>
        <?php endforeach; ?>
    </div>
</body>
</html>
