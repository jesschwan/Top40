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
    $options = '';
    foreach ($kwList as $entry) {
        $value = $entry['year'] . '-' . $entry['kw'];
        $label = ($entry['kw'] === 'yearly') ? $entry['year'] : $entry['year'] . " / KW" . $entry['kw'];
        $selected = ($selectedKw === $value) || ($selectedKw === null && $value === end($kwList)['year'] . '-' . end($kwList)['kw']) ? 'selected' : '';
        $options .= "<option value=\"$value\" $selected>$label</option>";
    }
    echo "<select name='kwYearDropDown' class='dropdown'>$options</select>";
}
