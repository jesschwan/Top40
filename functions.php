<?php
// Fetches a list of distinct calendar weeks (kw) and years (jahr) from the database,
// ordered by year and week ascending.
function getKwList($openDbConnection) {
    $kwList = [];

    $query = "SELECT DISTINCT kw, jahr FROM top40 WHERE 1 ORDER BY jahr ASC, kw ASC";

    // Prepare the SQL statement
    $stmt = $openDbConnection->prepare($query);
    if (!$stmt) {
        // Terminate script with error message if preparation fails
        die("Error preparing the query: " . $openDbConnection->error);
    }

    // Execute the prepared statement
    $stmt->execute();

    // Retrieve the result set from the executed statement
    $result = $stmt->get_result();

    // Fetch each row and append year and week as an associative array to $kwList
    while ($row = $result->fetch_assoc()) {
        $kwList[] = ['year' => $row["jahr"], 'kw' => $row["kw"]];
    }
    return $kwList;                        
}

// Generates an HTML dropdown (<select>) for the given list of calendar weeks and years.
// Optionally pre-selects a value if $selectedKw matches the option's value.
function generateDropdown($kwList, $selectedKw = null) {
    echo '<select name="kwYearDropDown" class="dropdown">';
    
    // Loop through each week-year entry to create an <option>
    foreach ($kwList as $entry) {
        // Pad week number with leading zero to two digits (e.g., '01', '02')
        $kw = str_pad($entry['kw'], 2, '0', STR_PAD_LEFT);
        $year = $entry['year'];
        
        // Compose the option value and label (e.g., "2025-01" and "KW01 / 2025")
        $value = "$year-$kw";
        $label = "KW$kw / $year";
        
        // Check if this option should be selected based on $selectedKw
        $selected = ($selectedKw === $value) ? 'selected' : '';
        
        // Output the option element with value, label, and selection status
        echo "<option value=\"$value\" $selected>$label</option>";
    }
    
    echo '</select>';
}