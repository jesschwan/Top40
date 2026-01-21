<?php
    require_once "classes/Top40Entry.php";
    require_once "SqlConnection.php";
    require_once "classes/ImageFromAPI.php";
    require "functions.php";

    // Fetch Top 40 data for a specific week (year + KW)
    function getData4KW($openDbConnection, $year, $kw, $kwList) {
        $KWDataArray = [];

        // Query: Top 40 entries of the selected calendar week directly from top40
        $query = "
        SELECT 
            t.platz AS platz,
            t.song_id AS song_id,
            s.song_name AS titel,
            s.artist AS interpret,
            s.cover_image AS cover
        FROM top40 t
        JOIN songs s ON s.song_id = t.song_id
        WHERE t.jahr = ? AND t.kw = ?
        ORDER BY t.platz
        LIMIT 40;
        ";

        $stmt = $openDbConnection->prepare($query);
        if (!$stmt) die("Prepare failed: (" . $openDbConnection->errno . ") " . $openDbConnection->error);

        // Placeholder is filled and query is executed
        $stmt->bind_param("ii", $year, $kw);
        $stmt->execute();
        $result = $stmt->get_result();

        $currentRank = 0;


        while ($row = $result->fetch_assoc()) {
            if ($currentRank != $row["platz"]) {
                $entry = new Top40Entry(
                    (int)$row["platz"],
                    $row["titel"],
                    $row["interpret"],
                    $row["cover"],
                    $kw,
                    $year,
                    null
                );

                // song_id an den Entry hängen (oder Constructor erweitern)
                if (property_exists($entry, 'songId')) {
                    $entry->songId = (int)$row['song_id'];
                }

                // Vorwoche über song_id ermitteln (siehe neue Funktion unten)
                $prev = getPreviousChartPosition(
                    (int)$row['song_id'],
                    $year,
                    $kw,
                    $entry->platz,
                    $openDbConnection,
                    $kwList
                );

                $entry->previousRank = $prev['prev'];
                $entry->diff = $prev['diff'];

                $KWDataArray[] = $entry;
            }
            $currentRank = $row["platz"];
        }

        $stmt->close();
        return $KWDataArray;
    }

    // Find the closest earlier week in the database
    function getNextEarlierWeek($openDbConnection, $year, $kw) {
        // Monday of the current calendar week
        $currentWeek = new DateTime();
        $currentWeek->setISODate($year, $kw);
        $currentDate = $currentWeek->format('Y-m-d');

        $stmt = $openDbConnection->prepare("
            SELECT weekYear 
            FROM placings 
            WHERE weekYear < ? 
            ORDER BY weekYear DESC 
            LIMIT 1
        ");

        if (!$stmt) return null;

        $stmt->bind_param("s", $currentDate);
        $stmt->execute();
        $stmt->bind_result($prevWeekDate);

        if ($stmt->fetch()) {
            $stmt->close();
            $prevDate = new DateTime($prevWeekDate);
            $prevYear = (int)$prevDate->format('o');   // ISO Year
            $prevKW   = (int)$prevDate->format('W');   // ISO Week
            return [$prevYear, $prevKW];
        }

        $stmt->close();
        return null;
    }

    // Return TRUE if no previous week exists
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
                WHERE LOWER(song_name) = LOWER(?) 
                AND LOWER(artist) = LOWER(?) 
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

        // Check if this is the first appearance of the song
        $stmt2 = $openDbConnection->prepare("
            SELECT jahr, kw 
            FROM top40 
            WHERE LOWER(song_name) = LOWER(?) 
            AND LOWER(artist) = LOWER(?) 
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

        // Return "RE" if previous week info not available
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

    // Open database connection
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

    $selectedKw = null;
    if ( isset($_POST['kwYearDropDown']))
        $selectedKw = $_POST['kwYearDropDown'];

    if ($selectedKw) {
        [$year, $kw] = explode('-', $selectedKw);
        $year = (int)$year;
        $kw = (int)$kw;
    } else {
        // Selected year/week from dropdown or fallback to latest        
        $latest = end($kwList);
        $year = (int)$latest['year'];
        $kw = (int)$latest['kw'];
    }

    $titel = null;
    $interpret = null;

    if (isset($_FILES['coverFile']) && isset($_POST['songId'])) {
        $coverData = file_get_contents($_FILES['coverFile']['tmp_name']);
        $songId = (int)$_POST['songId'];

        if ($coverData && $songId) {
            $stmt = $openDbConnection->prepare("UPDATE songs SET cover_image = ? WHERE song_id = ?");
            if (!$stmt) die("DB-Error: " . $openDbConnection->error);

            $stmt->bind_param("si", $coverData, $songId);
            $stmt->execute();
            $stmt->close();
        }
    }

    // Fetch data for selected week
    $data = getData4KW($openDbConnection, $year, $kw, $kwList);

    // Get previous week label
    $prevWeekLabel = getPrevWeekLabel4Header($openDbConnection, $year, $kw);

    // Label for current week
    $selectedLabel = "KW" . str_pad($kw, 2, '0', STR_PAD_LEFT) . " / $year";

    // Show warning if no previous week exists
    $showWarning = hasNoPreviousWeek($openDbConnection, $year, $kw);
                
?>

<!-- HTML starts here ------------->

<!DOCTYPE html>
<html lang="de">
    <head>
        <meta charset="UTF-8">
        <title>Top 40</title>
        <link href="styles.css" rel="stylesheet">
    </head>

    <!-- php-Teile des bodys in requires auslagern-->
    <body>
    <!-- Week selection form -->
        <div class="form-container">
            <form method="post" class="form-container">
                <label for="kwYearDropDown">Wähle:</label>
                <select name="kwYearDropDown" id="kwYearDropDown" class="dropdown">
                    <?php foreach ($kwList as $entry): 
                        $isSelected = ($entry['year'] == $year && $entry['kw'] == $kw) ? 'selected' : '';
                        $label = "KW" . str_pad($entry['kw'], 2, '0', STR_PAD_LEFT) . " / {$entry['year']}";
                        echo "<option value='{$entry['year']}-{$entry['kw']}' $isSelected>$label</option>";
                    endforeach; ?> <!--raus, in requires auslagern-->
                </select>
                <button type="submit" class="button-submit">Submit</button>
            </form>
        </div>

        <!-- Display header or warning -->
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

                <!-- Render each row of data -->
                <!-- // ToDo: Buttons for getting Cover, so use Form, POST.... -->
                <?php foreach ($data as $entry): 
                    echo ($entry->renderRow());
                    endforeach;
                ?>

            </table>
        <?php endif; ?>
    </body>
</html>
