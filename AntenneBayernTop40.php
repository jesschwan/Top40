<?php
    require "SqlConnection.php";

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

    // Load data for specific year and week
    // returns array containing:
    /*
    function getData4KW_notUsed($year, $kw, $csvDir) {
        $KWDataArray = array();
        $csvFile = "$year-" . str_pad($kw, 2, '0', STR_PAD_LEFT) . ".csv";
        $filePath = $csvDir . $csvFile;
        $KWDataArray = file_exists($filePath) ? array_map('str_getcsv', file($filePath)) : [];
        return $KWDataArray;
    }
    */
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


    // Show warning message if no data for previous week
    function showWarningMessage($year, $kw) {
        if ((int)$year === 2023 && (int)$kw === 43) {
            echo "<div class='warning'>Keine Daten der Vorwoche vorhanden!</div>";
            return true;
        }
        return false;
    }

    // Find next earlier week with available CSV data
    function getNextEarlierWeek($openDbConnection, $year, $kw) {
        $year = (int)$year;
        $previousKw = (int)$kw - 1;

        // if kw was first one -> pick last of previous year
        if ( $previousKw <= 0) {
            $year = $year-1;
            // ask DB for last existing week in previous year
            /* Select queries return a resultset */
            $result = $openDbConnection->query("SELECT kw, jahr FROM top40 WHERE jahr='$year' ORDER BY kw DESC LIMIT 1");
            if ($result->num_rows == 1) {
                $previousKW = $result->fetch_assoc();
            }
        }
        //echo "my previous KW to (".$kw.")=".$previousKw;
        return $previousKw;
    }

    // gets the list of kws over all years from csv files in directory 
    /*
    function getKwList_notUsed($csvDir) {
        $kwList = [];
        foreach (glob($csvDir . "*.csv") as $filename) {
            if (preg_match('/(\d{4})-(\d{2}|yearly)\.csv$/', basename($filename), $matches)) {
                $kwList[] = ['year' => $matches[1], 'kw' => $matches[2]];
            }
        }
        return $kwList;
    }
    */

    function getKwList($openDbConnection) {
        $kwList = [];

        $query = "SELECT DISTINCT kw, jahr FROM top40 WHERE 1 ORDER BY jahr ASC, kw ASC";
        //echo $query;

        // Prepare the SQL statement
        $stmt = $openDbConnection->prepare($query);
        if (!$stmt) {
            die("Error preparing the query: " . $openDbConnection->error);
        }

        // Execute the prepared statement
        $stmt->execute();

        // Get the result set
        $result = $stmt->get_result();

        // Process each row
        while ($row = $result->fetch_assoc()) {
            $kwList[] = ['year' => $row["jahr"], 'kw' => $row["kw"]];
        }
        return $kwList;                        
    }


    // Get previous week data (rank and diff or status)
    /*
    function getPreviousWeekData_notUsed($title, $interpret, $year, $kw, $csvDir, $kwList) {
        // If title or artist is just a question mark, return unknown indicators
        if (trim($title) === '?' || trim($interpret) === '?') {
            return ['vorw' => '?', 'diff' => '?'];
        }

        // Default return value if no previous data is found: mark as NEW
        $prevData = ['vorw' => "NEW", 'diff' => "NEW"];

        // Get the next earlier week (year and week number) relative to current week
        $nextEarlierWeek = getNextEarlierWeek($openDbConnection, $year, $kw);
        if (!$nextEarlierWeek) {
            // If no earlier week exists, return default NEW
            return $prevData;
        } 

        // Extract previous week and year
        list($prevKW, $prevYear) = $nextEarlierWeek;
        // Construct filename for the previous week's CSV file
        $prevFile = $csvDir . $prevYear . '-' . str_pad($prevKW, 2, '0', STR_PAD_LEFT) . '.csv';

        $foundInLastWeek = false;

        // Check if previous week's file exists
        if (file_exists($prevFile)) {
            // Read CSV file and parse data (skip header row)
            $prevWeekData = array_map('str_getcsv', file($prevFile));
            array_shift($prevWeekData);
            // Loop through previous week's data to find matching title and artist
            foreach ($prevWeekData as $prevRow) {
                if (trim($prevRow[1]) === trim($title) && trim($prevRow[2]) === trim($interpret)) {
                    $foundInLastWeek = true;
                    $vorw = $prevRow[0]; // Position or rank from previous week
                    // Return found position, diff is not calculated here (null)
                    return ['vorw' => $vorw, 'diff' => null];
                }
            }
        }

        // If NOT found in last week, check for "RE" (re-entry) in older weeks
        if (!$foundInLastWeek) {
            foreach ($kwList as $entry) {
                // Construct string representation of the week (year-week)
                $entryValue = $entry['year'] . '-' . $entry['kw'];
                $currentValue = $year . '-' . str_pad($kw, 2, '0', STR_PAD_LEFT);
                // Only check weeks older than the current week
                if ($entryValue < $currentValue) {
                    // Build filename for the older week CSV
                    $olderFile = $csvDir . $entry['year'] . '-' . str_pad($entry['kw'], 2, '0', STR_PAD_LEFT) . ".csv";
                    if (file_exists($olderFile)) {
                        // Read and parse older week data, skipping header
                        $olderData = array_map('str_getcsv', file($olderFile));
                        array_shift($olderData);
                        // Search for matching title and artist in older weeks
                        foreach ($olderData as $olderRow) {
                            if (trim($olderRow[1]) === trim($title) && trim($olderRow[2]) === trim($interpret)) {
                                // Mark as re-entry if found in an older week but not last week
                                return ['vorw' => "RE", 'diff' => "RE"];
                            }
                        }
                    }
                }
            }
        }

        // If not found in any previous week, return NEW status
        return $prevData;
    }
    */

   function getPreviousWeekData($title, $interpret, $year, $kw, $currentRank, $openDbConnection, $kwList) {
        // Find the current index in kwList
        $currentIndex = false;
        foreach ($kwList as $index => $entry) {
            if ((int)$entry['year'] === (int)$year && (int)$entry['kw'] === (int)$kw) {
                $currentIndex = $index;
                break;
            }
        }

        // No previous week available (either not found or it's the first entry)
        if ($currentIndex === false || $currentIndex === 0) {
            return ['prev' => 'NEW', 'diff' => 'NEW'];
        }

        // Get previous week's year and KW
        $prevEntry = $kwList[$currentIndex - 1];
        $prevKW = (int)$prevEntry['kw'];
        $prevYear = (int)$prevEntry['year'];

        // Check if the song was in the charts in the previous week
        $stmt = $openDbConnection->prepare("
            SELECT platz 
            FROM top40 
            WHERE LOWER(titel) = LOWER(?) 
            AND LOWER(interpret) = LOWER(?) 
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

            // Song was charted in the previous week
            $diff = $prevRank - $currentRank;
            return [
                'prev' => $prevRank,
                'diff' => (string)$diff  // No plus sign, just raw numeric difference
            ];
        }

        $stmt->close();

        // Check if the song has ever been in the charts before the previous week (RE or NEW)
        $stmt2 = $openDbConnection->prepare("
            SELECT COUNT(*) 
            FROM top40 
            WHERE LOWER(titel) = LOWER(?) 
            AND LOWER(interpret) = LOWER(?) 
            AND (jahr < ? OR (jahr = ? AND kw < ?))
        ");
        if (!$stmt2) {
            return ['prev' => 'ERR', 'diff' => 'ERR'];
        }

        // Look for previous occurrences strictly before the previous week
        $stmt2->bind_param("ssiii", $title, $interpret, $prevYear, $prevYear, $prevKW);
        $stmt2->execute();
        $stmt2->bind_result($count);
        $stmt2->fetch();
        $stmt2->close();

        if ($count > 0) {
            // Song has appeared before (but not in previous week) → Re-entry (RE)
            return ['prev' => 'RE', 'diff' => 'RE'];
        } else {
            // Song has never appeared in any previous chart → New entry (NEW)
            return ['prev' => 'NEW', 'diff' => 'NEW'];
        }
    }

    function getPrevWeekLabel4Header($openDbConnection, $year, $kw) {
            // Get previous week label for header
        $prevWeekInfo = getNextEarlierWeek($openDbConnection, $year, $kw);
        if ($prevWeekInfo) {
            [$prevKW, $prevYear] = $prevWeekInfo;
            $prevWeekLabel = "KW" . $prevKW . " / " . $prevYear;
        } else {
            $prevWeekLabel = "Vorwoche";
        }
        return $prevWeekLabel;
    }

    // following code will be exeuted, because this is opened in browser, also e.g. every time again refresh
    
    // open DB once
    $openDbConnection = getSqlConnection();

    $kwList = getKwList($openDbConnection);
    usort($kwList, function ($a, $b) {
        if ($a['year'] === $b['year']) {
            return (int)$a['kw'] <=> (int)$b['kw'];
        }
        return (int)$a['year'] <=> (int)$b['year'];
    });

    // Main logic depending on POST data
    if (isset($_POST['kwYearDropDown'])) {

        $kwList = getKwList($openDbConnection);

        usort($kwList, function ($a, $b) {
            if ($a['year'] === $b['year']) {
                return (int)$a['kw'] <=> (int)$b['kw'];
            }
            return (int)$a['year'] <=> (int)$b['year'];
        });

        [$year, $kw] = explode('-', $_POST['kwYearDropDown']);
        $kw = (int)$kw;

        $data = getData4KW($openDbConnection, $year, $kw);

        if (showWarningMessage($year, $kw)) return;

        if (!empty($data)) {
            getPrevWeekLabel4Header($openDbConnection, $year, $kw);
        }

        $selectedLabel = "$year / KW" . str_pad($kw, 2, '0', STR_PAD_LEFT);
        echo "<h1>Top 40 – $selectedLabel</h1>";

        $prevWeekLabel = getPrevWeekLabel4Header($openDbConnection, $year, $kw);

        echo "<table>
                <tr>
                    <th>Platz</th><th>Titel</th><th>Interpret</th><th>$prevWeekLabel</th><th>Diff.</th>
                </tr>";

        foreach ($data as $row) {
            $info = getPreviousWeekData($row['titel'], $row['interpret'], $year, $kw, $row['platz'], $openDbConnection, $kwList);
            renderTableRow($row['platz'], $row['titel'], $row['interpret'], $info['prev'], $info['diff']);
        }

        echo "</table>";

    } else {
            // Weekly view
            if (isset($_POST['kwYearDropDown'])) {
            // Split the selected value into year and week parts
            [$year, $kw] = explode('-', $_POST['kwYearDropDown']);
            $kw = (int)$kw; // Optional: Ensure the week (KW) is treated as an integer
            } else {
                $kwList = getKwList($openDbConnection);
                usort($kwList, function($a, $b) {
                    if ($a['year'] === $b['year']) {
                        return (int)$a['kw'] <=> (int)$b['kw'];
                    }
                    return (int)$a['year'] <=> (int)$b['year'];
                });
                $lastEntry = end($kwList);
                $year = $lastEntry['year'];
                $kw = (int)$lastEntry['kw'];
            }

            $kw = (int)$kw; // ensure integer
            $data = getData4KW($openDbConnection, $year, $kw);
            //$data = getData4KW_notUsed($year, $kw, $csvDir);

            if (showWarningMessage($year, $kw)) return;

            if (!empty($data)) {
                getPrevWeekLabel4Header($openDbConnection, $year, $kw);
            }

            $selectedLabel = "$year / KW" . str_pad($kw, 2, '0', STR_PAD_LEFT);
            echo "<h1>Top 40 – $selectedLabel</h1>";

            // Get the name of the previous week to show in the header
            $prevWeekLabel = getPrevWeekLabel4Header($openDbConnection, $year, $kw);

            
            echo "<table>
                <tr>
                    <th>Platz</th><th>Titel</th><th>Interpret</th><th>$prevWeekLabel</th><th>Diff.</th>
                </tr>";
    }

    if (isset($_POST['kw'])) {
        $kw = $_POST['kw'];

        // Hier deine eigene Funktion oder DB-Abfrage aufrufen:
        $data = getData4KW($openDbConnection, $year, $kw); // z. B. aus DB holen

        if (!is_array($data)) {
            $data = []; // Fallback für Sicherheit
        }

    }

    // calculate missing data (platz vorwoche, differenz)  by (titel, interpret)
    
    foreach ($data as $row) {
        $platz = $row['platz'];
        $title = $row['titel'];
        $interpret = $row['interpret'];
        $previousData = getPreviousWeekData($title, $interpret, $year, $kw, $platz, $openDbConnection, $kwList);
        renderTableRow($platz, $title, $interpret, $previousData['prev'], $previousData['diff']);
    }
    
    echo "</table>";        
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
                margin-top: 50px;
            }
        </style>
    </head>

    <body>
        <form method="post" class="form-container">
                <label for="kwYearDropDown">Wähle:</label>
                <?php 
                    $csvDir = "./csv/"; // Path to CSV files

                    if (!isset($kwList)) {
                        $kwList = [];
                        $kwList = getKwList($openDbConnection);

                        usort($kwList, function($a, $b) {
                            if ($a['year'] === $b['year']) {
                                if ($a['kw'] === 'yearly') return 1;
                                if ($b['kw'] === 'yearly') return -1;
                                return (int)$a['kw'] <=> (int)$b['kw'];
                            }
                            return (int)$a['year'] <=> (int)$b['year'];
                        });
                    }

                    function generateDropdown($kwList, $selectedKw = null) {
                        $options = '';
                        foreach ($kwList as $entry) {
                            $value = $entry['year'] . '-' . $entry['kw'];
                            $label = ($entry['kw'] === 'yearly') ? $entry['year'] : $entry['year'] . " / KW" . $entry['kw'];
                            $selected = ($selectedKw === $value) || ($selectedKw === null && $value === end($kwList)['year'] . '-' . end($kwList)['kw']) ? 'selected' : '';
                            $options .= "<option value=\"$value\" $selected>$label</option>";
                        }
                        echo "<select name= 'kwYearDropDown' class='dropdown'>$options</select>";
                    } 
                    
                generateDropdown($kwList, $_POST['kwYearDropDown'] ?? null);
                ?>
            <button type="submit">Submit</button>
        </form>
    </body>
</html>

