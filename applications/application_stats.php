<?php
$agentResponse = 'json';
require_once __DIR__ . '/agent_approval_guard.php';
require __DIR__ . '/agent_counts.php';
header('Cache-Control: no-store');
header('Content-Type: application/json; charset=UTF-8');
echo json_encode($agentCounts);
