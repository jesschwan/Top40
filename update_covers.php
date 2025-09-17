<?php
require_once "SqlConnection.php";
require_once "Top40Entry.php";
require_once "ImageFromAPI.php";

$db = getSqlConnection();

$sql = "SELECT titel, interpret FROM top40";
$result = $db->query($sql);

$counterUpdated = 0;
$counterCleared = 0;
$alreadyProcessed = [];

echo "<!DOCTYPE html><html lang='de'><head><meta charset='UTF-8'><title>Cover Update</title></head><body>";
echo "<h2>Cover Update for Top40</h2>";

$folder = __DIR__ . '/images/';

while ($row = $result->fetch_assoc()) {
    $titelRaw = trim($row['titel']);
    $interpretRaw = trim($row['interpret']);

    // Skip duplicate combinations
    $key = strtolower($titelRaw . '|' . $interpretRaw);
    if (isset($alreadyProcessed[$key])) continue;
    $alreadyProcessed[$key] = true;

    $entry = new Top40Entry(0, $titelRaw, $interpretRaw, null, 0, 0);
    $baseName = pathinfo($entry->getSafeFilename('avif'), PATHINFO_FILENAME);

    $avifFile = $folder . $baseName . '.avif';
    $actualFilename = null;

    if (file_exists($avifFile)) {
        $actualFilename = $baseName . '.avif';
    }
    // following section writes Image-Path to DB
    if ($actualFilename !== null) {
        // AVIF gefunden → Cover setzen
        $stmt = $db->prepare("UPDATE top40 SET cover = ? WHERE titel = ? AND interpret = ?");
        if ($stmt) {
            $stmt->bind_param("sss", $actualFilename, $titelRaw, $interpretRaw);
            $stmt->execute();
            if ($stmt->affected_rows > 0) $counterUpdated += $stmt->affected_rows;
            $stmt->close();
            echo "<p style='color:green;'>✔ Updated: <strong>{$titelRaw}</strong> - <em>{$interpretRaw}</em> → <code>{$actualFilename}</code></p>";
        }
    } else {
        // Kein AVIF gefunden → Cover leeren
        $stmt = $db->prepare("UPDATE top40 SET cover = NULL WHERE titel = ? AND interpret = ?");
        if ($stmt) {
            $stmt->bind_param("ss", $titelRaw, $interpretRaw);
            $stmt->execute();
            if ($stmt->affected_rows > 0) $counterCleared += $stmt->affected_rows;
            $stmt->close();
            echo "<p style='color:red;'>✘ Cleared: <strong>{$titelRaw}</strong> - <em>{$interpretRaw}</em></p>";
        }
    }
}

echo "<hr><p><strong>$counterUpdated</strong> covers updated (AVIF gesetzt).</p>";
echo "<p><strong>$counterCleared</strong> entries cleared (kein AVIF gefunden).</p>";

echo "</body></html>";