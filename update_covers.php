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

    // Prepare UPDATE statement for the database
    $stmt = $db->prepare("UPDATE songs SET cover_image = ? WHERE song_id = ?");
    $stmt->bind_param("si", $coverData, $songId);
    $stmt->execute();

    if (!$stmt) {
        die("Error preparing statement: " . $db->error);
    }

    // Check if any rows were updated
    if ($stmt->affected_rows > 0) {
        $counterUpdated++;
        echo "<p style='color:green;'>✅ Cover set for: <strong>$title</strong> - <em>$artist</em></p>";
    } else {
        echo "<p style='color:red;'>⚠️ No record found for: <strong>$title</strong> - <em>$artist</em></p>";
    }

    // Close the statement
    $stmt->close();
}

echo "<hr><p><strong>$counterUpdated</strong> covers were successfully uploaded.</p>";
echo "</body></html>";
?>