<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/permissions.php';
if (empty($_SESSION['auth']['logged_in']) || ($_SESSION['auth']['role'] ?? '') !== 'admin') {
    header('Location: ../index.php');
    exit();
}

if (in_array($_GET['lang'] ?? null, ['eng', 'ja'], true)) {
    $_SESSION['lang'] = $_GET['lang'];
}
$lang = ($_SESSION['lang'] ?? 'eng') === 'ja' ? 'ja' : 'eng';
$adminWords = require __DIR__ . '/../lang/' . $lang . '_admin.php';
$_SESSION['agency_admin_csrf'] ??= bin2hex(random_bytes(32));
require_once __DIR__ . '/../db_connect.php';
$conn->set_charset('utf8mb4');

function adminH($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function adminRequire(string $permission): void
{
    global $adminWords;
    if (!can($permission)) {
        http_response_code(403);
        exit(adminH($adminWords['denied']));
    }
}

function adminCheckPost(): void
{
    global $adminWords;
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
        http_response_code(405);
        header('Allow: POST');
        exit();
    }
    if (!is_string($_POST['csrf'] ?? null) || !hash_equals($_SESSION['agency_admin_csrf'], $_POST['csrf'])) {
        http_response_code(403);
        exit(adminH($adminWords['expired']));
    }
}

function adminCsrfField(): string
{
    return '<input type="hidden" name="csrf" value="' . adminH($_SESSION['agency_admin_csrf']) . '">';
}

function adminFlash(string $key, string $type = 'success'): void
{
    $_SESSION['admin_flash'] = [$key, $type];
}

function adminGo(string $target): void
{
    header('Location: ' . $target, true, 303);
    exit();
}
