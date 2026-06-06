<?php
/*
 * Name: Manar Alharbi, Wareef Alzubaidi, Sama Salloum
 * ID: 2206712, 2207221, 2205679
 * Section: CPCS403
 * Date: 31-05-2026
 * File: verify-email.php
 * Purpose: Complete registration after the user clicks the verification link in their email
 */

require_once __DIR__ . '/server/db_config.php';
require_once __DIR__ . '/server/includes/verification_token.php';
require_once __DIR__ . '/server/includes/mailer.php';
require_once __DIR__ . '/server/includes/db_log.php';

$token   = trim($_GET['token'] ?? '');
$status  = 'error';
$title   = 'Verification failed';
$message = 'This verification link is invalid or has expired.';
$email   = '';

if ($token !== '') {
    $payload = shipsmart_registration_token_parse($token);

    if ($payload !== null && $conn !== null) {
        $fullName     = trim($payload['full_name']);
        $email        = strtolower(trim($payload['email']));
        $passwordHash = $payload['password_hash'];
        $role         = 'user';

        $check = $conn->prepare('SELECT id FROM users WHERE LOWER(email) = ? LIMIT 1');
        $check->bind_param('s', $email);
        $check->execute();
        $check->store_result();

        if ($check->num_rows > 0) {
            $status  = 'exists';
            $title   = 'Account already exists';
            $message = 'This email is already registered. You can sign in with your existing account.';
        } else {
            $stmt = $conn->prepare(
                'INSERT INTO users (full_name, email, password_hash, role) VALUES (?, ?, ?, ?)'
            );
            $stmt->bind_param('ssss', $fullName, $email, $passwordHash, $role);

            if ($stmt->execute()) {
                $userId = $conn->insert_id;
                $stmt->close();

                $safeName = htmlspecialchars($fullName, ENT_QUOTES, 'UTF-8');
                $subject  = 'Welcome — ShipSmart Account Created';
                $body     = <<<HTML
<p style="margin:0 0 16px;color:#444;line-height:1.6;">
  Hi <strong>{$safeName}</strong>,<br />
  Your email has been verified and your ShipSmart account is now active.
</p>
<p style="margin:0;color:#444;line-height:1.6;">
  You can sign in to search shipments, upload documents, and manage your profile.
</p>
HTML;
                $emailSent = sendMail($email, $fullName, $subject, buildEmailTemplate('Welcome to ShipSmart!', $body));
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

                $status  = 'success';
                $title   = 'Email verified';
                $message = 'Your account has been created. You can now sign in.';
            } else {
                if ((int) $conn->errno === 1062) {
                    $status  = 'exists';
                    $title   = 'Account already exists';
                    $message = 'This email is already registered. You can sign in with your existing account.';
                } else {
                    $message = 'We could not create your account. Please try registering again.';
                }
                $stmt->close();
            }
        }

        $check->close();
    } elseif ($payload !== null && $conn === null) {
        $message = 'Database unavailable. Please try again later.';
    }
}

if ($conn !== null) {
    $conn->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>ShipSmart | Verify Email</title>
  <link rel="stylesheet" href="global/main.css">
  <style>
    body{
      min-height:100vh; display:flex; align-items:center; justify-content:center;
      padding:24px; background:#f4f4f8;
    }
    .verify-card{
      width:min(480px,100%); background:#fff; border-radius:18px;
      padding:36px 32px; text-align:center;
      box-shadow:0 8px 32px rgba(0,0,0,0.08);
    }
    .verify-icon{
      width:68px; height:68px; border-radius:50%; margin:0 auto 18px;
      display:flex; align-items:center; justify-content:center;
      font-size:2rem; color:#fff;
    }
    .verify-icon.ok{ background:var(--primary); }
    .verify-icon.warn{ background:#b26a00; }
    .verify-icon.err{ background:#b00020; }
    h1{ font-size:1.45rem; margin:0 0 10px; color:var(--text); }
    p{ color:var(--muted); line-height:1.6; margin:0 0 18px; }
    .verify-actions{ display:flex; gap:10px; justify-content:center; flex-wrap:wrap; }
    .verify-actions a{
      display:inline-block; padding:12px 20px; border-radius:12px;
      text-decoration:none; font-weight:800;
    }
    .btn-primary-link{ background:var(--primary); color:#fff; }
    .btn-ghost-link{ background:var(--soft); color:var(--text); }
  </style>
</head>
<body>
  <div class="verify-card">
    <div class="verify-icon <?=
      $status === 'success' ? 'ok' : ($status === 'exists' ? 'warn' : 'err')
    ?>"><?= $status === 'success' ? '✓' : ($status === 'exists' ? '!' : '×') ?></div>
    <h1><?= htmlspecialchars($title, ENT_QUOTES, 'UTF-8') ?></h1>
    <p><?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8') ?></p>
    <?php if ($email !== '' && $status === 'success'): ?>
      <p><strong><?= htmlspecialchars($email, ENT_QUOTES, 'UTF-8') ?></strong></p>
    <?php endif; ?>
    <div class="verify-actions">
      <a class="btn-primary-link" href="login.php">Sign In</a>
      <?php if ($status !== 'success'): ?>
        <a class="btn-ghost-link" href="register.php">Register Again</a>
      <?php endif; ?>
    </div>
  </div>
</body>
</html>
