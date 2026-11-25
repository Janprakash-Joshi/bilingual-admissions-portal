<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/portal_mail.php';
adminCheckPost();
if (empty($_SESSION['admin_alert_sent'])) {
    $_SESSION['admin_alert_sent'] = true;
    $settings = adminMailSettings();
    $adminEmail = $_SESSION['auth']['email'] ?? '';
    $to = $settings['alert_to'] ?? '' ?: $adminEmail;
    session_write_close();
    $body =
        '<p>' .
        adminH($adminWords['admin_login']) .
        '</p><p>' .
        adminH($adminEmail) .
        '<br>' .
        adminH(gmdate('Y-m-d H:i')) .
        ' UTC<br>IP ' .
        adminH($_SERVER['REMOTE_ADDR'] ?? '') .
        '</p>';
    if (!adminSendPortalMail($to, $adminWords['admin_login'], $body)) {
        error_log('Admin login email was not confirmed');
    }
}
http_response_code(204);
