<?php
require_once "SqlConnection.php";

// Verbindung zur Datenbank
$db = getSqlConnection();

// Pfad zu den lokalen AVIF-Bildern
$folder = __DIR__ . '/images/';

// Alle AVIF-Dateien im Ordner
$files = glob($folder . '*.avif');

$counterUpdated = 0;

echo "<!DOCTYPE html><html lang='de'><head><meta charset='UTF-8'><title>Cover Update</title></head><body>";
echo "<h1>🚀 Cover Upload in die Datenbank</h1>";
echo "<hr>";

foreach ($files as $filePath) {
    $basename = basename($filePath); // z. B. "Rock N Roll - Leony x G-Eazy.avif"
    $filenameNoExt = pathinfo($basename, PATHINFO_FILENAME);

    // Titel und Interpret extrahieren
    $parts = explode(' - ', $filenameNoExt, 2);
    if (count($parts) !== 2) {
        echo "<p style='color:orange;'>⚠️ Datei übersprungen, ungültiger Name: $basename</p>";
        continue;
    }

    [$title, $artist] = $parts;

    // Dateiinhalt als Binary-Stream lesen
    $coverData = file_get_contents($filePath);

    // UPDATE in die Datenbank
    $stmt = $db->prepare("UPDATE top40 SET cover = ? WHERE titel = ? AND interpret = ?");
    if (!$stmt) {
        die("Fehler beim Vorbereiten des Statements: " . $db->error);
    }

    $stmt->bind_param("sss", $coverData, $title, $artist);
    $stmt->execute();

    if ($stmt->affected_rows > 0) {
        $counterUpdated++;
        echo "<p style='color:green;'>✅ Cover gesetzt für: <strong>$title</strong> - <em>$artist</em></p>";
    } else {
        echo "<p style='color:red;'>⚠️ Kein Datensatz gefunden für: <strong>$title</strong> - <em>$artist</em></p>";
    }

    $stmt->close();
}

echo "<hr><p><strong>$counterUpdated</strong> Cover wurden erfolgreich hochgeladen.</p>";
echo "</body></html>";
?>