<?php
// Legacy forms can change their shared navigation without losing entered fields.
$agentResponse = 'json';
require __DIR__ . '/agent_page.php';
header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store');
echo json_encode(['lang' => $lang, 'labels' => $desk], JSON_UNESCAPED_UNICODE);
