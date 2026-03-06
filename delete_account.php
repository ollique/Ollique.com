<?php
session_start();
include 'connection.php';

header('Content-Type: application/json');

if(!isset($_SESSION['user_id']))
exit(json_encode(["success"=>false,"message"=>"Login required"]));

$user_id = $_SESSION['user_id'];

$stmt=$conn->prepare("SELECT email,is_deleted FROM users WHERE user_id=? LIMIT 1");
$stmt->bind_param("s",$user_id);
$stmt->execute();
$r=$stmt->get_result()->fetch_assoc();

if(!$r) exit(json_encode(["success"=>false,"message"=>"Account not found"]));

if($r['is_deleted'])
exit(json_encode(["success"=>false,"message"=>"Already deleted"]));

$newEmail = bin2hex(random_bytes(6))."@deleted.com";
$newPass  = password_hash(bin2hex(random_bytes(8)),PASSWORD_DEFAULT);

$stmt=$conn->prepare("
UPDATE users SET
email=?,
password=?,
otp=NULL,
otp_verified=0,
status='offline',
is_deleted=1,
deleted_at=NOW()
WHERE user_id=?
");

$stmt->bind_param("sss",$newEmail,$newPass,$user_id);
$stmt->execute();

session_destroy();

echo json_encode(["success"=>true,"message"=>"Account deleted"]);
exit;
?>