<?php
// Shared by the dashboard, its data endpoints and the existing per-school guard.
require_once __DIR__ . '/../db_connect.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$agentAuth = $_SESSION['auth'] ?? [];
$agentId = (int) ($agentAuth['id'] ?? 0);
$agentRole = $agentAuth['role'] ?? '';
$agentLoggedIn = !empty($agentAuth['logged_in']) && $agentId > 0;

// The earlier school guard can also be used in an authenticated admin workflow.
if ($agentLoggedIn && $agentRole === 'admin' && !isset($agentResponse)) {
    return;
}

if ($agentLoggedIn && $agentRole === 'agent') {
    $conn->set_charset('utf8mb4');
    $agentQuery = $conn->prepare("SELECT id, company_name, country, city, year_established,
        email, contact_person, contact_number, registration_status
        FROM agents WHERE id = ? AND registration_status = 'Approved'");
    $agentQuery->bind_param('i', $agentId);
    $agentQuery->execute();
    $agentAccount = $agentQuery->get_result()->fetch_assoc();
    $agentQuery->close();
    if ($agentAccount) {
        return;
    }
    unset($_SESSION['auth']);
}

if (($agentResponse ?? 'page') === 'page') {
    $destination = $agentLoggedIn && $agentRole === 'admin' ? '../dashboard/' : '../index';
    header('Location: ' . $destination);
    exit;
}

http_response_code($agentLoggedIn ? 403 : 401);
header('Cache-Control: no-store');
if ($agentResponse === 'flag') {
    header('Content-Type: text/plain; charset=UTF-8');
    exit('0');
}
header('Content-Type: application/json; charset=UTF-8');
exit('{"error":"access_denied"}');
