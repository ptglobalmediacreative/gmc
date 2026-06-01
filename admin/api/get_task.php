<?php
require_once "../config.php";
session_start();

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($id > 0) {
    $query = "SELECT t.*, 
              GROUP_CONCAT(ta.user_id SEPARATOR ',') as assigned_staff_ids
              FROM tasks t
              LEFT JOIN task_assignments ta ON t.id = ta.task_id
              WHERE t.id = $id
              GROUP BY t.id";
    $result = mysqli_query($conn, $query);
    
    if ($row = mysqli_fetch_assoc($result)) {
        echo json_encode($row);
    } else {
        echo json_encode(['error' => 'Task not found']);
    }
} else {
    echo json_encode(['error' => 'Invalid ID']);
}
?>