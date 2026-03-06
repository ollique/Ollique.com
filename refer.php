<?php
session_start();
header('Content-Type: application/json');
require 'connection.php';

if (!isset($_SESSION['user_id'])) {
    echo json_encode(["success" => false, "message" => "Not logged in"]);
    exit;
}

$user_id  = $_SESSION['user_id'];   // referrer
$useByMe  = $_SESSION['useByMe'];   // referral code
echo $useByMe;
$data = [];

/* ----------------------------------
   1️⃣ Get users referred by me
----------------------------------*/
$stmt = $conn->prepare("
    SELECT user_id, name
    FROM users
    WHERE referral_code = ?
");
$stmt->bind_param("s", $useByMe);
$stmt->execute();
$result = $stmt->get_result();

while ($user = $result->fetch_assoc()) {

    /* ----------------------------------
       2️⃣ FIRST completed deposit
    ----------------------------------*/
    $dep = $conn->prepare("
        SELECT amount, date
        FROM transactions
        WHERE user_id = ?
          AND type = 'deposit'
          AND status = 'complete'
        ORDER BY id ASC
        LIMIT 1
    ");
    $dep->bind_param("s", $user['user_id']);
    $dep->execute();
    $depRes = $dep->get_result();

    $firstDeposit = 0;
    $depositTime  = null;

    if ($d = $depRes->fetch_assoc()) {
        $firstDeposit = (float)$d['amount'];
        $depositTime  = $d['date'];
    }

    /* ----------------------------------
       3️⃣ Referral reward (ONLY ONCE)
    ----------------------------------*/
    if ($firstDeposit > 0) {

if ($firstDeposit >= 1000) {
    $percent = 10;
} else {
    $percent = 5;
}
$reward = round($firstDeposit * ($percent / 100), 2);        $reward = round($firstDeposit * 0.10, 2);
        $msg    = $user['name'];

        // 🔒 CHECK: already rewarded or not
        $check = $conn->prepare("
            SELECT id
            FROM transactions
            WHERE user_id = ?
              AND type = 'refer'
              AND message = ?
            LIMIT 1
        ");
        $check->bind_param("ss", $user_id, $msg);
        $check->execute();
        $checkRes = $check->get_result();

        // ➕ INSERT only if NOT exists
        if ($checkRes->num_rows === 0) {

            $ins = $conn->prepare("
                INSERT INTO transactions (user_id, type, amount, message)
                VALUES (?, 'refer', ?, ?)
            ");
            $ins->bind_param("sds", $user_id, $reward, $msg);
            $ins->execute();

            // 💰 Balance update sirf ek baar
            if ($ins->affected_rows > 0) {
                $upd = $conn->prepare("
                    UPDATE users
                    SET balance = balance + ?
                    WHERE user_id = ?
                ");
                $upd->bind_param("ds", $reward, $user_id);
                $upd->execute();
            }
        }
    }

    /* ----------------------------------
       4️⃣ Response data
    ----------------------------------*/
    $data[] = [
        "user_id"       => $user['user_id'],
        "name"          => $user['name'],
        "first_deposit" => $firstDeposit,
        "deposit_time"  => $depositTime
    ];
}

echo json_encode([
    "status" => "success",
    "data"   => $data
]);