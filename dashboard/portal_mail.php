<?php
function adminMailSettings(): array
{
    $file = __DIR__ . '/mail-settings.local.php';
    return is_file($file) ? require $file : [];
}

// The root send.php used by forgotPassword.php expects these four local variables.
function adminSendPortalMail(string $email, string $subject, string $content): bool
{
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return false;
    }
    $name = ''; // Use the registered address as the only recipient.
    $root = dirname(__DIR__);
    $cwd = getcwd();
    $sent = false;
    ob_start();
    try {
        // Some existing PHPMailer installations use a Latin-1 default.
        if (preg_match('/[^\x00-\x7F]/', $subject)) {
            $subject = '=?UTF-8?B?' . base64_encode($subject) . '?=';
        }
        if (function_exists('mb_encode_numericentity')) {
            $content = mb_encode_numericentity($content, [128, 0x10ffff, 0, 0x1fffff], 'UTF-8');
        } else {
            $content = preg_replace_callback(
                '/[^\x00-\x7F]/u',
                static function ($m) {
                    $point = unpack('Ncode', iconv('UTF-8', 'UTF-32BE', $m[0]));
                    return '&#' . $point['code'] . ';';
                },
                $content,
            );
        }
        if (!is_file($root . '/send.php') || !chdir($root)) {
            throw new RuntimeException('Mail setup missing');
        }
        $result = require $root . '/send.php';
        $sent =
            $result === true ||
            ($result !== false &&
                isset($mail) &&
                $mail instanceof \PHPMailer\PHPMailer\PHPMailer &&
                !$mail->isError() &&
                trim((string) ob_get_contents()) === '');
    } catch (Throwable $error) {
        error_log('Portal mail transport failed (' . get_class($error) . ')');
    } finally {
        ob_end_clean();
        if ($cwd !== false) {
            chdir($cwd);
        }
    }
    return $sent;
}
