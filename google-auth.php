<?php
require "init.php";
require "connection.php";

header("Content-Type: application/json");

/* ================== INPUT CHECK ================== */
$data = json_decode(file_get_contents("php://input"), true);

if (empty($data['credential'])) {
    echo json_encode(['success'=>false,'message'=>'Missing Google credential']);
    exit;
}

$token    = $data['credential'];
$referral = trim($data['referral'] ?? '');
$client_id = "561030545168-nnk6cejqmdsn9ig6n8cdn8tvu7uutrev.apps.googleusercontent.com";

/* ================== VERIFY TOKEN ================== */

$ch = curl_init();
curl_setopt_array($ch, [
    CURLOPT_URL => "https://oauth2.googleapis.com/tokeninfo?id_token=" . $token,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT => 5,
    CURLOPT_SSL_VERIFYPEER => true
]);

$response = curl_exec($ch);
$httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($httpcode !== 200 || !$response) {
    echo json_encode(['success'=>false,'message'=>'Token verification failed']);
    exit;
}

$userInfo = json_decode($response, true);

/* ================== STRICT SECURITY CHECKS ================== */

if (
    empty($userInfo['email']) ||
    empty($userInfo['sub']) ||
    empty($userInfo['aud']) ||
    empty($userInfo['iss']) ||
    $userInfo['aud'] !== $client_id ||
    !in_array($userInfo['iss'], [
        "accounts.google.com",
        "https://accounts.google.com"
    ]) ||
    !filter_var($userInfo['email_verified'], FILTER_VALIDATE_BOOLEAN)
) {
    echo json_encode(['success'=>false,'message'=>'Invalid Google token']);
    exit;
}

/* Token expiry check */
if (isset($userInfo['exp']) && $userInfo['exp'] < time()) {
    echo json_encode(['success'=>false,'message'=>'Token expired']);
    exit;
}

/* ================== USER DATA ================== */

$google_id = $userInfo['sub'];
$email     = strtolower($userInfo['email']);
$name      = trim($userInfo['name'] ?? 'User');
$image     = $userInfo['picture'] ?? '';

/* ================== FIND USER ================== */

/* First try by google_id */
$stmt = $conn->prepare("SELECT id, user_id FROM users WHERE google_id=? LIMIT 1");
$stmt->bind_param("s", $google_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {

    /* Try by email (manual account linking case) */
    $stmt2 = $conn->prepare("SELECT id, user_id FROM users WHERE email=? LIMIT 1");
    $stmt2->bind_param("s", $email);
    $stmt2->execute();
    $result2 = $stmt2->get_result();

    if ($result2->num_rows === 0) {

        /* ========== NEW USER ========== */

        $user_id = strtoupper(bin2hex(random_bytes(5)));
        $ref_code = strtoupper(substr(preg_replace('/[^A-Za-z]/','',$name),0,3)) . random_int(100,999);

        $insert = $conn->prepare("
            INSERT INTO users
            (user_id, name, email, google_id, login_type, referral_code, referred_by, otp_verified, status, last_activity)
            VALUES (?, ?, ?, ?, 'google', ?, ?, 1, 'online', NOW())
        ");

        $insert->bind_param(
            "ssssss",
            $user_id,
            $name,
            $email,
            $google_id,
            $ref_code,
            $referral
        );

        $insert->execute();
        $uid = $insert->insert_id;

        $message = "Signup successful";

    } else {

        /* ========== LINK EXISTING MANUAL ACCOUNT ========== */

        $user = $result2->fetch_assoc();
        $uid  = $user['id'];
        $user_id = $user['user_id'];

        $update = $conn->prepare("
            UPDATE users SET
            google_id=?,
            login_type='google',
            status='online',
            last_activity=NOW()
            WHERE id=?
        ");

        $update->bind_param("si", $google_id, $uid);
        $update->execute();

        $message = "Login successful";
    }

} else {

    /* ========== GOOGLE USER EXISTS ========== */

    $user = $result->fetch_assoc();
    $uid  = $user['id'];
    $user_id = $user['user_id'];

    $update = $conn->prepare("
        UPDATE users SET
        status='online',
        last_activity=NOW()
        WHERE id=?
    ");

    $update->bind_param("i", $uid);
    $update->execute();

    $message = "Login successful";
}

/* ================== ENSURE user_id FETCHED ================== */

if (!isset($user_id)) {
    $getUser = $conn->prepare("SELECT user_id FROM users WHERE id=?");
    $getUser->bind_param("i", $uid);
    $getUser->execute();
    $resUser = $getUser->get_result();
    $rowUser = $resUser->fetch_assoc();
    $user_id = $rowUser['user_id'];
}

/* ================== SESSION HARDENING ================== */

session_regenerate_id(true);

$_SESSION['id'] = $uid;
$_SESSION['user_id'] = $user_id;   // ⭐ IMPORTANT FOR DASHBOARD
$_SESSION['email'] = $email;
$_SESSION['name'] = $name;
$_SESSION['login_type'] = 'google';
$_SESSION['ip'] = $_SERVER['REMOTE_ADDR'] ?? '';

echo json_encode([
    'success'=>true,
    'message'=>$message,
    'name'=>$name,
    'email'=>$email
]);