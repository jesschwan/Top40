<?php
require_once __DIR__ . "/SqlConnection.php";

$db = getSqlConnection();

// Alle relevanten Songs aus den Top40 holen
$sql = "
    SELECT DISTINCT
        s.song_id,
        s.song_name,
        s.artist
    FROM top40 t
    JOIN songs s ON t.song_id = s.song_id
    ORDER BY s.song_id
";

$result = $db->query($sql);
if (!$result) {
    die("DB Error: " . $db->error);
}

// Bilderordner
$folder = __DIR__ . "/images/";

$missing = [];

echo "<h1>🖼️ Cover‑Check (song_id‑basiert)</h1><hr>";

while ($row = $result->fetch_assoc()) {

    $songId = (int)$row['song_id'];
    $title  = $row['song_name'];
    $artist = $row['artist'];

    $avif = $folder . $songId . ".avif";
    $jpg  = $folder . $songId . ".jpg";

    if (file_exists($avif)) {
        echo "✅ Cover vorhanden (AVIF): <strong>$songId</strong> – "
           . htmlspecialchars("$title – $artist") . "<br>";
    } elseif (file_exists($jpg)) {
        echo "✅ Cover vorhanden (JPG): <strong>$songId</strong> – "
           . htmlspecialchars("$title – $artist") . "<br>";
    } else {
        echo "⚠️ Kein Cover gefunden für: <strong>$songId</strong> – "
           . htmlspecialchars("$title – $artist") . "<br>";
        $missing[] = "$songId – $title – $artist";
    }
}

echo "<hr><strong>Fertig.</strong> Es wurden keine Dateien oder Datenbankeinträge geändert.<br>";

if (!empty($missing)) {
    echo "<h3>❌ Songs ohne Cover:</h3><ul>";
    foreach ($missing as $m) {
        echo "<li>" . htmlspecialchars($m) . "</li>";
    }
    echo "</ul>";
} else {
    echo "<p>🎉 Alle Top‑40‑Songs haben ein Cover!</p>";
}
?>