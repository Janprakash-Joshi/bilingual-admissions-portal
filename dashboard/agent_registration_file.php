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
header('Cache-Control: private, no-store');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: no-referrer');
require_once __DIR__ . '/../db_connect.php';
session_write_close();
require __DIR__ . '/../pages/agency-config.php';
require_once __DIR__ . '/../s3-config.php';

$id = (int) ($_GET['id'] ?? 0);
$stmt = $conn->prepare('SELECT stored_name, original_name FROM agency_registration_uploads
    WHERE id=? AND agent_id IS NOT NULL');
$stmt->bind_param('i', $id);
$stmt->execute();
$file = $stmt->get_result()->fetch_assoc();
if (!$file) {
    http_response_code(404);
    exit;
}
$key = $file['stored_name'];
$extension = pathinfo($key, PATHINFO_EXTENSION);
$original = agencyOriginalName($file['original_name'], $extension);
if (agencyS3IsKey($key)) {
    try {
        $client = portalS3();
        $get = $client->getCommand('GetObject', [
            'Bucket' => PORTAL_S3_BUCKET,
            'Key' => $key,
            'ResponseContentType' => 'application/octet-stream',
            'ResponseCacheControl' => 'private, no-store',
            'ResponseContentDisposition' => 'attachment; filename="agency-document.' . $extension
                . '"; filename*=UTF-8' . "''" . rawurlencode($original),
        ]);
        $request = $client->createPresignedRequest($get, '+5 minutes');
        header('Location: ' . (string) $request->getUri(), true, 302);
    } catch (Throwable $error) {
        agencyStorageLog('download', $error);
        http_response_code(503);
        echo ($_SESSION['lang'] ?? 'eng') === 'ja'
            ? '書類を開けませんでした。しばらくしてからもう一度お試しください。'
            : 'The document could not be opened. Please try again.';
    }
    exit;
}

// Old records keep working during migration.
if (!preg_match('~\A[a-f0-9]{48}\.(?:pdf|jpg|jpeg|png)\z~', $key)) {
    http_response_code(404);
    exit;
}
$path = $agencyPrivatePath . '/' . $key;
if (!is_file($path) || is_link($path) || !is_readable($path)) {
    http_response_code(404);
    exit;
}
header('Content-Type: application/octet-stream');
header('Content-Disposition: attachment; filename="agency-document.' . $extension
    . '"; filename*=UTF-8' . "''" . rawurlencode($original));
header('Content-Length: ' . filesize($path));
readfile($path);
