<?php
require_once __DIR__ . '/auth.php';
adminRequire('manage_agents');
require_once __DIR__ . '/approval_email.php';
include __DIR__ . '/../lang/' . $lang . '_additions.php';
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

$statusKeys = [
    'Pending' => 'pending',
    'Under Review' => 'under_review',
    'Approved' => 'approved',
    'Rejected' => 'rejected',
];
$id = max(0, (int) ($_GET['id'] ?? 0));
$error = $_SESSION['agency_upload_error'] ?? '';
$message = $_SESSION['agency_review_message'] ?? '';
unset($_SESSION['agency_upload_error'], $_SESSION['agency_review_message']);

function adminDeliverApproval(mysqli $conn, int $id): void
{
    try {
        $notice = sendAgentApprovalNotice($conn, $id);
    } catch (Throwable $e) {
        error_log('Approval email result unavailable for agent ' . $id . ' (' . get_class($e) . ')');
        $notice = 'mail_unconfirmed';
    }
    adminFlash($notice, $notice === 'mail_sent' ? 'success' : 'warning');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    adminCheckPost();
    if (($_POST['action'] ?? '') === 'retry_mail') {
        $expected = $_SESSION['approval_resend_tokens'][$id] ?? null;
        $submitted = $_POST['resend_token'] ?? null;
        if (is_string($expected) && is_string($submitted) && hash_equals($expected, $submitted)) {
            unset($_SESSION['approval_resend_tokens'][$id]);
            adminDeliverApproval($conn, $id);
        }
        adminGo('agent_registrations.php?id=' . $id);
    }
    $schoolId = filter_var($_POST['school_id'] ?? null, FILTER_VALIDATE_INT, [
        'options' => ['min_range' => 0],
    ]);
    $status = $_POST['status'] ?? null;
    $note = is_string($_POST['note'] ?? null) ? trim($_POST['note']) : '';
    $adminId = (int) $_SESSION['auth']['id'];
    $sendApproval = false;
    if (
        !$id ||
        $schoolId === false ||
        !is_string($status) ||
        !isset($statusKeys[$status]) ||
        preg_match('/.{2001}/us', $note)
    ) {
        $error = $text['agy_error'];
    } elseif ($status === 'Rejected' && $note === '') {
        $error = $text['agy_reviewReason'];
    } else {
        try {
            $conn->begin_transaction();
            $stmt = $conn->prepare(
                'SELECT a.registration_status FROM agents a JOIN agency_registration_details d ON d.agent_id=a.id WHERE a.id=? FOR UPDATE',
            );
            $stmt->bind_param('i', $id);
            $stmt->execute();
            $prior = $stmt->get_result()->fetch_assoc();
            if (!$prior) {
                throw new RuntimeException('missing');
            }
            if ($schoolId) {
                $stmt = $conn->prepare(
                    'SELECT id FROM agency_school_partnerships WHERE agent_id=? AND school_id=? FOR UPDATE',
                );
                $stmt->bind_param('ii', $id, $schoolId);
                $stmt->execute();
                if (!$stmt->get_result()->fetch_assoc()) {
                    throw new RuntimeException('missing');
                }
                $stmt = $conn->prepare(
                    'UPDATE agency_school_partnerships SET status=?,reviewed_at=UTC_TIMESTAMP(),reviewed_by=?,review_note=? WHERE agent_id=? AND school_id=?',
                );
                $stmt->bind_param('sisii', $status, $adminId, $note, $id, $schoolId);
                $stmt->execute();
                if ($status !== 'Approved') {
                    $stmt = $conn->prepare("UPDATE agents SET registration_status='Under Review' WHERE id=? AND registration_status='Approved'
                        AND NOT EXISTS (SELECT 1 FROM agency_school_partnerships WHERE agent_id=? AND status='Approved')");
                    $stmt->bind_param('ii', $id, $id);
                    $stmt->execute();
                }
            } else {
                if ($status === 'Approved') {
                    $stmt = $conn->prepare(
                        "SELECT id FROM agency_school_partnerships WHERE agent_id=? AND status='Approved' LIMIT 1",
                    );
                    $stmt->bind_param('i', $id);
                    $stmt->execute();
                    if (!$stmt->get_result()->fetch_row()) {
                        throw new DomainException('school');
                    }
                }
                $stmt = $conn->prepare('UPDATE agents SET registration_status=? WHERE id=?');
                $stmt->bind_param('si', $status, $id);
                $stmt->execute();
                $stmt = $conn->prepare(
                    'UPDATE agency_registration_details SET reviewed_at=UTC_TIMESTAMP(),reviewed_by=?,review_note=? WHERE agent_id=?',
                );
                $stmt->bind_param('isi', $adminId, $note, $id);
                $stmt->execute();
            }
            $schoolValue = $schoolId ?: null;
            $stmt = $conn->prepare(
                'INSERT INTO agency_registration_reviews(agent_id,school_id,admin_id,status,note,created_at) VALUES (?,?,?,?,?,UTC_TIMESTAMP())',
            );
            $stmt->bind_param('iiiss', $id, $schoolValue, $adminId, $status, $note);
            $stmt->execute();
            $sendApproval = !$schoolId && $status === 'Approved' && $prior['registration_status'] !== 'Approved';
            $conn->commit();
        } catch (Throwable $e) {
            $conn->rollback();
            $error = $e instanceof DomainException ? $text['agy_approvalRule'] : $text['agy_server'];
            error_log('Agency review failed for agent ' . $id . ' (' . $e->getCode() . ')');
        }
        if ($error === '') {
            if ($sendApproval) {
                adminDeliverApproval($conn, $id);
            } else {
                $_SESSION['agency_review_message'] = $text['agy_updated'];
            }
            adminGo('agent_registrations.php?id=' . $id);
        }
    }
}
$agent = null;
$limit = 30;
$page = max(1, (int) ($_GET['page'] ?? 1));
$offset = ($page - 1) * $limit;
$filter = is_string($_GET['status'] ?? null) && isset($statusKeys[$_GET['status']]) ? $_GET['status'] : '';
if (!$id) {
    $stmt = $conn->prepare("SELECT a.id,a.company_name,a.email,a.registration_status,d.submitted_at
        FROM agents a JOIN agency_registration_details d ON d.agent_id=a.id
        WHERE (?='' OR a.registration_status=?) ORDER BY (a.registration_status='Pending') DESC,d.submitted_at DESC,a.id DESC LIMIT 31 OFFSET ?");
    $stmt->bind_param('ssi', $filter, $filter, $offset);
    $stmt->execute();
    $list = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
} else {
    $stmt = $conn->prepare(
        'SELECT id,company_name,country,city,year_established,email,contact_person,contact_number,registration_status FROM agents WHERE id=?',
    );
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $agent = $stmt->get_result()->fetch_assoc();
    $stmt = $conn->prepare('SELECT * FROM agency_registration_details WHERE agent_id=?');
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $details = $stmt->get_result()->fetch_assoc();
    if (!$agent || !$details) {
        http_response_code(404);
        exit(adminH($adminWords['missing']));
    }
    $stmt = $conn->prepare(
        'SELECT p.*,s.name_eng,s.name_ja FROM agency_school_partnerships p JOIN agency_registration_schools s ON s.id=p.school_id WHERE p.agent_id=?',
    );
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $partnerships = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt = $conn->prepare(
        'SELECT id,category,document_type,original_name,issue_date,expiry_date,school_id FROM agency_registration_uploads WHERE agent_id=? ORDER BY id',
    );
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $documents = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    if ($agent['registration_status'] === 'Approved') {
        // Consume this form token on use so a double click cannot resend twice.
        $_SESSION['approval_resend_tokens'][$id] ??= bin2hex(random_bytes(16));
    } else {
        unset($_SESSION['approval_resend_tokens'][$id]);
    }
}
require __DIR__ . '/topnav.php';
require __DIR__ . '/sidebar.php';
?>
<main class="admin-main-panel admin-review"><h1><?= adminH($text['agy_adminTitle']) ?></h1>
<?php if ($message): ?><p role="status" class="admin-notice"><?= adminH($message) ?></p><?php endif; ?>
<?php if ($error): ?><p role="alert" class="admin-error"><?= adminH($error) ?></p><?php endif; ?>
<?php if (!$agent): ?><section class="admin-panel">
<form class="admin-filters" method="get"><label><?= adminH(
    $text['agy_status'],
) ?><select name="status"><option value=""><?= adminH($adminWords['all']) ?></option>
<?php foreach ($statusKeys as $value => $k): ?><option value="<?= $value ?>" <?= $filter === $value
    ? 'selected'
    : '' ?>><?= adminH(
    $text['agy_' . $k],
) ?></option><?php endforeach; ?></select></label><button class="admin-primary"><?= adminH(
    $adminWords['filter'],
) ?></button></form>
<?php foreach (
    array_slice($list, 0, $limit)
    as $row
): ?><article class="admin-review-row"><div><strong><?= adminH(
    $row['company_name'],
) ?></strong><small><?= adminH($row['email']) ?></small></div><span class="admin-status"><?= adminH(
    $text['agy_' . $statusKeys[$row['registration_status']]],
) ?></span><a href="?id=<?= (int) $row['id'] ?>"><?= adminH(
    $text['agy_review'],
) ?> →</a></article><?php endforeach; ?>
<?php if (!$list): ?><p><?= adminH($text['agy_noRequests']) ?></p><?php endif; ?>
<nav class="admin-pages"><?php foreach (
    [[$page > 1, $page - 1, 'previous'], [count($list) > $limit, $page + 1, 'next']]
    as [$show, $number, $k]
):
    if ($show): ?><a href="?<?= adminH(
    http_build_query(['status' => $filter, 'page' => $number]),
) ?>"><?= adminH($adminWords[$k]) ?></a><?php endif;
endforeach; ?></nav></section>
<?php else: ?><a href="agent_registrations.php">← <?= $text[
    'agy_back_list'
] ?></a><section class="review-details"><h2><?= htmlspecialchars(
    $agent['company_name'],
) ?></h2><div class="field-grid">
<?php foreach (
    [
        'companyName' => 'company_name',
        'email' => 'email',
        'country' => 'country',
        'city' => 'city',
        'yearEstablished' => 'year_established',
        'contactPerson' => 'contact_person',
        'contactNumber' => 'contact_number',
    ]
    as $label => $key
): ?><p><strong><?= $text['agy_' . $label] ?></strong><br><?= htmlspecialchars(
    $agent[$key],
) ?></p><?php endforeach; ?>
<?php foreach (
    [
        'registrationNumber' => 'registration_number',
        'headOfficeAddress' => 'head_office_address',
        'postalCode' => 'postal_code',
        'localCompanyName' => 'local_company_name',
    ]
    as $label => $key
): ?><p><strong><?= $text['agy_' . $label] ?></strong><br><?= nl2br(
    adminH($details[$key]),
) ?></p><?php endforeach; ?></div></section>
<?php foreach (
    [
        'legal' => 'legal_representatives',
        'branches' => 'branch_offices',
        'contacts' => 'contact_persons',
        'links' => 'online_links',
    ]
    as $label => $column
): ?><section class="review-details"><h2><?= $text['agy_' . $label] ?></h2><?php foreach (
    json_decode($details[$column], true) ?? []
    as $row
): ?><div class="repeat-row"><?php foreach ($row as $key => $value): ?><p><strong><?= htmlspecialchars(
    $text['agy_' . $key] ?? $key,
) ?>:</strong> <?= htmlspecialchars(
    is_scalar($value) ? (string) $value : '',
) ?></p><?php endforeach; ?></div><?php endforeach; ?></section><?php endforeach; ?>
<section class="review-details"><h2><?= $text[
    'agy_documents'
] ?></h2><table class="review-table"><thead><tr><th><?= $text['agy_documentType'] ?></th><th><?= $text[
    'agy_file'
] ?></th><th><?= $text['agy_issueDate'] ?></th><th><?= $text[
    'agy_expiryDate'
] ?></th></tr></thead><tbody><?php foreach ($documents as $d): ?><tr><td><?= htmlspecialchars(
    $text['agy_' . $d['document_type']] ?? $d['document_type'],
) .
    ($d['school_id']
        ? ' #' . (int) $d['school_id']
        : '') ?></td><td><a href="agent_registration_file.php?id=<?= (int) $d['id'] ?>"><?= htmlspecialchars(
    $d['original_name'],
) ?></a></td><td><?= htmlspecialchars($d['issue_date'] ?? '—') ?></td><td><?= htmlspecialchars(
    $d['expiry_date'] ?? '—',
) ?></td></tr><?php endforeach; ?></tbody></table></section>
<?php foreach ($partnerships as $part): ?><section class="review-details"><h2><?= htmlspecialchars(
    $part['name_' . $lang],
) ?></h2><p><?= nl2br(adminH($part['request_note'])) ?></p><p><?= $text['agy_status'] ?>: <?= $text[
    'agy_' . $statusKeys[$part['status']]
] ?></p><form method="post" action="agent_registration_agreement.php" enctype="multipart/form-data" class="review-edit"><input type="hidden" name="csrf" value="<?= htmlspecialchars(
    $_SESSION['agency_admin_csrf'],
) ?>"><input type="hidden" name="agent_id" value="<?= $id ?>"><input type="hidden" name="school_id" value="<?= (int) $part[
    'school_id'
] ?>"><label><?= $text[
    'agy_agreement'
] ?><input type="file" name="agreement" accept=".pdf" required></label><button class="button secondary"><?= $text[
    'agy_update'
] ?></button></form><form method="post" class="review-edit"><input type="hidden" name="csrf" value="<?= htmlspecialchars(
    $_SESSION['agency_admin_csrf'],
) ?>"><input type="hidden" name="school_id" value="<?= (int) $part['school_id'] ?>"><label><?= $text[
    'agy_status'
] ?><select name="status"><?php foreach (
    $statusKeys
    as $status => $key
): ?><option value="<?= $status ?>" <?= $status === $part['status'] ? 'selected' : '' ?>><?= $text[
    'agy_' . $key
] ?></option><?php endforeach; ?></select></label><label><?= $text[
    'agy_note'
] ?><textarea name="note" rows="3" maxlength="2000"><?= htmlspecialchars(
    $part['review_note'] ?? '',
) ?></textarea></label><button class="button primary"><?= $text[
    'agy_update'
] ?></button></form></section><?php endforeach; ?>
<section class="review-details"><h2><?= $text['agy_accountStatus'] ?></h2><p><?= $text[
    'agy_approvalRule'
] ?></p><form method="post" class="review-edit"><input type="hidden" name="csrf" value="<?= htmlspecialchars(
    $_SESSION['agency_admin_csrf'],
) ?>"><input type="hidden" name="school_id" value="0"><label><?= $text[
    'agy_status'
] ?><select name="status"><?php foreach (
    $statusKeys
    as $status => $key
): ?><option value="<?= $status ?>" <?= $status === $agent['registration_status']
    ? 'selected'
    : '' ?>><?= $text['agy_' . $key] ?></option><?php endforeach; ?></select></label><label><?= $text[
    'agy_note'
] ?><textarea name="note" rows="3" maxlength="2000"><?= htmlspecialchars(
    $details['review_note'] ?? '',
) ?></textarea></label><button class="button primary"><?= $text['agy_update'] ?></button></form></section>
<?php if ($agent['registration_status'] === 'Approved'): ?>
<section class="review-details">
    <h2><?= adminH($adminWords['approval_email']) ?></h2>
    <form method="post" data-confirm="<?= adminH($adminWords['retry_confirm']) ?>">
        <?= adminCsrfField() ?>
        <input type="hidden" name="action" value="retry_mail">
        <input type="hidden" name="resend_token" value="<?= adminH($_SESSION['approval_resend_tokens'][$id]) ?>">
        <button class="admin-primary"><?= adminH($adminWords['retry']) ?></button>
    </form>
</section>
<?php endif; ?>
<?php endif; ?></main><?php require __DIR__ . '/footer.php'; ?>
