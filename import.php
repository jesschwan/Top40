<?php
$csvDir = "CSV/";
$files = glob($csvDir . "*.csv");

$pdo = new PDO("mysql:host=localhost;dbname=top40db;charset=utf8mb4", "root", "");

// Bereite das Insert-Statement vor
$stmt = $pdo->prepare("INSERT INTO top40 (jahr, kw, platz, titel, interpret) VALUES (?, ?, ?, ?, ?)");

foreach ($files as $file) {
    $filename = basename($file);
    
    // Jahres- oder Wochen-Datei?
    if (preg_match('/(\d{4})-(\d{2})\.csv$/', $filename, $matches)) {
        $jahr = (int)$matches[1];
        $kw = (int)$matches[2];
    } elseif (preg_match('/(\d{4})\.csv$/', $filename, $matches)) {
        $jahr = (int)$matches[1];
        $kw = 0; // 0 steht für Jahrescharts
    } else {
        continue; // ungültiger Dateiname
    }

    $rows = array_map('str_getcsv', file($file));
    array_shift($rows); // Kopfzeile entfernen

    foreach ($rows as $row) {
        if (count($row) >= 3) {
            $platz = (int)$row[0];
            $titel = trim($row[1]);
            $interpret = trim($row[2]);

            $stmt->execute([$jahr, $kw, $platz, $titel, $interpret]);
        }
    }

    echo "Importiert: $filename<br>";
}

echo "Fertig!";
?>
