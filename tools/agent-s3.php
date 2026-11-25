<?php
// Run from a terminal. This file never serves browser requests.
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
require_once __DIR__ . '/../s3-config.php';
$command = $argv[1] ?? '';
if ($command === 'check' && $argc === 2) {
    $key = agencyS3Key('company', bin2hex(random_bytes(24)) . '.pdf');
    $temp = tempnam(sys_get_temp_dir(), 's3-check-');
    $sent = false;
    $failed = false;
    try {
        $sample = "%PDF-1.4\nPortal storage check\n%%EOF\n";
        if (!$temp || file_put_contents($temp, $sample) !== strlen($sample)) {
            throw new RuntimeException('Cannot create test file');
        }
        $sent = true;
        agencyS3Put($key, $temp, 'application/pdf');
        $object = portalS3()->getObject(['Bucket' => PORTAL_S3_BUCKET, 'Key' => $key]);
        if ((string) $object['Body'] !== $sample) {
            throw new RuntimeException('Read-back mismatch');
        }
        echo "S3 upload and read-back passed.\n";
    } catch (Throwable $error) {
        agencyStorageLog('connection check', $error);
        $failed = true;
    } finally {
        if ($sent) {
            try {
                agencyS3Delete($key);
                echo "S3 delete request succeeded.\n";
            } catch (Throwable $error) {
                agencyStorageLog('check cleanup ' . $key, $error);
                $failed = true;
            }
        }
        if ($temp && is_file($temp)) {
            unlink($temp);
        }
    }
    exit($failed ? 1 : 0);
}
if ($command !== 'migrate' || $argc > 3 || ($argc === 3 && $argv[2] !== '--apply')) {
    fwrite(STDERR, "Usage: php tools/agent-s3.php check\n"
        . "       php tools/agent-s3.php migrate [--apply]\n");
    exit(2);
}

require __DIR__ . '/../db_connect.php';
require __DIR__ . '/../pages/agency-config.php';
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
$apply = $argc === 3;
$after = 0;
$count = 0;
$failed = 0;
echo $apply ? "Copying and verifying legacy files.\n" : "Dry run. Checking local records only.\n";

do {
    $list = $conn->prepare("SELECT id FROM agency_registration_uploads
        WHERE id > ? AND stored_name NOT LIKE 'agent-files/%' ORDER BY id LIMIT 100");
    $list->bind_param('i', $after);
    $list->execute();
    $rows = $list->get_result()->fetch_all(MYSQLI_ASSOC);
    foreach ($rows as $row) {
        $after = (int) $row['id'];
        try {
            if ($apply) {
                $conn->begin_transaction();
            }
            $query = 'SELECT stored_name, category, mime_type, file_size FROM agency_registration_uploads WHERE id=?';
            $read = $conn->prepare($apply ? $query . ' FOR UPDATE' : $query);
            $read->bind_param('i', $after);
            $read->execute();
            $file = $read->get_result()->fetch_assoc();
            if (!$file || agencyS3IsKey($file['stored_name'])) {
                if ($apply) $conn->rollback();
                continue;
            }
            $oldName = $file['stored_name'];
            $key = agencyS3Key($file['category'], $oldName);
            $source = $agencyPrivatePath . '/' . $oldName;
            if (!is_file($source) || is_link($source) || !is_readable($source)
                || filesize($source) !== (int) $file['file_size'] || filesize($source) > $agencyMaxFileBytes) {
                throw new RuntimeException('Legacy file missing or size mismatch');
            }
            if ($apply) {
                agencyS3Put($key, $source, $file['mime_type']);
                $copy = portalS3()->getObject(['Bucket' => PORTAL_S3_BUCKET, 'Key' => $key]);
                if (hash('sha256', (string) $copy['Body']) !== hash_file('sha256', $source)) {
                    throw new RuntimeException('S3 verification failed');
                }
                $save = $conn->prepare('UPDATE agency_registration_uploads SET stored_name=? WHERE id=? AND stored_name=?');
                $save->bind_param('sis', $key, $after, $oldName);
                $save->execute();
                if ($save->affected_rows !== 1) {
                    throw new RuntimeException('Reference update failed');
                }
                $conn->commit();
            }
            ++$count;
            echo ($apply ? 'Migrated' : 'Ready') . " row $after\n";
        } catch (Throwable $error) {
            if ($apply) {
                try { $conn->rollback(); } catch (Throwable $rollbackError) { agencyStorageLog('migration rollback', $rollbackError); }
            }
            ++$failed;
            agencyStorageLog('migration row ' . $after, $error);
            // The original is retained. A retry uses the same object key for this row.
        }
    }
} while (count($rows) === 100);
echo "$count ready or migrated, $failed failed. Local originals retained.\n";
exit($failed ? 1 : 0);
