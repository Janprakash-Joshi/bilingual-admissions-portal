<?php
require_once __DIR__ . '/auth.php';
$screen = basename($_SERVER['SCRIPT_NAME'] ?? '', '.php');
$screenNames = [
    'index' => 'overview',
    'agent_registrations' => 'reviews',
    'partners' => 'partners',
    'users-roles' => 'users',
    'reset_password' => 'password',
    'profile' => 'profile',
    'applications' => 'applications',
    'apply' => 'apply',
];
$pageTitle = $adminWords[$screenNames[$screen] ?? 'admin'];
$managedChrome = in_array(
    $screen,
    ['index', 'agent_registrations', 'partners', 'reset_password', 'users-roles'],
    true,
);
?>
<!doctype html>
<html lang="<?= $lang === 'ja' ? 'ja' : 'en' ?>">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title><?= adminH($pageTitle) ?> · <?= adminH($adminWords['portal']) ?></title>
<link rel="icon" href="../img/logo.png">
<?php if (!$managedChrome): ?>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<?php endif; ?>
<?php if (
    $screen === 'agent_registrations'
): ?><link rel="stylesheet" href="../css/registration.css"><?php endif; ?>
<link rel="stylesheet" href="./css/style.css">
<?php if (!empty($view_css)): ?><link rel="stylesheet" href="<?= adminH($view_css) ?>"><?php endif; ?>
</head>
<body class="admin-shell<?= $managedChrome ? ' admin-managed' : '' ?>" data-csrf="<?= adminH(
    $_SESSION['agency_admin_csrf'],
) ?>" data-login-alert="<?= empty($_SESSION['admin_alert_sent']) ? '1' : '0' ?>">
<header class="topnav admin-top" aria-label="<?= adminH($adminWords['portal']) ?>">
<button type="button" class="admin-menu-button" aria-expanded="false" aria-controls="sidebar" aria-label="<?= adminH(
    $adminWords['menu'],
) ?>">☰</button>
<span class="admin-header-title"><?= adminH($pageTitle) ?></span>
<div class="admin-tools">
<nav class="admin-languages" aria-label="<?= adminH($adminWords['language']) ?>">
<?php foreach (['eng' => ['en', 'gb', 'English'], 'ja' => ['ja', 'jp', '日本語']] as $code => $choice):

    $params = $_GET;
    $params['lang'] = $code;
    ?>
<a href="?<?= adminH(http_build_query($params)) ?>" lang="<?= $choice[0] ?>" <?= $lang === $code
    ? 'aria-current="true"'
    : '' ?>><img src="../img/flag-<?= $choice[1] ?>.svg" alt=""><?= $choice[2] ?></a>
<?php
endforeach; ?></nav>
<details class="admin-account"><summary><?= adminH(
    $_SESSION['auth']['name'] ?? $adminWords['admin'],
) ?></summary>
<div><?php if (is_file(__DIR__ . '/profile.php')): ?><a href="profile.php"><?= adminH(
    $adminWords['profile'],
) ?></a><?php endif; ?>
<a href="reset_password.php"><?= adminH($adminWords['password']) ?></a>
<a href="logout.php"><?= adminH($adminWords['logout']) ?></a></div></details>
</div></header>
<?php if (!empty($_SESSION['admin_flash'])):

    [$flashKey, $flashType] = $_SESSION['admin_flash'];
    unset($_SESSION['admin_flash']);
    ?>
<p role="status" class="admin-flash <?= $flashType === 'success' ? 'ok' : 'warn' ?>"><?= adminH(
    $adminWords[$flashKey] ?? $flashKey,
) ?></p>
<?php
endif; ?>
