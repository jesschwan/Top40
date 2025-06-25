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
        //echo previous KW and the year;
        return [$previousKw, $year];

    }

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

        $prevWeekLabel = getPrevWeekLabel4Header($openDbConnection, $year, $kw);

    } else {
            // Weekly view
            if (isset($_POST['kwYearDropDown'])) {
            // Split the selected value into year and week parts
            [$year, $kw] = explode('-', $_POST['kwYearDropDown']);
            $kw = (int)$kw; // Optional: Ensure the week (KW) is treated as an integer
            } else {
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

            if (showWarningMessage($year, $kw)) return;

            if (!empty($data)) {
                getPrevWeekLabel4Header($openDbConnection, $year, $kw);
            }

            $selectedLabel = "$year / KW" . str_pad($kw, 2, '0', STR_PAD_LEFT);
            echo "<h1>Top 40 – $selectedLabel</h1>";

            // Get the name of the previous week to show in the header
            $prevWeekLabel = getPrevWeekLabel4Header($openDbConnection, $year, $kw);

    }

    // calculate missing data (platz vorwoche, differenz)  by (titel, interpret)   

    require_once 'SqlConnection.php';  // Für $openDbConnection (DB-Verbindung)
    require_once 'functions.php';      // Für getKwList() und generateDropdown()

    // DB-Verbindung aufbauen
    $openDbConnection = getSqlConnection(); // Beispiel, je nachdem wie deine Funktion heißt

    // KW-Liste holen
    $kwList = getKwList($openDbConnection);

    // Ausgewähltes Dropdown-Element aus POST
    $selectedKw = $_POST['kwYearDropDown'] ?? null;

    if ($selectedKw) {
        [$year, $kw] = explode('-', $selectedKw);
        $kw = (int)$kw;
    } else {
        usort($kwList, function ($a, $b) {
            if ($a['year'] === $b['year']) {
                return (int)$a['kw'] <=> (int)$b['kw'];
            }
            return (int)$a['year'] <=> (int)$b['year'];
        });
        $lastEntry = end($kwList);
        $year = $lastEntry['year'];
        $kw = (int)$lastEntry['kw'];
    }

    $data = getData4KW($openDbConnection, $year, $kw);

    $prevWeekLabel = getPrevWeekLabel4Header($openDbConnection, $year, $kw);
    $selectedLabel = "$year / KW" . str_pad($kw, 2, '0', STR_PAD_LEFT);
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
            <?php generateDropdown($kwList, $selectedKw); ?>
            <button type="submit">Submit</button>
        </form>

        <h1>Top 40 – <?php echo htmlspecialchars($selectedLabel); ?></h1>

        <?php if (showWarningMessage($year, $kw)) {
            // Warnung ausgegeben, kein Rest
            exit;
        } ?>

        <table>
            <tr>
                <th>Platz</th><th>Titel</th><th>Interpret</th><th><?php echo htmlspecialchars($prevWeekLabel); ?></th><th>Diff.</th>
            </tr>
            <?php
                foreach ($data as $row) {
                    $platz = $row['platz'];
                    $title = $row['titel'];
                    $interpret = $row['interpret'];
                    $previousData = getPreviousWeekData($title, $interpret, $year, $kw, $platz, $openDbConnection, $kwList);
                    renderTableRow($platz, $title, $interpret, $previousData['prev'], $previousData['diff']);
                }
            ?>
        </table>

    </body>

</html>