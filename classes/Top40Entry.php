<?php
    class Top40Entry {
        public int $platz;
        public string $titel;
        public string $interpret;
        public ?string $cover;
        public int $kw;
        public int $jahr;
        public $previousRank = null;
        public $diff = null;
        public ?int $songId = null; // optional, da manchmal nicht gesetzt

        public function __construct(int $platz, string $titel, string $interpret, ?string $cover, int $kw, int $jahr, ?int $songId = null) {
            $this->platz = $platz;
            $this->titel = $titel;
            $this->interpret = $interpret;
            $this->cover = $cover;
            $this->kw = $kw;
            $this->jahr = $jahr;
            $this->songId = $songId; // ← NEU
        }

        // Generate a safe filename for the cover image
        public function getSafeFilename(string $ext = 'avif'): string {
            $str = $this->titel . ' - ' . $this->interpret;

            if (class_exists('Normalizer')) {
                $str = Normalizer::normalize($str, Normalizer::FORM_C);
            }

            $str = str_replace(["’", "‘", "`"], "'", $str);
            $str = preg_replace('/[\p{Cc}\p{Cf}]+/u', '', $str);
            $clean = preg_replace("/[^\p{L}\p{N} ()'\\-\\.,&!]/u", '', $str);
            $clean = preg_replace('/\s+/u', ' ', $clean);

            return trim($clean) . '.' . $ext;
        }
        
        // Render a table row for this entry
        public function renderRow(): string {
            // Prüfen, ob ein Cover vorhanden ist (mindestens 500 Bytes)
            $hasCover = false;

            if (is_string($this->cover) && strlen($this->cover) > 500) {
                $imageInfo = @getimagesizefromstring($this->cover);
                if ($imageInfo !== false) {
                    $hasCover = true;
                }
            }

            // Cover HTML generieren
            if ($hasCover) {
                // Bild als Base64 einbinden
                $base64 = base64_encode($this->cover);
                $coverHtml = '<img src="data:image/avif;base64,' . $base64 . '" alt="Cover" width="100">';
            } else {
                // Kein Cover → Button + unsichtbarer File-Input
                $coverHtml = '<form method="post" enctype="multipart/form-data" class="cover-upload-form">
                    <input type="hidden" name="songId" value="' . htmlspecialchars($this->songId) . '">
                    <input type="file" name="coverFile" id="fileInput' . $this->platz . '" style="display:none"
                        onchange="this.form.submit()" required>
                    <button type="button" class="button-cover"
                        onclick="document.getElementById(\'fileInput' . $this->platz . '\').click()">
                        Cover holen
                    </button>
                </form>';
            }

            // CSS-Klasse für Positionsänderung (diff)
            $diffClass = '';
            if ($this->diff !== null && is_numeric($this->diff)) {
                if ($this->diff > 0) $diffClass = ' class="diff-up"';
                elseif ($this->diff < 0) $diffClass = ' class="diff-down"';
            }

            $prev = $this->previousRank ?? '';
            $diff = $this->diff ?? '';

            // Tabelle-Zeile zusammenstellen
            return "<tr>
                <td>{$this->platz}</td>
                <td>{$this->titel}</td>
                <td>{$this->interpret}</td>
                <td>$coverHtml</td>
                <td>$prev</td>
                <td$diffClass>$diff</td>
            </tr>";
        }
    }
?>