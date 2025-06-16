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
                <label for="kw">Wähle:</label>
                <?php 
                    $csvDir = "./csv/"; // Path to CSV files

                    if (!isset($kwList)) {
                        $kwList = [];
                        foreach (glob($csvDir . "*.csv") as $filename) {
                            if (preg_match('/(\d{4})-(\d{2}|yearly)\.csv$/', basename($filename), $matches)) {
                                $kwList[] = ['year' => $matches[1], 'kw' => $matches[2]];
                            }
                        }
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
                        echo "<select name=\"kw\" class=\"dropdown\">$options</select>";
                    }

                    generateDropdown($kwList, $_POST['kw'] ?? null);
                ?>
            <button type="submit">Submit</button>
        </form>

        <?php

                // Render a table row for yearly or weekly view
                function renderTableRow($platz, $titel, $interpret, $vorw = null, $diff = null, $bestePlatzierung = null, $alteNr1 = null) {
                    echo "<tr>
                        <td>$platz</td>
                        <td>$titel</td>
                        <td>$interpret</td>";
                    
                    if ($bestePlatzierung !== null && $alteNr1 !== null) {
                        echo "<td>$bestePlatzierung</td><td>$alteNr1</td>";
                    } elseif ($vorw !== null && $diff !== null) {
                        echo "<td>$vorw</td><td>$diff</td>";
                    }
                    echo "</tr>";
                }

                // Load data for specific year and week
                function getData4KW($year, $kw, $csvDir) {
                    $csvFile = "$year-" . str_pad($kw, 2, '0', STR_PAD_LEFT) . ".csv";
                    $filePath = $csvDir . $csvFile;
                    return file_exists($filePath) ? array_map('str_getcsv', file($filePath)) : [];
                }

                // Show warning message if no data for previous week
                function showWarningMessage($csvDir, $year, $kw) {
                    if ((int)$year === 2023 && (int)$kw === 43) {
                        echo "<div class='warning'>Keine Daten der Vorwoche vorhanden!</div>";
                        return true;
                    }
                    return false;
                }

                // Find next earlier week with available CSV data
                function getNextEarlierWeek($currentYear, $currentKW, $csvDir) {
                    $year = (int)$currentYear;
                    $kw = (int)$currentKW - 1;

                    while ($year >= 2023) {
                        while ($kw > 0) {
                            $filename = $csvDir . $year . '-' . str_pad($kw, 2, '0', STR_PAD_LEFT) . '.csv';
                            if (file_exists($filename)) {
                                return [$kw, $year];
                            }
                            $kw--;
                        }
                        $year--;
                        $kw = 53;
                    }
                    return null;
                }

                // Get best placement across all CSVs
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
                        if (preg_match('/^(2023|2024|2025)-\d{2}\.csv$/', $filename)) {
                            $data = array_map('str_getcsv', file($file));
                            array_shift($data);
                            foreach ($data as $row) {
                                if (isset($row[1], $row[2]) && trim($row[1]) === trim($titel) && trim($row[2]) === trim($interpret)) {
                                    $placement = (int)$row[0];
                                    if ($placement < $bestPlacement) {
                                        $bestPlacement = $placement;
                                    }
                                }
                            }
                        }
                    }
                    return $bestPlacement === 41 ? "Nie auf Platz 1" : $bestPlacement;
                }

                // Get previous week data (rank and diff or status)
                function getPreviousWeekData($titel, $interpret, $currentYear, $currentKW, $csvDir, $kwList) {
                    
                    // Return '?' for unknown titles or artists
                    if (trim($titel) === '?' || trim($interpret) === '?') {
                        return ['vorw' => '?', 'diff' => '?'];
                    }

                    $prevData = ['vorw' => "NEW", 'diff' => "NEW"];

                    $nextEarlierWeek = getNextEarlierWeek($currentYear, $currentKW, $csvDir);
                    if (!$nextEarlierWeek) {
                        return $prevData;
                    }

                    list($prevKW, $prevYear) = $nextEarlierWeek;
                    $prevFile = $csvDir . $prevYear . '-' . str_pad($prevKW, 2, '0', STR_PAD_LEFT) . '.csv';

                    if (!file_exists($prevFile)) {
                        return $prevData;
                    }

                    $prevWeekData = array_map('str_getcsv', file($prevFile));
                    array_shift($prevWeekData);

                    foreach ($prevWeekData as $prevRow) {
                        if (trim($prevRow[1]) === trim($titel) && trim($prevRow[2]) === trim($interpret)) {
                            $vorw = $prevRow[0];
                            // diff is calculated later
                            return ['vorw' => $vorw, 'diff' => null];
                        }
                    }

                    // Check older weeks for re-entries (RE)
                    for ($j = count($kwList) - 1; $j >= 0; $j--) {
                        $olderEntry = $kwList[$j];
                        if ($olderEntry['year'] . '-' . $olderEntry['kw'] < $currentYear . '-' . $currentKW) {
                            $olderFile = $csvDir . $olderEntry['year'] . '-' . $olderEntry['kw'] . ".csv";
                            if (file_exists($olderFile) && count(file($olderFile)) > 1) {
                                $olderData = array_map('str_getcsv', file($olderFile));
                                array_shift($olderData);
                                foreach ($olderData as $olderRow) {
                                    if (trim($olderRow[1]) === trim($titel) && trim($olderRow[2]) === trim($interpret)) {
                                        return ['vorw' => "RE", 'diff' => "RE"];
                                    }
                                }
                            }
                        }
                    }

                    return $prevData;
                }

                // Main logic depending on POST data
                if (isset($_POST['kw'])) {
                    $kwList = [];
                    foreach (glob($csvDir . "*.csv") as $filename) {
                        if (preg_match('/(\d{4})-(\d{2}|yearly)\.csv$/', basename($filename), $matches)) {
                            $kwList[] = ['year' => $matches[1], 'kw' => $matches[2]];
                        }
                    }
                    usort($kwList, function($a, $b) {
                        if ($a['year'] === $b['year']) {
                            if ($a['kw'] === 'yearly') return 1;
                            if ($b['kw'] === 'yearly') return -1;
                            return (int)$a['kw'] <=> (int)$b['kw'];
                        }
                        return (int)$a['year'] <=> (int)$b['year'];
                    });

                    if (strpos($_POST['kw'], 'yearly') !== false) {
                        // Yearly view
                        [$year] = explode('-', $_POST['kw']);
                        $filePath = $csvDir . "$year.csv";
                        $data = file_exists($filePath) ? array_map('str_getcsv', file($filePath)) : [];
                        if (!empty($data)) array_shift($data);

                        echo "<h1>Top 40 des Jahres $year</h1>";
                        echo "<table>
                            <tr>
                                <th>Platz</th><th>Titel</th><th>Interpret</th><th>Beste Platzierung</th><th>Ehemalige Nr. 1?</th>
                            </tr>";

                        if ($data) {
                            usort($data, function ($a, $b) { return (int)$a[0] <=> (int)$b[0]; });

                            foreach ($data as $row) {
                                if (isset($row[0], $row[1], $row[2])) {
                                    $bestePlatzierung = getBestPlacement($row[1], $row[2], $csvDir);
                                    $alteNr1 = ($bestePlatzierung == 1) ? "Ja" : "Nein";
                                    renderTableRow($row[0], $row[1], $row[2], null, null, $bestePlatzierung, $alteNr1);
                                }
                            }
                        }
                        echo "</table>";
                    } else {
                        // Weekly view
                        [$year, $kw] = explode('-', $_POST['kw']);
                        $kw = (int)$kw; // ensure integer
                        $data = getData4KW($year, $kw, $csvDir);

                        if (showWarningMessage($csvDir, $year, $kw)) return;

                        if (!empty($data)) {
                            array_shift($data); // remove header
                            usort($data, function ($a, $b) { return (int)$a[0] <=> (int)$b[0]; });

                            // Get previous week label for header
                            $prevWeekInfo = getNextEarlierWeek($year, $kw, $csvDir);
                            if ($prevWeekInfo) {
                                [$prevKW, $prevYear] = $prevWeekInfo;
                                $prevWeekLabel = "KW" . $prevKW . " / " . $prevYear;
                            } else {
                                $prevWeekLabel = "Vorwoche";
                            }

                        $selectedLabel = "$year / KW" . str_pad($kw, 2, '0', STR_PAD_LEFT);
                        echo "<h1>Top 40 – $selectedLabel</h1>";

                        $prevWeekLabel = $prevWeekInfo
                            ? $prevYear . " / KW" . str_pad($prevKW, 2, '0', STR_PAD_LEFT)
                            : "Vorwoche";

                        echo "<table>
                            <tr>
                                <th>Platz</th><th>Titel</th><th>Interpret</th><th>$prevWeekLabel</th><th>Diff.</th>
                            </tr>";


                    foreach ($data as $row) {
                        if (isset($row[0], $row[1], $row[2])) {
                            // Get previous week data
                            $previousData = getPreviousWeekData($row[1], $row[2], $year, $kw, $csvDir, $kwList);

                            // Calculate diff if not set
                            if (is_numeric($previousData['vorw']) && $previousData['diff'] === null) {
                                $diff = (int)$previousData['vorw'] - (int)$row[0];
                                $previousData['diff'] = (string)$diff; // no "+" sign
                            }

                            // Render table row
                            renderTableRow($row[0], $row[1], $row[2], $previousData['vorw'], $previousData['diff']);
                        }
                    }


                        echo "</table>";
                    } else {
                        echo "<div class='warning'>Keine Daten für KW$kw / $year gefunden.</div>";
                    }
                }
            }
        ?>

    </body>
</html>