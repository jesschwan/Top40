
START TRANSACTION;

DROP TABLE IF EXISTS placings;
DROP TABLE IF EXISTS songs;

CREATE TABLE songs (
  song_id INT AUTO_INCREMENT PRIMARY KEY,
  song_name TEXT NOT NULL,
  artist TEXT NOT NULL,
  cover_image TEXT DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE placings (
  place_id INT AUTO_INCREMENT PRIMARY KEY,
  placing INT NOT NULL,
  song_id INT NOT NULL,
  weekYear DATE NOT NULL,
  FOREIGN KEY (song_id) REFERENCES songs(song_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO songs (song_name, artist, cover_image)
SELECT DISTINCT Titel, Interpret, cover
FROM top40;

INSERT INTO placings (placing, song_id, weekYear)
SELECT 
    t.Platz,
    s.song_id,
    STR_TO_DATE(CONCAT(t.jahr, ' ', t.kw, ' Monday'), '%X %V %W')
FROM top40 t
JOIN songs s
  ON t.Titel = s.song_name
 AND t.Interpret = s.artist;

COMMIT;

