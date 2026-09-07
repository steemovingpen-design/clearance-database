<?php
// students/logout.php

// 1. Force error reporting for debugging safety
error_reporting(E_ALL);
ini_set('display_errors', 1);

// 2. Initialize the session framework so PHP knows which session to target
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 3. Clear all active session payload variables completely
$_SESSION = array();

// 4. Force erase the session tracking cookie from the user's web browser environment
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// 5. Annihilate the session state allocation container on the server
session_destroy();

// 6. Direct clean routing straight back to the login interface viewport
header("Location: login.php");
exit();
?>