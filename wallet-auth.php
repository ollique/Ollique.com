<?php
require "init.php";
require "connection.php";

header("Content-Type: application/json");

$data = json_decode(file_get_contents("php://input"), true);

if(empty($data['address']) || empty($data['signature'])){
echo json_encode([
"success"=>false,
"message"=>"Missing credentials"
]);
exit;
}

$address = strtolower(trim($data['address']));
$nonce   = $_SESSION['wallet_nonce'] ?? '';

if(!$nonce){
echo json_encode([
"success"=>false,
"message"=>"Session expired"
]);
exit;
}

if(!isset($_SESSION['wallet_nonce_time'])){
echo json_encode([
"success"=>false,
"message"=>"Invalid session"
]);
exit;
}

if(time() - $_SESSION['wallet_nonce_time'] > 300){

unset($_SESSION['wallet_nonce']);
unset($_SESSION['wallet_nonce_time']);

echo json_encode([
"success"=>false,
"message"=>"Nonce expired"
]);

exit;
}

unset($_SESSION['wallet_nonce']);
unset($_SESSION['wallet_nonce_time']);

/* ===== USER LOGIN ===== */

$stmt = $conn->prepare("SELECT id,user_id FROM users WHERE email=? LIMIT 1");
$stmt->bind_param("s",$address);
$stmt->execute();
$result = $stmt->get_result();

if($result->num_rows==0){

$user_id = strtoupper(bin2hex(random_bytes(5)));
$name = "User_" . substr($address,2,6);

$insert = $conn->prepare("
INSERT INTO users (user_id,name,email,login_type,status,last_activity)
VALUES (?, ?, ?, 'wallet','online',NOW())
");

$insert->bind_param("sss",$user_id,$name,$address);
$insert->execute();

$uid = $insert->insert_id;

}else{

$user=$result->fetch_assoc();

$uid = $user['id'];
$user_id = $user['user_id'];

$update = $conn->prepare("
UPDATE users SET
status='online',
last_activity=NOW()
WHERE id=?
");

$update->bind_param("i",$uid);
$update->execute();
}

session_regenerate_id(true);

$_SESSION['id']=$uid;
$_SESSION['user_id']=$user_id;
$_SESSION['login_type']="wallet";

echo json_encode([
"success"=>true,
"message"=>"Login successful"
]);