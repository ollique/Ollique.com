<?php
require 'init.php';
header('Content-Type: application/json');
require 'connection.php';

if (empty($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode([
        "success" => false,
        "message" => "Unauthorized"
    ]);
    exit;
}

$user_id = (int)$_SESSION['user_id'];

$sql = "
SELECT 
    t.id,
    t.user_id,
    t.type,
    t.amount,
    t.crypto_address,
    t.crypto_network,
    t.fuel_required,
    t.fuel_received,
    t.message,
    p.name AS product_name,
    t.status,  
    t.date
FROM transactions t
LEFT JOIN products p ON p.id = t.product_id
WHERE t.user_id = ?

UNION ALL

SELECT 
    r.id,
    r.user_id,
    'reward' AS type,
    r.amount,
    NULL,
    NULL,
    NULL,
    NULL,
    r.message,
    p.name,
    NULL,
    r.created_at
FROM rewards_log r
LEFT JOIN products p ON p.id = r.product_id
WHERE r.user_id = ?

ORDER BY date DESC
LIMIT 200
";

$stmt = $conn->prepare($sql);

if (!$stmt) {
    http_response_code(500);
    echo json_encode([
        "success" => false,
        "message" => "Server error"
    ]);
    exit;
}

$stmt->bind_param("ii", $user_id, $user_id);
$stmt->execute();
$result = $stmt->get_result();

$notifications = [];

while ($row = $result->fetch_assoc()) {
    $notifications[] = $row;
}

echo json_encode([
    "success" => true,
    "notifications" => $notifications
]);

$stmt->close();
$conn->close();