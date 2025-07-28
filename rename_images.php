<?php
require "SqlConnection.php";

function sanitizeFilename($string) {
    $clean = preg_replace('/[^A-Za-z0-9äöüÄÖÜß ()\'\-.,]/u', '', $string);
    $clean = preg_replace('/\s+/', ' ', $clean);
    return trim($clean);
}

$openDbConnection = getSqlConnection();
$sql = "SELECT DISTINCT titel, interpret FROM top40";
$result = $openDbConnection->query($sql);

if (!$result) die("DB-Fehler: " . $openDbConnection->error);

$folder = __DIR__ . '/images/';
$allFiles = array_merge(glob($folder . '*.jpg'), glob($folder . '*.JPG'));
$renamedCount = 0;
$notMatched = [];

echo "<h1>Bild-Umbenennung</h1>";

while ($row = $result->fetch_assoc()) {
    $title = $row['titel'];
    $artist = $row['interpret'];

    $expectedFilename = sanitizeFilename("$title - $artist") . ".jpg";
    $expectedPath = $folder . $expectedFilename;

    if (file_exists($expectedPath)) continue;

    $bestMatch = null;
    $highestSimilarity = 0;

    foreach ($allFiles as $filePath) {
        $filename = basename($filePath);
        $base = str_ireplace('_', ' ', pathinfo($filename, PATHINFO_FILENAME));
        $target = "$title - $artist";

        similar_text($base, $target, $percent);

        if ($percent > $highestSimilarity && $percent > 70) {
            $highestSimilarity = $percent;
            $bestMatch = $filePath;
        }
    }

    if ($bestMatch) {
        if (rename($bestMatch, $expectedPath)) {
            echo "✅ Umbenannt: <code>" . htmlspecialchars(basename($bestMatch)) . "</code> ➜ <code>$expectedFilename</code><br>";
            $renamedCount++;
        } else {
            echo "❌ Fehler beim Umbenennen: <code>" . htmlspecialchars(basename($bestMatch)) . "</code><br>";
        }
    } else {
        echo "⚠️ Kein Match gefunden für: <strong>" . htmlspecialchars($title) . " - " . htmlspecialchars($artist) . "</strong><br>";
        $notMatched[] = "$title - $artist";
    }
}

echo "<br><strong>Fertig!</strong> $renamedCount Datei(en) umbenannt.<br>";

if (!empty($notMatched)) {
    echo "<h3>Keine passenden Dateien gefunden für:</h3><ul>";
    foreach ($notMatched as $nm) {
        echo "<li>" . htmlspecialchars($nm) . "</li>";
    }
    echo "</ul>";
}
?>