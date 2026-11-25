<?php
function can(string $permission): bool
{
    $auth = $_SESSION['auth'] ?? [];
    return ($auth['role'] ?? '') === 'admin' &&
        !empty($auth['logged_in']) &&
        (!empty($auth['permissions']['full_control']) || !empty($auth['permissions'][$permission]));
}
