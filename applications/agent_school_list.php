<?php
if (!isset($agentAccount)) {
    http_response_code(404);
    exit;
}
// Both the dashboard summary and the profile use the same school records.
$schoolQuery = $conn->prepare("SELECT p.school_id, s.name_eng, s.name_ja,
    p.status, p.request_note, p.reviewed_at
    FROM agency_school_partnerships p
    JOIN agency_registration_schools s ON s.id = p.school_id
    WHERE p.agent_id = ?
    ORDER BY (p.status = 'Approved') DESC, s.name_eng, p.school_id");
$schoolQuery->bind_param('i', $agentId);
$schoolQuery->execute();
$agentSchools = $schoolQuery->get_result()->fetch_all(MYSQLI_ASSOC);
$schoolQuery->close();
