<?php
require_once __DIR__ . '/auth.php';
adminRequire('full_control');
adminCheckPost();
$id = (int) ($_POST['id'] ?? 0);
if ($id < 1 || $id === (int) $_SESSION['auth']['id']) {
    adminFlash('denied', 'error');
    adminGo('users-roles.php');
}
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
try {
    $conn->begin_transaction();
    $admins = $conn
        ->query('SELECT id,full_control FROM admin ORDER BY id FOR UPDATE')
        ->fetch_all(MYSQLI_ASSOC);
    $full = array_column(array_filter($admins, fn($row) => (bool) $row['full_control']), 'id');
    if (!in_array((int) $_SESSION['auth']['id'], array_map('intval', $full), true)) {
        throw new RuntimeException();
    }
    $stmt = $conn->prepare('DELETE FROM admin WHERE id=?');
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $conn->commit();
    adminFlash('saved');
} catch (Throwable $e) {
    $conn->rollback();
    adminFlash('failed', 'error');
}
adminGo('users-roles.php');
