<?php
require "SqlConnection.php";

$db = getSqlConnection();

$sql = "SELECT DISTINCT titel, interpret FROM top40";
$result = $db->query($sql);

$counter = 0;
$notFound = [];

echo "<!DOCTYPE html><html lang='de'><head><meta charset='UTF-8'><title>Cover-Update</title></head><body>";
echo "<h2>🎵 Cover-Update für Top40</h2>";

while ($row = $result->fetch_assoc()) {
    $titel = trim($row['titel']);
    $interpret = trim($row['interpret']);

    // Dateinamen konstruieren
    $filename = $titel . ' - ' . $interpret . '.jpg';
    $filepath = __DIR__ . '/images/' . $filename;

    // Prüfe auch .JPG als Fallback
    if (!file_exists($filepath)) {
        $filenameAlt = $titel . ' - ' . $interpret . '.JPG';
        $filepathAlt = __DIR__ . '/images/' . $filenameAlt;
        if (file_exists($filepathAlt)) {
            $filename = $filenameAlt;
            $filepath = $filepathAlt;
        }
    }

    if (file_exists($filepath)) {
        // Verwende LIKE, um Unterschiede in Groß-/Kleinschreibung & Leerzeichen auszugleichen
        $titelLike = "%" . $db->real_escape_string($titel) . "%";
        $interpretLike = "%" . $db->real_escape_string($interpret) . "%";

        $stmt = $db->prepare("UPDATE top40 SET cover = ? WHERE titel LIKE ? AND interpret LIKE ?");
        if (!$stmt) {
            echo "<p style='color:red;'>❌ Fehler beim Vorbereiten des Statements: " . htmlspecialchars($db->error) . "</p>";
            continue;
        }

        $stmt->bind_param("sss", $filename, $titelLike, $interpretLike);
        $stmt->execute();

        if ($stmt->affected_rows > 0) {
            $counter++;
            echo "<p style='color:green;'>✔ Cover eingetragen für: <strong>$titel</strong> - <em>$interpret</em></p>";
        } else {
            echo "<p style='color:orange;'>⚠ Kein Eintrag aktualisiert für: <strong>$titel</strong> - <em>$interpret</em></p>";
        }

        $stmt->close();
    } else {
        $notFound[] = $titel . ' - ' . $interpret;
    }
}

// Debug: Zeigt an, ob überhaupt ein passender Datenbankeintrag existiert
$titelLike = "%" . $db->real_escape_string($titel) . "%";
$interpretLike = "%" . $db->real_escape_string($interpret) . "%";

$check = $db->query("SELECT * FROM top40 WHERE titel LIKE '$titelLike' AND interpret LIKE '$interpretLike'");
if ($check && $check->num_rows === 0) {
    echo "<p style='color:gray;'>🔍 Kein DB-Treffer für: <code>$titelLike</code> / <code>$interpretLike</code></p>";
}

echo "<hr><p><strong>$counter</strong> Cover erfolgreich eingetragen.</p>";

if (!empty($notFound)) {
    echo "<h3>🚫 Kein Bild gefunden für:</h3><ul>";
    foreach ($notFound as $entry) {
        echo "<li>" . htmlspecialchars($entry) . "</li>";
    }
    echo "</ul>";
}

echo "</body></html>";
?>
