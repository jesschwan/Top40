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

            // calcukation for week difference column outfit --> setting css class
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
?>

<!-- HTML Code starts here -------------> 
<!DOCTYPE html>
<html lang="de">
    <head>
        <meta charset="UTF-8">
        <style>
            .button-cover {
                font-size: 25px;
                background-color: #d4d4d4;
                border: 2px solid black;
                font-weight: bold;
                cursor: pointer;
            }
        </style>
    </head>
    <body>
        <script>
            // Function triggered when a cover button is clicked
            function coverHolen(event) {

                const button = event.target; 
                button.textContent = 'Loading...'; // Feedback

                // Find the table row (<tr>) that contains this button
                const row = button.closest('tr');

                /* Select the 4th cell (<td>) in this row (the cover column)
                const cell = row.querySelector('td:nth-child(4)');
                $interpret = "Alvaro Soler";
                $titel = "Con Calma";
               
                // ImageFromAPI gets Cover as string stream
                $myFirstPicture = new ImageFromAPI( $interpret, $titel);
                // writes String to Database
                $myFirstPicture.writeImageToDB();*/
                
                // Create a new image element as a placeholder (embedded SVG!)
                const img = document.createElement('img');
                img.src = 'data:image/svg+xml;utf8,' + encodeURIComponent(`
                    <svg xmlns="http://www.w3.org/2000/svg" width="100" height="100">
                        <rect width="100" height="100" fill="#d4d4d4" stroke="black" stroke-width="2"/>
                        <text x="50%" y="50%" text-anchor="middle" dominant-baseline="middle" font-size="12" fill="black">
                            No Cover
                        </text>
                    </svg>
                `);
                img.alt = 'No cover available';
                img.width = 100;

                // Replace the button with the image
                cell.innerHTML = ''; 
                cell.appendChild(img);
            }

            // Wait until the DOM is fully loaded, then attach click event listeners
            document.addEventListener('DOMContentLoaded', function () {
                const buttons = document.querySelectorAll('.button-cover');
                buttons.forEach(button => {
                    button.addEventListener('click', coverHolen);
                });
            });
        </script>
    </body>
</html>
