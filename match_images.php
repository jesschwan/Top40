<?php
require_once "SqlConnection.php";
require_once "Top40Entry.php";
require_once "ImageFromAPI.php";

// Open database connection
$openDbConnection = getSqlConnection();

// Fetch all distinct songs from the Top 40
$sql = "SELECT DISTINCT titel, interpret FROM top40";
$result = $openDbConnection->query($sql);
if (!$result) die("DB Error: " . $openDbConnection->error);

// Folder containing images (can be omitted if not used)
$folder = __DIR__ . '/images/';

// Scan all AVIF and JPG files in the folder
$allFiles = [];
foreach (glob($folder . '*') as $file) {
    if (preg_match('/\.(avif|jpg)$/i', $file)) {
        $allFiles[] = $file;
    }
}

$notMatched = [];

// Display header
echo "<h1>Image Comparison – Possible Matches</h1>";

// Loop through all songs
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

    // If a matching file exists, display it and continue
    if ($expectedFilename !== null) {
        echo "✅ Image found: <code>" . htmlspecialchars($expectedFilename) . "</code><br>";
        continue;
    }

    // Fallback: scan all files and normalize names
    $matched = false;
    foreach ($allFiles as $filePath) {
        $basename = basename($filePath);
        $nameNoExt = pathinfo($basename, PATHINFO_FILENAME);

        // Normalize dash characters
        $nameNoExt = str_replace(["–", "—", "−"], " - ", $nameNoExt);

        // Split into title and artist if possible
        if (strpos($nameNoExt, ' - ') !== false) {
            list($fTitle, $fArtist) = explode(' - ', $nameNoExt, 2);
        } else {
            $fTitle = $nameNoExt;
            $fArtist = '';
        }

        $fileEntry = new Top40Entry(0, $fTitle, $fArtist, null, 0, 0);

        // Compare AVIF and JPG filenames
        if (strcasecmp($fileEntry->getSafeFilename('avif'), $entry->getSafeFilename('avif')) === 0 ||
            strcasecmp($fileEntry->getSafeFilename('jpg'), $entry->getSafeFilename('jpg')) === 0) {
            echo "✅ Image found (Fallback): <code>" . htmlspecialchars($basename) . "</code><br>";
            $matched = true;
            break;
        }
    }

    // If no match found, add to the list
    if (!$matched) {
        echo "⚠️ No match found for: <strong>" . htmlspecialchars($title) . " - " . htmlspecialchars($artist) . "</strong><br>";
        $notMatched[] = "$title - $artist";
    }
}

// Finished message
echo "<br><strong>Done!</strong> No files were changed.<br>";

// Display all unmatched entries
if (!empty($notMatched)) {
    echo "<h3>No match found for:</h3><ul>";
    foreach ($notMatched as $nm) {
        echo "<li>" . htmlspecialchars($nm) . "</li>";
    }
    echo "</ul>";
}
?>
