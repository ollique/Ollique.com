<?php
session_start();
include 'connection.php';
header('Content-Type: application/json');

$sender = $_SESSION['user_id'] ?? '';
$input = json_decode(file_get_contents('php://input'), true);
$receiver = trim($input['receiver'] ?? '');

if (!$sender || !$receiver) {
    echo json_encode(['typing' => false]);
    exit;
}

// Get last typing status from messages table
$stmt = $conn->prepare("SELECT typing_status FROM messages WHERE sender = ? AND typing_to = ? ORDER BY id DESC LIMIT 1");
$stmt->bind_param("ss", $receiver, $sender); // check if receiver is typing to me
$stmt->execute();
$result = $stmt->get_result();

if ($row = $result->fetch_assoc()) {
    echo json_encode(['typing' => $row['typing_status'] == 1]);
} else {
    echo json_encode(['typing' => false]);
}
?>