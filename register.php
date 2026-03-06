<?php
require "init.php";
require "connection.php";
header("Content-Type: application/json");

/* ================= CSRF CHECK ================= */
/* ================= METHOD + CSRF CHECK ================= */
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status'=>'error','message'=>'Method not allowed']);
    exit;
}

if (
    empty($_POST['csrf_token']) ||
    empty($_SESSION['csrf_token']) ||
    !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])
) {
    echo json_encode(['status'=>'error','message'=>'Oops something going wrong..']);
    exit;
}



function getUserIP() {
    return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
}

$ip = getUserIP();
$user_agent = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';

try {

    $email = trim($_POST['email'] ?? '');
    $name = trim($_POST['name'] ?? '');
    $password = $_POST['password'] ?? '';
    $otp = trim($_POST['otp'] ?? '');
    $ref = trim($_POST['referral'] ?? '');

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        echo json_encode(['status'=>'error','message'=>'Invalid email']);
        exit;
    }

    if (!preg_match('/^[a-zA-Z0-9 ]{1,20}$/', $name)) {
        echo json_encode(['status'=>'error','message'=>'Invalid name']);
        exit;
    }

    if (strlen($password) < 6) {
        echo json_encode(['status'=>'error','message'=>'Password min 6 chars']);
        exit;
    }

    if (!preg_match('/^\d{6}$/', $otp)) {
        echo json_encode(['status'=>'error','message'=>'Invalid OTP']);
        exit;
    }

    $stmt = $conn->prepare(
        "SELECT * FROM users WHERE email=? AND otp_verified=0"
    );
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $res = $stmt->get_result();

    if ($res->num_rows == 0) {
        echo json_encode(['status'=>'error','message'=>'Invalid request']);
        exit;
    }

    $user = $res->fetch_assoc();
if ($user['otp_verified'] == 1) {
    echo json_encode(['status'=>'error','message'=>'Already registered']);
    exit;
}
    if (time() - strtotime($user['otp_created_at']) > 300) {
    echo json_encode(['status'=>'error','message'=>'OTP expired']);
    exit;
}

if (!password_verify($otp, $user['otp'])) {
    echo json_encode(['status'=>'error','message'=>'Wrong OTP']);
    exit;
}
    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
    $ref_code = strtoupper(substr($name, 0, 3)) . random_int(100, 999);

    $update = $conn->prepare(
        "UPDATE users SET 
        name=?, password=?, referral_code=?, referred_by=?,
        otp_verified=1, status='online', last_activity=NOW(),
        registration_ip=?, browser_agent=? 
        WHERE id=?"
    );

    $update->bind_param(
        "ssssssi",
        $name,
        $hashed_password,
        $ref_code,
        $ref,
        $ip,
        $user_agent,
        $user['id']
    );

    $update->execute();

    echo json_encode(['status'=>'success','message'=>'Registration completed']);

} catch (Throwable $e) {
    echo json_encode(['status'=>'error','message'=>'Server error']);}