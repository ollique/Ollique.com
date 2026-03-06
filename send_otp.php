<?php
require "init.php";
require "connection.php";

header("Content-Type: application/json");
header("X-Frame-Options: DENY");
header("X-Content-Type-Options: nosniff");
define("ELASTIC_API_KEY", "840C17D1D753FB929F33066E0B02DADFD8DC98AEB6F21478140B8D4763BF304F82196A2C552F432D9D7A6A61F4B23CA9"); 
define("FROM_EMAIL", "notgov69@gmail.com");
define("FROM_NAME", "ICSC");

/* ================= CSRF CHECK ================= */
if ($_SERVER['REQUEST_METHOD'] !== 'POST' ||
    empty($_POST['csrf_token']) ||
    empty($_SESSION['csrf_token']) ||
    !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])
) {
    echo json_encode(['status'=>'error','message'=>'Invalid security token']);
    exit;
}

/* ================= GET IP & DEVICE ================= */
function getUserIP() {
    if (!empty($_SERVER['HTTP_CF_CONNECTING_IP'])) {
        return $_SERVER['HTTP_CF_CONNECTING_IP'];
    } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        return explode(',', $_SERVER['HTTP_X_FORWARDED_FOR'])[0];
    } else {
        return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    }
}

$ip = getUserIP();
$user_agent = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';

$email = trim($_POST['email'] ?? '');

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['status'=>'error','message'=>'Invalid email']);
    exit;
}

/* ================= DEVICE LIMIT ================= */
$limit = $conn->prepare(
    "SELECT COUNT(*) as total FROM users 
     WHERE registration_ip=? AND browser_agent=? AND otp_verified=1"
);
$limit->bind_param("ss", $ip, $user_agent);
$limit->execute();
$deviceCount = $limit->get_result()->fetch_assoc();

if ($deviceCount['total'] >= 2) {
    echo json_encode([
        'status'=>'error',
        'message'=>'Maximum 2 accounts allowed from this device'
    ]);
    exit;
}

/* ================= CHECK EXISTING USER ================= */
$stmt = $conn->prepare("SELECT id, otp_created_at, otp_verified FROM users WHERE email=?");
$stmt->bind_param("s", $email);
$stmt->execute();
$res = $stmt->get_result();

$otp_plain = random_int(100000, 999999);
$otp_hash = password_hash($otp_plain, PASSWORD_DEFAULT);
$otp_time = date("Y-m-d H:i:s");

if ($res->num_rows > 0) {

    $user = $res->fetch_assoc();

    if ($user['otp_verified'] == 1) {
        echo json_encode(['status'=>'exists','message'=>'User already exists']);
        exit;
    }

    if (!empty($user['otp_created_at']) &&
        time() - strtotime($user['otp_created_at']) < 120) {
        echo json_encode(['status'=>'error','message'=>'Wait 2 minutes']);
        exit;
    }

    $update = $conn->prepare(
        "UPDATE users 
         SET otp=?, otp_created_at=?, otp_verified=0,
         registration_ip=?, browser_agent=?
         WHERE email=?"
    );
    $update->bind_param("sssss", $otp_hash, $otp_time, $ip, $user_agent, $email);
    $update->execute();

} else {

    $user_id = strtoupper(bin2hex(random_bytes(5)));

    $insert = $conn->prepare(
        "INSERT INTO users 
        (user_id,email,otp,otp_created_at,otp_verified,
         login_type,registration_ip,browser_agent)
         VALUES (?,?,?, ?,0,'manual',?,?)"
    );
    $insert->bind_param("ssssss",
        $user_id,$email,$otp_hash,$otp_time,$ip,$user_agent
    );
    $insert->execute();
}

/* ================= SEND OTP VIA ELASTIC ================= */

$url = "https://api.elasticemail.com/v2/email/send";

$postData = [
    'apikey' => ELASTIC_API_KEY,
    'from' => FROM_EMAIL,
    'fromName' => FROM_NAME,
    'subject' => "Your OTP Code",
    'to' => $email,
    'bodyHtml' => "
        <h2>ICSC Verification</h2>
        <p>Your OTP code is:</p>
        <h1>$otp_plain</h1>
        <p>This OTP expires in 5 minutes.</p>
    ",
    'isTransactional' => true
];

$ch = curl_init($url);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
$response = curl_exec($ch);

if (curl_errno($ch)) {
    echo json_encode(['status'=>'error','message'=>'Email sending failed']);
    exit;
}
curl_close($ch);

echo json_encode([
    'status'=>'success',
    'message'=>'OTP sent to your email'
]);