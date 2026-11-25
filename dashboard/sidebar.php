<?php require_once __DIR__ . '/auth.php';
$links = [
    ['index', 'home', ''],
    ['agent_registrations', 'reviews', 'manage_agents'],
    ['partners', 'partners', 'manage_agents'],
    ['applications', 'applications', 'manage_applications'],
    ['apply', 'apply', 'manage_applications'],
    ['agentchange', 'application_control', 'manage_applications'],
    ['edit_course_list', 'courses', 'manage_courses'],
    ['appointments', 'appointments', 'manage_users'],
    ['users-roles', 'users', 'full_control'],
    ['event', 'events', ''],
    ['media-gallery', 'gallery', ''],
    ['help-desk', 'help', ''],
    ['profile', 'profile', ''],
    ['reset_password', 'password', ''],
    ['logout', 'logout', ''],
];
?>
<aside id="sidebar" class="sidebar admin-sidebar">
<a href="./" class="admin-brand"><img src="../img/logo.png" alt="<?= adminH($adminWords['portal']) ?>"></a>
<nav aria-label="<?= adminH($adminWords['menu']) ?>">
<?php foreach ($links as [$path, $key, $perm]):
    if (($perm && !can($perm)) || !is_file(__DIR__ . '/' . $path . '.php')) {
        continue;
    } ?>
<a href="<?= $path ?>.php" <?= ($screen ?? '') === $path ? 'aria-current="page"' : '' ?>><?= adminH(
    $adminWords[$key],
) ?></a>
<?php
endforeach; ?></nav></aside>
<button class="admin-scrim" hidden type="button" aria-label="<?= adminH($adminWords['close']) ?>"></button>
