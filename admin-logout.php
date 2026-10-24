<?php
// Ends the admin session completely, then sends the person to the admin login page.
session_start();

$_SESSION = [];

if (ini_get('session.use_cookies')) {
    $c = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000, $c['path'], $c['domain'], $c['secure'], $c['httponly']);
}

session_destroy();

header('Location: admin-login.php');
exit;
