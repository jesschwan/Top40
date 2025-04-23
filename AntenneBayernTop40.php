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

<!-- Form with KW dropdown -->
<form method="post">
    <div class="form-container">
        <label for="kw">Wähle eine KW: </label>

        <?php
        // Set path to the CSV directory
        $csvDir = "CSV/";
        $files = glob($csvDir . "*.csv");
        $kwList = [];

        // Read all CSV files and extract KW/year
        foreach ($files as $file) {
            if (preg_match('/(\d{4})-(\d{1,2})\.csv$/', basename($file), $matches)) {
                $year = $matches[1];
                $kw = str_pad($matches[2], 2, '0', STR_PAD_LEFT);
                $kwList[] = ['year' => $year, 'kw' => $kw];
            }
        }

        // Sort list by year and KW
        usort($kwList, function ($a, $b) {
            return ($a['year'] . $a['kw']) <=> ($b['year'] . $b['kw']);
        });
        ?>

        <select name="kw" class="dropdown">
            <?php
            foreach ($kwList as $entry) {
                $value = $entry['year'] . '-' . $entry['kw'];
                $label = $entry['year'] . " / KW" . $entry['kw'];
                $selected = (isset($_POST['kw']) && $_POST['kw'] === $value) || (!isset($_POST['kw']) && $value === end($kwList)['year'] . '-' . end($kwList)['kw']) ? 'selected' : '';
                echo "<option value=\"$value\" $selected>$label</option>";
            }
            ?>
        </select>

        <button type="submit">Submit</button>
    </div>
</form>

<?php
// Function to display the warning message
function showWarningMessage() {
    echo "<div class='warning'>Keine Daten der Vorwoche vorhanden!</div>";
}

if (isset($_POST['kw'])) {
    [$year, $kw] = explode('-', $_POST['kw']);
    $csvFile = "$year-$kw.csv";
    $filePath = $csvDir . $csvFile;

    $data = file_exists($filePath) ? array_map('str_getcsv', file($filePath)) : [];

    if (!empty($data)) array_shift($data); // Remove header from selected KW

    // Fallback loop to find previous data
    $prevData = [];
    $prevLabel = "";
    $foundPrev = false;

    // Flag to track if any previous week data was found
    $previousWeekFound = false;

    // Loop through previous weeks to find data
    for ($i = array_search(['year' => $year, 'kw' => $kw], $kwList) - 1; $i >= 0; $i--) {
        $prevEntry = $kwList[$i];
        $prevFile = $csvDir . $prevEntry['year'] . '-' . $prevEntry['kw'] . ".csv";
        
        // Check if the file exists and contains data (more than just the header)
        if (file_exists($prevFile) && count(file($prevFile)) > 1) {
            $prevData = array_map('str_getcsv', file($prevFile));
            if (!empty($prevData)) array_shift($prevData); // Remove header from prev file
            $prevLabel = "KW" . $prevEntry['kw'] . " / " . $prevEntry['year'];
            $foundPrev = true;
            $previousWeekFound = true; // Mark that previous week data was found
            break;
        }
    }

    // If no previous week data was found, show warning message and stop further processing
    if (!$previousWeekFound) {
        showWarningMessage();
    } else {
        // Show headline
        echo "<h1>Top 40 – KW$kw / $year</h1>";

        if (!empty($data)) {
            // Sort current KW data by position (Platz) as integer
            usort($data, function ($a, $b) {
                return (int)$a[0] <=> (int)$b[0];
            });

            echo "<table>
                <tr>
                    <th>Platz</th>
                    <th>Titel</th>
                    <th>Interpret</th>
                    <th>$prevLabel</th>
                    <th>Diff.</th>
                </tr>";

            foreach ($data as $row) {
                $platz = $row[0];
                $titel = $row[1];
                $interpret = $row[2];

                // Initialize default values for Vorwoche and Diff
                $vorw = "";
                $diff = "";

                // Check if previous week's data is available and match the title
                if ($foundPrev) {
                    $vorw = "NEW"; // Default is "NEW"
                    $diff = "NEW"; // Default is "NEW"

                    // If title exists in previous data, update Vorwoche and Diff
                    foreach ($prevData as $prevRow) {
                        if ($prevRow[1] == $titel) {
                            $vorw = $prevRow[0]; // Get Platz from previous week
                            $diff = (int)$vorw - (int)$platz; // Calculate the difference
                            break; // Stop once we find the title
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

            echo "</table>";
        }
    }
}
?>
</body>
</html>
