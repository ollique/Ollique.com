<?php
date_default_timezone_set('Asia/Kolkata');
session_start();
include 'connection.php';
if (!isset($_SESSION['user_id'])) {
    echo json_encode(["success"=>false,"message"=>"Login required"]);
    exit;
}

$user_id = $_SESSION['user_id'];
/* ===== CHECK LAST DAILY CLAIM ===== */
$check = $conn->prepare("
    SELECT date FROM transactions 
    WHERE user_id = ? AND type = 'today'
    ORDER BY date DESC LIMIT 1
");
$check->bind_param("s", $user_id);
$check->execute();
$res = $check->get_result();
if ($res->num_rows > 0) {
    $row = $res->fetch_assoc();
    $lastClaim = strtotime($row['date']);
    if (time() - $lastClaim < 86400) {
        echo json_encode([
            "success" => false,
            "message" => "Already claimed",
            "remaining" => 86400 - (time() - $lastClaim)
        ]);
        exit;
    }
}
/* ===== RANDOM DAILY REWARD ===== */
$chance = random_int(1, 100);
if ($chance <= 70) {
    $reward = 0.1;
} elseif ($chance <= 90) {
    $reward = 0.5;
} else {
    $reward = 0.7;
}

/* ===== TRANSACTION START ===== */
$conn->begin_transaction();

try {
    // Wallet update
    $wallet = $conn->prepare("
        UPDATE users SET balance = balance + ? WHERE user_id = ?
    ");
    $wallet->bind_param("ds", $reward, $user_id);
    $wallet->execute();

    // Insert reward transaction
    $notify = $conn->prepare("
        INSERT INTO transactions (user_id, type, amount)
        VALUES (?, 'today', ?)
    ");
    $notify->bind_param("sd", $user_id, $reward);
    $notify->execute();

    $conn->commit();

    echo json_encode([
        "success" => true,
        "message" => "Daily reward claimed",
        "reward" => $reward
    ]);

} catch (Exception $e) {
    $conn->rollback();
    echo json_encode([
        "success" => false,
        "message" => "Something went wrong"
    ]);
}