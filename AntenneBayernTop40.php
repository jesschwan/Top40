<?php
    require "SqlConnection.php";
    require "functions.php";

    /*
    function sanitizeFilename($string) {
        // Erlaubt: Buchstaben, Zahlen, Leerzeichen, Klammern, Bindestriche, Apostroph, Punkte, deutsche Umlaute
        $clean = preg_replace('/[^A-Za-z0-9äöüÄÖÜß ()\'\-.,]/u', '', $string);

        // Mehrfache Leerzeichen auf eins reduzieren
        $clean = preg_replace('/\s+/', ' ', $clean);

        // Vorne und hinten trimmen
        return trim($clean);
    }
    */

    function renderTableRow($platz, $title, $interpret, $cover = null, $vorw = null, $diff = null) {
        // Wenn kein $cover gesetzt, direkt "Kein Bild gefunden" anzeigen
        if (!$cover) {
            $filename = null;
        } else {
            $filename = $cover;
        }

        if ($filename) {
            $filepath = __DIR__ . '/images/' . $filename;
            $bildGefunden = file_exists($filepath);
            $coverPath = 'images/' . rawurlencode($filename) . '?v=' . time();
        } else {
            $bildGefunden = false;
        }

        echo "<!-- Debug filename: " . ($filename ?? 'null') . " -->";

        echo "<tr>
            <td>$platz</td>
            <td>$title</td>
            <td>$interpret</td>
            <td>";

        if ($bildGefunden) {
            echo "<img src=\"$coverPath\" alt=\"Cover\" width=\"100\">";
        } else {
            echo "<span style='color:red;'>Kein Bild gefunden</span>";
        }

        echo "</td>";

        if ($vorw !== null && $diff !== null) {
            $diffClass = '';
            if (is_numeric($diff)) {
                if ($diff > 0) $diffClass = ' class="diff-up"';
                elseif ($diff < 0) $diffClass = ' class="diff-down"';
            }
            echo "<td>$vorw</td><td$diffClass>$diff</td>";
        } else {
            echo "<td></td><td></td>";
        }

        echo "</tr>";
    }

    $data = []; // Default value to avoid errors

    function getData4KW($openDbConnection, $year, $kw) {
        $KWDataArray = array();

        // SQL-Abfrage: Hole Platz, Titel, Interpret und Cover für Jahr und KW
        $query = "SELECT platz, titel, interpret, cover FROM top40 WHERE kw = ? AND jahr = ? ORDER BY platz LIMIT 40";

        $stmt = $openDbConnection->prepare($query);
        if (!$stmt) {
            die("Error preparing the query: " . $openDbConnection->error);
        }

        $stmt->bind_param("ii", $kw, $year);
        $stmt->execute();

        $result = $stmt->get_result();

        $currentRank = 0;

        while ($row = $result->fetch_assoc()) {
            if ($currentRank != $row["platz"]) {
                $KWDataArray[] = [
                    'platz' => $row["platz"],
                    'titel' => $row["titel"],
                    'interpret' => $row["interpret"],
                    'cover' => $row["cover"],  // Cover mitgeben
                    'kw' => $kw,
                    'jahr' => $year,
                    // 'vorw' => null,  // Hier kannst du Vorwoche einfügen
                    // 'diff' => null,  // Hier kannst du Diff einfügen
                ];
            }
            $currentRank = $row["platz"];
        }

        return $KWDataArray;
    }

    // Returns the next earlier year and week available in the DB, given a year and week
    function getNextEarlierWeek($openDbConnection, $year, $kw) {
        $current = $year * 100 + $kw;

        $stmt = $openDbConnection->prepare(" 
            SELECT jahr, kw 
            FROM top40 
            WHERE (jahr * 100 + kw) < ? 
            ORDER BY jahr DESC, kw DESC 
            LIMIT 1
        ");
        // WHERE (jahr * 100 + kw) < ?  -- Combines year and week into one number (e.g. 202523) to make it easier to compare dates
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


    // Show warning message if there is no previous week data
    function hasNoPreviousWeek($openDbConnection, $year, $kw) {
        $prev = getNextEarlierWeek($openDbConnection, $year, $kw);
        return !$prev; // Returns TRUE if NO previous week exists
    }

    // Get previous week's rank and difference for a given song and current rank
    function getPreviousChartPosition($title, $interpret, $year, $kw, $currentRank, $openDbConnection, $kwList) {
        // Check KW formatting in kwList, best like this:
        $searchKey = $year . '-' . str_pad($kw, 2, '0', STR_PAD_LEFT); // Combination of title and artist as search key
        $mappedKeys = array_map(fn($e) => $e['year'] . '-' . str_pad($e['kw'], 2, '0', STR_PAD_LEFT), $kwList); // Array of all songs from the previous week
        $currentIndex = array_search($searchKey, $mappedKeys); // Current position of the song this week
        if ($currentIndex === false) return ['prev' => 'ERR', 'diff' => 'ERR']; 

        $hasPrev = $currentIndex > 0; // If any data from last week is available
        $prevYear = $hasPrev ? (int)$kwList[$currentIndex - 1]['year'] : null; // Previous year
        $prevKW = $hasPrev ? (int)$kwList[$currentIndex - 1]['kw'] : null; // Previous KW

        if ($hasPrev) {
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
                $diff = $prevRank - $currentRank;  // Reverse sign as desired
                return ['prev' => $prevRank, 'diff' => (string)$diff];
            }
            $stmt->close();
        }

        // Find first week the song appeared
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

        if ((int)$firstYear === (int)$year && (int)$firstKW === (int)$kw) { // Year and calendar week of the current week
            return ['prev' => 'NEW', 'diff' => 'NEW'];
        }

        if ((int)$year === $firstDbYear && (int)$kw === $firstDbKW) { // First (oldest) week in the database
            return ['prev' => 'NEW', 'diff' => 'NEW'];
        }

        return ['prev' => 'RE', 'diff' => 'RE'];
    }

    // Get label for previous week to display in table header
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

    // open DB once
    $openDbConnection = getSqlConnection();

    $kwList = getKwList($openDbConnection);
    // Sort kwList ascending by year and week, with leading zeros for weeks
    usort($kwList, function ($a, $b) {
        if ($a['year'] === $b['year']) {
            // If year is equal, sort ascending by week
            return (int)$a['kw'] <=> (int)$b['kw'];
        }
        // Otherwise, sort ascending by year
        return (int)$a['year'] <=> (int)$b['year'];
    });

    // Get selected week and year from POST or default to latest
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

    $data = getData4KW($openDbConnection, $year, $kw);

    $prevWeekLabel = getPrevWeekLabel4Header($openDbConnection, $year, $kw);

    $selectedLabel = "KW" . str_pad($kw, 2, '0', STR_PAD_LEFT) . " / $year";

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
                        $cover = $row['cover']; // falls du das Cover brauchst

                        $previousData = getPreviousChartPosition($title, $interpret, $year, $kw, $platz, $openDbConnection, $kwList);
                        echo "<!-- Debug cover filename: " . htmlspecialchars($cover) . " -->";
                        renderTableRow($platz, $title, $interpret, $cover, $previousData['prev'], $previousData['diff']);
                    ?>
                <?php endforeach; ?>

            </table>
        <?php endif; ?>

    </body>

</html>