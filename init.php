<?php
ini_set('session.cookie_httponly', 1);
ini_set('session.cookie_secure', 0); // Use only if HTTPS
ini_set('session.use_strict_mode', 1);
ini_set('session.cookie_samesite', 'Lax');
session_start();
header("X-Frame-Options: DENY");
header("X-Content-Type-Options: nosniff");
header("Referrer-Policy: strict-origin-when-cross-origin");
header("X-XSS-Protection: 1; mode=block");

if (!isset($_SESSION['initiated'])) {
    session_regenerate_id(true);
    $_SESSION['initiated'] = true;
}

/* ================= CSRF TOKEN GENERATION ================= */
if (isset($_GET['csrf'])) {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    echo $_SESSION['csrf_token'];
    exit;
}
?>