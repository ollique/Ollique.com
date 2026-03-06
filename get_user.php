<?php
session_start();
header('Content-Type: application/json');
require "connection.php";
if (!isset($_SESSION['user_id'])) {
    echo json_encode(["loggedIn" => false]);
    exit;
}
$uid = $_SESSION['user_id'];
$stmt = $conn->prepare("SELECT name, balance, wallet, referral_code, assigned_admin, status FROM users WHERE user_id = ?");
$stmt->bind_param("i", $uid);
$stmt->execute();
$result = $stmt->get_result();

if ($row = $result->fetch_assoc()) {
    echo json_encode([
    "loggedIn" => true,
    "uid" => $uid,
    "name" => $row['name'],
    "balance" => $row['balance'],
    "wallet" => $row['wallet'],
    "referral" => $row['referral_code'],
    "status" => $row['status'],
    "receiver" => $row['assigned_admin']
]);
    
} else {
    echo json_encode(["loggedIn" => false]);
}

$stmt->close();
$conn->close();
?>