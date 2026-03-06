<?php
include 'init.php';
/* ================= DB CONNECTION ================= */
require_once 'connection.php';

/* ================= LOGIN CHECK ================= */
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode([
        "success" => false,
        "message" => "Unauthorized"
    ]);
    exit;
}

/* ================= FETCH PRODUCTS ================= */
try {

    $result = $conn->query("SELECT * FROM products ORDER BY id ASC");

    if (!$result) {
        throw new Exception("Query failed");
    }

    $products = [];

    while ($row = $result->fetch_assoc()) {

        $products[] = [
            "id" => (int)$row["id"],
            "name" => $row["name"],
            "symbol" => $row["symbol"],
            "atomic" => $row["atomic"],
            "type" => $row["type"],
            "cubeColor" => $row["cube_color"],
            "investment" => (float)$row["investment"],
            "cycle" => (int)$row["cycle"],
            "dailyIncome" => (float)$row["daily_income"],
            "label" => $row["label"]
        ];
    }

    http_response_code(200);
    echo json_encode([
        "success" => true,
        "products" => $products
    ]);

} catch (Exception $e) {

    http_response_code(500);
    echo json_encode([
        "success" => false,
        "message" => "Failed to fetch products"
    ]);
}

$conn->close();