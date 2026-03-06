<?php
declare(strict_types=1);
require_once 'init.php';
require_once 'connection.php';

header('Content-Type: application/json');

function jsonResponse(array $data, int $code = 200): void {
    http_response_code($code);
    echo json_encode($data);
    exit;
}

// ✅ Only POST allowed
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(["success" => false, "message" => "Invalid request method"], 405);
}

// ✅ Login check
if (empty($_SESSION['user_id'])) {
    jsonResponse(["success" => false, "message" => "User not logged in"], 401);
}

$user_id = (string) $_SESSION['user_id'];

// ✅ CSRF check
if (
    empty($_SERVER['HTTP_X_CSRF_TOKEN']) ||
    empty($_SESSION['csrf_token']) ||
    !hash_equals($_SESSION['csrf_token'], $_SERVER['HTTP_X_CSRF_TOKEN'])
) {
    jsonResponse(["success" => false, "message" => "Invalid CSRF token"], 403);
}

// ✅ Read JSON safely
$rawInput = file_get_contents("php://input");
$data = json_decode($rawInput, true);

if (!is_array($data)) {
    jsonResponse(["success" => false, "message" => "Invalid input format"], 400);
}

$old_password = trim($data['old_password'] ?? '');
$new_password = trim($data['new_password'] ?? '');

if ($old_password === '' || $new_password === '') {
    jsonResponse(["success" => false, "message" => "All fields required"], 400);
}

// ✅ Password strength validation
if (strlen($new_password) < 8) {
    jsonResponse([
        "success" => false,
        "message" => "Password must be at least 8 characters"
    ], 400);
}

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

try {

    // 🔍 Fetch user
    $stmt = $conn->prepare("SELECT password FROM users WHERE user_id = ? LIMIT 1");
    $stmt->bind_param("s", $user_id);
    $stmt->execute();
    $stmt->bind_result($current_hash);
    $stmt->fetch();
    $stmt->close();

    if (!$current_hash) {
        jsonResponse(["success" => false, "message" => "User not found"], 404);
    }

    // 🔐 Verify old password
    if (!password_verify($old_password, $current_hash)) {
        jsonResponse(["success" => false, "message" => "Old password incorrect"], 401);
    }

    // 🔒 Hash new password
    $new_hash = password_hash($new_password, PASSWORD_DEFAULT);

    // 🔄 Update password
    $update = $conn->prepare("UPDATE users SET password = ? WHERE user_id = ?");
    $update->bind_param("ss", $new_hash, $user_id);
    $update->execute();
    $update->close();

    // 🔐 Regenerate session after password change
    session_regenerate_id(true);

    jsonResponse([
        "success" => true,
        "message" => "Password changed successfully"
    ], 200);

} catch (Throwable $e) {

    error_log("Password change error: " . $e->getMessage());

    jsonResponse([
        "success" => false,
        "message" => "Something went wrong. Try again later."
    ], 500);
}