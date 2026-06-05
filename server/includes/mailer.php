<?php
/*
 * Name: Manar Alharbi, Wareef Alzubaidi, Sama Salloum
 * ID: 2206712, 2207221, 2205679
 * Section: CPCS403
 * Date: 31-05-2026
 * File: server/includes/mailer.php
 * Purpose: Email helper — branded HTML templates and PHP mail() sender
 */
/**
 * Send an HTML email using PHP's built-in mail().
 *
 * @param string $toEmail     Recipient email address
 * @param string $toName      Recipient display name (used in "To:" header)
 * @param string $subject     Email subject line
 * @param string $htmlBody    Full HTML content to send
 * @return bool               true on success, false on failure
 */
function sendMail(string $toEmail, string $toName, string $subject, string $htmlBody): bool
{
    $fromName  = 'ShipSmart';
    $fromEmail = 'noreply@shipsmart.com';

    // Build "To: Name <email>" header
    $recipient = $toName ? "{$toName} <{$toEmail}>" : $toEmail;

    // Required email headers
    $headers  = "MIME-Version: 1.0\r\n";
    $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
    $headers .= "From: {$fromName} <{$fromEmail}>\r\n";
    $headers .= "Reply-To: {$fromEmail}\r\n";
    $headers .= "X-Mailer: PHP/" . phpversion() . "\r\n";

    return mail($recipient, $subject, $htmlBody, $headers);
}

/**
 * Wrap email content in ShipSmart's branded HTML email template.
 *
 * @param string $title        Heading shown at top of email body
 * @param string $bodyContent  HTML content (paragraphs, tables, etc.)
 * @return string              Complete HTML email string
 */
function buildEmailTemplate(string $title, string $bodyContent): string
{
    // Escape title for safe HTML output
    $safeTitle = htmlspecialchars($title, ENT_QUOTES, 'UTF-8');

    return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>{$safeTitle}</title>
</head>
<body style="margin:0;padding:0;background:#f4f4f8;font-family:system-ui,Arial,sans-serif;">

  <table width="100%" cellpadding="0" cellspacing="0"
         style="background:#f4f4f8;padding:32px 16px;">
    <tr>
      <td align="center">
        <table width="600" cellpadding="0" cellspacing="0"
               style="max-width:600px;width:100%;background:#ffffff;
                      border-radius:16px;overflow:hidden;
                      box-shadow:0 4px 24px rgba(0,0,0,0.08);">

          <!-- ── Brand header ── -->
          <tr>
            <td style="background:#7b2b6a;padding:28px 32px;text-align:center;">
              <h1 style="margin:0;color:#ffffff;font-size:22px;
                         font-weight:700;letter-spacing:-0.5px;">
                ShipSmart
              </h1>
              <p style="margin:6px 0 0;color:rgba(255,255,255,0.8);font-size:13px;">
                Universal Shipment Tracker
              </p>
            </td>
          </tr>

          <!-- ── Email body ── -->
          <tr>
            <td style="padding:32px 32px 24px;">
              <h2 style="margin:0 0 16px;color:#151515;font-size:20px;">
                {$safeTitle}
              </h2>
              {$bodyContent}
            </td>
          </tr>

          <!-- ── Footer ── -->
          <tr>
            <td style="background:#f8f4fb;padding:20px 32px;
                       border-top:1px solid #ece6f0;text-align:center;">
              <p style="margin:0;font-size:12px;color:#888;">
                &copy; 2026 ShipSmart &mdash;
                King Abdulaziz University, Jeddah<br />
                This is an automated message, please do not reply.
              </p>
            </td>
          </tr>

        </table>
      </td>
    </tr>
  </table>

</body>
</html>
HTML;
}
