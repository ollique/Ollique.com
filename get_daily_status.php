<?php
session_start();
include 'connection.php';
header('Content-Type: application/json');

// Enable MySQLi error reporting for debugging (safe)
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

try {
    // 🔐 Ensure user is logged in
    if (!isset($_SESSION['user_id'])) {
        echo json_encode(["success" => false, "message" => "Not logged in"]);
        exit;
    }

    $user_id = (int)$_SESSION['user_id'];

    // 🧾 Fetch all active investments with claim status
    $stmt = $conn->prepare("
        SELECT 
            up.id AS user_product_id,
            p.name,
            p.daily_income,
            up.remaining_days,up.start_date,up.end_date,
            (
                SELECT COUNT(*) 
                FROM daily_claims dc 
                WHERE dc.purchase_id = up.id 
                  AND dc.user_id = ? 
                  AND dc.claim_date = CURDATE()
            ) AS claimed_today
        FROM user_products up
        JOIN products p ON up.product_id = p.id
        WHERE up.user_id = ?
        ORDER BY up.id DESC
    ");
    $stmt->bind_param("ii", $user_id, $user_id);
    $stmt->execute();
    $result = $stmt->get_result();

    $products = [];
    while ($row = $result->fetch_assoc()) {
        $products[] = [
            "id" => (int)$row['user_product_id'],
            "name" => $row['name'],
            "daily_income" => (float)$row['daily_income'],
            "remaining_days" => (int)$row['remaining_days'],
            "claimed_today" => (int)$row['claimed_today'], 
            "start_date" => $row['start_date'],
           "end_date" => $row['end_date']

        ];
    }

    $stmt->close();

    echo json_encode(["success" => true, "products" => $products]);

} catch (Exception $e) {
    error_log("get_daily_status Error: " . $e->getMessage());
    echo json_encode(["success" => false, "message" => "Database error"]);
}
?>

