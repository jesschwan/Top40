<?php
    require_once "SqlConnection.php";

    // Sanitize filename by removing forbidden Windows characters and reducing multiple spaces to one
    function sanitizeFilename($string) {
        // Remove forbidden Windows characters: \ / : * ? " < > |
        $clean = preg_replace('/[\\\\\/:*?"<>|]/', '', $string);
        // Reduce multiple spaces to one
        $clean = preg_replace('/\s+/', ' ', $clean);
        // Trim spaces at start and end
        return trim($clean);
    }

    // Open database connection
    $openDbConnection = getSqlConnection();

    // Query distinct song titles and artists from the top40 table
    $sql = "SELECT DISTINCT titel, interpret FROM top40";
    $result = $openDbConnection->query($sql);

    // Die if query fails, showing the DB error
    if (!$result) die("DB-Fehler: " . $openDbConnection->error);

    // Define folder path where images are stored
    $folder = __DIR__ . '/images/';

    // Get all jpg files (case insensitive) in the folder
    $allFiles = array_merge(glob($folder . '*.jpg'), glob($folder . '*.JPG'));

    $renamedCount = 0;   // Counter for renamed files (not used here, but reserved)
    $notMatched = [];    // Array to hold titles/artists for which no matching file was found

    echo "<h1>Bildvergleich – mögliche Übereinstimmungen</h1>";

    // Loop through each distinct song title and artist
    while ($row = $result->fetch_assoc()) {
        $title = $row['titel'];
        $artist = $row['interpret'];

        // Generate expected sanitized filename: "title - artist.jpg"
        $expectedFilename = sanitizeFilename("$title - $artist") . ".jpg";
        $expectedPath = $folder . $expectedFilename;

        // Skip if expected file already exists
        if (file_exists($expectedPath)) continue;

        $bestMatch = null;
        $highestSimilarity = 0;

        // Compare each file in the folder with expected "title - artist" string
        foreach ($allFiles as $filePath) {
            // Get filename without extension and replace underscores with spaces for better matching
            $filename = basename($filePath);
            $base = str_ireplace('_', ' ', pathinfo($filename, PATHINFO_FILENAME));
            $target = "$title - $artist";

            // Calculate similarity percentage between filename and target string
            similar_text($base, $target, $percent);

            // Update best match if similarity is above 70% and higher than previous best
            if ($percent > $highestSimilarity && $percent > 70) {
                $highestSimilarity = $percent;
                $bestMatch = $filePath;
            }
        }

        // Output possible match or warning if no match found
        if ($bestMatch) {
            echo "✅ Möglicher Treffer: <code>" . htmlspecialchars(basename($bestMatch)) . "</code> ➜ <code>$expectedFilename</code><br>";
        } else {
            echo "⚠️ Kein Match gefunden für: <strong>" . htmlspecialchars($title) . " - " . htmlspecialchars($artist) . "</strong><br>";
            $notMatched[] = "$title - $artist";
        }

    }

    echo "<br><strong>Fertig!</strong> Abgleich abgeschlossen – keine Dateien wurden verändert.<br>";

    // List all songs for which no matching image file was found
    if (!empty($notMatched)) {
        echo "<h3>Keine passenden Dateien gefunden für:</h3><ul>";
        foreach ($notMatched as $nm) {
            echo "<li>" . htmlspecialchars($nm) . "</li>";
        }
        echo "</ul>";
    }
?>