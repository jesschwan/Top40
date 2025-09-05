<?php
    require_once "Top40Entry.php";
    require_once "SqlConnection.php";
    require "functions.php";

    function getData4KW($openDbConnection, $year, $kw, $kwList) {
        $KWDataArray = [];

        $query = "SELECT platz, titel, interpret, cover 
                FROM top40 
                WHERE kw = ? AND jahr = ? 
                ORDER BY platz LIMIT 40";
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
                // Here we save the object in $entry
                $entry = new Top40Entry(
                    (int)$row["platz"],
                    $row["titel"],
                    $row["interpret"],
                    $row["cover"],
                    $kw,
                    $year
                );

                // Vorwochen-Daten berechnen
                $prev = getPreviousChartPosition(
                    $entry->titel,
                    $entry->interpret,
                    $year,
                    $kw,               
                    $entry->platz,
                    $openDbConnection,
                    $kwList            
                );

                $entry->previousRank = $prev['prev'];
                $entry->diff = $prev['diff'];

                // in Array speichern
                $KWDataArray[] = $entry;
            }
            $currentRank = $row["platz"];
        }

        return $KWDataArray;
    }

    // Find the closest earlier week in DB
    function getNextEarlierWeek($openDbConnection, $year, $kw) {
        $current = $year * 100 + $kw;

        $stmt = $openDbConnection->prepare(" 
            SELECT jahr, kw 
            FROM top40 
            WHERE (jahr * 100 + kw) < ? 
            ORDER BY jahr DESC, kw DESC 
            LIMIT 1
        ");

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

    // TRUE if no earlier week exists
    function hasNoPreviousWeek($openDbConnection, $year, $kw) {
        $prev = getNextEarlierWeek($openDbConnection, $year, $kw);
        return !$prev;
    }

    // Get previous rank and difference for a song
    function getPreviousChartPosition($title, $interpret, $year, $kw, $currentRank, $openDbConnection, $kwList) {
        $searchKey = $year . '-' . str_pad($kw, 2, '0', STR_PAD_LEFT);
        $mappedKeys = array_map(fn($e) => $e['year'] . '-' . str_pad($e['kw'], 2, '0', STR_PAD_LEFT), $kwList);
        $currentIndex = array_search($searchKey, $mappedKeys);

        if ($currentIndex === false) return ['prev' => 'ERR', 'diff' => 'ERR']; 

        $hasPrev = $currentIndex > 0;
        $prevYear = $hasPrev ? (int)$kwList[$currentIndex - 1]['year'] : null;
        $prevKW = $hasPrev ? (int)$kwList[$currentIndex - 1]['kw'] : null;

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
                $diff = $prevRank - $currentRank;
                return ['prev' => $prevRank, 'diff' => (string)$diff];
            }
            $stmt->close();
        }

        // Check if first appearance
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

        if ((int)$firstYear === (int)$year && (int)$firstKW === (int)$kw) {
            return ['prev' => 'NEW', 'diff' => 'NEW'];
        }

        if ((int)$year === $firstDbYear && (int)$kw === $firstDbKW) {
            return ['prev' => 'NEW', 'diff' => 'NEW'];
        }

        return ['prev' => 'RE', 'diff' => 'RE'];
    }

    // Label for previous week (table header)
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

    // Open DB connection
    $openDbConnection = getSqlConnection();

    // Get all available weeks
    $kwList = getKwList($openDbConnection);

    // Sort ascending by year and week
    usort($kwList, function ($a, $b) {
        if ($a['year'] === $b['year']) {
            return (int)$a['kw'] <=> (int)$b['kw'];
        }
        return (int)$a['year'] <=> (int)$b['year'];
    });

    // Selected year/week or fallback to latest
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

    // Fetch data for selected week
    $data = getData4KW($openDbConnection, $year, $kw, $kwList);

    // Previous week label
    $prevWeekLabel = getPrevWeekLabel4Header($openDbConnection, $year, $kw);

    // Label for current week
    $selectedLabel = "KW" . str_pad($kw, 2, '0', STR_PAD_LEFT) . " / $year";

    // Show warning if no previous week exists
    $showWarning = hasNoPreviousWeek($openDbConnection, $year, $kw);

?>

<!-- HTML Code starts here ------------->

<!DOCTYPE html>
<html lang= "de">
    <head>
        <meta charset="UTF-8">
        <title>Top 40</title>
        <style>

            body {
                font-family: Arial, sans-serif;
                text-align: center;
                padding-top: 25px;
                background-color: white;
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
                background-color: rgb(30, 144, 255);
                color: black;
                position: sticky;
                top: 55px;
                padding: 10px;
                z-index: 10;
            }

            tr td:first-child {
                font-weight: bold;
                background-color: rgb(30, 144, 255);
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
                
                <?php foreach ($data as $entry): ?>
                    <?= $entry->renderRow() ?>
                <?php endforeach; ?>

            </table>
        <?php endif; ?>

    </body>

</html>