<?php
require_once 'init.php';
require_once 'connection.php';
header('Content-Type: application/json');
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
try {
    // ✅ Allow only POST
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode(['status' => 'error', 'message' => 'Method not allowed']);
        exit;
    }
    // ✅ Check login
    if (empty($_SESSION['user_id'])) {
        echo json_encode(['status' => 'error', 'message' => 'Not logged in']);
        exit;
    }

    $user_id = trim($_SESSION['user_id']);

    // ✅ CSRF VERIFY
    if (
        empty($_POST['csrf']) ||
        empty($_SESSION['csrf_token']) ||
        !hash_equals($_SESSION['csrf_token'], $_POST['csrf'])
    ) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid CSRF token']);
        exit;
    }

    // ====================================================
    // 🟢 1️⃣ MARK ALL MESSAGES AS SEEN
    // ====================================================
    if (!empty($_POST['receiver'])) {

        $receiver = trim($_POST['receiver']);

        $stmt = $conn->prepare("
            UPDATE messages 
            SET status = 'seen' 
            WHERE sender = ? 
            AND receiver = ? 
            AND status != 'seen'
        ");

        $stmt->bind_param("ss", $receiver, $user_id);
        $stmt->execute();

        echo json_encode([
            'status' => 'success',
            'updated_rows' => $stmt->affected_rows,
            'message' => $stmt->affected_rows > 0 
                ? 'Messages marked as seen'
                : 'No new messages to update'
        ]);
        exit;
    }

    // ====================================================
    // 🟢 2️⃣ UPDATE SINGLE MESSAGE STATUS
    // ====================================================
    if (!empty($_POST['id']) && !empty($_POST['status'])) {

        $id = intval($_POST['id']);
        $newStatus = trim($_POST['status']);

        $stmt = $conn->prepare("
            UPDATE messages 
            SET status = ? 
            WHERE id = ?
        ");

        $stmt->bind_param("si", $newStatus, $id);
        $stmt->execute();

        echo json_encode([
            'status' => 'success',
            'updated_rows' => $stmt->affected_rows,
            'new_status' => $newStatus
        ]);
        exit;
    }

    // ❌ Invalid Request
    echo json_encode([
        'status' => 'error',
        'message' => 'Invalid request parameters'
    ]);

} catch (Exception $e) {

    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage()
    ]);
}
?>