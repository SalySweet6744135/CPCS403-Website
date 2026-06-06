<?php
/*
 * Name: Manar Alharbi, Wareef Alzubaidi, Sama Salloum
 * ID: 2206712, 2207221, 2205679
 * Section: CPCS403
 * Date: 31-05-2026
 * File: api/login.php
 * Purpose: Login API — verify credentials against users table, start session, return JSON
 */
header('Content-Type: application/json');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed.']);
    exit;
}

require_once __DIR__ . '/../server/db_config.php';
require_once __DIR__ . '/../server/includes/auth.php';
require_once __DIR__ . '/../server/includes/db_log.php';

$clientIp = $_SERVER['REMOTE_ADDR'] ?? null;

$email    = strtolower(trim($_POST['email'] ?? ''));
$password =       $_POST['password'] ?? '';

$errors = [];

if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $errors['email'] = 'Please enter a valid email address.';
}
if ($password === '') {
    $errors['password'] = 'Password is required.';
}

if (!empty($errors)) {
    http_response_code(422);
    echo json_encode(['success' => false, 'errors' => $errors]);
    exit;
}

if ($conn === null) {
    http_response_code(503);
    echo json_encode(['success' => false, 'errors' => [
        'general' => 'Database unavailable. Please try again later.',
    ]]);
    exit;
}

// Only users registered in the database may sign in
$stmt = $conn->prepare(
    'SELECT id, full_name, password_hash, role, is_active FROM users WHERE LOWER(email) = ? LIMIT 1'
);
$stmt->bind_param('s', $email);
$stmt->execute();
$result = $stmt->get_result();
$user   = $result->fetch_assoc();
$stmt->close();

if (!$user) {
    logLoginAttempt($conn, $email, false, $clientIp);
    $conn->close();
    http_response_code(401);
    echo json_encode(['success' => false, 'errors' => [
        'general' => 'No account found with this email. Please register first.',
    ]]);
    exit;
}

if (!password_verify($password, $user['password_hash'])) {
    logLoginAttempt($conn, $email, false, $clientIp);
    $conn->close();
    http_response_code(401);
    echo json_encode(['success' => false, 'errors' => [
        'general' => 'Incorrect password. Please try again.',
    ]]);
    exit;
}

if (isset($user['is_active']) && (int) $user['is_active'] !== 1) {
    logLoginAttempt($conn, $email, false, $clientIp);
    $conn->close();
    http_response_code(403);
    echo json_encode(['success' => false, 'errors' => [
        'general' => 'This account has been deactivated. Contact support.',
    ]]);
    exit;
}

session_regenerate_id(true);

$_SESSION['user_id']   = (int) $user['id'];
$_SESSION['full_name'] = $user['full_name'];
$_SESSION['role']      = $user['role'];

logLoginAttempt($conn, $email, true, $clientIp);
$conn->close();

$redirect = $user['role'] === 'admin'
    ? 'admin/dashboard.php'
    : 'index.php';

echo json_encode([
    'success'  => true,
    'role'     => $user['role'],
    'redirect' => $redirect,
]);
