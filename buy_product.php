<?php
include 'init.php';
include 'connection.php';
/* ================= REQUIRE LOGIN ================= */
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode([
        "success" => false,
        "message" => "Unauthorized"
    ]);
    exit;
}

$user_id = $_SESSION['user_id'];

/* ================= CSRF VALIDATION ================= */
$csrfHeader = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';

if (
    empty($_SESSION['csrf_token']) ||
    empty($csrfHeader) ||
    !hash_equals($_SESSION['csrf_token'], $csrfHeader)
) {
    http_response_code(403);
    echo json_encode([
        "success" => false,
        "message" => "Invalid CSRF token"
    ]);
    exit;
}

/* ================= DB ================= */

/* ================= READ INPUT ================= */
$input = json_decode(file_get_contents("php://input"), true);

if (!isset($input['product']['id'])) {
    http_response_code(400);
    echo json_encode([
        "success" => false,
        "message" => "Product ID missing"
    ]);
    exit;
}

$product_id = (int)$input['product']['id'];

if ($product_id <= 0) {
    http_response_code(400);
    echo json_encode([
        "success" => false,
        "message" => "Invalid product ID"
    ]);
    exit;
}

/* ================= FETCH PRODUCT FROM DB ================= */
$stmt = $conn->prepare("SELECT investment, cycle FROM products WHERE id = ?");
$stmt->bind_param("i", $product_id);
$stmt->execute();
$result = $stmt->get_result();
$product = $result->fetch_assoc();
$stmt->close();

if (!$product) {
    http_response_code(404);
    echo json_encode([
        "success" => false,
        "message" => "Product not found"
    ]);
    exit;
}

$price = (float)$product['investment'];
$cycle = (int)$product['cycle'];

/* ================= FETCH USER WALLET ================= */
$stmt = $conn->prepare("SELECT wallet FROM users WHERE user_id = ?");
$stmt->bind_param("s", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();
$stmt->close();

if (!$user) {
    http_response_code(404);
    echo json_encode([
        "success" => false,
        "message" => "User not found"
    ]);
    exit;
}

if ((float)$user['wallet'] < $price) {
    http_response_code(409);
    echo json_encode([
        "success" => false,
        "message" => "Insufficient wallet balance"
    ]);
    exit;
}

/* ================= TRANSACTION ================= */
$conn->begin_transaction();

try {

    /* Safe wallet deduction (race safe) */
    $stmt1 = $conn->prepare(
        "UPDATE users 
         SET wallet = wallet - ? 
         WHERE user_id = ? AND wallet >= ?"
    );
    $stmt1->bind_param("dss", $price, $user_id, $price);
    $stmt1->execute();

    if ($stmt1->affected_rows === 0) {
        throw new Exception("Balance update failed");
    }

    $stmt1->close();

    $start_date = date('Y-m-d');
    $end_date = date('Y-m-d', strtotime("+{$cycle} days"));

    /* Insert into user_products */
    $stmt2 = $conn->prepare(
        "INSERT INTO user_products 
        (user_id, product_id, start_date, end_date, remaining_days) 
        VALUES (?, ?, ?, ?, ?)"
    );
    $stmt2->bind_param("ssssi", $user_id, $product_id, $start_date, $end_date, $cycle);
    $stmt2->execute();
    $stmt2->close();

    /* Insert transaction record */
    $stmt3 = $conn->prepare(
        "INSERT INTO transactions 
        (user_id, type, product_id, amount) 
        VALUES (?, 'buy', ?, ?)"
    );
    $stmt3->bind_param("ssd", $user_id, $product_id, $price);
    $stmt3->execute();
    $stmt3->close();

    $conn->commit();

    http_response_code(200);
    echo json_encode([
        "success" => true,
        "message" => "Purchase successful"
    ]);

} catch (Exception $e) {

    $conn->rollback();

    http_response_code(500);
    echo json_encode([
        "success" => false,
        "message" => "Purchase failed"
    ]);
}

$conn->close();