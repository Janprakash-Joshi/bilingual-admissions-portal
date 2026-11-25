<?php
$agentResponse = 'flag';
require_once __DIR__ . '/agent_approval_guard.php';
header('Content-Type: text/plain; charset=UTF-8');
header('Cache-Control: no-store');

$noticeQuery = $conn->prepare("SELECT 1 FROM applications a
    WHERE a.agent_id = ? AND (
        COALESCE(a.passport_image, '') = '' OR COALESCE(a.academic_image, '') = ''
        OR EXISTS (SELECT 1 FROM student_notes n WHERE n.application_id = a.id AND n.is_read = 0)
    ) LIMIT 1");
$noticeQuery->bind_param('i', $agentId);
$noticeQuery->execute();
echo $noticeQuery->get_result()->num_rows ? '1' : '0';
