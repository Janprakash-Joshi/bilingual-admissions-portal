<?php
require_once __DIR__ . '/auth.php';
adminRequire('full_control');
adminCheckPost();
$name = is_string($_POST['username'] ?? null) ? trim($_POST['username']) : '';
$email = is_string($_POST['email'] ?? null) ? trim($_POST['email']) : '';
$password = $_POST['password'] ?? null;
if (
    $name === '' ||
    !filter_var($email, FILTER_VALIDATE_EMAIL) ||
    !is_string($password) ||
    strlen($password) < 6 ||
    strlen($password) > 72 ||
    str_contains($password, chr(0))
) {
    adminFlash('invalid', 'error');
    adminGo('users-roles.php');
}
$hash = password_hash($password, PASSWORD_DEFAULT);
$app = (int) isset($_POST['manage_applications']);
$agents = (int) isset($_POST['manage_agents']);
$courses = (int) isset($_POST['manage_courses']);
$users = (int) (isset($_POST['manage_users']) || isset($_POST['manage_appointments']));
$full = (int) isset($_POST['full_control']);
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
try {
    $stmt = $conn->prepare(
        'INSERT INTO admin(username,email,password,manage_applications,manage_agents,manage_courses,manage_users,full_control) VALUES (?,?,?,?,?,?,?,?)',
    );
    $stmt->bind_param('sssiiiii', $name, $email, $hash, $app, $agents, $courses, $users, $full);
    $stmt->execute();
    adminFlash('admin_added');
} catch (mysqli_sql_exception $e) {
    adminFlash($e->getCode() === 1062 ? 'duplicate' : 'failed', 'error');
}
adminGo('users-roles.php');
