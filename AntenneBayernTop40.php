<?php
    require "SqlConnection.php";
    require "functions.php";

    // Render a table row for yearly or weekly view
    function renderTableRow($platz, $title, $interpret, $vorw = null, $diff = null, $bestePlatzierung = null, $alteNr1 = null) {
        echo "<tr>
            <td>$platz</td>
            <td>$title</td>
            <td>$interpret</td>";
        
        if ($bestePlatzierung !== null && $alteNr1 !== null) {
            echo "<td>$bestePlatzierung</td><td>$alteNr1</td>";
        } elseif ($vorw !== null && $diff !== null) {
            echo "<td>$vorw</td><td>$diff</td>";
        }
        echo "</tr>";
    }

    $data = []; // Standardwert, damit keine Fehler kommen

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

    function showWarningMessage($year, $kw, $openDbConnection) {
        $prev = getNextEarlierWeek($openDbConnection, $year, $kw);
        return !$prev; // Gibt TRUE zurück, wenn KEINE Vorwoche vorhanden ist
    }

    // Returns the next earlier week that exists in the DB, given a year and kw
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

        $stmt->bind_param("iii", $year, $year, $kw);
        $stmt->execute();
        $stmt->bind_result($prevYear, $prevKw);

        if ($stmt->fetch()) {
            $stmt->close();
            return [$prevKw, $prevYear];
        }

        $stmt->close();
        return null;
    }

    function getPreviousWeekData($title, $interpret, $year, $kw, $currentRank, $openDbConnection, $kwList) {
        // 1. Finde die tatsächlich vorherige Woche aus der Liste
        $prevEntry = null;
        foreach ($kwList as $entry) {
            if (
                ($entry['year'] < $year) ||
                ($entry['year'] === $year && $entry['kw'] < $kw)
            ) {
                $prevEntry = $entry;
                break;
            }
        }

        if (!$prevEntry) {
            return ['prev' => 'NEW', 'diff' => 'NEW'];
        }

        $prevKW = (int)$prevEntry['kw'];
        $prevYear = (int)$prevEntry['year'];

        // 2. Song-Platzierung in der Vorwoche suchen
        $stmt = $openDbConnection->prepare("
            SELECT platz 
            FROM top40 
            WHERE LOWER(CONVERT(titel USING utf8mb4)) COLLATE utf8mb4_general_ci = LOWER(CONVERT(? USING utf8mb4)) COLLATE utf8mb4_general_ci
            AND LOWER(CONVERT(interpret USING utf8mb4)) COLLATE utf8mb4_general_ci = LOWER(CONVERT(? USING utf8mb4)) COLLATE utf8mb4_general_ci
            AND jahr = ? 
            AND kw = ?
        ");

        if (!$stmt) {
            return ['prev' => 'ERR', 'diff' => 'ERR'];
        }

        $stmt->bind_param("ssii", $title, $interpret, $prevYear, $prevKW);
        $stmt->execute();
        $stmt->bind_result($prevRank);

        if ($stmt->fetch()) {
            $stmt->close();
            $diff = $prevRank - $currentRank;
            return [
                'prev' => $prevRank,
                'diff' => (string)$diff
            ];
        }

        $stmt->close();

        // 3. Re-Entry prüfen
       // Wenn nicht gefunden, prüfe auf Re-Entry:
        $stmt2 = $openDbConnection->prepare("
            SELECT COUNT(*) 
            FROM top40 
            WHERE LOWER(CONVERT(titel USING utf8mb4)) COLLATE utf8mb4_general_ci = LOWER(CONVERT(? USING utf8mb4)) COLLATE utf8mb4_general_ci
            AND LOWER(CONVERT(interpret USING utf8mb4)) COLLATE utf8mb4_general_ci = LOWER(CONVERT(? USING utf8mb4)) COLLATE utf8mb4_general_ci
            AND (jahr < ? OR (jahr = ? AND kw < ?))
        ");

        if (!$stmt2) {
            return ['prev' => 'ERR', 'diff' => 'ERR'];
        }

        $stmt2->bind_param("ssiii", $title, $interpret, $prevYear, $prevYear, $prevKW);
        $stmt2->execute();
        $stmt2->bind_result($count);
        $stmt2->fetch();
        $stmt2->close();

        if ($count > 0) {
            return ['prev' => 'RE', 'diff' => 'RE'];
        } else {
            return ['prev' => 'NEW', 'diff' => 'NEW'];
        }
        
        if ($stmt->fetch()) {
            $stmt->close();
            
            // Unterschied berechnen NUR wenn der Song auch in der Vorwoche war (kein Re-Entry)
            $diff = $prevRank - $currentRank;

            return [
                'prev' => $prevRank,
                'diff' => (string)$diff
            ];
        } else {
            $stmt->close();

            // Statt sofort 'NEW' prüfen ob RE
            // (Re-Entry-Abfrage siehe oben)
        }

        if ($count > 0) {
            return ['prev' => 'RE', 'diff' => 'RE'];
        } else {
            return ['prev' => 'NEW', 'diff' => 'NEW'];
        }
    }


    function getPrevWeekLabel4Header($openDbConnection, $year, $kw) {
        $prevWeekInfo = getNextEarlierWeek($openDbConnection, $year, $kw);
        if ($prevWeekInfo) {
            [$prevKW, $prevYear] = $prevWeekInfo;
            $prevWeekLabel = "KW" . str_pad($prevKW, 2, '0', STR_PAD_LEFT) . " / " . $prevYear;
        } else {
            $prevWeekLabel = "Keine Vorwoche";
        }
        return $prevWeekLabel;
    }

    // following code will be exeuted, because this is opened in browser, also e.g. every time again refresh
    
    // open DB once
    $openDbConnection = getSqlConnection();

    $kwList = getKwList($openDbConnection);
    usort($kwList, function ($a, $b) {
        if ($a['year'] === $b['year']) {
            return (int)$b['kw'] <=> (int)$a['kw'];
        }
        return (int)$b['year'] <=> (int)$a['year'];
    });


    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['kwYearDropDown'])) {
        [$year, $kw] = explode('-', $_POST['kwYearDropDown']);
        $year = (int)$year;
        $kw = (int)$kw;
    } else {
        $latest = reset($kwList);
        $year = (int)$latest['year'];
        $kw = (int)$latest['kw'];
    }


    // Main logic depending on POST data
    if (isset($_POST['kwYearDropDown'])) {

    [$year, $kw] = explode('-', $_POST['kwYearDropDown']);
    $kw = (int)$kw;

    $data = getData4KW($openDbConnection, $year, $kw);

        if (!empty($data)) {
            getPrevWeekLabel4Header($openDbConnection, $year, $kw);
        }

        $selectedLabel = "$year / KW" . str_pad($kw, 2, '0', STR_PAD_LEFT);

        $prevWeekLabel = getPrevWeekLabel4Header($openDbConnection, $year, $kw);

    } else {
            // Weekly view
            if (isset($_POST['kwYearDropDown'])) {
            // Split the selected value into year and week parts
            [$year, $kw] = explode('-', $_POST['kwYearDropDown']);
            $kw = (int)$kw; // Optional: Ensure the week (KW) is treated as an integer
            } else {
                $lastEntry = end($kwList);
                $year = $lastEntry['year'];
                $kw = (int)$lastEntry['kw'];
            }

            $kw = (int)$kw; // ensure integer
            $data = getData4KW($openDbConnection, $year, $kw);

            if (!empty($data)) {
                getPrevWeekLabel4Header($openDbConnection, $year, $kw);
            }
            
            // Get the name of the previous week to show in the header
            $prevWeekLabel = getPrevWeekLabel4Header($openDbConnection, $year, $kw);

    }

    $selectedLabel = "$year / KW" . str_pad($kw, 2, '0', STR_PAD_LEFT);
    // echo "<h1>Top 40 – $selectedLabel</h1>";

    // calculate missing data (platz vorwoche, differenz)  by (titel, interpret)   

    require_once 'SqlConnection.php';  // Für $openDbConnection (DB-Verbindung)
    require_once 'functions.php';      // Für getKwList() und generateDropdown()

    // KW-Liste holen
    $kwList = getKwList($openDbConnection);

    // Ausgewähltes Dropdown-Element aus POST
    $selectedKw = $_POST['kwYearDropDown'] ?? null;

    if ($selectedKw) {
        [$year, $kw] = explode('-', $selectedKw);
        $kw = (int)$kw;
    } else {
        $lastEntry = end($kwList);
        $year = $lastEntry['year'];
        $kw = (int)$lastEntry['kw'];
    }

    $data = getData4KW($openDbConnection, $year, $kw);

    $prevWeekLabel = getPrevWeekLabel4Header($openDbConnection, $year, $kw);
    // $selectedLabel = "KW" . str_pad($kw, 2, '0', STR_PAD_LEFT) . " / $year";

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (isset($_POST['kwYearDropDown'])) {
            [$year, $kw] = explode('-', $_POST['kwYearDropDown']);
            $kw = (int)$kw;
            $year = (int)$year;
        }
        $selectedLabel = "KW" . str_pad($kw, 2, '0', STR_PAD_LEFT) . " / $year";
    }

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

        <?php
            echo "<h1>Top 40 – $selectedLabel</h1>";
        ?>

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