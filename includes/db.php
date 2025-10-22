<?php
$servername = "localhost";   // meestal localhost
$username = "root";          // jouw database username
$password = "";              // jouw database password
$dbname = "skillradar"; // jouw database naam

// Maak connectie
$conn = mysqli_connect($servername, $username, $password, $dbname);

// Check connectie
if (!$conn) {
    die("Connectie mislukt: " . mysqli_connect_error());
}
?>
