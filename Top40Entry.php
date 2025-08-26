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

    // Safe filename for cover
    public function getSafeFilename(): string {
        $clean = preg_replace('/[^A-Za-z0-9äöüÄÖÜß ()\'\-.,]/u', '', $this->titel . ' - ' . $this->interpret);
        $clean = preg_replace('/\s+/', ' ', $clean);
        return trim($clean) . '.jpg';
    }

    // HTML for a tablerow
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

        $coverHtml = $imageFound ? "<img src=\"$coverPath\" alt=\"Cover\" width=\"100\">" : "<span style='color:red;'>Kein Bild gefunden!</span>";

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
