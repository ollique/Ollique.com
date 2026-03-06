<?php
session_start();
include 'connection.php';
header('Content-Type: application/json');

// Validate user
$sender = $_SESSION['user_id'] ?? '';
$input = json_decode(file_get_contents('php://input'), true);
$receiver = trim($input['receiver'] ?? '');
$is_typing = isset($input['typing']) ? intval($input['typing']) : 0;


if (!$sender || !$receiver) {
    echo json_encode(['status' => 'error', 'message' => 'Missing parameters']);
    exit;
}

// Check last message record
$check = $conn->prepare("SELECT id FROM messages WHERE sender = ? AND receiver = ? ORDER BY id DESC LIMIT 1");
$check->bind_param("ss", $sender, $receiver);
$check->execute();
$result = $check->get_result();

if ($result->num_rows > 0) {
    $row = $result->fetch_assoc();
    $update = $conn->prepare("UPDATE messages SET typing_status = ? WHERE id = ?");
    $update->bind_param("ii", $is_typing, $row['id']);
    $update->execute();
} else {
    $insert = $conn->prepare("INSERT INTO messages (sender, receiver, typing_to, typing_status) VALUES (?, ?, ?, ?)");
    $insert->bind_param("sssi", $sender, $receiver, $receiver, $is_typing);
    $insert->execute();
}

echo json_encode(['status' => 'success', 'is_typing' => $is_typing]);
?>