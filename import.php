<?php
// Directory with CSV files
$csvDir = "CSV/";
// Get all CSV files
$files = glob($csvDir . "*.csv");

// Database connection
$pdo = new PDO("mysql:host=localhost;dbname=top40db;charset=utf8mb4", "root", "");
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// Prepare INSERT statement
$stmt = $pdo->prepare("INSERT INTO top40 (jahr, kw, platz, titel, interpret) VALUES (?, ?, ?, ?, ?)");

// Loop through each file
foreach ($files as $file) {
    $filename = basename($file);

    if (preg_match('/(\d{4})-(\d{2})\.csv$/', $filename, $matches)) {
        $jahr = (int)$matches[1];
        $kw = (int)$matches[2];
    } elseif (preg_match('/(\d{4})\.csv$/', $filename, $matches)) {
        $jahr = (int)$matches[1];
        $kw = 0;
    } else {
        continue;
    }

    $rows = array_map('str_getcsv', file($file));
    array_shift($rows);

    foreach ($rows as $row) {
        if (count($row) >= 3) {
            $platz = (int)$row[0];
            $titel = trim($row[1]);
            $interpret = trim($row[2]);

            $stmt->execute([$jahr, $kw, $platz, $titel, $interpret]);
        }
    }

    echo "Imported: $filename<br>";
}

echo "Done!";
?>
