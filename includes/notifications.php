<?php
/**
 * TCC RRS – Notification System
 * Sends email via configured SMTP (Registrar → System Settings)
 * or falls back to PHP's mail() when no SMTP host is set.
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/helpers.php';

// ══════════════════════════════════════════════════════════════════════════════
// EMAIL SENDING
// ══════════════════════════════════════════════════════════════════════════════

function sendEmail(string $to, string $subject, string $htmlBody, string $recipientName = ''): bool
{
    $fromName  = getSetting('smtp_from_name', 'TCC Registrar');
    // Gmail/SMTP providers require the From address to match the authenticated user.
    $fromEmail = getSetting('smtp_user') ?: getSetting('school_email', 'registrar@tcc.edu.ph');

    // SMTP is considered configured only when host + username are both set
    // (the database seeds a placeholder smtp_host with empty credentials).
    if (getSetting('smtp_host') !== '' && getSetting('smtp_user') !== '') {
        return sendSmtpEmail($to, $subject, $htmlBody, $fromEmail, $fromName, $recipientName);
    }
    return sendNativeMail($to, $subject, $htmlBody, $fromEmail, $fromName, $recipientName);
}

function sendNativeMail(
    string $to, string $subject, string $htmlBody,
    string $fromEmail, string $fromName, string $recipientName
): bool {
    $headers  = "MIME-Version: 1.0\r\n";
    $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
    $headers .= "From: {$fromName} <{$fromEmail}>\r\n";
    $headers .= "Reply-To: {$fromEmail}\r\n";
    $headers .= "X-Mailer: TCC-RRS/1.0\r\n";

    $to = $recipientName ? "{$recipientName} <{$to}>" : $to;

    return @mail($to, $subject, $htmlBody, $headers);
}

// ── Minimal SMTP client (no external dependencies) ────────────────────────────

function smtpRead($conn): string
{
    $data = '';
    while (($line = fgets($conn, 515)) !== false) {
        $data .= $line;
        // Last line of a reply is "<code><space>"; continuations use "<code>-".
        if (strlen($line) >= 4 && $line[3] === ' ') break;
    }
    return $data;
}

function smtpCommand($conn, string $command, array $expectedCodes): bool
{
    if ($command !== '') {
        fwrite($conn, $command . "\r\n");
    }
    $code = substr(smtpRead($conn), 0, 3);
    return in_array($code, $expectedCodes, true);
}

function smtpEncodeHeader(string $value): string
{
    return '=?UTF-8?B?' . base64_encode($value) . '?=';
}

function sendSmtpEmail(
    string $to, string $subject, string $htmlBody,
    string $fromEmail, string $fromName, string $recipientName
): bool {
    $host = getSetting('smtp_host');
    $port = (int) getSetting('smtp_port', '587') ?: 587;
    $user = getSetting('smtp_user');
    $pass = getSetting('smtp_pass');
    $enc  = strtolower(getSetting('smtp_encryption', 'tls'));

    $remote = ($enc === 'ssl' ? 'ssl://' : '') . $host;
    $conn   = @stream_socket_client("{$remote}:{$port}", $errno, $errstr, 15);
    if (!$conn) return false;
    stream_set_timeout($conn, 15);

    try {
        $ehlo = 'EHLO ' . ($_SERVER['SERVER_NAME'] ?? 'localhost');

        if (!smtpCommand($conn, '', ['220']))      throw new RuntimeException('SMTP banner');
        if (!smtpCommand($conn, $ehlo, ['250']))   throw new RuntimeException('EHLO');

        if ($enc === 'tls') {
            if (!smtpCommand($conn, 'STARTTLS', ['220'])) throw new RuntimeException('STARTTLS');
            if (!stream_socket_enable_crypto($conn, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                throw new RuntimeException('TLS handshake');
            }
            if (!smtpCommand($conn, $ehlo, ['250'])) throw new RuntimeException('EHLO after TLS');
        }

        if ($user !== '') {
            if (!smtpCommand($conn, 'AUTH LOGIN', ['334']))              throw new RuntimeException('AUTH');
            if (!smtpCommand($conn, base64_encode($user), ['334']))      throw new RuntimeException('AUTH user');
            if (!smtpCommand($conn, base64_encode($pass), ['235']))      throw new RuntimeException('AUTH pass');
        }

        $envelopeFrom = $user !== '' ? $user : $fromEmail;
        if (!smtpCommand($conn, "MAIL FROM:<{$envelopeFrom}>", ['250'])) throw new RuntimeException('MAIL FROM');
        if (!smtpCommand($conn, "RCPT TO:<{$to}>", ['250', '251']))      throw new RuntimeException('RCPT TO');
        if (!smtpCommand($conn, 'DATA', ['354']))                        throw new RuntimeException('DATA');

        $toHeader = $recipientName !== ''
            ? smtpEncodeHeader($recipientName) . " <{$to}>"
            : $to;

        // Body is base64-chunked: lines can never start with '.', so no dot-stuffing needed.
        $message  = 'From: '    . smtpEncodeHeader($fromName) . " <{$fromEmail}>\r\n";
        $message .= "To: {$toHeader}\r\n";
        $message .= 'Subject: ' . smtpEncodeHeader($subject) . "\r\n";
        $message .= "Reply-To: {$fromEmail}\r\n";
        $message .= 'Date: '    . date(DATE_RFC2822) . "\r\n";
        $message .= "MIME-Version: 1.0\r\n";
        $message .= "Content-Type: text/html; charset=UTF-8\r\n";
        $message .= "Content-Transfer-Encoding: base64\r\n";
        $message .= "X-Mailer: TCC-RRS/1.0\r\n";
        $message .= "\r\n" . chunk_split(base64_encode($htmlBody));

        fwrite($conn, $message . "\r\n.\r\n");
        if (!in_array(substr(smtpRead($conn), 0, 3), ['250'], true)) {
            throw new RuntimeException('Message rejected');
        }

        smtpCommand($conn, 'QUIT', ['221', '250', '']);
        return true;
    } catch (Throwable $e) {
        $GLOBALS['smtp_last_error'] = $e->getMessage();
        return false;
    } finally {
        fclose($conn);
    }
}

// ══════════════════════════════════════════════════════════════════════════════
// NOTIFICATION LOG
// ══════════════════════════════════════════════════════════════════════════════

function queueNotification(
    string  $trackingCode,
    string  $recipientEmail,
    string  $recipientPhone,
    string  $subject,
    string  $message
): void {
    db()->execute(
        "INSERT INTO notifications
            (tracking_code, recipient_email, recipient_phone, subject, message, channel)
         VALUES (?, ?, ?, ?, ?, 'email')",
        [$trackingCode, $recipientEmail, $recipientPhone, $subject, $message]
    );
}

function markNotificationSent(int $id): void
{
    db()->execute(
        "UPDATE notifications SET status = 'sent', sent_at = NOW() WHERE id = ?",
        [$id]
    );
}

function markNotificationFailed(int $id): void
{
    db()->execute(
        "UPDATE notifications SET status = 'failed' WHERE id = ?",
        [$id]
    );
}

// ══════════════════════════════════════════════════════════════════════════════
// EMAIL TEMPLATES
// ══════════════════════════════════════════════════════════════════════════════

function emailLayout(string $content, string $title = ''): string
{
    $school     = getSetting('school_name',    'Tampakan Community College');
    $schoolAddr = getSetting('school_address', 'Tampakan, South Cotabato, Philippines');
    $year       = date('Y');

    return <<<HTML
    <!DOCTYPE html>
    <html lang="en">
    <head>
      <meta charset="UTF-8">
      <meta name="viewport" content="width=device-width,initial-scale=1">
      <title>{$title}</title>
      <style>
        body { font-family: Arial, sans-serif; background:#f4f6f9; margin:0; padding:20px; }
        .email-wrap { max-width:600px; margin:0 auto; background:#fff; border-radius:8px; overflow:hidden; box-shadow:0 2px 8px rgba(0,0,0,.1); }
        .email-header { background:#0d4530; color:#fff; padding:24px 32px; text-align:center; border-bottom:4px solid #c9a227; }
        .email-header h1 { margin:0; font-size:22px; }
        .email-header p  { margin:4px 0 0; font-size:13px; opacity:.85; }
        .email-body { padding:28px 32px; color:#333; line-height:1.6; }
        .email-body h2 { color:#0d4530; font-size:18px; }
        .tracking-box { background:#edf6f0; border-left:4px solid #115540; border-radius:4px; padding:14px 18px; margin:16px 0; }
        .tracking-box .code { font-family:monospace; font-size:20px; font-weight:700; color:#0d4530; letter-spacing:1px; }
        .status-badge { display:inline-block; padding:4px 12px; border-radius:20px; font-size:13px; font-weight:600; }
        .status-pending    { background:#e2e8f0; color:#475569; }
        .status-processing { background:#d9ede1; color:#115540; }
        .status-approved   { background:#dcfce7; color:#15803d; }
        .status-released   { background:#f0fdf4; color:#166534; }
        .status-declined   { background:#fee2e2; color:#dc2626; }
        .status-correction { background:#fef9c3; color:#854d0e; }
        .btn { display:inline-block; padding:10px 22px; background:#115540; color:#fff; text-decoration:none; border-radius:5px; font-size:14px; margin-top:10px; }
        .divider { border:none; border-top:1px solid #e2e8f0; margin:20px 0; }
        .email-footer { background:#f8fafc; padding:16px 32px; text-align:center; font-size:12px; color:#94a3b8; }
      </style>
    </head>
    <body>
      <div class="email-wrap">
        <div class="email-header">
          <h1>{$school}</h1>
          <p>Office of the Registrar – Registration & Records System</p>
        </div>
        <div class="email-body">
          {$content}
        </div>
        <div class="email-footer">
          <p>{$school}<br>{$schoolAddr}</p>
          <p>© {$year} – This is an automated notification. Please do not reply.</p>
        </div>
      </div>
    </body>
    </html>
    HTML;
}

// ── PRE-REGISTRATION notifications ───────────────────────────────────────────

function notifyRegistrationReceived(array $reg): void
{
    if (!getSetting('notifications_enabled', '1')) return;

    $name    = e(fullName($reg));
    $code    = e($reg['tracking_code']);
    $trackUrl = trackingUrl($reg['tracking_code']);
    $subject = "Pre-Registration Received – {$code}";

    $content = <<<HTML
    <h2>Pre-Registration Received</h2>
    <p>Dear <strong>{$name}</strong>,</p>
    <p>Your pre-registration for enrollment at <strong>Tampakan Community College</strong> has been successfully received.</p>
    <div class="tracking-box">
      <div>Your Tracking Code</div>
      <div class="code">{$code}</div>
    </div>
    <p>You can use this tracking code to monitor the status of your pre-registration.</p>
    <a href="{$trackUrl}" class="btn">Track My Registration</a>
    <hr class="divider">
    <p style="font-size:13px;color:#64748b;">
      Please save your tracking code for future reference. You may also scan the QR Code provided upon submission to track your registration status.
    </p>
    HTML;

    $html = emailLayout($content, $subject);

    $notifId = (int) db()->insert(
        "INSERT INTO notifications (tracking_code, recipient_email, recipient_phone, subject, message, channel)
         VALUES (?, ?, ?, ?, ?, 'email')",
        [$reg['tracking_code'], $reg['email'], $reg['contact_number'], $subject, $html]
    );

    if (sendEmail($reg['email'], $subject, $html, fullName($reg))) {
        markNotificationSent($notifId);
    } else {
        markNotificationFailed($notifId);
    }
}

function notifyRegistrationStatusUpdate(array $reg, string $oldStatus): void
{
    if (!getSetting('notifications_enabled', '1')) return;

    $name     = e(fullName($reg));
    $code     = e($reg['tracking_code']);
    $status   = e($reg['status']);
    $trackUrl = trackingUrl($reg['tracking_code']);
    $subject  = "Registration Update: {$status} – {$code}";
    $remarks  = !empty($reg['remarks']) ? '<p><strong>Remarks:</strong> ' . e($reg['remarks']) . '</p>' : '';

    $statusMsg = match ($reg['status']) {
        'Under Review'     => 'Your pre-registration is now being reviewed by our staff.',
        'Processing'       => 'Your pre-registration is currently being processed.',
        'Approved'         => 'Congratulations! Your pre-registration has been <strong>approved</strong>. Please proceed to the Registrar\'s Office for further enrollment steps.',
        'Needs Correction' => 'Your pre-registration requires some corrections. Please review the remarks below.',
        'Declined'         => 'We regret to inform you that your pre-registration has been declined. Please see the remarks below.',
        default            => "Your registration status has been updated to <strong>{$status}</strong>.",
    };

    $content = <<<HTML
    <h2>Registration Status Update</h2>
    <p>Dear <strong>{$name}</strong>,</p>
    <p>{$statusMsg}</p>
    <div class="tracking-box">
      <div>Tracking Code</div>
      <div class="code">{$code}</div>
      <div style="margin-top:6px;">Status: <span class="status-badge">{$status}</span></div>
    </div>
    {$remarks}
    <a href="{$trackUrl}" class="btn">View Status</a>
    HTML;

    $html = emailLayout($content, $subject);

    $notifId = (int) db()->insert(
        "INSERT INTO notifications (tracking_code, recipient_email, recipient_phone, subject, message, channel)
         VALUES (?, ?, ?, ?, ?, 'email')",
        [$reg['tracking_code'], $reg['email'], $reg['contact_number'], $subject, $html]
    );

    if (sendEmail($reg['email'], $subject, $html, fullName($reg))) {
        markNotificationSent($notifId);
    } else {
        markNotificationFailed($notifId);
    }
}

// ── DOCUMENT REQUEST notifications ───────────────────────────────────────────

function notifyDocumentRequestReceived(array $req): void
{
    if (!getSetting('notifications_enabled', '1')) return;

    $name     = e(fullName($req));
    $code     = e($req['tracking_code']);
    $docType  = e($req['document_type_other'] ?: getDocumentTypeName((int)($req['document_type_id'] ?? 0)));
    $trackUrl = trackingUrl($req['tracking_code']);
    $subject  = "Document Request Received – {$code}";

    $content = <<<HTML
    <h2>Document Request Received</h2>
    <p>Dear <strong>{$name}</strong>,</p>
    <p>Your document request has been successfully received by the TCC Registrar's Office.</p>
    <div class="tracking-box">
      <div>Your Tracking Code</div>
      <div class="code">{$code}</div>
      <div style="margin-top:6px;">Document: <strong>{$docType}</strong></div>
    </div>
    <p>You will be notified when your request is ready for release.</p>
    <a href="{$trackUrl}" class="btn">Track My Request</a>
    HTML;

    $html = emailLayout($content, $subject);

    $notifId = (int) db()->insert(
        "INSERT INTO notifications (tracking_code, recipient_email, recipient_phone, subject, message, channel)
         VALUES (?, ?, ?, ?, ?, 'email')",
        [$req['tracking_code'], $req['email'], $req['contact_number'], $subject, $html]
    );

    if (sendEmail($req['email'], $subject, $html, fullName($req))) {
        markNotificationSent($notifId);
    } else {
        markNotificationFailed($notifId);
    }
}

function notifyDocumentRequestStatusUpdate(array $req, string $oldStatus): void
{
    if (!getSetting('notifications_enabled', '1')) return;

    $name     = e(fullName($req));
    $code     = e($req['tracking_code']);
    $status   = e($req['status']);
    $trackUrl = trackingUrl($req['tracking_code']);
    $subject  = "Document Request Update: {$status} – {$code}";
    $remarks  = !empty($req['remarks']) ? '<p><strong>Remarks:</strong> ' . e($req['remarks']) . '</p>' : '';

    $statusMsg = match ($req['status']) {
        'Under Review'      => 'Your document request is now being reviewed.',
        'Processing'        => 'Your document request is currently being processed.',
        'Approved'          => 'Your document request has been approved and is being prepared.',
        'Ready for Release' => 'Your requested document is <strong>ready for release</strong>. Please visit the Registrar\'s Office with your tracking code to claim it.',
        'Released'          => 'Your document has been successfully released. Thank you!',
        'Needs Correction'  => 'Your document request requires correction. Please review the remarks.',
        'Declined'          => 'Your document request has been declined. Please see remarks below.',
        default             => "Your document request status has been updated to <strong>{$status}</strong>.",
    };

    $content = <<<HTML
    <h2>Document Request Status Update</h2>
    <p>Dear <strong>{$name}</strong>,</p>
    <p>{$statusMsg}</p>
    <div class="tracking-box">
      <div>Tracking Code</div>
      <div class="code">{$code}</div>
      <div style="margin-top:6px;">Status: <strong>{$status}</strong></div>
    </div>
    {$remarks}
    <a href="{$trackUrl}" class="btn">View Status</a>
    HTML;

    $html = emailLayout($content, $subject);

    $notifId = (int) db()->insert(
        "INSERT INTO notifications (tracking_code, recipient_email, recipient_phone, subject, message, channel)
         VALUES (?, ?, ?, ?, ?, 'email')",
        [$req['tracking_code'], $req['email'], $req['contact_number'], $subject, $html]
    );

    if (sendEmail($req['email'], $subject, $html, fullName($req))) {
        markNotificationSent($notifId);
    } else {
        markNotificationFailed($notifId);
    }
}
