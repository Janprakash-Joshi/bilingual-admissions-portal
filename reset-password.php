<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
header('Content-Type: text/html; charset=UTF-8');
header('Cache-Control: no-store');
header('Referrer-Policy: no-referrer');

if (in_array($_GET['lang'] ?? null, ['eng', 'ja'], true)) {
    $_SESSION['lang'] = $_GET['lang'];
}
$ja = ($_SESSION['lang'] ?? 'eng') === 'ja';
$t = $ja ? [
    'title' => 'パスワードの再設定', 'password' => '新しいパスワード', 'confirm' => '新しいパスワード（確認）',
    'hint' => '12文字以上、UTF-8で72バイト以内で入力してください。', 'match' => '入力したパスワードが一致しません。',
    'save' => 'パスワードを更新', 'login' => 'ログイン画面へ', 'done' => 'パスワードを更新しました。新しいパスワードでログインしてください。',
    'invalid' => 'このリンクは無効か、有効期限が切れています。ログイン画面でパスワードの再設定をもう一度お申し込みください。',
    'retry' => '更新できませんでした。しばらくしてからもう一度お試しください。', 'show' => '表示', 'hide' => '隠す',
    'admin' => '管理者', 'agent' => '代理店', 'session' => 'ページを再読み込みしてからもう一度お試しください。',
] : [
    'title' => 'Reset your password', 'password' => 'New password', 'confirm' => 'Confirm new password',
    'hint' => 'Use at least 12 characters, up to 72 UTF-8 bytes.', 'match' => 'The passwords do not match.',
    'save' => 'Update password', 'login' => 'Go to login', 'done' => 'Your password has been updated. Sign in with your new password.',
    'invalid' => 'This link is invalid or has expired. Request a fresh link from the login page.',
    'retry' => 'We could not complete this request. Please try again.', 'show' => 'Show', 'hide' => 'Hide',
    'admin' => 'Admin', 'agent' => 'Agent', 'session' => 'Reload the page and try again.',
];
function resetH($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}
$token = is_string($_GET['token'] ?? null) ? $_GET['token'] : '';
$modern = preg_match('/^v2\.([a-f0-9]{64})\.([a-f0-9]{64})$/D', $token, $parts) === 1;
$legacy = preg_match('/^[a-f0-9]{64}$/D', $token) === 1;
$post = $_SERVER['REQUEST_METHOD'] === 'POST';
$complete = !$post && $token === '' && (int) ($_SESSION['portal_reset_complete'] ?? 0) > time();
$_SESSION['portal_reset_csrf'] ??= bin2hex(random_bytes(32));
$message = $t['invalid'];
$valid = false;
$inTransaction = false;

if ($modern || $legacy) {
    $lookup = $modern ? 'h:' . substr(hash('sha256', $parts[1]), 0, 62) : $token;
    $clock = $modern ? 'UTC_TIMESTAMP()' : 'NOW()';
    $tokenQuery = "SELECT id,email,user_type FROM password_resets
        WHERE token=? AND used=0 AND expires_at>$clock AND user_type IN ('agent','admin') LIMIT 1";
    try {
        mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
        require __DIR__ . '/db_connect.php';
        $conn->set_charset('utf8mb4');
        $stmt = $conn->prepare($tokenQuery);
        $stmt->bind_param('s', $lookup);
        $stmt->execute();
        $reset = $stmt->get_result()->fetch_assoc();
        if ($reset) {
            $table = $reset['user_type'] === 'agent' ? 'agents' : 'admin';
            $approved = $table === 'agents' ? " AND registration_status='Approved'" : '';
            if ($post) {
                $conn->begin_transaction();
                $inTransaction = true;
            }
            $lock = $post ? ' FOR UPDATE' : '';
            $stmt = $conn->prepare("SELECT id,password FROM $table WHERE email=? $approved LIMIT 2" . $lock);
            $stmt->bind_param('s', $reset['email']);
            $stmt->execute();
            $accounts = $stmt->get_result();
            $account = $accounts->num_rows === 1 ? $accounts->fetch_assoc() : null;
            $valid = $account && (!$modern || hash_equals(
                hash_hmac('sha256', $reset['user_type'] . '|' . $account['id'] . '|' . $parts[1], $account['password']),
                $parts[2]
            ));
            if ($valid && $post) {
                // Recheck the token under a lock after acquiring the account lock.
                $stmt = $conn->prepare($tokenQuery . ' FOR UPDATE');
                $stmt->bind_param('s', $lookup);
                $stmt->execute();
                $latest = $stmt->get_result()->fetch_assoc();
                $valid = $latest && $latest['id'] === $reset['id'];
            }
            if ($valid) {
                $message = '';
            }
            if ($valid && $post) {
                $password = $_POST['password'] ?? null;
                $confirm = $_POST['confirm_password'] ?? null;
                if (!is_string($_POST['csrf'] ?? null) || !hash_equals($_SESSION['portal_reset_csrf'], $_POST['csrf'])) {
                    $message = $t['session'];
                } elseif (!is_string($password) || strlen($password) > 72 || strpos($password, "\0") !== false
                    || !preg_match('/^.{12,}$/usD', $password)) {
                    $message = $t['hint'];
                } elseif ($password !== $confirm) {
                    $message = $t['match'];
                } else {
                    $hash = password_hash($password, PASSWORD_DEFAULT);
                    $stmt = $conn->prepare("UPDATE $table SET password=? WHERE id=?");
                    $stmt->bind_param('si', $hash, $account['id']);
                    $stmt->execute();
                    $stmt = $conn->prepare('UPDATE password_resets SET used=1 WHERE email=? AND user_type=? AND used=0');
                    $stmt->bind_param('ss', $reset['email'], $reset['user_type']);
                    $stmt->execute();
                    $conn->commit();
                    $inTransaction = false;
                    $_SESSION['portal_reset_complete'] = time() + 120;
                    unset($_SESSION['portal_reset_csrf']);
                    header('Location: reset-password.php', true, 303);
                    exit;
                }
            }
            if ($inTransaction) {
                $conn->rollback();
                $inTransaction = false;
            }
        }
    } catch (Throwable $e) {
        if ($inTransaction) {
            $conn->rollback();
        }
        error_log('Portal password reset: ' . get_class($e) . ' (' . $e->getCode() . ')');
        $valid = false;
        $message = $t['retry'];
        http_response_code(503);
    }
}
?>
<!DOCTYPE html>
<html lang="<?= $ja ? 'ja' : 'en' ?>">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= resetH($t['title']) ?> | Admissions Portal</title>
<style>
*{box-sizing:border-box}body{margin:0;min-height:100vh;display:grid;place-items:center;padding:20px;background:radial-gradient(ellipse at 5% 4%,#344f72,transparent 63%),#102338;color:#22364d;font:16px/1.55 "Segoe UI","Yu Gothic",Meiryo,sans-serif}
main{width:min(100%,430px);padding:clamp(22px,5vw,38px);border-radius:16px;background:white;box-shadow:0 18px 55px #09192e55}
header,nav,.field{display:flex;align-items:center}header{justify-content:space-between;gap:12px;flex-wrap:wrap}header img{width:135px;max-width:65%}nav{gap:10px}nav a{font-size:.85rem}a{color:#1b547a}a[aria-current]{font-weight:700}h1{margin:28px 0 8px;font-size:1.65rem}p{margin:8px 0 18px;overflow-wrap:anywhere}
label{display:block;font-weight:600;margin:14px 0 6px}.field{border:1px solid #a9bac8;border-radius:8px;padding-right:4px}.field:focus-within{outline:2px solid #416e90}input{min-width:0;width:100%;font:inherit;padding:11px;border:0;border-radius:8px;background:white}.toggle{display:none}body.has-js .toggle{display:block}
button{font:inherit;cursor:pointer}.toggle{color:#205473;background:white;border:0;min-height:40px}.save{width:100%;margin:20px 0 12px;padding:12px;background:#173d5e;color:white;border:0;border-radius:8px;font-weight:650}.message{color:#8c3333}.login{display:inline-block;margin-top:8px}small{color:#52677b}
</style>
</head>
<body><main>
<header><img src="img/logo.png" alt="Admissions Portal">
<nav aria-label="<?= $ja ? '言語' : 'Language' ?>">
<?php foreach (['eng' => 'English', 'ja' => '日本語'] as $code => $label): ?>
<a lang="<?= $code === 'eng' ? 'en' : 'ja' ?>" href="?<?= resetH(http_build_query(array_filter(['token' => $token, 'lang' => $code]))) ?>" <?= ($ja ? 'ja' : 'eng') === $code ? 'aria-current="true"' : '' ?>><?= $label ?></a>
<?php endforeach; ?></nav></header>
<h1><?= resetH($t['title']) ?></h1>
<?php if ($complete): ?>
<p role="status"><?= resetH($t['done']) ?></p>
<?php else: ?>
<?php if ($message): ?><p role="alert" class="message"><?= resetH($message) ?></p><?php endif; ?>
<?php if ($valid): ?>
<p><strong><?= resetH($t[$reset['user_type']]) ?></strong><br><?= resetH($reset['email']) ?></p>
<form method="post">
<input type="hidden" name="csrf" value="<?= resetH($_SESSION['portal_reset_csrf']) ?>">
<small id="hint"><?= resetH($t['hint']) ?></small>
<?php foreach (['password' => 'password', 'confirm_password' => 'confirm'] as $field => $label): ?>
<label for="<?= $field ?>"><?= resetH($t[$label]) ?></label>
<div class="field"><input id="<?= $field ?>" name="<?= $field ?>" type="password" autocomplete="new-password" aria-describedby="hint" required>
<button class="toggle" type="button" aria-controls="<?= $field ?>" aria-pressed="false" data-show="<?= resetH($t['show']) ?>" data-hide="<?= resetH($t['hide']) ?>"><?= resetH($t['show']) ?></button></div>
<?php endforeach; ?>
<button class="save" type="submit"><?= resetH($t['save']) ?></button></form>
<?php endif; endif; ?>
<a class="login" href="index.php"><?= resetH($t['login']) ?></a></main>
<script>
document.body.classList.add('has-js');
document.querySelectorAll('.toggle').forEach(button => {
    button.addEventListener('click', () => {
        const field = document.getElementById(button.getAttribute('aria-controls'));
        const show = field.type === 'password';
        field.type = show ? 'text' : 'password';
        button.textContent = show ? button.dataset.hide : button.dataset.show;
        button.setAttribute('aria-pressed', String(show));
    });
});
</script>
</body></html>
