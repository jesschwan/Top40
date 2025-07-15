<?php
    require "SqlConnection.php";
    require "functions.php";

    // Render a table row for yearly or weekly view
    function renderTableRow($platz, $title, $interpret, $vorw = null, $diff = null) {
        echo "<tr>
                <td>$platz</td>
                <td>$title</td>
                <td>$interpret</td>";
        
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

        // Prepare SQL query to select top 40 entries for the given year and week
        $query = "SELECT * FROM top40 WHERE kw = ? AND jahr = ? ORDER BY platz LIMIT 40";

        // Prepare the SQL statement
        $stmt = $openDbConnection->prepare($query);
        if (!$stmt) {
            die("Error preparing the query: " . $openDbConnection->error);
        }

        // Bind parameters to the SQL query to prevent SQL injection
        $stmt->bind_param("ii", $kw, $year);

        // Execute the prepared statement
        $stmt->execute();

        // Get the result set from the executed statement
        $result = $stmt->get_result();

        $currentRank = 0;

        // Fetch each row as an associative array and build the data array
        while ($row = $result->fetch_assoc()) {
            if ($currentRank != $row["platz"]) {
                $KWDataArray[] = [
                    'platz' => $row["platz"],
                    'titel' => $row["titel"],
                    'interpret' => $row["interpret"],
                    'kw' => $row["kw"],
                    // You can add more fields if needed
                ];
            }
            $currentRank = $row["platz"];
        }

        // Return the array of top 40 entries for the requested week and year
        return $KWDataArray;
    }

    // Show warning message if there is no previous week data
    function showWarningMessage($year, $kw, $openDbConnection) {
        $prev = getNextEarlierWeek($openDbConnection, $year, $kw);
        return !$prev; // Returns TRUE if NO previous week exists
    }

    // Returns the next earlier week available in the DB, given a year and week
    function getNextEarlierWeek($openDbConnection, $year, $kw) {
        $stmt = $openDbConnection->prepare("
            SELECT jahr, kw 
            FROM top40 
            WHERE (jahr < ? OR (jahr = ? AND kw < ?)) 
            GROUP BY jahr, kw 
            ORDER BY jahr DESC, kw DESC 
            LIMIT 1
        ");
        if (!$stmt) {
            return null;
        }

        $stmt->bind_param("iii", $year, $year, $kw); // first ? --> $year (for jahr < ?), second ? --> $year (for jahr = ?), third ? --> $kw (for kw < ?)
        $stmt->execute();
        $stmt->bind_result($prevYear, $prevKw);

        if ($stmt->fetch()) {
            $stmt->close();
            // **HERE CORRECTION: Return order correctly (year, KW)**
            return [$prevYear, $prevKw];
        }

        $stmt->close();
        return null;
    }

    // Get previous week's rank and difference for a given song and current rank
    function getPreviousWeekData($title, $interpret, $year, $kw, $currentRank, $openDbConnection, $kwList) {
        // Check KW formatting in kwList, best like this:
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

        if ((int)$firstYear === (int)$year && (int)$firstKW === (int)$kw) {
            return ['prev' => 'NEW', 'diff' => 'NEW'];
        }

        if ((int)$year === $firstDbYear && (int)$kw === $firstDbKW) {
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

    $showWarning = showWarningMessage($year, $kw, $openDbConnection);

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
                margin: 0;
            }
            h1 {
                font-size: 40px;
                position: sticky;
                top: 0;
                background-color: white;
                border-bottom: 1px solid lightgrey;
                margin: 0;
                padding: 10px;
                z-index: 10;
            }
            table {
                margin: auto;
                border-collapse: collapse;
                font-size: 25px;
                width: auto;
                color: black;
            }
            th, td {
                border: 2px solid black;
                padding: 10px;
            }
            th {
                background-color: blue;
                color: black;
                position: sticky;
                top: 55px;
                padding: 10px;
                z-index: 10;
            }
            tr td:first-child {
                font-weight: bold;
                background-color: blue;
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
            label {
                font-size: 25px;
                color: black;
                font-family: Arial;
                display: flex;
                align-items: center;
                height: 50px;
            }
            .dropdown, button {
                font-size: 25px;
                color: black;
                font-family: Arial;
                height: 50px;
                padding: 0 20px;
            }
            button {
                background-color: #ccc;
                border: 2px solid black;
                cursor: pointer;
            }
            .warning {
                color: red;
                font-size: 50px;
                font-weight: bold;
                margin: 20px 0;
                text-align: center;
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
                    <th>Platz</th><th>Titel</th><th>Interpret</th>
                    <th><?= htmlspecialchars($prevWeekLabel) ?></th><th>Diff.</th>
                </tr>
                <?php foreach ($data as $row): ?>
                    <?php
                        $platz = $row['platz'];
                        $title = $row['titel'];
                        $interpret = $row['interpret'];
                        $previousData = getPreviousWeekData($title, $interpret, $year, $kw, $platz, $openDbConnection, $kwList);
                        renderTableRow($platz, $title, $interpret, $previousData['prev'], $previousData['diff']);
                    ?>
                <?php endforeach; ?>
            </table>
        <?php endif; ?>

    </body>

</html>
