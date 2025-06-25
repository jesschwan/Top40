<?php
function getKwList($openDbConnection) {
    $kwList = [];

    $query = "SELECT DISTINCT kw, jahr FROM top40 WHERE 1 ORDER BY jahr ASC, kw ASC";

    $stmt = $openDbConnection->prepare($query);
    if (!$stmt) {
        die("Error preparing the query: " . $openDbConnection->error);
    }

    $stmt->execute();

    $result = $stmt->get_result();

    while ($row = $result->fetch_assoc()) {
        $kwList[] = ['year' => $row["jahr"], 'kw' => $row["kw"]];
    }
    return $kwList;                        
}

function generateDropdown($kwList, $selectedKw = null) {
    echo '<select name="kwYearDropDown" class="dropdown">';
    foreach ($kwList as $entry) {
        $kw = str_pad($entry['kw'], 2, '0', STR_PAD_LEFT);
        $year = $entry['year'];
        $value = "$year-$kw";
        $label = "KW$kw / $year";
        $selected = ($selectedKw === $value) ? 'selected' : '';
        $selectedLabel = "KW" . str_pad($kw, 2, '0', STR_PAD_LEFT) . " / $year";
        echo "<option value=\"$value\" $selected>$label</option>";
    }
    echo '</select>';
    
}
