<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$auth = $_SESSION['auth'] ?? [];
if (empty($auth['logged_in']) || ($auth['role'] ?? '') !== 'admin'
    || (empty($auth['permissions']['manage_agents']) && empty($auth['permissions']['full_control']))) {
    http_response_code(403);
    exit('Access denied');
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit;
}
if (empty($_SESSION['agency_admin_csrf']) || !is_string($_POST['csrf'] ?? null)
    || !hash_equals($_SESSION['agency_admin_csrf'], $_POST['csrf'])) {
    http_response_code(403);
    exit('Invalid session');
}

require_once __DIR__ . '/../db_connect.php';
require __DIR__ . '/../pages/agency-config.php';
require_once __DIR__ . '/../s3-config.php';
$lang = ($_SESSION['lang'] ?? 'eng') === 'ja' ? 'ja' : 'eng';
include __DIR__ . '/../lang/' . $lang . '_additions.php';
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
$conn->set_charset('utf8mb4');

$id = (int) ($_POST['agent_id'] ?? 0);
$schoolId = (int) ($_POST['school_id'] ?? 0);
$newKeys = [];
$commitStarted = false;
try {
    $conn->begin_transaction();
    $stmt = $conn->prepare('SELECT d.id FROM agency_registration_drafts d
        JOIN agency_school_partnerships p ON p.agent_id = d.submitted_agent_id
        WHERE d.submitted_agent_id = ? AND p.school_id = ? LIMIT 1 FOR UPDATE');
    $stmt->bind_param('ii', $id, $schoolId);
    $stmt->execute();
    $draft = $stmt->get_result()->fetch_assoc();
    $file = $_FILES['agreement'] ?? null;
    if (!$draft || !is_array($file) || $file['error'] !== UPLOAD_ERR_OK
        || !is_string($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])
        || $file['size'] <= 0 || $file['size'] > $agencyMaxFileBytes) {
        throw new DomainException('Invalid agreement');
    }
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
    if ($mime !== 'application/pdf' || strtolower(pathinfo($file['name'], PATHINFO_EXTENSION)) !== 'pdf') {
        throw new DomainException('PDF required');
    }

    $stored = agencyS3Key('agreement', bin2hex(random_bytes(24)) . '.pdf');
    $newKeys[] = $stored;
    agencyS3Put($stored, $file['tmp_name'], $mime);
    $draftId = (int) $draft['id'];
    $original = agencyOriginalName($file['name'], 'pdf');
    $bytes = (int) $file['size'];
    $stmt = $conn->prepare("INSERT INTO agency_registration_uploads
        (draft_id,agent_id,category,document_type,stored_name,original_name,mime_type,file_size,school_id,created_at)
        VALUES (?,?,'agreement','signed_agreement',?,?,?,?,?,UTC_TIMESTAMP())");
    $stmt->bind_param('iisssii', $draftId, $id, $stored, $original, $mime, $bytes, $schoolId);
    $stmt->execute();
    $commitStarted = true;
    $conn->commit();
    $_SESSION['agency_review_message'] = $text['agy_updated'];
} catch (Throwable $error) {
    try {
        $conn->rollback();
    } catch (Throwable $rollbackError) {
        agencyStorageLog('agreement rollback', $rollbackError);
    }
    if (!$commitStarted) {
        agencyS3Rollback($newKeys);
    } elseif ($newKeys) {
        error_log('Agent storage: verify database references before cleanup: ' . implode(', ', $newKeys));
    }
    agencyStorageLog('admin agreement', $error);
    $_SESSION['agency_upload_error'] = $text[$error instanceof DomainException ? 'agy_fileError' : 'agy_server'];
}
header('Location: agent_registrations.php?id=' . $id, true, 303);
exit;
