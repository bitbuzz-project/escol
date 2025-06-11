<?php
session_start();
header('Content-Type: application/json');

// Check if user is admin
if (!isset($_SESSION['student']) || $_SESSION['student']['apoL_a01_code'] !== '16005333') {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

require_once 'db.php';

try {
    // Get pending reclamations count
    $query = "SELECT COUNT(*) as count FROM reclamations WHERE status = 'pending'";
    $result = $conn->query($query);

    if ($result) {
        $count = $result->fetch_assoc()['count'];
        echo json_encode(['count' => (int)$count]);
    } else {
        echo json_encode(['count' => 0]);
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error']);
}

$conn->close();
?>
