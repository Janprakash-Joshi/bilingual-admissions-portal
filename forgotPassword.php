<?php
// Portal root, alongside index.php, db_connect.php and send.php.
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store');

$ja = ($_SESSION['lang'] ?? 'eng') === 'ja';
session_write_close();
function resetReply(bool $success, string $message, int $status = 200): void
{
    http_response_code($status);
    echo json_encode(['success' => $success, 'message' => $message], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Allow: POST');
    resetReply(false, $ja ? 'POST で送信してください。' : 'Use POST to request a link.', 405);
}
$email = is_string($_POST['email'] ?? null) ? trim($_POST['email']) : '';
if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 254) {
    resetReply(false, $ja ? '正しいメールアドレスを入力してください。' : 'Enter a valid email address.', 422);
}
$role = $_POST['role'] ?? '';
if (!in_array($role, ['', 'admin', 'agent'], true)) {
    resetReply(false, $ja ? 'ログイン種別を確認してください。' : 'Choose a valid login type.', 422);
}

// Optional for hosting. Use the public portal directory including any /portal path.
$portalBase = rtrim(getenv('PORTAL_BASE_URL') ?: 'http://localhost/portal', '/');
$logo = 'https://janprakash-joshi.github.io/portal/logo.png';
$subject = $ja ? '入学申請ポータル｜パスワードの再設定' : 'Reset your Admissions Portal password';
$intro = $ja ? '以下のリンクから新しいパスワードを設定してください。リンクは24時間有効です。'
             : 'Choose your account below to set a new password. The link expires in 24 hours.';
$footer = $ja ? 'お心当たりがない場合は、このメールを破棄してください。このメールは自動送信されています。返信はご遠慮ください。'
              : 'If you did not request this change, you can ignore this email. This is an automated message. Please do not reply.';
$generic = $ja ? '該当するアカウントがある場合は、再設定用のメールをご案内します。'
               : 'If an eligible account exists, you will receive password reset instructions.';

$links = [];
$inTransaction = false;
try {
    mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
    require __DIR__ . '/db_connect.php';
    $conn->set_charset('utf8mb4');
    $conn->begin_transaction();
    $inTransaction = true;
    foreach (['admin' => 'admin', 'agent' => 'agents'] as $type => $table) {
        if ($role !== '' && $role !== $type) {
            continue;
        }
        $approved = $type === 'agent' ? " AND registration_status='Approved'" : '';
        $stmt = $conn->prepare("SELECT id,email,password FROM $table WHERE email=? $approved LIMIT 2 FOR UPDATE");
        $stmt->bind_param('s', $email);
        $stmt->execute();
        $accounts = $stmt->get_result();
        if ($accounts->num_rows !== 1) {
            continue;
        }
        $account = $accounts->fetch_assoc();
        $loginEmail = $account['email'];
        $last = $conn->prepare("SELECT id FROM password_resets WHERE email=? AND user_type=?
            AND created_at > NOW() - INTERVAL 60 SECOND AND token LIKE 'h:%' LIMIT 1");
        $last->bind_param('ss', $loginEmail, $type);
        $last->execute();
        if ($last->get_result()->fetch_row()) {
            continue;
        }
        $nonce = bin2hex(random_bytes(32));
        // The prefix distinguishes hashed tokens from older 64-character plaintext tokens.
        $digest = 'h:' . substr(hash('sha256', $nonce), 0, 62);
        $signature = hash_hmac('sha256', $type . '|' . $account['id'] . '|' . $nonce, $account['password']);
        $insert = $conn->prepare("INSERT INTO password_resets (email,user_type,token,expires_at,used)
            VALUES (?,?,?,UTC_TIMESTAMP() + INTERVAL 24 HOUR,0)");
        $insert->bind_param('sss', $loginEmail, $type, $digest);
        $insert->execute();
        $links[$type] = [
            'id' => $insert->insert_id, 'email' => $loginEmail,
            'url' => $portalBase . '/reset-password.php?' . http_build_query([
                'token' => 'v2.' . $nonce . '.' . $signature, 'lang' => $ja ? 'ja' : 'eng',
            ]),
        ];
    }
    $conn->commit();
    $inTransaction = false;
    if ($links) {
        $buttons = '';
        foreach ($links as $type => $link) {
            $label = $type === 'admin'
                ? ($ja ? '管理者パスワードの再設定' : 'Reset admin password')
                : ($ja ? '代理店パスワードの再設定' : 'Reset agent password');
            $url = htmlspecialchars($link['url'], ENT_QUOTES, 'UTF-8');
            $buttons .= '<p style="margin:20px 0"><a style="display:inline-block;padding:12px 18px;background:#173b59;color:#fff;border-radius:6px;text-decoration:none" href="' . $url . '">' . $label . '</a></p>';
        }
        $content = '<div style="max-width:530px;margin:24px auto;padding:24px;font:16px/1.6 Arial,sans-serif;color:#20344a;border:1px solid #dce5ec;border-top:4px solid #173b59">'
            . '<img src="' . $logo . '" width="170" alt="Admissions Portal"><h2>' . $subject . '</h2>'
            . '<p>' . $intro . '</p>' . $buttons . '<p style="color:#526577;font-size:13px">' . $footer . '</p></div>';
        require_once __DIR__ . '/dashboard/portal_mail.php'; // Calls the configured root send.php.
        $reason = 'mail_failed';
        $recipient = reset($links)['email'];
        $sent = adminSendPortalMail($recipient, $subject, $content, $reason);
        if (!$sent) {
            error_log('Portal password-reset mail: ' . $reason);
        }
        foreach ($links as $type => $link) {
            // Keep earlier links usable when sending fails. An uncertain send might still arrive.
            if ($sent) {
                $update = $conn->prepare('UPDATE password_resets SET used=1 WHERE email=? AND user_type=? AND id<? AND used=0');
                $update->bind_param('ssi', $link['email'], $type, $link['id']);
                $update->execute();
            } elseif ($reason !== 'mail_unconfirmed') {
                $update = $conn->prepare('UPDATE password_resets SET used=1 WHERE id=?');
                $update->bind_param('i', $link['id']);
                $update->execute();
            }
        }
    }
} catch (Throwable $e) {
    if ($inTransaction) {
        $conn->rollback();
    }
    error_log('Portal password-reset request: ' . get_class($e) . ' (' . $e->getCode() . ')');
    // Keep transport/lookup failures private. The existing popup expects JSON.
}
resetReply(true, $generic);
