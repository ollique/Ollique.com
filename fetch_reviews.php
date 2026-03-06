<?php
require_once "init.php";
require_once "connection.php";
header("Content-Type: application/json");
/* ================= METHOD CHECK ================= */
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405); // Method Not Allowed
    echo json_encode([
        "success" => false,
        "message" => "Method not allowed"
    ]);
    exit;
}
try {

    $stmt = $conn->prepare("
        SELECT 
            u.name,
            r.rating,
            r.review,
            r.created_at
        FROM reviews r
        LEFT JOIN users u 
            ON u.user_id = r.user_id
        ORDER BY r.id DESC
        LIMIT 50
    ");

    if (!$stmt) {
        throw new Exception("Database prepare failed");
    }

    $stmt->execute();
    $result = $stmt->get_result();

    $reviews = [];

    while ($row = $result->fetch_assoc()) {
        $reviews[] = [
            "name" => htmlspecialchars($row["name"] ?? "Anonymous", ENT_QUOTES, 'UTF-8'),
            "rating" => (int)$row["rating"],
            "review" => htmlspecialchars($row["review"], ENT_QUOTES, 'UTF-8'),
            "created_at" => $row["created_at"],
        ];
    }

    $stmt->close();

    /* ================= SUCCESS ================= */

    http_response_code(200); // OK
    echo json_encode([
        "success" => true,
        "reviews" => $reviews
    ]);

} catch (Throwable $e) {
    error_log($e->getMessage());

    http_response_code(500); // Internal Server Error

    echo json_encode([
        "success" => false,
        "message" => "Unable to fetch reviews"
    ]);
}

$conn->close();
exit;