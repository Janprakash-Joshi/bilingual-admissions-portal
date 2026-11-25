<?php
require_once __DIR__ . '/agent_page.php';
$_SESSION['agent_form_csrf'] ??= bin2hex(random_bytes(32));
$errorMessage = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $current = is_string($_POST['current_password'] ?? null) ? $_POST['current_password'] : '';
    $new = is_string($_POST['new_password'] ?? null) ? $_POST['new_password'] : '';
    $confirmation = is_string($_POST['confirm_password'] ?? null) ? $_POST['confirm_password'] : '';
    if (!is_string($_POST['csrf'] ?? null) || !hash_equals($_SESSION['agent_form_csrf'], $_POST['csrf'])) {
        $errorMessage = $desk['csrf'];
    } elseif ($new !== $confirmation) {
        $errorMessage = $desk['mismatch'];
    } elseif (strlen($new) > 72 || str_contains($new, chr(0)) || preg_match_all('/./us', $new) < 12) {
        $errorMessage = $desk['passHint'];
    } else {
        mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
        try {
            $query = $conn->prepare('SELECT password FROM agents WHERE id=?');
            $query->bind_param('i', $agentId);
            $query->execute();
            $credential = $query->get_result()->fetch_assoc();
            if (!$credential || !password_verify($current, $credential['password'])) {
                $errorMessage = $desk['wrongPass'];
            } else {
                $hash = password_hash($new, PASSWORD_DEFAULT);
                $update = $conn->prepare("UPDATE agents SET password=? WHERE id=? AND BINARY password=? AND registration_status='Approved'");
                $update->bind_param('sis', $hash, $agentId, $credential['password']);
                $update->execute();
                if ($update->affected_rows === 1) {
                    session_regenerate_id(true);
                    $_SESSION['agent_password_saved'] = true;
                    header('Location: reset_password', true, 303);
                    exit;
                }
                $errorMessage = $desk['saveFail'];
            }
        } catch (mysqli_sql_exception $error) {
            error_log('Agent password update failed (' . $error->getCode() . ')');
            $errorMessage = $desk['saveFail'];
        }
    }
}
$passwordSaved = !empty($_SESSION['agent_password_saved']);
unset($_SESSION['agent_password_saved']);
include __DIR__ . '/nav.php';
include __DIR__ . '/sidebar.php';
?>
<main class="ad-main"><div class="ad-inner">
    <div class="ad-heading"><div><h1><?= $desk['password'] ?></h1>
        <p class="ad-subtitle"><?= agentHtml($agentAccount['company_name']) ?></p></div></div>
    <section class="ad-panel ad-password-panel">
        <h2><?= $text['agy_account'] ?></h2>
        <p class="ad-subtitle"><?= agentHtml($agentAccount['email']) ?></p>
        <?php if ($errorMessage): ?><p class="ad-message" role="alert"><?= agentHtml($errorMessage) ?></p><?php endif; ?>
        <?php if ($passwordSaved): ?><p class="ad-message ad-good" role="status"><?= $desk['passwordSaved'] ?></p><?php endif; ?>
        <form class="ad-form" method="post" action="reset_password">
            <input type="hidden" name="csrf" value="<?= agentHtml($_SESSION['agent_form_csrf']) ?>">
            <label><?= $desk['currentPass'] ?><input type="password" autocomplete="current-password" name="current_password" required></label>
            <label><?= $desk['newPass'] ?><input type="password" autocomplete="new-password" name="new_password" required aria-describedby="passHint">
                <small id="passHint"><?= $desk['passHint'] ?></small></label>
            <label><?= $desk['confirmPass'] ?><input type="password" autocomplete="new-password" name="confirm_password" required></label>
            <div class="ad-form-actions"><button class="ad-action" type="submit"><?= $desk['password'] ?></button></div>
        </form>
    </section>
</div></main>
<?php include __DIR__ . '/footer.php'; ?>
