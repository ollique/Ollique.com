<?php
require_once __DIR__ . "/init.php"; // session + security settings
require_once __DIR__ . "/connection.php";
header('Content-Type: application/json');
/* ================= LOGIN CHECK ================= */
if (empty($_SESSION['id'])) {
    echo json_encode([
        "status" => false,
        "message" => "Not logged in"
    ]);
    exit;
}
/* ================= ONLY POST ================= */
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode([
        "status" => false,
        "message" => "Invalid request"
    ]);
    exit;
}

/* ================= READ JSON ================= */
$input = json_decode(file_get_contents('php://input'), true);

/* ================= GENERATE CSRF IF NOT EXISTS ================= */
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

/* ================= FIRST LOAD (NO TOKEN SENT) ================= */
if (empty($input['csrf_token'])) {

    echo json_encode([
        "status" => true,
        "wallets" => [],
        "csrf_token" => $_SESSION['csrf_token']
    ]);
    exit;
}

/* ================= VERIFY CSRF ================= */
if (!hash_equals($_SESSION['csrf_token'], $input['csrf_token'])) {
    echo json_encode([
        "status" => false,
        "message" => "Invalid CSRF"
    ]);
    exit;
}

/* ================= FETCH WALLETS ================= */
$user_id = (int) $_SESSION['id'];

$sql = "SELECT network, address 
        FROM wallets 
        WHERE user_id=? AND is_active=1 
        ORDER BY network";

$stmt = $conn->prepare($sql);

if (!$stmt) {
    echo json_encode([
        "status" => false,
        "message" => "Database error"
    ]);
    exit;
}

$stmt->bind_param("i", $user_id);
$stmt->execute();

$result = $stmt->get_result();
$wallets = [];

while ($row = $result->fetch_assoc()) {
    $wallets[] = [
        "network" => htmlspecialchars($row['network'], ENT_QUOTES, 'UTF-8'),
        "address" => htmlspecialchars($row['address'], ENT_QUOTES, 'UTF-8')
    ];
}

$stmt->close();

/* ================= RESPONSE ================= */
echo json_encode([
    "status" => true,
    "wallets" => $wallets,
    "csrf_token" => $_SESSION['csrf_token']
]);

exit;