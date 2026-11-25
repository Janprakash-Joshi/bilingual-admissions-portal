<?php
require_once __DIR__ . '/auth.php';
adminRequire('manage_applications');

// A positive agent_id identifies an agent submission.
$isAgent = 'COALESCE(a.agent_id, 0) > 0';

// Admin submissions have neither an agent nor a student submitter.
$isAdmin = '(COALESCE(a.agent_id, 0) = 0 AND COALESCE(a.student_id, 0) = 0)';

// Agent submissions need both documents. Admin submissions count immediately.
$hasDocuments = "(
    TRIM(COALESCE(a.passport_image, '')) <> ''
    AND TRIM(COALESCE(a.academic_image, '')) <> ''
)";

$eligible = "($isAdmin OR ($isAgent AND $hasDocuments))";

$sql = "
    SELECT
        COUNT(*) AS total,
        COALESCE(SUM($isAgent), 0) AS agent,
        COALESCE(SUM($isAdmin), 0) AS admin,
        COALESCE(SUM(a.status = 'Accept'), 0) AS accepted,
        COALESCE(SUM(a.status = 'Pending'), 0) AS pending,
        COALESCE(SUM(a.status = 'Reject'), 0) AS rejected,
        COALESCE(SUM(a.status = 'Success'), 0) AS success
    FROM applications a
    WHERE $eligible
";

$totals = $conn->query($sql)->fetch_assoc();
$totals = array_map('intval', $totals);