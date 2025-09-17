<?php
require_once "SqlConnection.php";
require_once "Top40Entry.php";
require_once "ImageFromAPI.php";

$openDbConnection = getSqlConnection();

$sql = "SELECT DISTINCT titel, interpret FROM top40";
$result = $openDbConnection->query($sql);
if (!$result) die("DB Error: " . $openDbConnection->error);

$folder = __DIR__ . '/images/';

// Scan both AVIF and JPG files
$allFiles = [];
foreach (glob($folder . '*') as $file) {
    if (preg_match('/\.(avif|jpg)$/i', $file)) {
        $allFiles[] = $file;
    }
}

$notMatched = [];

echo "<h1>Bildvergleich – mögliche Übereinstimmungen</h1>";

while ($row = $result->fetch_assoc()) {
    $title = $row['titel'];
    $artist = $row['interpret'];

    $entry = new Top40Entry(0, $title, $artist, null, 0, 0);

    // Prefer AVIF, fallback to JPG
    $expectedFilenameAvif = $entry->getSafeFilename('avif');
    $expectedFilenameJpg  = $entry->getSafeFilename('jpg');
    $expectedPathAvif = $folder . $expectedFilenameAvif;
    $expectedPathJpg  = $folder . $expectedFilenameJpg;

    $expectedFilename = null;
    if (file_exists($expectedPathAvif)) {
        $expectedFilename = $expectedFilenameAvif;
    } elseif (file_exists($expectedPathJpg)) {
        $expectedFilename = $expectedFilenameJpg;
    }

    if ($expectedFilename !== null) {
        echo "✅ Bild gefunden: <code>" . htmlspecialchars($expectedFilename) . "</code><br>";
        continue;
    }

    // Fallback: scan all files and normalize
    $matched = false;
    foreach ($allFiles as $filePath) {
        $basename = basename($filePath);
        $nameNoExt = pathinfo($basename, PATHINFO_FILENAME);

        $nameNoExt = str_replace(["–", "—", "−"], " - ", $nameNoExt);

        if (strpos($nameNoExt, ' - ') !== false) {
            list($fTitle, $fArtist) = explode(' - ', $nameNoExt, 2);
        } else {
            $fTitle = $nameNoExt;
            $fArtist = '';
        }

        $fileEntry = new Top40Entry(0, $fTitle, $fArtist, null, 0, 0);

        // Compare both AVIF and JPG filenames
        if (strcasecmp($fileEntry->getSafeFilename('avif'), $entry->getSafeFilename('avif')) === 0 ||
            strcasecmp($fileEntry->getSafeFilename('jpg'), $entry->getSafeFilename('jpg')) === 0) {
            echo "✅ Bild gefunden (Fallback): <code>" . htmlspecialchars($basename) . "</code><br>";
            $matched = true;
            break;
        }
    }

    if (!$matched) {
        echo "⚠️ Keine Übereinstimmung gefunden für: <strong>" . htmlspecialchars($title) . " - " . htmlspecialchars($artist) . "</strong><br>";
        $notMatched[] = "$title - $artist";
    }
}

echo "<br><strong>Erledigt!</strong> Keine Dateien wurden verändert.<br>";

if (!empty($notMatched)) {
    echo "<h3>Keine Übereinstimmung gefunden für:</h3><ul>";
    foreach ($notMatched as $nm) {
        echo "<li>" . htmlspecialchars($nm) . "</li>";
    }
    echo "</ul>";
}
?>