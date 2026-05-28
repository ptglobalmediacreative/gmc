<?php
$host = "localhost";
$user = "u475225363_gmcpanel";
$pass = "Gmcpanel22!";
$db = "u475225363_gmcpanel";

$conn = mysqli_connect($host, $user, $pass, $db);

if (!$conn) {
    die("Koneksi gagal: " . mysqli_connect_error());
}
?>