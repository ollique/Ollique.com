<?php
require "init.php";

header("Content-Type: application/json");

/* prevent caching */
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");

/* session check */
if(!isset($_SESSION)){
    session_start();
}

try{

$nonce = bin2hex(random_bytes(16));

$_SESSION['wallet_nonce'] = $nonce;
$_SESSION['wallet_nonce_time'] = time();

echo json_encode([
    "success" => true,
    "nonce" => $nonce
]);

}catch(Exception $e){

echo json_encode([
    "success" => false,
    "message" => "Nonce generation failed"
]);

}