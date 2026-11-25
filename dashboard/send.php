<?php
// Existing document and remarks POST endpoint. Approval delivery uses the root send.php.
require_once __DIR__ . '/auth.php';
adminRequire('manage_applications');
// Older document forms have no hidden token until the shared footer runs.
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
$scheme = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' ? 'https' : 'http';
$ownOrigin = $scheme . '://' . ($_SERVER['HTTP_HOST'] ?? '');
$legacyForm =
    $_SERVER['REQUEST_METHOD'] === 'POST' &&
    !isset($_POST['csrf']) &&
    $origin !== '' &&
    strcasecmp($origin, $ownOrigin) === 0;
if (!$legacyForm) {
    adminCheckPost();
}
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
$columns = [
    'app' => 'application_url',
    'cal' => 'cal_url',
    'ass' => 'assessment_url',
    'final' => 'FinalAccept_url',
    'pay' => 'payment_url',
    'acco' => 'Accommodation_url',
    'visa' => 'visa_url',
    'travel' => 'travel',
    'courseComplete' => 'course_complete_url',
];
$id = filter_var($_POST['app_id'] ?? ($_POST['remarks_id'] ?? null), FILTER_VALIDATE_INT, [
    'options' => ['min_range' => 1],
]);
$isDocument = array_key_exists('doc_type', $_POST);
$type = $_POST['doc_type'] ?? '';
$value = $isDocument ? $_POST['file_path'] ?? null : $_POST['remarks'] ?? null;
if (
    !$id ||
    !is_string($value) ||
    ($isDocument && (!is_string($type) || !isset($columns[$type]) || trim($value) === ''))
) {
    http_response_code(422);
    exit(adminH($adminWords['invalid']));
}
if ($isDocument) {
    $scheme = parse_url(trim($value), PHP_URL_SCHEME);
    if ($scheme === false || ($scheme !== null && !in_array(strtolower($scheme), ['http', 'https'], true))) {
        http_response_code(422);
        exit(adminH($adminWords['invalid']));
    }
}
try {
    $conn->begin_transaction();
    $check = $conn->prepare('SELECT id FROM applications WHERE id=? FOR UPDATE');
    $check->bind_param('i', $id);
    $check->execute();
    if (!$check->get_result()->fetch_row()) {
        throw new DomainException('missing');
    }
    if ($isDocument) {
        $column = $columns[$type];
        $find = $conn->prepare('SELECT id FROM documents WHERE app_id=? LIMIT 1');
        $find->bind_param('i', $id);
        $find->execute();
        if ($find->get_result()->fetch_row()) {
            $stmt = $conn->prepare("UPDATE documents SET $column=? WHERE app_id=?");
            $stmt->bind_param('si', $value, $id);
        } else {
            $stmt = $conn->prepare("INSERT INTO documents(app_id,$column) VALUES (?,?)");
            $stmt->bind_param('is', $id, $value);
        }
        $stmt->execute();
    } else {
        $stmt = $conn->prepare('UPDATE applications SET remarks=? WHERE id=?');
        $stmt->bind_param('si', $value, $id);
        $stmt->execute();
        $stmt = $conn->prepare('INSERT INTO student_notes(application_id,note,is_read) VALUES (?,?,0)
            ON DUPLICATE KEY UPDATE note=VALUES(note),is_read=0');
        $stmt->bind_param('is', $id, $value);
        $stmt->execute();
    }
    $conn->commit();
} catch (Throwable $err) {
    $conn->rollback();
    http_response_code($err instanceof DomainException ? 404 : 500);
    exit(adminH($adminWords['failed']));
}
adminGo('viewApplication?id=' . $id);
