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

    // Generate a safe filename for the cover image.
    // The extension can be specified (default 'jpg').
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
    
    // Render a table row for this entry.

    public function renderRow(): string {
        $baseName = pathinfo($this->cover ?? $this->getSafeFilename('jpg'), PATHINFO_FILENAME);
        $folder = __DIR__ . '/images/';

        $avifFile = $folder . $baseName . '.avif';
        $jpgFile  = $folder . $baseName . '.jpg';

        // Build picture HTML
        if (file_exists($avifFile) || file_exists($jpgFile)) {
            $coverHtml = '<picture>';
            if (file_exists($avifFile)) {
                $coverHtml .= '<source srcset="images/' . rawurlencode($baseName) . '.avif" type="image/avif">';
            }
            if (file_exists($jpgFile)) {
                $coverHtml .= '<img src="images/' . rawurlencode($baseName) . '.jpg" alt="Cover" width="100">';
            } else {
                // Fallback: use whatever $this->cover points to
                $coverHtml .= '<img src="images/' . rawurlencode($this->cover) . '" alt="Cover" width="100">';
            }
            $coverHtml .= '</picture>';
        } else {
            $coverHtml = '<span style="color:red;">Kein Bild gefunden!</span>';
        }

        $diffClass = '';
        if (is_numeric($this->diff)) {
            if ($this->diff > 0) $diffClass = ' class="diff-up"';
            elseif ($this->diff < 0) $diffClass = ' class="diff-down"';
        }

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