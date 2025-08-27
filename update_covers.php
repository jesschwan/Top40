<?php
    require_once "SqlConnection.php";
    require_once "Top40Entry.php";
    $db = getSqlConnection();

    // Select all titles and artists from the top40 table (no DISTINCT to catch all entries)
    $sql = "SELECT titel, interpret FROM top40";
    $result = $db->query($sql);

    $counter = 0;            // Count of successfully updated entries
    $notFound = [];          // List of titles/artists for which no image file was found
    $alreadyProcessed = [];  // Array to avoid processing duplicate title-artist combinations

    echo "<!DOCTYPE html><html lang='en'><head><meta charset='UTF-8'><title>Cover Update</title></head><body>";
    echo "<h2>🎵 Cover Update for Top40</h2>";

    // Normalize strings for consistent comparison and filename generation
    function normalize($str) {
        $str = trim($str);                       // Remove whitespace from start/end
        $str = mb_strtolower($str);             // Convert to lowercase (multibyte safe)
        $str = str_replace(['ß'], ['ss'], $str); // Replace German sharp S with 'ss'
        $str = str_replace(['&'], ['and'], $str); // Replace ampersand with 'and'
        $str = preg_replace('/\s+/', ' ', $str);  // Reduce multiple spaces to one
        return $str;
    }

    // Iterate over all rows in the result
    while ($row = $result->fetch_assoc()) {
        $titelRaw = trim($row['titel']);
        $interpretRaw = trim($row['interpret']);

        // Skip already processed title-artist combinations to avoid duplicate updates
        $key = strtolower($titelRaw . '|' . $interpretRaw);
        if (isset($alreadyProcessed[$key])) {
            continue;
        }
        $alreadyProcessed[$key] = true;

        // Normalize strings for consistent processing
        $titel = normalize($titelRaw);
        $interpret = normalize($interpretRaw);

        // Create expected filename for the cover image
        $filename = getSafeFilename($titelRaw . ' - ' . $interpretRaw) . '.jpg';
        $filepath = __DIR__ . '/images/' . $filename;

        // If the lowercase .jpg file doesn't exist, check for uppercase .JPG extension
        if (!file_exists($filepath)) {
            $filenameAlt = $titelRaw . ' - ' . $interpretRaw . '.JPG';
            $filepathAlt = __DIR__ . '/images/' . $filenameAlt;
            if (file_exists($filepathAlt)) {
                $filename = $filenameAlt;
                $filepath = $filepathAlt;
            }
        }

        // If the image file exists, update the database cover field for matching title and artist
        if (file_exists($filepath)) {
            $stmt = $db->prepare("UPDATE top40 SET cover = ? WHERE titel = ? AND interpret = ?");
            if (!$stmt) {
                echo "<p style='color:red;'>❌ Error preparing statement: " . htmlspecialchars($db->error) . "</p>";
                continue;
            }

            // Bind parameters and execute update
            $stmt->bind_param("sss", $filename, $titelRaw, $interpretRaw);
            $stmt->execute();

            // Check how many rows were affected and provide feedback
            if ($stmt->affected_rows > 0) {
                $counter += $stmt->affected_rows;
                echo "<p style='color:green;'>✔ Cover updated for <strong>" . htmlspecialchars($titelRaw) . "</strong> - <em>" . htmlspecialchars($interpretRaw) . "</em> (" . $stmt->affected_rows . " entries)</p>";
            } else {
                echo "<p style='color:orange;'>⚠ No entry updated for: <strong>" . htmlspecialchars($titelRaw) . "</strong> - <em>" . htmlspecialchars($interpretRaw) . "</em></p>";
            }

            $stmt->close();
        } else {
            // Image file not found, add to list of missing covers
            $notFound[] = $titelRaw . ' - ' . $interpretRaw;
        }

        // Debug info about which title and artist are currently searched for
        echo "<p style='color:blue;'>🔍 Exact match search: <code>" . htmlspecialchars($titelRaw) . "</code> - <code>" . htmlspecialchars($interpretRaw) . "</code></p>";
    }

    echo "<hr><p><strong>$counter</strong> covers successfully updated.</p>";

    // List entries for which no cover image was found
    if (!empty($notFound)) {
        echo "<h3>🚫 No image found for:</h3><ul>";
        foreach ($notFound as $entry) {
            echo "<li>" . htmlspecialchars($entry) . "</li>";
        }
        echo "</ul>";
    }

    echo "</body></html>";
?>