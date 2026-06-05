<?php
/*
 * Name: Manar Alharbi, Wareef Alzubaidi, Sama Salloum
 * ID: 2206712, 2207221, 2205679
 * Section: CPCS403
 * Date: 31-05-2026
 * File: server/includes/auth.php
 * Purpose: Authentication helpers — sessions, role checks, login/admin guards for pages and APIs
 */
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/** True when a user is logged in */
function is_logged_in(): bool
{
    return isset($_SESSION['user_id']);
}

/** True when the logged-in user is an admin */
function is_admin(): bool
{
    return is_logged_in() && ($_SESSION['role'] ?? '') === 'admin';
}

/** True when the current script lives under /api/ */
function shipsmart_is_api_request(): bool
{
    $script = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '');
    return str_contains($script, '/api/');
}

/**
 * Relative URL prefix from the running script back to the project root.
 * e.g. admin/dashboard.php → ../   api/admin/shipments.php → ../../
 */
function shipsmart_url_prefix(): string
{
    $root = realpath(dirname(__DIR__, 2));
    $script = realpath($_SERVER['SCRIPT_FILENAME'] ?? '');
    if (!$root || !$script || !str_starts_with($script, $root)) {
        return '../';
    }

    $rel = ltrim(str_replace('\\', '/', substr($script, strlen($root))), '/');
    $dir = dirname($rel);
    if ($dir === '.' || $dir === '') {
        return '';
    }

    $depth = count(explode('/', $dir));
    return str_repeat('../', $depth);
}

/**
 * Require login.
 * HTML pages → redirect to login.php.
 * API endpoints → 401 JSON (no redirect).
 */
function require_login(?int $depth = null): void
{
    if (is_logged_in()) {
        return;
    }

    if (shipsmart_is_api_request()) {
        http_response_code(401);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'success' => false,
            'ok'      => false,
            'message' => 'Authentication required.',
        ]);
        exit;
    }

    $prefix   = $depth !== null ? str_repeat('../', $depth) : shipsmart_url_prefix();
    $redirect = urlencode($_SERVER['REQUEST_URI'] ?? '');
    header("Location: {$prefix}login.php?redirect={$redirect}");
    exit;
}

/**
 * Require admin role.
 * Non-admins on HTML admin pages → redirect to 403.php.
 * Non-admins on admin API → 403 JSON.
 */
function require_admin(?int $depth = null): void
{
    require_login($depth);

    if (is_admin()) {
        return;
    }

    if (shipsmart_is_api_request()) {
        http_response_code(403);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'success' => false,
            'ok'      => false,
            'message' => 'Admin access required.',
        ]);
        exit;
    }

    $prefix = $depth !== null ? str_repeat('../', $depth) : shipsmart_url_prefix();
    header("Location: {$prefix}403.php");
    exit;
}
