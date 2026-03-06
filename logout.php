<?php
require 'init.php'; 
$_SESSION = [];
session_regenerate_id(true);
session_destroy();
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();

    setcookie(
        session_name(),
        '',
        time() - 42000,
        $params["path"],
        $params["domain"],
        $params["secure"],
        $params["httponly"]
    );
}

/* ================= REDIRECT ================= */

header("Location: ../login.html");
exit;