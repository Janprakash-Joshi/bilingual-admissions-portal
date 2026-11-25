<?php
require_once __DIR__ . '/auth.php';
adminRequire('manage_applications');
header('Content-Type: application/json; charset=utf-8');
try {
    require __DIR__ . '/application_counts.php';
    echo json_encode(
        array_intersect_key($totals, array_flip(['total', 'accepted', 'pending', 'rejected', 'success'])),
    );
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => $adminWords['stats_error']], JSON_UNESCAPED_UNICODE);
}
