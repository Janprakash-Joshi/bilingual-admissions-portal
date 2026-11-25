<?php
require_once __DIR__ . '/agent_page.php';
require __DIR__ . '/agent_counts.php';
require __DIR__ . '/agent_school_list.php';

$recentQuery = $conn->prepare("SELECT id, first_name, middle_name, last_name, program, status, created_at
    FROM applications WHERE agent_id = ? ORDER BY created_at DESC, id DESC LIMIT 5");
$recentQuery->bind_param('i', $agentId);
$recentQuery->execute();
$recentApplications = $recentQuery->get_result()->fetch_all(MYSQLI_ASSOC);
$recentQuery->close();

$statusFilters = ['pending' => 'Pending', 'accepted' => 'Accept', 'success' => 'Success', 'rejected' => 'Reject'];
$otherCount = $agentCounts['total'] - array_sum(array_intersect_key($agentCounts, $statusFilters));
if ($otherCount > 0) {
    $agentCounts['other'] = $otherCount;
    $statusFilters['other'] = '';
}
$segments = [];
$end = 0;
foreach ($statusFilters as $key => $filter) {
    if ($agentCounts[$key] > 0) {
        $start = $end;
        $end += 100 * $agentCounts[$key] / $agentCounts['total'];
        $segments[] = 'var(--ad-' . $key . ') ' . round($start, 3) . '% ' . round($end, 3) . '%';
    }
}
$ringStyle = $segments ? 'conic-gradient(' . implode(', ', $segments) . ')' : 'var(--ad-line)';
$approvedSchools = count(array_filter($agentSchools, fn($school) => $school['status'] === 'Approved'));
include __DIR__ . '/nav.php';
include __DIR__ . '/sidebar.php';
?>
<main class="ad-main">
    <div class="ad-inner">
        <div class="ad-heading">
            <div><h1><?= $desk['dashboard'] ?></h1><p class="ad-subtitle"><?= agentHtml($agentAccount['company_name']) ?></p></div>
            <a class="ad-light-link" href="profile"><?= $desk['profile'] ?>
                <svg class="ad-icon" aria-hidden="true"><use href="./img/agent-icons.svg#arrow"></use></svg>
            </a>
        </div>
        <a id="globalNotifBox" class="ad-notice" href="my_applications" hidden>
            <svg class="ad-icon" aria-hidden="true"><use href="./img/agent-icons.svg#bell"></use></svg>
            <span><strong><?= $desk['attention'] ?></strong><small><?= $desk['checkApps'] ?></small></span>
            <span aria-hidden="true">→</span>
        </a>

        <div class="ad-overview">
            <section class="ad-panel ad-stats">
                <h2><?= $desk['overview'] ?></h2>
                <div class="ad-breakdown">
                    <a class="ad-ring" href="my_applications" style="--ad-chart: <?= agentHtml($ringStyle) ?>">
                        <span class="ad-ring-label"><strong><?= number_format($agentCounts['total']) ?></strong><span><?= $desk['total'] ?></span></span>
                    </a>
                    <ul class="ad-legend">
                        <?php foreach ($statusFilters as $key => $filter): ?>
                            <li>
                                <?php if ($filter !== ''): ?><a href="my_applications?status=<?= rawurlencode($filter) ?>"><?php else: ?><span class="ad-legend-row"><?php endif; ?>
                                    <i class="ad-dot tone-<?= $key ?>" aria-hidden="true"></i>
                                    <span><?= $desk[$key] ?></span><strong><?= number_format($agentCounts[$key]) ?></strong>
                                <?php if ($filter !== ''): ?></a><?php else: ?></span><?php endif; ?>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </section>

            <section class="ad-school-card">
                <h2><?= $text['agy_schools'] ?></h2>
                <div class="ad-school-summary">
                    <strong><?= number_format($approvedSchools) ?></strong><span><?= $desk['approvedSchools'] ?></span>
                    <small><?php if ($approvedSchools === 1): ?><?= agentHtml($agentSchools[0]['name_' . $lang] ?: $agentSchools[0]['name_eng']) ?><br><?php endif; ?>
                        <?= $desk['schoolRequests'] ?> · <?= count($agentSchools) ?></small>
                </div>
                <a href="profile#partnerships"><?= $desk['viewSchools'] ?><span aria-hidden="true">→</span></a>
            </section>
        </div>

        <section class="ad-panel ad-recent">
            <div class="ad-section-head"><h2><?= $desk['recent'] ?></h2>
                <a class="ad-light-link" href="my_applications"><?= $desk['allApps'] ?><span aria-hidden="true">→</span></a>
            </div>
            <?php if (!$recentApplications): ?>
                <p class="ad-empty"><?= $desk['noApps'] ?></p>
            <?php else: ?>
                <div class="ad-table-wrap">
                    <table class="ad-table">
                        <thead><tr><th scope="col"><?= $desk['applicant'] ?></th><th scope="col"><?= $desk['program'] ?></th>
                            <th scope="col"><?= $desk['submitted'] ?></th><th scope="col"><?= $text['agy_status'] ?></th></tr></thead>
                        <tbody>
                        <?php foreach ($recentApplications as $item):
                            $name = trim(implode(' ', array_filter([$item['first_name'], $item['middle_name'], $item['last_name']], fn($part) => $part !== null && $part !== '')));
                            $statusKey = array_search($item['status'], $statusFilters, true);
                            $statusLabel = $statusKey ? $desk[$statusKey] : ($item['status'] ?: $desk['unknownStatus']);
                        ?>
                            <tr>
                                <td><strong><?= agentHtml($name ?: '—') ?></strong><small><?= $desk['applicationId'] ?> · <?= agentHtml($item['id']) ?><span class="ad-mobile-date"><?= agentDate($item['created_at'], $lang) ?></span></small></td>
                                <td><?= agentHtml($item['program'] ?: '—') ?></td><td><?= agentDate($item['created_at'], $lang) ?></td>
                                <td><span class="ad-badge tone-<?= $statusKey ?: 'other' ?>"><?= agentHtml($statusLabel) ?></span></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </section>
    </div>
</main>
<?php include __DIR__ . '/footer.php'; ?>
