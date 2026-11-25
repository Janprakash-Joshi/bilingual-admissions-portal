<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$language = ($_SESSION['lang'] ?? '') === 'ja' ? 'ja' : 'eng';
$_SESSION = [];
if (ini_get('session.use_cookies')) {
    $p = session_get_cookie_params();
    setcookie(session_name(), '', time() - 3600, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
}
session_destroy();
header('Location: ../index.php?lang=' . $language, true, 303);
exit();
