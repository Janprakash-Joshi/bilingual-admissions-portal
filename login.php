<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once "db_connect.php";
require_once "auth1.php";
$lang = in_array($_SESSION["lang"] ?? "eng", ["eng", "ja"], true)
    ? $_SESSION["lang"] ?? "eng"
    : "eng";
include __DIR__ . "/lang/" . $lang . ".php";
if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Location: index.php");
    exit();
}
$email = is_string($_POST["email"] ?? null) ? trim($_POST["email"]) : "";
// Passwords are not trimmed: spaces may be intentional.
$password = is_string($_POST["password"] ?? null) ? $_POST["password"] : "";
$role = is_string($_POST["role"] ?? null) ? trim($_POST["role"]) : "";
if ($email === "" || $password === "" || $role === "") {
    header("Location: index.php");
    exit();
}
$tables = ["agent" => "agents", "admin" => "admin"];
if (!array_key_exists($role, $tables)) {
    header("Location: index.php");
    exit();
}
$stmt = $conn->prepare("SELECT * FROM {$tables[$role]} WHERE email=?");
$stmt->bind_param("s", $email);
$stmt->execute();
$result = $stmt->get_result();
if ($result->num_rows === 0) {
    if ($role === "admin") {
        triggerFailedLoginAlert();
    }
    echo "<script>alert(" .
        json_encode(
            $text["agy_userMissing"],
            JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT,
        ) .
        ');window.location.href="index.php";</script>';
    exit();
}
$user = $result->fetch_assoc();
if (!password_verify($password, $user["password"])) {
    if ($role === "admin") {
        triggerFailedLoginAlert();
    }
    echo "<script>alert(" .
        json_encode(
            $text["agy_wrongPassword"],
            JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT,
        ) .
        ');window.location.href="index.php";</script>';
    exit();
}
// Enforce approval BEFORE creating an authenticated session. Fail closed without migration.
if (
    $role === "agent" &&
    ($user["registration_status"] ?? "Pending") !== "Approved"
) {
    $message =
        ($user["registration_status"] ?? "Pending") === "Rejected"
            ? $text["agy_loginRejected"]
            : $text["agy_loginPending"];
    echo "<script>alert(" .
        json_encode(
            $message,
            JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT,
        ) .
        ');window.location.href="index.php";</script>';
    exit();
}
session_regenerate_id(true);
$_SESSION["auth"] = [
    "logged_in" => true,
    "role" => $role,
    "id" => $user["id"],
    "email" => $user["email"],
    "name" => $role === "agent" ? $user["company_name"] : "Admin",
];
if ($_SESSION["auth"]["role"] === "admin") {
    $_SESSION["auth"]["permissions"] = [
        "manage_applications" => (bool) $user["manage_applications"],
        "manage_agents" => (bool) $user["manage_agents"],
        "manage_courses" => (bool) $user["manage_courses"],
        "manage_users" => (bool) $user["manage_users"],
        "full_control" => (bool) $user["full_control"],
    ];
    header("Location: dashboard/");
    exit();
} else {
    header("Location: applications/");
    exit();
}
