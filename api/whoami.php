<?php
/*
 * Name: Manar Alharbi, Wareef Alzubaidi, Sama Salloum
 * ID: 2206712, 2207221, 2205679
 * Section: CPCS403
 * Date: 31-05-2026
 * File: api/whoami.php
 * Purpose: Session API — return current user role for client-side nav and UI
 */
// Simple whoami endpoint used by client-side scripts to show/hide admin UI.
header('Content-Type: application/json');
// include session helpers but do not require DB
require_once __DIR__ . '/../server/includes/auth.php';
if (session_status() === PHP_SESSION_NONE) session_start();
$loggedIn = isset($_SESSION['user_id']);
echo json_encode([
    'loggedIn'  => $loggedIn,
    'role'      => $loggedIn ? ($_SESSION['role'] ?? null) : null,
    'full_name' => $loggedIn ? ($_SESSION['full_name'] ?? null) : null,
]);
