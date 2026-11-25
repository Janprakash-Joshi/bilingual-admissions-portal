<?php
require_once __DIR__ . '/auth.php';
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
$pending = null;
$recent = [];
$groups = [];
$totals = null;
if (can('manage_agents')) {
    try {
        $pending = (int) $conn
            ->query(
                "SELECT COUNT(*) FROM agents a JOIN agency_registration_details d ON d.agent_id=a.id WHERE a.registration_status='Pending'",
            )
            ->fetch_row()[0];
        $recent = $conn
            ->query(
                "SELECT a.id,a.company_name,a.email,d.submitted_at FROM agents a
            JOIN agency_registration_details d ON d.agent_id=a.id WHERE a.registration_status='Pending'
            ORDER BY d.submitted_at ASC,a.id LIMIT 4",
            )
            ->fetch_all(MYSQLI_ASSOC);
    } catch (mysqli_sql_exception $e) {
        error_log('Admin review overview database error ' . $e->getCode());
    }
}
if (can('manage_applications')) {
    try {
        require __DIR__ . '/application_counts.php';
        $queries = [
            'country' => "SELECT TRIM(COALESCE(a.country,'')) label,COUNT(*) total FROM applications a WHERE $eligible GROUP BY label",
            'course' => "SELECT COALESCE(NULLIF(TRIM(c.title),''),'') label,a.course_id,COUNT(*) total FROM applications a LEFT JOIN courses c ON c.id=a.course_id WHERE $eligible GROUP BY a.course_id,c.title",
            'level' => "SELECT TRIM(COALESCE(a.level,'')) label,COUNT(*) total FROM applications a WHERE $eligible GROUP BY label",
            'gender' => "SELECT CASE WHEN LOWER(TRIM(a.gender)) IN ('m','male') THEN 'Male' WHEN LOWER(TRIM(a.gender)) IN ('f','female') THEN 'Female' ELSE TRIM(COALESCE(a.gender,'')) END label,COUNT(*) total FROM applications a WHERE $eligible GROUP BY label",
        ];
        foreach ($queries as $key => $sql) {
            $groups[$key] = $conn->query($sql . ' ORDER BY total DESC,label ASC')->fetch_all(MYSQLI_ASSOC);
        }
    } catch (mysqli_sql_exception $e) {
        error_log('Admin application overview database error ' . $e->getCode());
    }
}
$countries = $lang === 'ja' ? require __DIR__ . '/../lang/ja_agent_countries.php' : [];
function adminStatusRing(array $totals): string
{
    if (!$totals['total']) {
        return '#dce5e9';
    }
    $pieces = [];
    $start = 0;
    foreach (
        ['accepted' => '#176864', 'pending' => '#d79843', 'rejected' => '#ab5360', 'success' => '#4c82ad']
        as $key => $color
    ) {
        $end = $start + (100 * $totals[$key]) / $totals['total'];
        if ($end > $start) {
            $pieces[] = sprintf('%s %.3F%% %.3F%%', $color, $start, $end);
        }
        $start = $end;
    }
    if ($start < 100) {
        $pieces[] = sprintf('#dce5e9 %.3F%% 100%%', $start);
    }
    return 'conic-gradient(' . implode(',', $pieces) . ')';
}
function adminPercent(int $part, int $total): string
{
    return $total ? round((100 * $part) / $total, 1) . '%' : '0%';
}
require __DIR__ . '/topnav.php';
require __DIR__ . '/sidebar.php';
?>
<main class="admin-main-panel"><h1><?= adminH($adminWords['overview']) ?></h1>
<?php if (can('manage_agents')): ?>
<section class="admin-review-head"><div><h2><?= adminH($adminWords['reviews']) ?></h2>
<p class="admin-review-count"><strong><?= $pending === null
    ? '—'
    : number_format($pending) ?></strong> <?= adminH($adminWords['awaiting']) ?></p>
<a class="admin-light-link" href="agent_registrations.php?status=Pending"><?= adminH(
    $adminWords['open_queue'],
) ?> →</a></div></section>
<?php if ($recent): ?><section class="admin-panel"><h2><?= adminH($adminWords['next_reviews']) ?></h2>
<?php foreach ($recent as $r): ?><article class="admin-review-row"><div><strong><?= adminH(
    $r['company_name'],
) ?></strong><small><?= adminH($r['email']) ?></small></div><time><?= adminH(
    substr($r['submitted_at'], 0, 10),
) ?></time><a href="agent_registrations.php?id=<?= (int) $r['id'] ?>"><?= adminH(
    $adminWords['review'],
) ?> →</a></article><?php endforeach; ?></section>
<?php elseif ($pending === null): ?><p class="admin-error"><?= adminH(
    $adminWords['registration_error'],
) ?></p><?php endif;endif; ?>
<?php if (can('manage_applications')): ?>
<h2 class="admin-section-title"><?= adminH($adminWords['applications_overview']) ?></h2>
<?php if (!is_array($totals)): ?><p class="admin-error"><?= adminH(
    $adminWords['stats_error'],
) ?></p><?php else: ?>
<div class="admin-metrics"><?php foreach (
    ['total', 'agent', 'admin']
    as $k
): ?><article><h3><?= adminH($adminWords['source_' . $k]) ?></h3><strong><?= number_format(
    $totals[$k],
) ?></strong>
<?php if ($k !== 'total'): ?><small><?= adminPercent($totals[$k], $totals['total']) ?></small><?php elseif (
    is_file(__DIR__ . '/applications.php')
): ?><a href="applications.php"><?= adminH(
    $adminWords['view_all'],
) ?> →</a><?php endif; ?></article><?php endforeach; ?></div>
<section class="admin-panel"><h2><?= adminH(
    $adminWords['by_status'],
) ?></h2><div class="admin-status-summary"><div class="admin-status-ring" aria-hidden="true" style="--ring:<?= adminH(
    adminStatusRing($totals),
) ?>"><strong><?= number_format($totals['total']) ?></strong></div><div class="admin-status-grid">
<?php foreach (
    ['accepted' => 'Accept', 'pending' => 'Pending', 'rejected' => 'Reject', 'success' => 'Success']
    as $k => $status
): ?>
<div><?php if (
    is_file(__DIR__ . '/applications.php')
): ?><a href="applications.php?status=<?= $status ?>"><?= adminH(
    $adminWords[$k],
) ?></a><?php else: ?><span><?= adminH($adminWords[$k]) ?></span><?php endif; ?><strong><?= number_format(
    $totals[$k],
) ?></strong></div>
<?php endforeach; ?></div></div>
<?php if (can('full_control') && is_file(__DIR__ . '/delete_rejected.php') && $totals['rejected']): ?>
<button type="button" class="admin-link admin-danger" id="deleteRejectedBtn"><?= adminH(
    $adminWords['delete_rejected'],
) ?></button><?php endif; ?></section>
<div class="admin-grid"><?php foreach (
    ['country', 'course', 'level', 'gender']
    as $group
): ?><section class="admin-panel admin-breakdown" data-breakdown>
<h2><?= adminH($adminWords['by_' . $group]) ?></h2>
<?php if (!isset($groups[$group])): ?><p><?= adminH($adminWords['stats_error']) ?></p>
<?php elseif (!$groups[$group]): ?><p><?= adminH($adminWords['empty']) ?></p><?php else: ?>
<?php if (
    in_array($group, ['country', 'course'], true)
): ?><label class="admin-search"><span class="sr-only"><?= adminH(
    $adminWords['search'],
) ?></span><input type="search" placeholder="<?= adminH($adminWords['search']) ?>"></label><?php endif; ?>
<ul><?php foreach ($groups[$group] as $row):

    $label = $row['label'];
    if ($label === '') {
        $label =
            $group === 'course' && !empty($row['course_id'])
                ? $adminWords['course'] . ' #' . (int) $row['course_id']
                : $adminWords['unspecified'];
    } elseif ($group === 'country') {
        $label = $countries[$label] ?? $label;
    } elseif ($group === 'gender' && in_array($label, ['Male', 'Female'], true)) {
        $label = $adminWords[strtolower($label)];
    }
    ?><li data-label="<?= adminH($label) ?>"><div><span><?= adminH($label) ?></span><strong><?= number_format(
    $row['total'],
) ?> <small><?= adminPercent((int) $row['total'], $totals['total']) ?></small></strong></div>
<meter min="0" max="<?= max(1, (int) $groups[$group][0]['total']) ?>" value="<?= (int) $row[
    'total'
] ?>" aria-label="<?= adminH($label) ?>"></meter></li><?php
endforeach; ?></ul>
<p data-no-match hidden><?= adminH(
    $adminWords['no_match'],
) ?></p><button class="admin-link" data-expand data-more="<?= adminH(
    $adminWords['view_all'],
) ?>" data-less="<?= adminH(
    $adminWords['less'],
) ?>" hidden></button><?php endif; ?></section><?php endforeach; ?></div>
<?php endif;endif; ?>
<?php if (!can('manage_agents') && !can('manage_applications')): ?><section class="admin-panel"><p><?= adminH(
    $adminWords['use_menu'],
) ?></p></section><?php endif; ?>
</main><?php require __DIR__ . '/footer.php'; ?>
