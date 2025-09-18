<?php

class ImageFromAPI {
        // return string is a binary string ! -- Image data
        public function getFrontCover(string $interpret, string $titel): string {
            $ImageContent = NULL;
            // ToDO: call API to get FrontCover from www
            // for now old style:
            // 1. try to find Cover path + filename by  interpret & title --> like in update_covers
            $titelRaw = trim($titel);
            $interpretRaw = trim($interpret);

            // Use Top40Entry to generate a safe and consistent filename
            $entry = new Top40Entry(0, $titelRaw, $interpretRaw, null, 0, 0);
            $baseName = pathinfo($entry->getSafeFilename('avif'), PATHINFO_FILENAME);

            // Build full path to the expected AVIF file
            $avifFile = $this->folder . $baseName . '.avif';

            // If the file exists, return its binary content
            if (file_exists($avifFile)) {
                $ImageContent = file_get_contents($avifFile);
            }

            // check if length is OK ??
            return $ImageContent;
        }

        public function writeImageToDB(){
        $stmt = $db->prepare("UPDATE top40 SET cover = ? WHERE titel = ? AND interpret = ?");
            if ($stmt) {
                $stmt->bind_param("sss", $ImageContent, $titelRaw, $interpretRaw);
                $stmt->execute();
                if ($stmt->affected_rows > 0) $counterUpdated += $stmt->affected_rows;
                $stmt->close();
            }
            
        }
    }
?>