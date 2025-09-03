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

    $folder = __DIR__ . '/images/';
    $allFiles = array_merge(glob($folder . '*.jpg'), glob($folder . '*.JPG'));

    while ($row = $result->fetch_assoc()) {
        $titelRaw = trim($row['titel']);
        $interpretRaw = trim($row['interpret']);

        // Skip already processed title-artist combinations
        $key = strtolower($titelRaw . '|' . $interpretRaw);
        if (isset($alreadyProcessed[$key])) {
            continue;
        }
        $alreadyProcessed[$key] = true;

        // Create expected filename via the single Top40Entry->getSafeFilename()
        $entry = new Top40Entry(0, $titelRaw, $interpretRaw, null, 0, 0);
        $filename = $entry->getSafeFilename();
        $filepath = $folder . $filename;

        // If exact file exists -> update
        $actualBasename = null;
        if (file_exists($filepath)) {
            $actualBasename = $filename;
        } else {
            // Fallback: scan files and normalize each filename using Top40Entry->getSafeFilename()
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
                $normalizedFileName = $fileEntry->getSafeFilename();

                if (strcasecmp($normalizedFileName, $filename) === 0) {
                    $actualBasename = $basename;
                    $filepath = $filePath;
                    break;
                }
            }
        }

        // Update DB if we found a matching image
        if ($actualBasename !== null && file_exists($filepath)) {
            $stmt = $db->prepare("UPDATE top40 SET cover = ? WHERE titel = ? AND interpret = ?");
            if (!$stmt) {
                echo "<p style='color:red;'>❌ Error preparing statement: " . htmlspecialchars($db->error) . "</p>";
                continue;
            }

            $stmt->bind_param("sss", $actualBasename, $titelRaw, $interpretRaw);
            $stmt->execute();

            if ($stmt->affected_rows > 0) {
                $counter += $stmt->affected_rows;
                echo "<p style='color:green;'>✔ Cover updated for <strong>" . htmlspecialchars($titelRaw) . "</strong> - <em>" . htmlspecialchars($interpretRaw) . "</em> (" . $stmt->affected_rows . " entries) — file: <code>" . htmlspecialchars($actualBasename) . "</code></p>";
            } else {
                echo "<p style='color:orange;'>⚠ No entry updated for: <strong>" . htmlspecialchars($titelRaw) . "</strong> - <em>" . htmlspecialchars($interpretRaw) . "</em></p>";
            }

            $stmt->close();
        } else {
            $notFound[] = $titelRaw . ' - ' . $interpretRaw;
            echo "<p style='color:blue;'>🔍 Not found: <code>" . htmlspecialchars($titelRaw) . " - " . htmlspecialchars($interpretRaw) . "</code> (expected: <code>" . htmlspecialchars($filename) . "</code>)</p>";
        }
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