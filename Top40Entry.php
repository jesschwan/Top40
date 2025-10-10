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

        public function __construct(int $platz, string $titel, string $interpret, ?string $cover, int $kw, int $jahr) {
            $this->platz = $platz;
            $this->titel = $titel;
            $this->interpret = $interpret;
            $this->cover = $cover; // Store cover data
            $this->kw = $kw;
            $this->jahr = $jahr;
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
            // Check if a cover exists (at least 500 bytes for a real AVIF file)
            $hasCover = ($this->cover !== null && strlen($this->cover) > 500);

            if ($hasCover) {
                $base64 = base64_encode($this->cover);
                $coverHtml = '<img src="data:image/avif;base64,' . $base64 . '" alt="Cover" width="100">';
            } else {
                // No cover → show a button
                $coverHtml = '<span><button type="submit" class="button-cover">Get Cover</button></span>';
            }

            // calculation for week difference column outfit --> setting css class
            $diffClass = '';
            if ($this->diff !== null && is_numeric($this->diff)) {
                if ($this->diff > 0) $diffClass = ' class="diff-up"';
                elseif ($this->diff < 0) $diffClass = ' class="diff-down"';
            }

            $prev = '';
            if ($this->previousRank !== null)
                $prev = $this->previousRank;
            $diff = '';
            if ($this->diff != null)
                $diff = $this->diff;

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