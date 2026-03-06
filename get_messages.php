<?php
require_once 'init.php';
require_once 'connection.php';
header('Content-Type: application/json');
// ✅ Allow only POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['status' => 'error', 'message' => 'Method not allowed']);
    exit;
}
// ✅ Check login session
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'Not logged in']);
    exit;
}
// ✅ CSRF VERIFY
if (
    empty($_POST['csrf']) ||
    empty($_SESSION['csrf_token']) ||
    !hash_equals($_SESSION['csrf_token'], $_POST['csrf'])
) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid CSRF token']);
    exit;
}

$sender   = $_SESSION['user_id'];
$receiver = trim($_POST['receiver'] ?? '');

if (!$receiver) {
    echo json_encode(['status' => 'error', 'message' => 'Receiver missing']);
    exit;
}
try {

    $stmt = $conn->prepare("
        SELECT *,
        CASE WHEN sender = ? THEN 1 ELSE 0 END AS is_me
        FROM messages
        WHERE (sender = ? AND receiver = ?)
           OR (sender = ? AND receiver = ?)
        ORDER BY created_at ASC
    ");

    if (!$stmt) {
        throw new Exception("Prepare failed");
    }

    $stmt->bind_param("sssss", $sender, $sender, $receiver, $receiver, $sender);
    $stmt->execute();

    $result = $stmt->get_result();
    $messages = [];

    while ($row = $result->fetch_assoc()) {
        $messages[] = $row;
    }

    echo json_encode([
        'status'   => 'success',
        'messages' => $messages
    ]);

} catch (Exception $e) {

    http_response_code(500);

    echo json_encode([
        'status'  => 'error',
        'message' => 'Database error'
    ]);
}