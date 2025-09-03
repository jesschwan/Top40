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
        $this->cover = $cover;
        $this->kw = $kw;
        $this->jahr = $jahr;
    }

    /**
     * Generate a safe filename for the cover image.
     * Keeps apostrophes and normalizes typographic variants.
     */
    public function getSafeFilename(): string {
        $str = $this->titel . ' - ' . $this->interpret;

        // Normalize Unicode (NFC)
        if (class_exists('Normalizer')) {
            $str = Normalizer::normalize($str, Normalizer::FORM_C);
        }

        // Convert typographic apostrophes to simple '
        $str = str_replace(["’", "‘", "`"], "'", $str);

        // Remove control/format characters
        $str = preg_replace('/[\p{Cc}\p{Cf}]+/u', '', $str);

        // Allow letters, numbers, spaces, and some punctuation including apostrophes
        $clean = preg_replace("/[^\p{L}\p{N} ()'\\-\\.,&!]/u", '', $str);

        // Collapse multiple spaces
        $clean = preg_replace('/\s+/u', ' ', $clean);

        // Return trimmed filename with lowercase .jpg
        return trim($clean) . '.jpg';
    }

    /**
     * Render a table row for this entry.
     */
    public function renderRow(): string {
        $filename = $this->cover ?? $this->getSafeFilename();
        $filepath = __DIR__ . '/images/' . $filename;
        $coverPath = 'images/' . rawurlencode($filename) . '?v=' . time();
        $imageFound = file_exists($filepath);

        $diffClass = '';
        if (is_numeric($this->diff)) {
            if ($this->diff > 0) $diffClass = ' class="diff-up"';
            elseif ($this->diff < 0) $diffClass = ' class="diff-down"';
        }

        $coverHtml = $imageFound 
            ? "<img src=\"$coverPath\" alt=\"Cover\" width=\"100\">" 
            : "<span style='color:red;'>Kein Bild gefunden!</span>";

        $prev = $this->previousRank ?? '';
        $diff = $this->diff ?? '';

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