<?php
require_once "../config.php";
session_start();

if (!isset($_SESSION['user_id'])) {
    die(json_encode(['error' => 'Unauthorized']));
}

$id = (int)$_GET['id'];
$query = "SELECT t.*, p.kode as kode FROM tasks t LEFT JOIN projects p ON t.project_id = p.id WHERE t.id = $id";
$result = mysqli_query($conn, $query);
$data = mysqli_fetch_assoc($result);

header('Content-Type: application/json');
echo json_encode($data);
?>