<?php
/*
 * Name: Manar Alharbi, Wareef Alzubaidi, Sama Salloum
 * ID: 2206712, 2207221, 2205679
 * Section: CPCS403
 * Date: 31-05-2026
 * File: api/register.php
 * Purpose: Registration API — validate input, hash password, insert user record
 */
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed.']);
    exit;
}

require_once __DIR__ . '/../server/db_config.php';
require_once __DIR__ . '/../server/includes/password_policy.php';
require_once __DIR__ . '/../server/includes/mailer.php';
require_once __DIR__ . '/../server/includes/db_log.php';

if ($conn === null) {
    http_response_code(503);
    echo json_encode([
        'success' => false,
        'message' => 'Database unavailable. Check server/db_config.php and ensure MySQL is reachable.',
    ]);
    exit;
}

$fullName = trim($_POST['full_name'] ?? '');
$email    = trim($_POST['email']     ?? '');
$password =       $_POST['password'] ?? '';
$confirm  =       $_POST['confirm']  ?? '';

$errors = [];

if ($fullName === '' || mb_strlen($fullName) < 2) {
    $errors['full_name'] = 'Full name must be at least 2 characters.';
} elseif (!preg_match('/^[\p{L}\s]+$/u', $fullName)) {
    $errors['full_name'] = 'Full name may only contain letters and spaces.';
}

if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $errors['email'] = 'Please enter a valid email address.';
}

$passwordIssues = shipsmart_password_errors($password);
if (!empty($passwordIssues)) {
    $errors['password'] = implode(' ', $passwordIssues);
}

if ($password !== $confirm) {
    $errors['confirm'] = 'Passwords do not match.';
}

if (!empty($errors)) {
    http_response_code(422);
    echo json_encode(['success' => false, 'errors' => $errors]);
    exit;
}

// Check email not already taken
$check = $conn->prepare('SELECT id FROM users WHERE email = ?');
$check->bind_param('s', $email);
$check->execute();
$check->store_result();

if ($check->num_rows > 0) {
    http_response_code(422);
    echo json_encode(['success' => false, 'errors' => [
        'email' => 'An account with this email already exists.'
    ]]);
    $check->close();
    $conn->close();
    exit;
}
$check->close();

// Hash with bcrypt — never store plain text
$hash = password_hash($password, PASSWORD_BCRYPT);
$role = 'user'; // new registrations are always regular users

$stmt = $conn->prepare(
    'INSERT INTO users (full_name, email, password_hash, role) VALUES (?, ?, ?, ?)'
);
$stmt->bind_param('ssss', $fullName, $email, $hash, $role);

if (!$stmt->execute()) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Registration failed. Please try again.']);
    $stmt->close();
    $conn->close();
    exit;
}

$userId = $conn->insert_id;
$stmt->close();

// ── Send welcome confirmation email (same flow as upload / feedback) ──
$emailSent  = false;
$emailError = null;

$safeName       = htmlspecialchars($fullName, ENT_QUOTES, 'UTF-8');
$safeEmail      = htmlspecialchars($email, ENT_QUOTES, 'UTF-8');
$registeredAt   = date('d M Y, H:i');

$emailBody = <<<HTML
<p style="margin:0 0 16px;color:#444;line-height:1.6;">
  Hi <strong>{$safeName}</strong>,<br />
  Welcome to <strong>ShipSmart</strong> — your universal shipment tracker for Aramex, DHL, FedEx, and SMSA.
  Your account has been created successfully. Here are your details:
</p>

<table width="100%" cellpadding="0" cellspacing="0"
       style="border-collapse:collapse;font-size:14px;margin-bottom:20px;">
  <tr style="background:#f8f4fb;">
    <td style="padding:10px 14px;border:1px solid #ece6f0;
               font-weight:600;color:#7b2b6a;width:40%;">Full Name</td>
    <td style="padding:10px 14px;border:1px solid #ece6f0;color:#333;">{$safeName}</td>
  </tr>
  <tr>
    <td style="padding:10px 14px;border:1px solid #ece6f0;
               font-weight:600;color:#7b2b6a;">Account Email</td>
    <td style="padding:10px 14px;border:1px solid #ece6f0;color:#333;">{$safeEmail}</td>
  </tr>
  <tr style="background:#f8f4fb;">
    <td style="padding:10px 14px;border:1px solid #ece6f0;
               font-weight:600;color:#7b2b6a;">Role</td>
    <td style="padding:10px 14px;border:1px solid #ece6f0;color:#333;">User</td>
  </tr>
  <tr>
    <td style="padding:10px 14px;border:1px solid #ece6f0;
               font-weight:600;color:#7b2b6a;">Registered At</td>
    <td style="padding:10px 14px;border:1px solid #ece6f0;color:#333;">{$registeredAt}</td>
  </tr>
</table>

<p style="margin:0 0 10px;color:#444;line-height:1.6;">
  You can now sign in to search shipments, upload documents, and manage your profile.
</p>
<p style="margin:0;color:#444;">— The ShipSmart Team</p>
HTML;

$subject   = "Welcome — ShipSmart Account Created";
$emailHtml = buildEmailTemplate('Welcome to ShipSmart!', $emailBody);
$emailSent = sendMail($email, $fullName, $subject, $emailHtml);

if (!$emailSent) {
    $emailError = 'Account created, but welcome email could not be sent.';
}

logEmail(
    $conn,
    $email,
    $subject,
    'other',
    $emailSent ? 'sent' : 'failed',
    $userId,
    'users',
    $userId
);

$conn->close();

echo json_encode([
    'success'        => true,
    'message'        => 'Account created! You can now log in.',
    'full_name'      => $fullName,
    'email'          => $email,
    'registered_at'  => $registeredAt,
    'emailSent'      => $emailSent,
    'emailError'     => $emailError,
]);