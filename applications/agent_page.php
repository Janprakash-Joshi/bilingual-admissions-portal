<?php
$agentResponse = $agentResponse ?? 'page';
require_once __DIR__ . '/agent_approval_guard.php';

if (in_array($_GET['lang'] ?? '', ['eng', 'ja'], true)) {
    $_SESSION['lang'] = $_GET['lang'];
}
$lang = ($_SESSION['lang'] ?? 'eng') === 'ja' ? 'ja' : 'eng';
require __DIR__ . '/../lang/' . $lang . '.php';
require __DIR__ . '/../lang/' . $lang . '.php';
$desk = require __DIR__ . '/../lang/' . $lang . '_agent.php';

function agentHtml($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function agentDate($value, string $lang): string
{
    if (!$value || str_starts_with($value, '0000-00-00')) {
        return '—';
    }
    $day = DateTimeImmutable::createFromFormat('!Y-m-d', substr($value, 0, 10));
    if (!$day) {
        return '—';
    }
    return $day->format($lang === 'ja' ? 'Y年n月j日' : 'd M Y');
}

$agentScreen = basename($_SERVER['SCRIPT_NAME'] ?? '', '.php');
$agentPageTitles = [
    'index' => 'dashboard', 'profile' => 'profile', 'reset_password' => 'password',
    'my_applications' => 'applications', 'applications' => 'apply', 'courses' => 'apply',
    'appointment-request' => 'appointments', 'event' => 'events',
    'media-gallery' => 'gallery', 'help-desk' => 'help',
];
$agentTitleKey = $agentPageTitles[$agentScreen] ?? 'portal';
$agentManagedScreen = in_array($agentScreen, ['index', 'profile', 'reset_password'], true);
$agentStatusKeys = [
    'Pending' => 'agy_pending', 'Under Review' => 'agy_under_review',
    'Approved' => 'agy_approved', 'Rejected' => 'agy_rejected',
];
