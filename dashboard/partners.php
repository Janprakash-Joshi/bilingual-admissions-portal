<?php
require_once __DIR__ . '/auth.php';
adminRequire('manage_agents');
include __DIR__ . '/../lang/' . $lang . '_additions.php';
$name = is_string($_GET['name'] ?? null) ? trim($_GET['name']) : '';
$country = is_string($_GET['country'] ?? null) ? trim($_GET['country']) : '';
$companyLike = '%' . $name . '%';
$countryLike = '%' . $country . '%';
$page = max(1, (int) ($_GET['page'] ?? 1));
$offset = ($page - 1) * 30;
$stmt = $conn->prepare('SELECT a.id,a.company_name,a.country,a.city,a.email,a.contact_person,a.contact_number,a.registration_status,d.agent_id AS submission
    FROM agents a LEFT JOIN agency_registration_details d ON d.agent_id=a.id
    WHERE a.company_name LIKE ? AND COALESCE(a.country,\'\') LIKE ? ORDER BY a.id DESC LIMIT 31 OFFSET ?');
$stmt->bind_param('ssi', $companyLike, $countryLike, $offset);
$stmt->execute();
$partners = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$more = count($partners) > 30;
$statusKeys = [
    'Pending' => 'agy_pending',
    'Under Review' => 'agy_under_review',
    'Approved' => 'agy_approved',
    'Rejected' => 'agy_rejected',
];
require __DIR__ . '/topnav.php';
require __DIR__ . '/sidebar.php';
?>
<main class="admin-main-panel"><div class="admin-heading"><h1><?= adminH(
    $adminWords['partners'],
) ?></h1><a href="agent_registrations.php" class="admin-primary"><?= adminH(
    $adminWords['reviews'],
) ?> →</a></div>
<?php if (
    is_file(__DIR__ . '/../pages/registration.php')
): ?><p><a href="../pages/registration.php"><?= adminH(
    $adminWords['register_agency'],
) ?></a></p><?php endif; ?>
<section class="admin-panel"><form class="admin-filters" method="get">
<label><?= adminH($text['agy_companyName']) ?><input name="name" value="<?= adminH($name) ?>"></label>
<label><?= adminH($text['agy_country']) ?><input name="country" value="<?= adminH($country) ?>"></label>
<button class="admin-primary"><?= adminH($adminWords['filter']) ?></button><a href="partners.php"><?= adminH(
    $adminWords['clear'],
) ?></a></form>
<div class="admin-scroll"><table class="admin-table"><thead><tr><?php foreach (
    ['companyName', 'country', 'city', 'email', 'contactPerson', 'contactNumber', 'status']
    as $k
): ?><th><?= adminH($text['agy_' . $k]) ?></th><?php endforeach; ?><th><?= adminH(
    $adminWords['action'],
) ?></th></tr></thead>
<tbody><?php foreach (array_slice($partners, 0, 30) as $p): ?><tr><th scope="row"><?= adminH(
    $p['company_name'],
) ?></th><?php foreach (
    ['country', 'city', 'email', 'contact_person', 'contact_number']
    as $k
): ?><td><?= adminH($p[$k]) ?></td><?php endforeach; ?>
<td><?= adminH($text[$statusKeys[$p['registration_status']]] ?? $p['registration_status']) ?></td><td>
<?php if ($p['submission']): ?><a href="agent_registrations.php?id=<?= (int) $p['id'] ?>"><?= adminH(
    $text['agy_review'],
) ?></a><?php endif; ?>
<?php if (is_file(__DIR__ . '/agent_profile.php')): ?><a href="agent_profile.php?id=<?= (int) $p[
    'id'
] ?>"><?= adminH(
    $adminWords['profile'],
) ?></a><?php endif; ?></td></tr><?php endforeach; ?></tbody></table></div>
<?php if (!$partners): ?><p><?= adminH($adminWords['no_partners']) ?></p><?php endif; ?>
<nav class="admin-pages"><?php foreach (
    [[$page > 1, $page - 1, 'previous'], [$more, $page + 1, 'next']]
    as [$show, $number, $label]
):
    if ($show): ?>
<a href="?<?= adminH(
    http_build_query(['name' => $name, 'country' => $country, 'page' => $number]),
) ?>"><?= adminH($adminWords[$label]) ?></a><?php endif;
endforeach; ?></nav></section></main><?php require __DIR__ . '/footer.php'; ?>
