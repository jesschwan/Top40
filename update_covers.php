<?php
require "SqlConnection.php";

$db = getSqlConnection();

// Get all unique title-artist combinations from the top40 table
$sql = "SELECT DISTINCT titel, interpret FROM top40";
$result = $db->query($sql);

$counter = 0;
$notFound = [];

echo "<!DOCTYPE html><html lang='en'><head><meta charset='UTF-8'><title>Cover Update</title></head><body>";
echo "<h2>🎵 Cover Update for Top40</h2>";

function normalize($str) {
    $str = trim($str);
    $str = mb_strtolower($str);
    $str = str_replace(['ß'], ['ss'], $str);
    $str = str_replace(['&'], ['and'], $str);
    $str = preg_replace('/\s+/', ' ', $str);
    return $str;
}

while ($row = $result->fetch_assoc()) {
    $titelRaw = trim($row['titel']);
    $interpretRaw = trim($row['interpret']);

    $titel = normalize($titelRaw);
    $interpret = normalize($interpretRaw);

    // Construct the expected filename using ORIGINAL strings!
    $filename = $titelRaw . ' - ' . $interpretRaw . '.jpg';
    $filepath = __DIR__ . '/images/' . $filename;

    // Check uppercase .JPG fallback using ORIGINAL strings!
    if (!file_exists($filepath)) {
        $filenameAlt = $titelRaw . ' - ' . $interpretRaw . '.JPG';
        $filepathAlt = __DIR__ . '/images/' . $filenameAlt;
        if (file_exists($filepathAlt)) {
            $filename = $filenameAlt;
            $filepath = $filepathAlt;
        }
    }

    if (file_exists($filepath)) {
        // Use exact match in SQL for better accuracy (using original strings)
        $stmt = $db->prepare("UPDATE top40 SET cover = ? WHERE titel = ? AND interpret = ?");
        if (!$stmt) {
            echo "<p style='color:red;'>❌ Error preparing statement: " . htmlspecialchars($db->error) . "</p>";
            continue;
        }

        $stmt->bind_param("sss", $filename, $titelRaw, $interpretRaw);
        $stmt->execute();

        if ($stmt->affected_rows > 0) {
            $counter++;
            echo "<p style='color:green;'>✔ Cover updated for: <strong>" . htmlspecialchars($titelRaw) . "</strong> - <em>" . htmlspecialchars($interpretRaw) . "</em></p>";
        } else {
            echo "<p style='color:orange;'>⚠ No entry updated for: <strong>" . htmlspecialchars($titelRaw) . "</strong> - <em>" . htmlspecialchars($interpretRaw) . "</em></p>";
        }

        $stmt->close();
    } else {
        $notFound[] = $titelRaw . ' - ' . $interpretRaw;
    }

    // Debug output to show which entries are checked
    echo "<p style='color:blue;'>🔍 Exact match search: <code>" . htmlspecialchars($titelRaw) . "</code> - <code>" . htmlspecialchars($interpretRaw) . "</code></p>";
}

echo "<hr><p><strong>$counter</strong> covers successfully updated.</p>";

if (!empty($notFound)) {
    echo "<h3>🚫 No image found for:</h3><ul>";
    foreach ($notFound as $entry) {
        echo "<li>" . htmlspecialchars($entry) . "</li>";
    }
    echo "</ul>";
}

echo "</body></html>";
?>