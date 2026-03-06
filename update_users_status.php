<?php
session_start();
header('Content-Type: application/json');
include 'connection.php';
if (!isset($_SESSION['user_id'])) {
    echo json_encode(["success" => false, "message" => "Not logged in"]);
    exit;
}
$uid = $_SESSION['user_id'];
$status = $_POST['status'] ?? 'offline';
// Update last_activity and status
$stmt = $conn->prepare("UPDATE users SET status = ?, last_activity = NOW() WHERE user_id = ?");
$stmt->bind_param("ss", $status, $uid);
$stmt->execute();
echo json_encode(["success" => true, "status" => $status]);
$stmt->close();
$conn->close();
?>