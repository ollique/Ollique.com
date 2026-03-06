<?php
require_once 'init.php';
require_once 'connection.php';

header('Content-Type: application/json');
error_reporting(E_ALL);
ini_set('display_errors', 1);

// 🔐 Allow only POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit;
}

// 🔐 Session check
if (!isset($_SESSION['user_id'])) {
    echo json_encode([
        "status" => "error",
        "message" => "Session expired"
    ]);
    exit;
}
if (!isset($_POST['csrf']) || $_POST['csrf'] !== $_SESSION['csrf_token']) {
    echo json_encode([
        "status" => "error",
        "message" => "Invalid CSRF token"
    ]);
    exit;
}

$sender   = $_SESSION['user_id'];
$receiver = trim($_POST['receiver'] ?? '');
$message  = trim($_POST['message'] ?? '');

if (empty($receiver)) {
    echo json_encode(['status'=>'error','message'=>'Receiver is required']);
    exit;
}

if (empty($message) && empty($_FILES['image'])) {
    echo json_encode(['status'=>'error','message'=>'Message or image required']);
    exit;
}

if (isset($_FILES['image']) && $_FILES['image']['size'] > 2*1024*1024) {
    echo json_encode(['status'=>'error','message'=>'Max 2MB allowed']);
    exit;
}

// 🔴 Same Cloudinary Details (No Change)
$cloud_name = "dbiptquja";
$api_key    = "657365313595767";
$api_secret = "rIehIhi7uuExV8Hdp0rMAHQ3DEs";
$folder     = "chat_images";

$image_url = null;

if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {

    $allowedTypes = ['image/jpeg','image/png','image/gif','image/webp'];
    $mime = mime_content_type($_FILES['image']['tmp_name']);
    if($mime === 'image/jpg') $mime = 'image/jpeg';

    if (!in_array($mime, $allowedTypes)) {
        echo json_encode(['status'=>'error','message'=>'Invalid image type']);
        exit;
    }

    $timestamp = time();
    $params = [
        'folder' => $folder,
        'timestamp' => $timestamp
    ];
    ksort($params);

    $param_string = http_build_query($params, '', '&', PHP_QUERY_RFC3986);
    $signature = sha1($param_string . $api_secret);

    $post = [
        'file'      => new CURLFile($_FILES['image']['tmp_name'], $mime, $_FILES['image']['name']),
        'api_key'   => $api_key,
        'timestamp' => $timestamp,
        'signature' => $signature,
        'folder'    => $folder,
        'quality'   => 'auto:best'
    ];

    $url = "https://api.cloudinary.com/v1_1/$cloud_name/image/upload";

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $post,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2
    ]);

    $response = curl_exec($ch);

    if ($response === false) {
        echo json_encode(['status'=>'error','message'=>curl_error($ch)]);
        exit;
    }

    curl_close($ch);

    $result = json_decode($response, true);

    if (!empty($result['secure_url'])) {
        $image_url = $result['secure_url'];
    } else {
        echo json_encode(['status'=>'error','message'=>'Cloudinary upload failed']);
        exit;
    }
}

// 🔐 Rate Limit
$stmt = $conn->prepare("
    SELECT COUNT(*) 
    FROM messages 
    WHERE sender = ? 
    AND created_at > (NOW() - INTERVAL 5 SECOND)
");
$stmt->bind_param("s", $sender);
$stmt->execute();
$stmt->bind_result($count);
$stmt->fetch();
$stmt->close();

if ($count >= 5) {
    echo json_encode(['status'=>'error','message'=>'Slow down']);
    exit;
}
$stmt = $conn->prepare("SELECT user_id FROM users WHERE user_id = ?");
$stmt->bind_param("s", $receiver);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    echo json_encode(['status'=>'error','message'=>'Invalid receiver']);
    exit;
}
$stmt->close();
// 🔹 Insert Message
$stmt = $conn->prepare(
    "INSERT INTO messages (sender, receiver, message, image_path) VALUES (?, ?, ?, ?)"
);

$stmt->bind_param("ssss", $sender, $receiver, $message, $image_url);

if ($stmt->execute()) {

    $blur_url = $image_url ? str_replace(
        '/upload/',
        '/upload/c_scale,w_200,e_blur:200/',
        $image_url
    ) : null;

    echo json_encode([
        'status' => 'success',
        'id'     => $stmt->insert_id,
        'image'  => $image_url,
        'blur'   => $blur_url
    ]);

} else {
    echo json_encode(['status'=>'error','message'=>'Message insert failed']);
}

$stmt->close();
$conn->close();
?>