<?php
require_once "SqlConnection.php";
require_once "Top40Entry.php";

// Open database connection
$openDbConnection = getSqlConnection();

// Query distinct song titles and artists
$sql = "SELECT DISTINCT titel, interpret FROM top40";
$result = $openDbConnection->query($sql);
if (!$result) die("DB Error: " . $openDbConnection->error);

$folder = __DIR__ . '/images/';
$allFiles = array_merge(glob($folder . '*.jpg'), glob($folder . '*.JPG'));

$notMatched = [];

echo "<h1>Bildvergleich – mögliche Übereinstimmungen</h1>";

while ($row = $result->fetch_assoc()) {
    $title = $row['titel'];
    $artist = $row['interpret'];

    // Use the single Top40Entry::getSafeFilename() path
    $entry = new Top40Entry(0, $title, $artist, null, 0, 0);
    $expectedFilename = $entry->getSafeFilename();
    $expectedPath = $folder . $expectedFilename;

    // Exact file exists?
    if (file_exists($expectedPath)) {
        echo "✅ Bild gefunden: <code>" . htmlspecialchars($expectedFilename) . "</code><br>";
        continue;
    }

    // Fallback: scan existing files and normalize each filename using the same getSafeFilename()
    $matched = false;
    foreach ($allFiles as $filePath) {
        $basename = basename($filePath);
        $nameNoExt = pathinfo($basename, PATHINFO_FILENAME);

        // Normalize common dash variants to the ASCII hyphen so splitting works
        $nameNoExt = str_replace(["–", "—", "−"], " - ", $nameNoExt);

        if (strpos($nameNoExt, ' - ') !== false) {
            list($fTitle, $fArtist) = explode(' - ', $nameNoExt, 2);
        } else {
            // If no " - " found, treat whole filename as title
            $fTitle = $nameNoExt;
            $fArtist = '';
        }

        $fileEntry = new Top40Entry(0, $fTitle, $fArtist, null, 0, 0);
        $normalizedFileName = $fileEntry->getSafeFilename();

        if (strcasecmp($normalizedFileName, $expectedFilename) === 0) {
            echo "✅ Bild gefunden (Fallback): <code>" . htmlspecialchars($basename) . "</code> — mapped to <code>" . htmlspecialchars($expectedFilename) . "</code><br>";
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