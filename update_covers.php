<?php
require_once "SqlConnection.php";

// Open database connection
$db = getSqlConnection();

// Path to local AVIF images
$folder = __DIR__ . '/images/';

// Get all AVIF files in the folder
$files = glob($folder . '*.avif');

$counterUpdated = 0;

echo "<!DOCTYPE html><html lang='de'><head><meta charset='UTF-8'><title>Cover Update</title></head><body>";
echo "<h1>🚀 Upload Covers to Database</h1>";
echo "<hr>";

// Loop through each AVIF file
foreach ($files as $filePath) {
    $basename = basename($filePath); // e.g. "Rock N Roll - Leony x G-Eazy.avif"
    $filenameNoExt = pathinfo($basename, PATHINFO_FILENAME);

    // Extract title and artist from filename
    $parts = explode(' - ', $filenameNoExt, 2);
    if (count($parts) !== 2) {
        echo "<p style='color:orange;'>⚠️ File skipped, invalid name: $basename</p>";
        continue;
    }

    [$title, $artist] = $parts;

    // Read file content as binary stream
    $coverData = file_get_contents($filePath);

    // Get song_id from songs table
    $stmtSong = $db->prepare("SELECT song_id FROM songs WHERE LOWER(song_name) = LOWER(?) AND LOWER(artist) = LOWER(?)");
    $stmtSong->bind_param("ss", $title, $artist);
    $stmtSong->execute();
    $stmtSong->bind_result($songId);
    $stmtSong->fetch();
    $stmtSong->close();

    if (!$songId) {
        echo "<p style='color:red;'>⚠️ No song found for: <strong>$title</strong> - <em>$artist</em></p>";
        continue;
    }

    // Update cover in songs table
    $stmtUpdate = $db->prepare("UPDATE songs SET cover_image = ? WHERE song_id = ?");
    if (!$stmtUpdate) {
        die("Error preparing statement: " . $db->error);
    }

    $stmtUpdate->bind_param("si", $coverData, $songId);
    $stmtUpdate->execute();

    if ($stmtUpdate->affected_rows > 0) {
        $counterUpdated++;
        echo "<p style='color:green;'>✅ Cover set for: <strong>$title</strong> - <em>$artist</em></p>";
    } else {
        echo "<p style='color:red;'>⚠️ Cover not updated for: <strong>$title</strong> - <em>$artist</em></p>";
    }

    $stmtUpdate->close();
}

echo "<hr><p><strong>$counterUpdated</strong> covers were successfully uploaded.</p>";
echo "</body></html>";
?>