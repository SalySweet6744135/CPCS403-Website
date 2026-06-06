<?php
/*
 * Name: Manar Alharbi, Wareef Alzubaidi, Sama Salloum
 * ID: 2206712, 2207221, 2205679
 * Section: CPCS403
 * Date: 31-05-2026
 * File: api/register.php
 * Purpose: Registration API — validate input, send email verification link (no DB insert until verified)
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
require_once __DIR__ . '/../server/includes/verification_token.php';

if ($conn === null) {
    http_response_code(503);
    echo json_encode([
        'success' => false,
        'message' => 'Database unavailable. Check server/db_config.php and ensure MySQL is reachable.',
    ]);
    exit;
}

$fullName = trim($_POST['full_name'] ?? '');
$email    = strtolower(trim($_POST['email'] ?? ''));
$password =       $_POST['password'] ?? '';
$confirm  =       $_POST['confirm']  ?? '';

$duplicateEmailMessage = 'An account with this email already exists. Please sign in instead.';

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

// Read-only check: email must not already be registered
$check = $conn->prepare('SELECT id FROM users WHERE LOWER(email) = ? LIMIT 1');
$check->bind_param('s', $email);
$check->execute();
$check->store_result();

if ($check->num_rows > 0) {
    http_response_code(422);
    echo json_encode([
        'success' => false,
        'message' => $duplicateEmailMessage,
        'errors'  => ['email' => $duplicateEmailMessage],
    ]);
    $check->close();
    $conn->close();
    exit;
}
$check->close();

// Hash password now, but do not insert the user until the email link is clicked
$hash = password_hash($password, PASSWORD_BCRYPT);
$exp  = time() + SHHIPSMART_REG_TOKEN_TTL;

$token = shipsmart_registration_token_create([
    'full_name'     => $fullName,
    'email'         => $email,
    'password_hash' => $hash,
    'exp'           => $exp,
]);

if ($token === '') {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Could not start registration. Please try again.']);
    $conn->close();
    exit;
}

$verifyUrl    = shipsmart_registration_verify_url($token);
$safeName     = htmlspecialchars($fullName, ENT_QUOTES, 'UTF-8');
$safeEmail    = htmlspecialchars($email, ENT_QUOTES, 'UTF-8');
$safeLink     = htmlspecialchars($verifyUrl, ENT_QUOTES, 'UTF-8');
$expiresLabel = date('d M Y, H:i', $exp);

$emailBody = <<<HTML
<p style="margin:0 0 16px;color:#444;line-height:1.6;">
  Hi <strong>{$safeName}</strong>,<br />
  Thanks for signing up for <strong>ShipSmart</strong>. Please confirm that you own this email address
  to finish creating your account.
</p>

<table width="100%" cellpadding="0" cellspacing="0"
       style="border-collapse:collapse;font-size:14px;margin-bottom:20px;">
  <tr style="background:#f8f4fb;">
    <td style="padding:10px 14px;border:1px solid #ece6f0;
               font-weight:600;color:#7b2b6a;width:40%;">Email</td>
    <td style="padding:10px 14px;border:1px solid #ece6f0;color:#333;">{$safeEmail}</td>
  </tr>
  <tr>
    <td style="padding:10px 14px;border:1px solid #ece6f0;
               font-weight:600;color:#7b2b6a;">Link expires</td>
    <td style="padding:10px 14px;border:1px solid #ece6f0;color:#333;">{$expiresLabel}</td>
  </tr>
</table>

<p style="margin:0 0 20px;text-align:center;">
  <a href="{$safeLink}"
     style="display:inline-block;background:#7b2b6a;color:#ffffff;text-decoration:none;
            padding:14px 28px;border-radius:10px;font-weight:700;font-size:15px;">
    Verify Email &amp; Create Account
  </a>
</p>

<p style="margin:0 0 10px;color:#666;font-size:13px;line-height:1.6;">
  If the button does not work, copy and paste this link into your browser:<br />
  <a href="{$safeLink}" style="color:#7b2b6a;word-break:break-all;">{$safeLink}</a>
</p>

<p style="margin:0;color:#888;font-size:12px;line-height:1.5;">
  If you did not request this account, you can safely ignore this email. No account will be created
  unless you click the verification link.
</p>
HTML;

$subject   = 'Verify your ShipSmart email address';
$emailHtml = buildEmailTemplate('Verify your email', $emailBody);
$emailSent = sendMail($email, $fullName, $subject, $emailHtml);

$emailError = null;
if (!$emailSent) {
    $emailError = 'Could not send verification email. Check the address and try again.';
    logEmail($conn, $email, $subject, 'other', 'failed', null, null, null);
    $conn->close();
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $emailError,
        'errors'  => ['email' => $emailError],
    ]);
    exit;
}

logEmail($conn, $email, $subject, 'other', 'sent', null, null, null);
$conn->close();

echo json_encode([
    'success'           => true,
    'needsVerification' => true,
    'message'           => 'Check your inbox and click the verification link to finish registration.',
    'full_name'         => $fullName,
    'email'             => $email,
    'expires_at'        => $expiresLabel,
    'emailSent'         => true,
    'emailError'        => $emailError,
]);
