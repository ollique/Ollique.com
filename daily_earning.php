<?php
declare(strict_types=1);

require_once 'init.php';
require_once 'connection.php';

header('Content-Type: application/json');

// ============================
// 🔐 Secure Response Helper
// ============================
function jsonResponse(array $data, int $code = 200): void {
    http_response_code($code);
    echo json_encode($data);
    exit;
}

// ============================
// 🔐 Only POST allowed
// ============================
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(["success" => false, "message" => "Invalid request method"], 405);
}

// ============================
// 🔐 Check Login
// ============================
if (empty($_SESSION['user_id'])) {
    jsonResponse(["success" => false, "message" => "Not logged in"], 401);
}

$user_id = (string) $_SESSION['user_id'];

// ============================
// 🔐 CSRF Validation
// ============================
if (
    empty($_SERVER['HTTP_X_CSRF_TOKEN']) ||
    empty($_SESSION['csrf_token']) ||
    !hash_equals($_SESSION['csrf_token'], $_SERVER['HTTP_X_CSRF_TOKEN'])
) {
    jsonResponse(["success" => false, "message" => "Invalid CSRF token"], 403);
}

// ============================
// 🔐 Validate Input
// ============================
$user_product_id = filter_input(INPUT_POST, 'user_product_id', FILTER_VALIDATE_INT);

if (!$user_product_id || $user_product_id <= 0) {
    jsonResponse(["success" => false, "message" => "Invalid product ID"], 400);
}

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

try {

    // ============================
    // 🟡 Auto decrease skipped days
    // ============================
    $autoUpdate = $conn->prepare("
        UPDATE user_products 
        SET remaining_days = GREATEST(
            remaining_days - DATEDIFF(CURDATE(), COALESCE(last_update_date, start_date)),
            0
        ),
        last_update_date = CURDATE()
        WHERE user_id = ?
          AND (last_update_date IS NULL OR last_update_date < CURDATE())
    ");
    $autoUpdate->bind_param("s", $user_id);
    $autoUpdate->execute();
    $autoUpdate->close();

    // ============================
    // 🔍 Check already claimed
    // ============================
    $check = $conn->prepare("
        SELECT COUNT(*) 
        FROM daily_claims 
        WHERE user_id = ? AND purchase_id = ? AND claim_date = CURDATE()
    ");
    $check->bind_param("si", $user_id, $user_product_id);
    $check->execute();
    $check->bind_result($alreadyClaimed);
    $check->fetch();
    $check->close();

    if ($alreadyClaimed > 0) {
        jsonResponse([
            "success" => false,
            "message" => "Already claimed today"
        ], 409);
    }

    // ============================
    // 🟢 Get product details
    // ============================
    $product = $conn->prepare("
        SELECT p.daily_income, up.remaining_days, up.end_date 
        FROM user_products up
        JOIN products p ON up.product_id = p.id
        WHERE up.id = ? AND up.user_id = ?
        LIMIT 1
    ");
    $product->bind_param("is", $user_product_id, $user_id);
    $product->execute();
    $result = $product->get_result();
    $info = $result->fetch_assoc();
    $product->close();

    if (!$info) {
        jsonResponse(["success" => false, "message" => "Invalid product"], 404);
    }

    if ((int)$info['remaining_days'] <= 0) {
        jsonResponse([
            "success" => false,
            "message" => "Cycle ended. No remaining days left.",
            "expired" => true,
            "end_date" => $info['end_date']
        ], 410);
    }

    $income = (float) $info['daily_income'];

    if ($income <= 0) {
        jsonResponse(["success" => false, "message" => "Invalid income configuration"], 500);
    }

    // ============================
    // 🔒 Begin Transaction
    // ============================
    $conn->begin_transaction();

    // Insert claim
    $insert = $conn->prepare("
        INSERT INTO daily_claims (user_id, purchase_id, claim_date, amount)
        VALUES (?, ?, CURDATE(), ?)
    ");
    $insert->bind_param("sid", $user_id, $user_product_id, $income);
    $insert->execute();
    $insert->close();

    // Decrease remaining days
    $updateDays = $conn->prepare("
        UPDATE user_products 
        SET remaining_days = GREATEST(remaining_days - 1, 0),
            last_update_date = CURDATE()
        WHERE id = ? AND user_id = ?
    ");
    $updateDays->bind_param("is", $user_product_id, $user_id);
    $updateDays->execute();
    $updateDays->close();

    // Update balance
    $updateBalance = $conn->prepare("
        UPDATE users 
        SET balance = balance + ? 
        WHERE user_id = ?
    ");
    $updateBalance->bind_param("ds", $income, $user_id);
    $updateBalance->execute();
    $updateBalance->close();

    // Commit
    $conn->commit();

    jsonResponse([
        "success" => true,
        "message" => "Daily claim successful",
        "earned" => $income,
        "remaining_days" => max((int)$info['remaining_days'] - 1, 0),
        "end_date" => $info['end_date']
    ], 200);

} catch (Throwable $e) {

    if ($conn->errno) {
        $conn->rollback();
    }

    error_log("Daily claim error: " . $e->getMessage());

    jsonResponse([
        "success" => false,
        "message" => "Something went wrong. Please try again."
    ], 500);
}