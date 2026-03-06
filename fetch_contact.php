<?php
session_start();
require 'connection.php';
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode([]);
    exit;
}

$sender = $_SESSION['user_id'];

$sql = "SELECT user_id, name, email, image_url, status
        FROM users
        WHERE user_id != ? AND is_deleted=0";
$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $sender);
$stmt->execute();
$res = $stmt->get_result();

$contacts = [];

while ($row = $res->fetch_assoc()) {
    // Last message
    $stmt2 = $conn->prepare("
        SELECT message, created_at
        FROM messages
        WHERE (sender = ? AND receiver = ?)
           OR (sender = ? AND receiver = ?)
        ORDER BY created_at DESC
        LIMIT 1
    ");
    $stmt2->bind_param("ssss", $sender, $row['user_id'], $row['user_id'], $sender);
    $stmt2->execute();
    $res2 = $stmt2->get_result();
    $msg = $res2->fetch_assoc();

    // Unseen messages from this contact
    $stmt3 = $conn->prepare("
        SELECT COUNT(*) AS unseen_count
        FROM messages
        WHERE sender = ? AND receiver = ? AND status = 'sent'
    ");
    $stmt3->bind_param("ss", $row['user_id'], $sender);
    $stmt3->execute();
    $res3 = $stmt3->get_result();
    $unseen = $res3->fetch_assoc();

    $contacts[] = [
        'user_id' => $row['user_id'],
        'name' => $row['name'] ?: $row['email'],
        'image_url' => $row['image_url'] ?: '',
        'status' => $row['status'] ?: '',
        'last_message' => $msg['message'] ?? '',
        'last_message_time' => $msg['created_at'] ?? '',
        'unseen_count' => (int)($unseen['unseen_count'] ?? 0)
    ];
}

echo json_encode($contacts);