<?php
require_once __DIR__ . '/db.php';

function h($value)
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

function flash_set($type, $message)
{
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

function flash_get()
{
    if (!empty($_SESSION['flash'])) {
        $flash = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $flash;
    }
    return null;
}

function is_logged_in()
{
    if (empty($_SESSION['user_id'])) {
        return false;
    }
    $user = fetch_user_by_id($_SESSION['user_id']);
    if (!$user) {
        unset($_SESSION['user_id']);
        return false;
    }
    return true;
}

function current_user()
{
    if (empty($_SESSION['user_id'])) {
        return null;
    }
    $user = fetch_user_by_id($_SESSION['user_id']);
    if (!$user) {
        unset($_SESSION['user_id']);
        return null;
    }
    return $user;
}

function login_user($user)
{
    $_SESSION['user_id'] = $user['id'];
}

function ensure_logged_in()
{
    if (!is_logged_in()) {
        header('Location: ?page=login');
        exit;
    }
}

function ensure_guest()
{
    if (is_logged_in()) {
        header('Location: ?page=dashboard');
        exit;
    }
}

function is_admin_of($houseId)
{
    $user = current_user();
    if (!$user) {
        return false;
    }
    $member = get_house_member($houseId, $user['id']);
    return $member && $member['role'] === 'admin';
}

function user_house_role($houseId)
{
    $user = current_user();
    if (!$user) {
        return null;
    }
    $member = get_house_member($houseId, $user['id']);
    return $member ? $member['role'] : null;
}
