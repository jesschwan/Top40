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

<form method="post">
    <div class="form-container">
        <label for="kw">Wähle: </label>
        <?php
        // Set CSV directory
        $csvDir = "CSV/";
        $files = glob($csvDir . "*.csv");
        $kwList = [];

        // Extract year and week from filenames for weekly files (year-week.csv)
        foreach ($files as $file) {
            if (preg_match('/(\d{4})-(\d{2})\.csv$/', basename($file), $matches)) {
                $year = $matches[1];
                $kw = $matches[2];
                $kwList[] = ['year' => $year, 'kw' => $kw];
            }
        }

        // Extract year from filenames for yearly files (year.csv)
        foreach ($files as $file) {
            if (preg_match('/(\d{4})\.csv$/', basename($file), $matches)) {
                $year = $matches[1];
                $kwList[] = ['year' => $year, 'kw' => 'yearly'];
            }
        }

        // Sort the list by year and week
        usort($kwList, function ($a, $b) {
            return ($a['year'] . $a['kw']) <=> ($b['year'] . $b['kw']);
        });
        ?>
        <select name="kw" class="dropdown">
            <?php
            foreach ($kwList as $entry) {
                $value = $entry['year'] . '-' . $entry['kw'];
                $label = ($entry['kw'] === 'yearly') ? $entry['year'] : $entry['year'] . " / KW" . $entry['kw'];
                $selected = (isset($_POST['kw']) && $_POST['kw'] === $value) || (!isset($_POST['kw']) && $value === end($kwList)['year'] . '-' . end($kwList)['kw']) ? 'selected' : '';
                echo "<option value=\"$value\" $selected>$label</option>";
            }
            ?>
        </select>
        <button type="submit">Submit</button>
    </div>
</form>

<?php
    // Display warning if no earlier week available
    function showWarningMessage($csvDir, $year, $kw) {
        $previousWeekData = getNextEarlierWeek($year, $kw, $csvDir);
        if (!$previousWeekData) {
            echo "<div class='warning'>Keine Daten der Vorwoche vorhanden!</div>";
            return true;
        }
        return false;
    }

    // Get the most recent earlier week, supports year transition
    function getNextEarlierWeek($currentYear, $currentKW, $csvDir) {
        $year = (int)$currentYear;
        $kw = (int)$currentKW - 1;

        while ($year >= 2023) { // Limit backwards to 2023
            while ($kw > 0) {
                $filename = $csvDir . $year . '-' . str_pad($kw, 2, '0', STR_PAD_LEFT) . '.csv';
                if (file_exists($filename)) {
                    return [$kw, $year];
                }
                $kw--;
            }

            // If no week found in current year, move to previous year
            $year--;
            $kw = 53; // Max possible weeks in a year
        }

        return null;
    }

    // Get the best placement of a song from weekly data (2023-2024 only)
    function getBestPlacement($titel, $interpret, $csvDir) {
        $specialCases = [
            ['titel' => 'Cruel Summer', 'interpret' => 'Taylor Swift'],
            ['titel' => 'The Feeling', 'interpret' => 'Lost Frequencies']
        ];

        foreach ($specialCases as $case) {
            if (trim($titel) === $case['titel'] && trim($interpret) === $case['interpret']) {
                return 1;
            }
        }

        $files = glob($csvDir . "*.csv");
        $bestPlacement = 41;
        foreach ($files as $file) {
            $filename = basename($file);
            if (preg_match('/^(2023|2024)-\d{2}\.csv$/', $filename)) {
                $data = array_map('str_getcsv', file($file));
                array_shift($data);
                foreach ($data as $row) {
                    if (isset($row[1]) && isset($row[2]) && trim($row[1]) === trim($titel) && trim($row[2]) === trim($interpret)) {
                        $placement = (int) $row[0];
                        if ($placement < $bestPlacement) {
                            $bestPlacement = $placement;
                        }
                    }
                }
            }
        }
        return $bestPlacement === 41 ? "Nie auf Platz 1" : $bestPlacement;
    }

    // Handle display logic based on selected week/year
    if (isset($_POST['kw'])) {
        if (strpos($_POST['kw'], 'yearly') !== false) {
            [$year] = explode('-', $_POST['kw']);
            $csvFile = "$year.csv";
            $filePath = $csvDir . $csvFile;
            $data = file_exists($filePath) ? array_map('str_getcsv', file($filePath)) : [];
            if (!empty($data)) array_shift($data);

            if (!empty($data)) {
                echo "<h1>Top 40 des Jahres $year</h1>";

                echo "<table>
                    <tr>
                        <th>Platz</th>
                        <th>Titel</th>
                        <th>Interpret</th>
                        <th>Beste Platzierung</th>
                        <th>Ehemalige Nr. 1?</th>
                    </tr>";

                usort($data, function ($a, $b) {
                    return (int)$a[0] <=> (int)$b[0];
                });

                foreach ($data as $row) {
                    if (isset($row[0], $row[1], $row[2])) {
                        $platz = $row[0];
                        $titel = $row[1];
                        $interpret = $row[2];
                        $bestePlatzierung = getBestPlacement($titel, $interpret, $csvDir);
                        $alteNr1 = ($bestePlatzierung == 1) ? "Ja" : "Nein";

                        echo "<tr>
                                <td>$platz</td>
                                <td>$titel</td>
                                <td>$interpret</td>
                                <td>$bestePlatzierung</td>
                                <td>$alteNr1</td>
                            </tr>";
                    }
                }

                echo "</table>";
            }
        } else {
            [$year, $kw] = explode('-', $_POST['kw']);
            $csvFile = "$year-$kw.csv";
            $filePath = $csvDir . $csvFile;
            $data = file_exists($filePath) ? array_map('str_getcsv', file($filePath)) : [];
            if (!empty($data)) array_shift($data);

            if (showWarningMessage($csvDir, $year, $kw)) {
                return;
            }

            $nextEarlierWeek = getNextEarlierWeek($year, $kw, $csvDir);

            echo "<h1>Top 40 - KW$kw / $year</h1>";

            if (!empty($data)) {
                usort($data, function ($a, $b) {
                    return (int)$a[0] <=> (int)$b[0];
                });

                if ($nextEarlierWeek) {
                    echo "<table>
                        <tr>
                            <th>Platz</th>
                            <th>Titel</th>
                            <th>Interpret</th>
                            <th>KW{$nextEarlierWeek[0]} / {$nextEarlierWeek[1]}</th>
                            <th>Diff.</th>
                        </tr>";

                    foreach ($data as $row) {
                        if (isset($row[0], $row[1], $row[2])) {
                            $platz = $row[0];
                            $titel = $row[1];
                            $interpret = $row[2];
                            $vorw = "NEW";
                            $diff = "NEW";

                            if ($nextEarlierWeek) {
                                $prevFile = $csvDir . $nextEarlierWeek[1] . '-' . str_pad($nextEarlierWeek[0], 2, '0', STR_PAD_LEFT) . '.csv';
                                $prevData = array_map('str_getcsv', file($prevFile));
                                array_shift($prevData);
                                $foundInPrevWeek = false;
                                foreach ($prevData as $prevRow) {
                                    if (trim($prevRow[1]) === trim($titel) && trim($prevRow[2]) === trim($interpret)) {
                                        $vorw = $prevRow[0];
                                        $diff = (int)$vorw - (int)$platz;
                                        $foundInPrevWeek = true;
                                        break;
                                    }
                                }
                                if (!$foundInPrevWeek) {
                                    for ($j = count($kwList) - 1; $j >= 0; $j--) {
                                        $olderEntry = $kwList[$j];
                                        if ($olderEntry['year'] . '-' . $olderEntry['kw'] < $year . '-' . $kw) {
                                            $olderFile = $csvDir . $olderEntry['year'] . '-' . $olderEntry['kw'] . ".csv";
                                            if (file_exists($olderFile) && count(file($olderFile)) > 1) {
                                                $olderData = array_map('str_getcsv', file($olderFile));
                                                array_shift($olderData);
                                                foreach ($olderData as $olderRow) {
                                                    if (trim($olderRow[1]) === trim($titel) && trim($olderRow[2]) === trim($interpret)) {
                                                        $vorw = "RE";
                                                        $diff = "RE";
                                                        break 2;
                                                    }
                                                }
                                            }
                                        }
                                    }
                                }
                            }

                            echo "<tr>
                                    <td>$platz</td>
                                    <td>$titel</td>
                                    <td>$interpret</td>
                                    <td>$vorw</td>
                                    <td>$diff</td>
                                </tr>";
                        }
                    }

                    echo "</table>";
                }
            }
        }
    }
?>
</body>
</html>
