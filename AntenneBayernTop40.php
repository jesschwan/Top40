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
        <label for="kw">Select: </label>

        <?php
        // Set CSV directory
        $csvDir = "CSV/";
        $files = glob($csvDir . "*.csv");
        $kwList = [];

        // Collect week files (format: year-week.csv)
        foreach ($files as $file) {
            if (preg_match('/(\d{4})-(\d{2})\.csv$/', basename($file), $matches)) {
                $year = $matches[1];
                $kw = $matches[2];
                $kwList[] = ['year' => $year, 'kw' => $kw];
            }
        }

        // Collect yearly files (format: year.csv)
        foreach ($files as $file) {
            if (preg_match('/(\d{4})\.csv$/', basename($file), $matches)) {
                $year = $matches[1];
                $kwList[] = ['year' => $year, 'kw' => 'yearly'];
            }
        }

        // Sort list
        usort($kwList, function ($a, $b) {
            return ($a['year'] . $a['kw']) <=> ($b['year'] . $b['kw']);
        });
        ?>

        <select name="kw" class="dropdown">
            <?php
            foreach ($kwList as $entry) {
                $value = $entry['year'] . '-' . $entry['kw'];
                $label = ($entry['kw'] === 'yearly') ? $entry['year'] : $entry['year'] . " / KW" . $entry['kw'];
                $selected = (isset($_POST['kw']) && $_POST['kw'] === $value) ||
                    (!isset($_POST['kw']) && $value === end($kwList)['year'] . '-' . end($kwList)['kw']) ? 'selected' : '';
                echo "<option value=\"$value\" $selected>$label</option>";
            }
            ?>
        </select>

        <button type="submit">Submit</button>
    </div>
</form>

<?php
// Show warning if no previous week data exists
function showWarningMessage() {
    echo "<div class='warning'>No previous week data available!</div>";
}

// Get best chart position of a song across all weeks (up to current selection)
function getBestPlacement($title, $artist, $csvDir, $maxYear) {
    $files = glob($csvDir . "*.csv");
    $bestPlacement = 41;

    foreach ($files as $file) {
        if (preg_match('/(\d{4})/', basename($file), $matches)) {
            if ((int)$matches[1] > (int)$maxYear) continue;
        }

        $data = array_map('str_getcsv', file($file));
        array_shift($data);
        foreach ($data as $row) {
            if (isset($row[1], $row[2]) && trim($row[1]) === trim($title) && trim($row[2]) === trim($artist)) {
                $placement = (int) $row[0];
                if ($placement < $bestPlacement) {
                    $bestPlacement = $placement;
                }
            }
        }
    }

    // Special cases
    if ($title === 'Cruel Summer' && $artist === 'Taylor Swift') return 1;
    if ($title === 'The Feeling' && $artist === 'Lost Frequencies') return 1;

    return $bestPlacement === 41 ? "Never #1" : $bestPlacement;
}

// Find next earlier available week
function getNextEarlierWeek($currentYear, $currentKW, $csvDir) {
    $prevKW = (int) $currentKW - 1;
    $prevFile = $csvDir . $currentYear . '-' . str_pad($prevKW, 2, '0', STR_PAD_LEFT) . '.csv';

    while (!file_exists($prevFile) && $prevKW > 0) {
        $prevKW--;
        $prevFile = $csvDir . $currentYear . '-' . str_pad($prevKW, 2, '0', STR_PAD_LEFT) . '.csv';
    }

    return file_exists($prevFile) ? [$prevKW, $currentYear] : null;
}

// Find previous appearance for RE (re-entry)
function findPreviousPlacement($title, $artist, $csvDir, $currentYear, $currentKW) {
    $years = range((int)$currentYear, 2020); // Adjust start year if needed
    foreach ($years as $year) {
        for ($kw = 53; $kw >= 1; $kw--) {
            if ($year == $currentYear && $kw >= $currentKW) continue;
            $file = sprintf("%s%04d-%02d.csv", $csvDir, $year, $kw);
            if (file_exists($file)) {
                $data = array_map('str_getcsv', file($file));
                foreach ($data as $row) {
                    if (isset($row[1], $row[2]) && trim($row[1]) === trim($title) && trim($row[2]) === trim($artist)) {
                        return true;
                    }
                }
            }
        }
    }
    return false;
}

// Main logic
if (isset($_POST['kw'])) {
    if (strpos($_POST['kw'], 'yearly') !== false) {
        // Display yearly charts
        [$year] = explode('-', $_POST['kw']);
        $csvFile = "$year.csv";
        $filePath = $csvDir . $csvFile;
        $data = file_exists($filePath) ? array_map('str_getcsv', file($filePath)) : [];

        echo "<h1>Top 40 of $year</h1>";

        if (!empty($data)) {
            echo "<table>
                <tr>
                    <th>Position</th>
                    <th>Title</th>
                    <th>Artist</th>
                    <th>Best Position</th>
                    <th>Former #1?</th>
                </tr>";

            foreach ($data as $row) {
                if (isset($row[0], $row[1], $row[2])) {
                    $position = $row[0];
                    $title = $row[1];
                    $artist = $row[2];
                    $bestPlacement = getBestPlacement($title, $artist, $csvDir, $year);
                    $formerNo1 = ($bestPlacement == 1) ? "Yes" : "No";

                    echo "<tr>
                        <td>$position</td>
                        <td>$title</td>
                        <td>$artist</td>
                        <td>$bestPlacement</td>
                        <td>$formerNo1</td>
                    </tr>";
                }
            }

            echo "</table>";
        }
    } else {
        // Display weekly charts
        [$year, $kw] = explode('-', $_POST['kw']);
        $csvFile = "$year-$kw.csv";
        $filePath = $csvDir . $csvFile;
        $data = file_exists($filePath) ? array_map('str_getcsv', file($filePath)) : [];

        if (!empty($data)) array_shift($data); // Remove header

        $nextEarlierWeek = getNextEarlierWeek($year, $kw, $csvDir);

        echo "<h1>Top 40 - KW$kw / $year</h1>";

        if (!empty($data)) {
            echo "<table>
                <tr>
                    <th>Position</th>
                    <th>Title</th>
                    <th>Artist</th>
                    <th>KW" . ($nextEarlierWeek ? $nextEarlierWeek[0] : "") . "</th>
                    <th>Diff.</th>
                </tr>";

            foreach ($data as $row) {
                if (isset($row[0], $row[1], $row[2])) {
                    $position = $row[0];
                    $title = $row[1];
                    $artist = $row[2];
                    $prevWeek = "NEW";
                    $diff = "NEW";

                    if ($nextEarlierWeek) {
                        $prevFile = sprintf("%s%04d-%02d.csv", $csvDir, $nextEarlierWeek[1], $nextEarlierWeek[0]);
                        if (file_exists($prevFile)) {
                            $prevData = array_map('str_getcsv', file($prevFile));
                            foreach ($prevData as $prevRow) {
                                if (isset($prevRow[1], $prevRow[2]) && trim($prevRow[1]) === trim($title) && trim($prevRow[2]) === trim($artist)) {
                                    $prevWeek = $prevRow[0];
                                    $diff = (int)$prevWeek - (int)$position;
                                    break;
                                }
                            }
                        }
                    }

                    // If not found in previous week but exists earlier -> RE
                    if ($prevWeek === "NEW" && findPreviousPlacement($title, $artist, $csvDir, $year, $kw)) {
                        $prevWeek = "RE";
                        $diff = "RE";
                    }

                    echo "<tr>
                        <td>$position</td>
                        <td>$title</td>
                        <td>$artist</td>
                        <td>$prevWeek</td>
                        <td>$diff</td>
                    </tr>";
                }
            }

            echo "</table>";
        }
    }
}
?>
</body>
</html>
