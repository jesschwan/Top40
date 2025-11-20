<?php

class ImageFromAPI {

    private static string $folder = _DIR_.'/images/';


    // Returns a string containing the binary image data
    public static function getFrontCover(string $interpret, string $titel): string {
        $ImageContent = NULL;

        // TODO: Call external API to fetch front cover image
        // For now, use the old style:
        // 1. Try to find cover file path + filename based on interpret & title (like in update_covers)

        $titelRaw = trim($titel);
        $interpretRaw = trim($interpret);

        // Use Top40Entry to generate a safe and consistent filename
        $entry = new Top40Entry(0, $titelRaw, $interpretRaw, null, 0, 0);
        $baseName = pathinfo($entry->getSafeFilename('avif'), PATHINFO_FILENAME);

        // Build full path to the expected AVIF file
        $avifFile = self::$folder . $baseName . '.avif';

        // If the file exists, read its binary content
        if (file_exists($avifFile)) {
            $ImageContent = file_get_contents($avifFile);
        }

        // Optional: check if content length is sufficient / valid
        return $ImageContent;
    }

    // Write the image data to the database for a specific song
    public function writeImageToDB(){
        // Prepare the SQL statement
        $stmt = $db->prepare("UPDATE songs SET cover_image = ? WHERE song_id = ?");
        $stmt->bind_param("si", $coverData, $songId);
        $stmt->execute();

        if ($stmt) {
            // Bind parameters: image data, title, artist
            $stmt->bind_param("sss", $ImageContent, $titelRaw, $interpretRaw);
            $stmt->execute();

            // Increment counter if rows were updated
            if ($stmt->affected_rows > 0) $counterUpdated += $stmt->affected_rows;

            // Close the statement
            $stmt->close();
        }
    }
}
?>
