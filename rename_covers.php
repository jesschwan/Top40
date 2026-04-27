<?php
    /**
     * Einmaliges Migrations-Script:
     * Benennt vorhandene Cover-Dateien von
     *   "<Titel> - <Interpret>.avif"
     * zu
     *   "<song_id>.avif"
     *
     * Danach NICHT mehr erneut ausführen!
     */

    require_once __DIR__ . '/SqlConnection.php';
    require_once __DIR__ . '/classes/Top40Entry.php';

    $db = getSqlConnection();
    $imageDir = __DIR__ . '/images/';

    echo "<pre>";

    // Alle Songs laden
    $result = $db->query("
        SELECT song_id, song_name, artist
        FROM songs
        ORDER BY song_id ASC
    ");

    if (!$result) {
        die("DB-Fehler: " . $db->error);
    }

    $renamed = 0;
    $missing = 0;

    while ($row = $result->fetch_assoc()) {
        $songId = (int)$row['song_id'];

        // Alten Dateinamen so erzeugen wie früher
        $entry = new Top40Entry(
            0,
            $row['song_name'],
            $row['artist'],
            null,
            0,
            0,
            $songId
        );

        $oldName = $entry->getSafeFilename('avif');
        $oldPath = $imageDir . $oldName;
        $newPath = $imageDir . $songId . '.avif';

        // Überspringen, wenn Ziel-Datei schon existiert
        if (file_exists($newPath)) {
            echo "↪ SKIP   : {$songId}.avif existiert bereits\n";
            continue;
        }

        if (file_exists($oldPath)) {
            if (rename($oldPath, $newPath)) {
                echo "✔ RENAMED: $oldName → {$songId}.avif\n";
                $renamed++;
            } else {
                echo "✖ ERROR  : Konnte $oldName nicht umbenennen\n";
            }
        } else {
            echo "⚠ MISSING: $oldName\n";
            $missing++;
        }
    }

    echo "\nFERTIG.\n";
    echo "Umbenannt: $renamed\n";
    echo "Fehlend  : $missing\n";
    echo "</pre>";
?>