<?php
// CLI-only — block browser access
if (php_sapi_name() !== 'cli') {
    http_response_code(404);
    exit('Not found');
}
//
/**
 * email_worker.php
 * Called via cron every minute OR triggered manually
 * Processes the email_queue table using PHP mail() or SMTP
 * 
 * Add to crontab: * * * * * php /var/www/html/email_worker.php >> /tmp/email_worker.log 2>&1
 */
require_once __DIR__ . '/config.php';

define('SMTP_HOST', getenv('SMTP_HOST') ?: 'mailhog');
define('SMTP_PORT', (int)(getenv('SMTP_PORT') ?: 1025));
define('SMTP_FROM', getenv('SMTP_FROM') ?: 'noreply@quranhub.om');
define('SMTP_FROM_NAME', 'Digital Quran Hub 🕌');

function sendSmtpEmail(string $toEmail, string $toName, string $subject, string $htmlBody): bool {
    $from    = SMTP_FROM;
    $fromName = SMTP_FROM_NAME;
    $boundary = md5(time());

    $headers  = "MIME-Version: 1.0\r\n";
    $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
    $headers .= "From: $fromName <$from>\r\n";
    $headers .= "To: $toName <$toEmail>\r\n";
    $headers .= "Reply-To: $from\r\n";
    $headers .= "X-Mailer: QuranHub/2.0\r\n";

    // Try SMTP socket
    $fp = @fsockopen(SMTP_HOST, SMTP_PORT, $errno, $errstr, 5);
    if (!$fp) {
        // Fallback to PHP mail()
        return mail($toEmail, $subject, $htmlBody, $headers);
    }

    $recv = fgets($fp, 512);
    fputs($fp, "EHLO quranhub.om\r\n");
    $recv = fgets($fp, 512);
    fputs($fp, "MAIL FROM:<$from>\r\n");
    $recv = fgets($fp, 512);
    fputs($fp, "RCPT TO:<$toEmail>\r\n");
    $recv = fgets($fp, 512);
    fputs($fp, "DATA\r\n");
    $recv = fgets($fp, 512);
    $msg  = "Subject: $subject\r\n";
    $msg .= "From: $fromName <$from>\r\n";
    $msg .= "To: $toName <$toEmail>\r\n";
    $msg .= "MIME-Version: 1.0\r\n";
    $msg .= "Content-Type: text/html; charset=UTF-8\r\n\r\n";
    $msg .= $htmlBody . "\r\n.\r\n";
    fputs($fp, $msg);
    $recv = fgets($fp, 512);
    fputs($fp, "QUIT\r\n");
    fclose($fp);
    return str_starts_with(trim($recv), '2');
}

$pdo = getPDO();

// Process up to 20 pending emails
$pending = $pdo->query(
    "SELECT * FROM email_queue WHERE status='pending' AND attempts < 3 ORDER BY created_at LIMIT 20"
)->fetchAll();

$sent = 0;
foreach ($pending as $email) {
    $ok = sendSmtpEmail(
        $email['to_email'],
        $email['to_name'] ?? '',
        $email['subject'],
        $email['body_html']
    );
    if ($ok) {
        $pdo->prepare("UPDATE email_queue SET status='sent', sent_at=NOW() WHERE id=?")
            ->execute([$email['id']]);
        $sent++;
    } else {
        $newAttempts = $email['attempts'] + 1;
        $newStatus   = $newAttempts >= 3 ? 'failed' : 'pending';
        $pdo->prepare("UPDATE email_queue SET attempts=?, status=? WHERE id=?")
            ->execute([$newAttempts, $newStatus, $email['id']]);
    }
}

if (PHP_SAPI === 'cli') {
    echo date('Y-m-d H:i:s') . " — Processed: {$sent}/{" . count($pending) . "}\n";
}
