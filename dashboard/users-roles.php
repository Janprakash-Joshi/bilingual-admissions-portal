<?php
require_once __DIR__ . '/auth.php';
adminRequire('full_control');
$perms = ['manage_applications', 'manage_agents', 'manage_courses', 'manage_users', 'full_control'];
$admins = $conn
    ->query(
        'SELECT id,username,email,manage_applications,manage_agents,manage_courses,manage_users,full_control FROM admin ORDER BY username,id',
    )
    ->fetch_all(MYSQLI_ASSOC);
require __DIR__ . '/topnav.php';
require __DIR__ . '/sidebar.php';
?>
<main class="admin-main-panel"><h1><?= adminH($adminWords['users']) ?></h1>
<section class="admin-panel"><h2><?= adminH(
    $adminWords['admins'],
) ?></h2><div class="admin-scroll"><table class="admin-table">
<thead><tr><?php foreach (['name', 'email', 'permissions', 'action'] as $key): ?><th><?= adminH(
    $adminWords[$key],
) ?></th><?php endforeach; ?></tr></thead>
<tbody><?php foreach ($admins as $row): ?><tr><th scope="row"><?= adminH(
    $row['username'],
) ?></th><td><?= adminH($row['email']) ?></td><td>
<?= adminH(
    implode(' / ', array_map(fn($k) => $adminWords[$k], array_filter($perms, fn($p) => !empty($row[$p])))),
) ?></td>
<td><?php if (
    (int) $row['id'] !== (int) $_SESSION['auth']['id']
): ?><form method="post" action="delete_admin.php" data-confirm="<?= adminH(
    $adminWords['delete_admin_confirm'],
) ?>"><?= adminCsrfField() ?><input type="hidden" name="id" value="<?= (int) $row[
    'id'
] ?>"><button class="admin-link"><?= adminH(
    $adminWords['delete'],
) ?></button></form><?php endif; ?></td></tr><?php endforeach; ?></tbody></table></div></section>
<section class="admin-panel admin-form-panel"><h2><?= adminH($adminWords['create_admin']) ?></h2>
<form method="post" action="save_admin.php" class="admin-form"><?= adminCsrfField() ?>
<label><?= adminH($adminWords['name']) ?><input name="username" required></label>
<label><?= adminH($adminWords['email']) ?><input name="email" type="email" required></label>
<label><?= adminH(
    $adminWords['new_password'],
) ?><input name="password" type="password" minlength="6" required autocomplete="new-password"></label>
<fieldset><legend><?= adminH($adminWords['permissions']) ?></legend><?php foreach (
    $perms
    as $p
): ?><label class="admin-check"><input type="checkbox" name="<?= $p ?>"> <?= adminH(
    $adminWords[$p],
) ?></label><?php endforeach; ?></fieldset>
<button class="admin-primary"><?= adminH($adminWords['create_admin']) ?></button></form></section></main>
<?php require __DIR__ . '/footer.php'; ?>
