<?php
require_once "init.php";
require_once "connection.php";
header("Content-Type: application/json");
/* ================= METHOD CHECK ================= */
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405); // Method Not Allowed
    echo json_encode([
        "success" => false,
        "message" => "Method not allowed"
    ]);
    exit;
}

/* ================= CSRF VALIDATION ================= */
if (
    empty($_POST['csrf_token']) ||
    empty($_SESSION['csrf_token']) ||
    !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])
) {
    http_response_code(403); // Forbidden
    echo json_encode([
        "success" => false,
        "message" => "Session expired. Please refresh."
    ]);
    exit;
}

try {

    /* ================= LOGIN CHECK ================= */
    if (!isset($_SESSION['user_id'])) {
        http_response_code(401); // Unauthorized
        echo json_encode([
            "success" => false,
            "message" => "Please login first."
        ]);
        exit;
    }

    $user_id = $_SESSION['user_id'];
    $review = trim($_POST['review'] ?? '');
    $rating = (int) ($_POST['rating'] ?? 0);

    /* ================= VALIDATION ================= */
    if ($review === '' || $rating === 0) {
        http_response_code(400); // Bad Request
        echo json_encode([
            "success" => false,
            "message" => "All fields are required."
        ]);
        exit;
    }

    if (mb_strlen($review) > 50) {
        http_response_code(400); // Bad Request
        echo json_encode([
            "success" => false,
            "message" => "Review is too long."
        ]);
        exit;
    }

    if ($rating < 1 || $rating > 5) {
        http_response_code(400); // Bad Request
        echo json_encode([
            "success" => false,
            "message" => "Rating must be between 1 and 5."
        ]);
        exit;
    }

    /* ================= CHECK IF ALREADY SUBMITTED ================= */
    $check = $conn->prepare("SELECT user_id FROM reviews WHERE user_id = ?");
    $check->bind_param("s", $user_id);
    $check->execute();
    $check->store_result();

    if ($check->num_rows > 0) {
        http_response_code(409); // Conflict
        echo json_encode([
            "success" => false,
            "message" => "You have already submitted a review."
        ]);
        exit;
    }

    /* ================= INSERT ================= */
    $stmt = $conn->prepare("
        INSERT INTO reviews (user_id, rating, review, created_at)
        VALUES (?, ?, ?, NOW())
    ");

    $stmt->bind_param("sis", $user_id, $rating, $review);
    $stmt->execute();

    http_response_code(201); // Created
    echo json_encode([
        "success" => true,
        "message" => "Review submitted successfully."
    ]);

} catch (Throwable $e) {

    // Log actual error internally (important for production)
    error_log($e->getMessage());

    http_response_code(500); // Internal Server Error
    echo json_encode([
        "success" => false,
        "message" => "Something went wrong. Please try later."
    ]);
}