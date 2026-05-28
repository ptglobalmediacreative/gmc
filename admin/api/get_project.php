<?php
// api/get_project.php
require_once "../config.php";  // <-- karena file di dalam folder api, harus naik 1 level
session_start();

if (!isset($_SESSION['user_id'])) {
    die(json_encode(['error' => 'Unauthorized']));
}

$id = (int)$_GET['id'];
$query = "SELECT id, kode, client_name, start_date, end_date, sales, status FROM projects WHERE id = $id";
$result = mysqli_query($conn, $query);
$data = mysqli_fetch_assoc($result);

header('Content-Type: application/json');
echo json_encode($data);
?>