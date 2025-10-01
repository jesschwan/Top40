<?php
// Fehleranzeigen einschalten
ini_set('display_errors', 1);
error_reporting(E_ALL);

// Datenbankverbindung testen
$servername = "localhost";
$username = "root";
$password = ""; // Passwort, falls vorhanden
$dbname = "top40db";

$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    die("Verbindung fehlgeschlagen: " . $conn->connect_error);
} else {
    echo "MySQL Verbindung erfolgreich!";
}
?>
