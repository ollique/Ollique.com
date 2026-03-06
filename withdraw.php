<?php
require 'init.php';
header('Content-Type: application/json');
require 'connection.php';

if (!$conn) {
    exit(json_encode(["success"=>false,"message"=>"Database error"]));
}

if (empty($_SESSION['user_id'])) {
    exit(json_encode(["success"=>false,"message"=>"Not logged in"]));
}

/* ================= CSRF VALIDATION ================= */
if (
    empty($_POST['csrf_token']) ||
    empty($_SESSION['csrf_token']) ||
    !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])
) {
    exit(json_encode(["success"=>false,"message"=>"Invalid request"]));
}

$user_id = $_SESSION['user_id'];
$address = trim($_POST['address'] ?? '');
$network = trim($_POST['network'] ?? '');
$amount  = (float)($_POST['amount'] ?? 0);

$min = 5;
$feePercent = 0.05;
$status = "pending";
$type = "withdraw";

/* ================= VALIDATION ================= */
/* ================= BASIC VALIDATION ================= */
if ($address === '' || $network === '' || $amount <= 0) {
    exit(json_encode(["success"=>false,"message"=>"All fields required"]));
}

/* ================= ADDRESS VALIDATION ================= */
if ($network === "TRON (TRC20)") {

    if (!preg_match('/^T[a-zA-Z0-9]{33}$/', $address)) {
        exit(json_encode([
            "success"=>false,
            "message"=>"Invalid TRON address"
        ]));
    }

} elseif ($network === "BSC (BEP20)") {

    if (!preg_match('/^0x[a-fA-F0-9]{40}$/', $address)) {
        exit(json_encode([
            "success"=>false,
            "message"=>"Invalid BSC address"
        ]));
    }

} else {

    exit(json_encode([
        "success"=>false,
        "message"=>"Invalid network selected"
    ]));
}
if ($amount < $min) {
    exit(json_encode(["success"=>false,"message"=>"Minimum withdrawal USDT $min"]));
}

/* ================= SQL TRANSACTION ================= */
$conn->begin_transaction();

try {

    /* Lock user row */
    $stmt = $conn->prepare("SELECT balance FROM users WHERE user_id=? FOR UPDATE");
    $stmt->bind_param("s", $user_id);
    $stmt->execute();
    $stmt->bind_result($balance);

    if (!$stmt->fetch()) {
        throw new Exception("User not found");
    }
    $stmt->close();

    if ($amount > $balance) {
        throw new Exception("Insufficient balance");
    }

    /* Check pending withdrawal */
    $check = $conn->prepare(
        "SELECT COUNT(*) FROM transactions WHERE user_id=? AND status='pending'"
    );
    $check->bind_param("s", $user_id);
    $check->execute();
    $check->bind_result($pendingCount);
    $check->fetch();
    $check->close();

    if ($pendingCount > 0) {
        throw new Exception("Previous withdrawal still pending");
    }

    /* Calculate fee */
    $fee = round($amount * $feePercent, 2);
    $netAmount = round($amount - $fee, 2);

    /* Insert transaction */
    $insert = $conn->prepare(
        "INSERT INTO transactions 
        (user_id, crypto_address, crypto_network, type, amount, status)
        VALUES (?,?,?,?,?,?)"
    );
    $insert->bind_param("ssssds", $user_id, $address, $network, $type, $netAmount, $status);
    $insert->execute();
    $insert->close();

    /* Update balance */
    $new_balance = round($balance - $amount, 2);
    $update = $conn->prepare("UPDATE users SET balance=? WHERE user_id=?");
    $update->bind_param("ds", $new_balance, $user_id);
    $update->execute();
    $update->close();

    $conn->commit();

    echo json_encode([
        "success"=>true,
        "message"=>"Withdrawal submitted successfully",
        "new_balance"=>$new_balance
    ]);

} catch (Exception $e) {

    $conn->rollback();

    echo json_encode([
        "success"=>false,
        "message"=>$e->getMessage()
    ]);
}

$conn->close();