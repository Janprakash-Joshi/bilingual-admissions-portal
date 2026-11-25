<?php
require_once __DIR__ . '/auth.php';
$id = (int) $_SESSION['auth']['id'];
$stmt = $conn->prepare('SELECT username,email,password FROM admin WHERE id=?');
$stmt->bind_param('i', $id);
$stmt->execute();
$account = $stmt->get_result()->fetch_assoc();
if (!$account) {
    http_response_code(403);
    exit(adminH($adminWords['denied']));
}
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    adminCheckPost();
    $current = $_POST['current_password'] ?? null;
    $new = $_POST['new_password'] ?? null;
    if (!is_string($current) || !password_verify($current, $account['password'])) {
        $error = 'wrong_password';
    } elseif (!is_string($new) || strlen($new) < 6 || strlen($new) > 72 || str_contains($new, chr(0))) {
        $error = 'password_length';
    } elseif ($new !== ($_POST['confirm_password'] ?? null)) {
        $error = 'mismatch';
    } else {
        $hash = password_hash($new, PASSWORD_DEFAULT);
        $stmt = $conn->prepare('UPDATE admin SET password=? WHERE id=? AND password=?');
        $stmt->bind_param('sis', $hash, $id, $account['password']);
        $stmt->execute();
        if ($stmt->affected_rows === 1) {
            session_regenerate_id(true);
            adminFlash('password_saved');
            adminGo('reset_password.php');
        }
        $error = 'failed';
    }
}
require __DIR__ . '/topnav.php';
require __DIR__ . '/sidebar.php';
?>
<main class="admin-main-panel"><h1><?= adminH($adminWords['password']) ?></h1>
<section class="admin-panel admin-form-panel"><h2><?= adminH($account['username']) ?></h2><p><?= adminH(
    $account['email'],
) ?></p>
<?php if ($error): ?><p role="alert" class="admin-error"><?= adminH($adminWords[$error]) ?></p><?php endif; ?>
<form method="post" class="admin-form"><?= adminCsrfField() ?>
<?php foreach (
    ['current_password' => 'current', 'new_password' => 'new_password', 'confirm_password' => 'confirm']
    as $name => $k
): ?>
<label><?= adminH($adminWords[$k]) ?><input name="<?= $name ?>" type="password" autocomplete="<?= $name ===
'current_password'
    ? 'current-password'
    : 'new-password' ?>" <?= $name === 'current_password' ? '' : 'minlength="6"' ?> required></label>
<?php endforeach; ?><button class="admin-primary"><?= adminH(
    $adminWords['save'],
) ?></button></form></section></main>
<?php require __DIR__ . '/footer.php'; ?>
