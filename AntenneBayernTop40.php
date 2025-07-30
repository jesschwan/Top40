<?php
    require_once "SqlConnection.php";
    require "functions.php";

    // Sanitizes a filename by allowing only letters, numbers, spaces, parentheses,
    // hyphens, apostrophes, dots, and German umlauts. Removes other characters.
    function sanitizeFilename($string) {
        // Allowed characters: letters, numbers, spaces, parentheses, hyphens, apostrophes, dots, German umlauts
        $clean = preg_replace('/[^A-Za-z0-9äöüÄÖÜß ()\'\-.,]/u', '', $string);

        // Reduce multiple spaces to a single space
        $clean = preg_replace('/\s+/', ' ', $clean);

        // Trim spaces from start and end
        return trim($clean);
    }

    // Renders a table row with rank, title, artist, cover image, previous week position and difference
    function renderTableRow($platz, $title, $interpret, $cover = null, $vorw = null, $diff = null) {
        if ($cover) {
            $filename = $cover;
        } else {
            // Fallback filename if no cover is set in DB
            $filename = sanitizeFilename($title . ' - ' . $interpret) . '.jpg';
        }

        $filepath = __DIR__ . '/images/' . $filename;

        // Web path for HTML with cache-busting query parameter to avoid caching issues
        $coverPath = 'images/' . rawurlencode($filename) . '?v=' . time();

        // Check if image file exists on server
        $imageFound = file_exists($filepath);

        // Debug output to check filenames in HTML comments
        echo "<!-- Debug filename: $filename -->";

        // Start the table row
        echo "<tr>
            <td>$platz</td>
            <td>$title</td>
            <td>$interpret</td>
            <td>";

        // Show image if found, else show warning message
        if ($imageFound) {
            echo "<img src=\"$coverPath\" alt=\"Cover\" width=\"100\">";
        } else {
            echo "<span style='color:red;'>Kein Bild gefunden</span>"; // "No image found"
        }

        echo "</td>";

        // Show previous week's rank and difference if available
        if ($vorw !== null && $diff !== null) {
            $diffClass = '';
            if (is_numeric($diff)) {
                if ($diff > 0) $diffClass = ' class="diff-up"';   // Green if rank improved
                elseif ($diff < 0) $diffClass = ' class="diff-down"'; // Red if rank dropped
            }
            echo "<td>$vorw</td><td$diffClass>$diff</td>";
        } else {
            // Empty cells if previous week data not available
            echo "<td></td><td></td>";
        }

        // End table row
        echo "</tr>";
    }

    $data = []; // Default value to avoid errors if no data is fetched

    // Fetches chart data (rank, title, artist, cover) for a given year and calendar week
    function getData4KW($openDbConnection, $year, $kw) {
        $KWDataArray = array();

        // SQL query to get rank, title, artist, cover for specified year and week, ordered by rank
        $query = "SELECT platz, titel, interpret, cover FROM top40 WHERE kw = ? AND jahr = ? ORDER BY platz LIMIT 40";

        $stmt = $openDbConnection->prepare($query);
        if (!$stmt) {
            die("Error preparing the query: " . $openDbConnection->error);
        }

        $stmt->bind_param("ii", $kw, $year);
        $stmt->execute();

        $result = $stmt->get_result();

        $currentRank = 0;

        // Loop through results and avoid duplicates for the same rank
        while ($row = $result->fetch_assoc()) {
            if ($currentRank != $row["platz"]) {
                $KWDataArray[] = [
                    'platz' => $row["platz"],
                    'titel' => $row["titel"],
                    'interpret' => $row["interpret"],
                    'cover' => $row["cover"],  // Pass cover filename
                    'kw' => $kw,
                    'jahr' => $year,
                    // 'vorw' and 'diff' can be added here later if needed
                ];
            }
            $currentRank = $row["platz"];
        }

        return $KWDataArray;
    }

    // Returns the next earlier year and calendar week available in the DB, given a year and week
    function getNextEarlierWeek($openDbConnection, $year, $kw) {
        $current = $year * 100 + $kw;

        // Prepare SQL to find the closest earlier week than the current one
        $stmt = $openDbConnection->prepare(" 
            SELECT jahr, kw 
            FROM top40 
            WHERE (jahr * 100 + kw) < ? 
            ORDER BY jahr DESC, kw DESC 
            LIMIT 1
        ");
        // Combining year and week into a single integer for easy comparison (e.g. 202523)

        if (!$stmt) return null;

        $stmt->bind_param("i", $current);
        $stmt->execute();
        $stmt->bind_result($prevYear, $prevKw);

        if ($stmt->fetch()) {
            $stmt->close();
            return [$prevYear, $prevKw];
        }

        $stmt->close();
        return null;
    }

    // Returns TRUE if no previous week exists in DB for given year and week
    function hasNoPreviousWeek($openDbConnection, $year, $kw) {
        $prev = getNextEarlierWeek($openDbConnection, $year, $kw);
        return !$prev;
    }

    // Gets the previous week's rank and the difference in position for a given song and current rank
    function getPreviousChartPosition($title, $interpret, $year, $kw, $currentRank, $openDbConnection, $kwList) {
        // Construct search key for current week
        $searchKey = $year . '-' . str_pad($kw, 2, '0', STR_PAD_LEFT);

        // Map all keys (year-week) in kwList to find the current position
        $mappedKeys = array_map(fn($e) => $e['year'] . '-' . str_pad($e['kw'], 2, '0', STR_PAD_LEFT), $kwList);

        $currentIndex = array_search($searchKey, $mappedKeys);

        if ($currentIndex === false) return ['prev' => 'ERR', 'diff' => 'ERR']; 

        $hasPrev = $currentIndex > 0;
        $prevYear = $hasPrev ? (int)$kwList[$currentIndex - 1]['year'] : null;
        $prevKW = $hasPrev ? (int)$kwList[$currentIndex - 1]['kw'] : null;

        if ($hasPrev) {
            // Query DB for previous rank of the song
            $stmt = $openDbConnection->prepare("
                SELECT platz 
                FROM top40 
                WHERE LOWER(titel) = LOWER(?) 
                AND LOWER(interpret) = LOWER(?) 
                AND jahr = ? 
                AND kw = ?
            ");
            $stmt->bind_param("ssii", $title, $interpret, $prevYear, $prevKW);
            $stmt->execute();
            $stmt->bind_result($prevRank);

            if ($stmt->fetch()) {
                $stmt->close();
                $diff = $prevRank - $currentRank;  // Calculate difference (positive if rank improved)
                return ['prev' => $prevRank, 'diff' => (string)$diff];
            }
            $stmt->close();
        }

        // If no previous rank found, check if this is the first week the song appeared
        $stmt2 = $openDbConnection->prepare("
            SELECT jahr, kw 
            FROM top40 
            WHERE LOWER(titel) = LOWER(?) 
            AND LOWER(interpret) = LOWER(?) 
            ORDER BY jahr ASC, kw ASC 
            LIMIT 1
        ");
        $stmt2->bind_param("ss", $title, $interpret);
        $stmt2->execute();
        $stmt2->bind_result($firstYear, $firstKW);
        $stmt2->fetch();
        $stmt2->close();

        $firstDbYear = (int)$kwList[0]['year'];
        $firstDbKW   = (int)$kwList[0]['kw'];

        // If current week is the song's first appearance
        if ((int)$firstYear === (int)$year && (int)$firstKW === (int)$kw) {
            return ['prev' => 'NEW', 'diff' => 'NEW'];
        }

        // If current week is the oldest week in the DB but song appeared later
        if ((int)$year === $firstDbYear && (int)$kw === $firstDbKW) {
            return ['prev' => 'NEW', 'diff' => 'NEW'];
        }

        // Fallback return if no previous data found
        return ['prev' => 'RE', 'diff' => 'RE'];
    }

    // Returns a label for the previous week, used in table header
    function getPrevWeekLabel4Header($openDbConnection, $year, $kw) {
        $prevWeekInfo = getNextEarlierWeek($openDbConnection, $year, $kw);
        if ($prevWeekInfo) {
            [$prevYear, $prevKW] = $prevWeekInfo;
            $prevWeekLabel = "KW" . str_pad($prevKW, 2, '0', STR_PAD_LEFT) . " / " . $prevYear;
        } else {
            $prevWeekLabel = "No previous week";
        }
        return $prevWeekLabel;
    }

    // Open database connection once
    $openDbConnection = getSqlConnection();

    // Retrieve list of all available calendar weeks from DB
    $kwList = getKwList($openDbConnection);

    // Sort the list ascending by year and week (with leading zeros for week)
    usort($kwList, function ($a, $b) {
        if ($a['year'] === $b['year']) {
            return (int)$a['kw'] <=> (int)$b['kw'];
        }
        return (int)$a['year'] <=> (int)$b['year'];
    });

    // Get selected year and week from POST or default to the latest available
    $selectedKw = $_POST['kwYearDropDown'] ?? null;

    if ($selectedKw) {
        [$year, $kw] = explode('-', $selectedKw);
        $year = (int)$year;
        $kw = (int)$kw;
    } else {
        $latest = end($kwList);
        $year = (int)$latest['year'];
        $kw = (int)$latest['kw'];
    }

    // Fetch data for the selected year and week
    $data = getData4KW($openDbConnection, $year, $kw);

    // Get label for previous week to show in table header
    $prevWeekLabel = getPrevWeekLabel4Header($openDbConnection, $year, $kw);

    // Label for current selected week (e.g. "KW23 / 2025")
    $selectedLabel = "KW" . str_pad($kw, 2, '0', STR_PAD_LEFT) . " / $year";

    // Flag to show warning if no previous week data available
    $showWarning = hasNoPreviousWeek($openDbConnection, $year, $kw);

?>

<!-- HTML Code starts here ------------->

<!DOCTYPE html>
<html lang="de">
    <head>
        <meta charset="UTF-8">
        <title>Top 40</title>
        <style>
            body {
                font-family: Arial, sans-serif;
                text-align: center;
                padding-top: 25px;
                background-color: white;
                margin: 0;
            }
            h1 {
                font-size: 45px;
                position: sticky;
                top: 0;
                border-bottom: 1px solid lightgrey;
                padding: 10px;
                background-color: white;
                margin: 0;
                z-index: 10;
            }
            .warning {
                color: red;
                font-size: 50px;
                font-weight: bold;
                margin: 20px 0;
                text-align: center;
            }
            table {
                margin: auto;
                border-collapse: collapse;
                font-size: 30px;
                width: auto;
                color: black;
                background: white;
            }
            th, td {
                border: 2px solid black;
                padding: 10px;
            }
            td {
                height: 40px;
                width: 100px;
            }
            th {
                background-color: rgb(0, 0, 205);
                color: black;
                position: sticky;
                top: 55px;
                padding: 10px;
                z-index: 10;
            }
            tr td:first-child {
                font-weight: bold;
                background-color: rgb(0, 0, 205);
                color: black;
                text-align: center;
            }
            td:nth-child(2), th:nth-child(2),
            td:nth-child(3), th:nth-child(3) {
                text-align: left;
            }
            td:nth-child(4), th:nth-child(4),
            td:nth-child(5), th:nth-child(5) {
                text-align: center;
            }
            .form-container {
                display: flex;
                justify-content: center;
                gap: 20px;
                margin-bottom: 30px;
            }
            .dropdown, button, label {
                font-size: 30px;
                font-family: Arial;
                color: black;
                height: 50px;
                padding: 0 20px;
            }
            .dropdown, button {
                cursor: pointer;
            }
            button, label {
               font-weight: bold;
            }
            .dropdown, label {
                background-color: white;
            }
            .dropdown {
                border: 1px solid black;
            }
            button {
                background-color: #b4b4b4;
                border: 2px solid black;
            }
            label {
                font-weight: bold;
                color: black;
                font-family: Arial;
                display: flex;
                align-items: center;
                height: 50px;
            }
           .diff-up {
            color: green;
            font-weight: bold;
            }
            .diff-down {
                color: red;
                font-weight: bold;
            }
        </style>
    </head>

    <body>

        <div class="form-container">
            <form method="post" class="form-container">
                <label for="kwYearDropDown">Wähle:</label>
                <select name="kwYearDropDown" id="kwYearDropDown" class="dropdown">
                    <?php foreach ($kwList as $entry): 
                        $isSelected = ($entry['year'] == $year && $entry['kw'] == $kw) ? 'selected' : '';
                        $label = "KW" . str_pad($entry['kw'], 2, '0', STR_PAD_LEFT) . " / {$entry['year']}";
                        echo "<option value='{$entry['year']}-{$entry['kw']}' $isSelected>$label</option>";
                    endforeach; ?>
                </select>
                <button type="submit">Submit</button>
            </form>
        </div>

        <?php if (!$showWarning): ?>
            <h1>Top 40 – <?= htmlspecialchars($selectedLabel) ?></h1>
        <?php endif; ?>


        <?php if ($showWarning): ?>
            <div class="warning">Keine Daten der Vorwoche vorhanden!</div>
        <?php else: ?>
            <table>
                <tr>
                    <th>Platz</th><th>Titel</th><th>Interpret</th><th>Cover</th>
                    <th><?= htmlspecialchars($prevWeekLabel) ?></th><th>Diff.</th>
                </tr>
                
                <?php foreach ($data as $row): ?>
                    <?php
                        $platz = $row['platz'];
                        $title = $row['titel'];
                        $interpret = $row['interpret'];
                        $cover = $row['cover'];
                        $previousData = getPreviousChartPosition($title, $interpret, $year, $kw, $platz, $openDbConnection, $kwList);
                        renderTableRow($platz, $title, $interpret, $cover, $previousData['prev'], $previousData['diff']);
                    ?>
                <?php endforeach; ?>

            </table>
        <?php endif; ?>

    </body>

</html>