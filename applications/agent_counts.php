<?php
if (!isset($agentAccount)) {
    http_response_code(404);
    exit;
}
// Use the same counts for the dashboard and the existing application_stats endpoint.
$countsQuery = $conn->prepare("SELECT COUNT(*) AS total,
    COUNT(CASE WHEN status = 'Accept' THEN 1 END) AS accepted,
    COUNT(CASE WHEN status = 'Pending' THEN 1 END) AS pending,
    COUNT(CASE WHEN status = 'Reject' THEN 1 END) AS rejected,
    COUNT(CASE WHEN status = 'Success' THEN 1 END) AS success
    FROM applications WHERE agent_id = ?");
$countsQuery->bind_param('i', $agentId);
$countsQuery->execute();
$agentCounts = array_map('intval', $countsQuery->get_result()->fetch_assoc());
$countsQuery->close();
