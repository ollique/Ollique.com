<?php
require "init.php";
header("Content-Type: application/json");
require "connection.php";
/* ================= INPUT ================= */
$email = trim($_POST['email'] ?? '');
$password = trim($_POST['password'] ?? '');
$csrf = $_POST['csrf_token'] ?? '';

if (!$email || !$password) {
    echo json_encode(["success"=>false,"message"=>"Invalid email or password."]);
    exit;
}
/* ================= CSRF VALIDATION ================= */
if (
    empty($_SESSION['csrf_token']) ||
    empty($csrf) ||
    !hash_equals($_SESSION['csrf_token'], $csrf)
) {
    echo json_encode(["success"=>false,"message"=>"Invalid request."]);
    exit;
}

/* ================= BASIC IP RATE LIMIT ================= */
$ip = $_SERVER['REMOTE_ADDR'];

if (!isset($_SESSION['ip_limit'][$ip])) {
    $_SESSION['ip_limit'][$ip] = ['count'=>0, 'time'=>time()];
}

$limit = &$_SESSION['ip_limit'][$ip];

if (time() - $limit['time'] > 600) {
    $limit = ['count'=>0, 'time'=>time()];
}

$limit['count']++;

if ($limit['count'] > 15) {
    echo json_encode(["success"=>false,"message"=>"Too many attempts. Try later."]);
    exit;
}

/* ================= USER FETCH ================= */
$stmt = $conn->prepare("
    SELECT id, user_id, referred_by, password, 
    failed_attempts, account_locked_until 
    FROM users 
    WHERE email = ? AND is_deleted = 0
");

$stmt->bind_param("s", $email);
$stmt->execute();
$result = $stmt->get_result();

if ($row = $result->fetch_assoc()) {

    /* ===== CHECK ACCOUNT LOCK ===== */
    if (!empty($row['account_locked_until']) &&
        strtotime($row['account_locked_until']) > time()) {

        echo json_encode([
            "success"=>false,
            "message"=>"Account temporarily locked. Try later."
        ]);
        exit;
    }

    /* ===== VERIFY PASSWORD ===== */
    if (password_verify($password, $row['password'])) {

        /* Reset failed attempts */
        $reset = $conn->prepare("
            UPDATE users 
            SET failed_attempts = 0, 
                account_locked_until = NULL 
            WHERE id = ?
        ");
        $reset->bind_param("i", $row['id']);
        $reset->execute();

        session_regenerate_id(true);

        $_SESSION['user_id'] = $row['user_id'];
        $_SESSION['id'] = $row['id'];
        $_SESSION['useByMe'] = $row['referred_by'];
        $_SESSION['ip'] = $_SERVER['REMOTE_ADDR'];
        $_SESSION['user_agent'] = $_SERVER['HTTP_USER_AGENT'];

        /* Regenerate CSRF */
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));

        $limit = ['count'=>0, 'time'=>time()];

        echo json_encode(["success"=>true,"message"=>"Login successful"]);
        exit;

    } else {

        /* ===== FAILED LOGIN ===== */
        $attempts = $row['failed_attempts'] + 1;
        $lockTime = NULL;

        /* Progressive lock system */
        if ($attempts >= 15) {
            $lockTime = date("Y-m-d H:i:s", strtotime("+24 hours"));
            $attempts = 0;
        } elseif ($attempts >= 10) {
            $lockTime = date("Y-m-d H:i:s", strtotime("+1 hour"));
        } elseif ($attempts >= 5) {
            $lockTime = date("Y-m-d H:i:s", strtotime("+15 minutes"));
        }

        $update = $conn->prepare("
            UPDATE users 
            SET failed_attempts = ?, 
                account_locked_until = ? 
            WHERE id = ?
        ");
        $update->bind_param("isi", $attempts, $lockTime, $row['id']);
        $update->execute();

        /* Anti timing attack delay */
        usleep(random_int(200000, 400000));

        echo json_encode(["success"=>false,"message"=>"Invalid email or password."]);
        exit;
    }

} else {

    /* Anti timing attack delay */
    usleep(random_int(200000, 400000));

    echo json_encode(["success"=>false,"message"=>"Invalid email or password."]);
    exit;
}

$stmt->close();
$conn->close();
?>