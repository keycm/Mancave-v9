<?php
// config.php
$host = "localhost";
$user = "u763865560_ManCaveGallery";
$pass = "TheManCaveGallery2025";
$db = "u763865560_kanto_db"; // <-- This has been updated from "af_system"

$conn = mysqli_connect($host, $user, $pass, $db);

if (!$conn) {
die("Connection failed: " . mysqli_connect_error());
}

return $conn;
?>