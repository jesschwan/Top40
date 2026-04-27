<?php
require_once "SqlConnection.php";

$db = getSqlConnection();
$folder = __DIR__ . '/images/';
$files = glob($folder . '*.avif');

$counterUpdated = 0;

echo "<!DOCTYPE html><html lang='de'><head>
<meta charset='UTF-8'>
<title>Cover Update</title>
</head><body>";

echo "<h1>🚀 Upload Covers per song_id</h1><hr>";

$stmt = $db->prepare(
    "UPDATE songs SET cover_image = ? WHERE song_id = ?"
);

foreach ($files as $filePath) {

    $basename = basename($filePath);                     // 123.avif
    $songId = (int) pathinfo($basename, PATHINFO_FILENAME);

    if ($songId <= 0) {
        echo "<p style='color:orange;'>⚠️ Ungültiger Dateiname: $basename</p>";
        continue;
    }

    $coverData = file_get_contents($filePath);

    $stmt->bind_param("si", $coverData, $songId);
    $stmt->execute();

    if ($stmt->affected_rows > 0) {
        $counterUpdated++;
        echo "<p style='color:green;'>✅ Cover gesetzt für song_id: <strong>$songId</strong></p>";
    } else {
        echo "<p style='color:red;'>⚠️ Keine DB-Zeile für song_id: <strong>$songId</strong></p>";
    }
}

$stmt->close();

echo "<hr><p><strong>$counterUpdated</strong> Covers erfolgreich hochgeladen.</p>";
echo "</body></html>";
?>