<?php
/**
 * FundedControl — Auth Helper (v3.3.0 Phase 4)
 * Session management, SMTP email sending, token generation
 */

/**
 * Generate a secure random token for email verification
 */
function generateToken($length = 32) {
    return bin2hex(random_bytes($length));
}

/**
 * Send email via SMTP (Namecheap shared hosting)
 * Uses direct socket connection — no external libraries needed
 */
function smtpSend($toEmail, $subject, $htmlBody) {
    $host = 'ssl://fundedcontrol.com';
    $port = 465;
    $username = MAIL_FROM;          // norepl@fundedcontrol.com
    $password = MAIL_PASSWORD;      // Set in config.php
    $fromName = 'FundedControl';

    // Connect to SMTP server
    $socket = @fsockopen($host, $port, $errno, $errstr, 15);
    if (!$socket) {
        error_log("SMTP Connect failed: $errstr ($errno)");
        return false;
    }

    // Helper to send command and read response
    $read = function() use ($socket) {
        $response = '';
        while ($line = fgets($socket, 515)) {
            $response .= $line;
            if (substr($line, 3, 1) == ' ') break;
        }
        return $response;
    };

    $send = function($cmd) use ($socket, $read) {
        fwrite($socket, $cmd . "\r\n");
        return $read();
    };

    // SMTP conversation
    $read(); // Read greeting
    $send("EHLO fundedcontrol.com");
    $send("AUTH LOGIN");
    $send(base64_encode($username));
    $response = $send(base64_encode($password));

    // Check auth success (235)
    if (strpos($response, '235') === false) {
        error_log("SMTP Auth failed: $response");
        fclose($socket);
        return false;
    }

    $send("MAIL FROM:<{$username}>");
    $send("RCPT TO:<{$toEmail}>");
    $send("DATA");

    // Build email headers + body
    $boundary = md5(time());
    $headers  = "Date: " . date('r') . "\r\n";
    $headers .= "From: {$fromName} <{$username}>\r\n";
    $headers .= "To: <{$toEmail}>\r\n";
    $headers .= "Subject: {$subject}\r\n";
    $headers .= "MIME-Version: 1.0\r\n";
    $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
    $headers .= "X-Mailer: FundedControl/3.3.0\r\n";
    $headers .= "\r\n";

    // Send headers + body, end with single dot
    fwrite($socket, $headers . $htmlBody . "\r\n.\r\n");
    $response = $read();

    $send("QUIT");
    fclose($socket);

    // Check if message accepted (250)
    $success = strpos($response, '250') !== false;
    if (!$success) {
        error_log("SMTP Send failed: $response");
    }
    return $success;
}

/**
 * Send verification email to new user
 */
function sendVerificationEmail($toEmail, $username, $token) {
    $verifyUrl = SITE_URL . '/verify.php?token=' . $token;
    
    $subject = 'Verify your FundedControl account';
    
    $htmlBody = '<!DOCTYPE html>
<html>
<head><meta charset="UTF-8"></head>
<body style="margin:0;padding:0;background:#FAFBFC;font-family:Arial,sans-serif">
    <div style="max-width:500px;margin:40px auto;background:#FFFFFF;border:1px solid #E2E8F0;border-radius:8px;overflow:hidden">
        <div style="background:#0B1D3A;padding:30px;text-align:center">
            <h1 style="color:#FFFFFF;font-size:22px;margin:0;letter-spacing:1px">FundedControl</h1>
            <p style="color:#7A8FA5;font-size:13px;margin:6px 0 0">Control Your Trading. Get Funded. Stay Funded.</p>
        </div>
        <div style="padding:30px">
            <h2 style="color:#0B1D3A;font-size:18px;margin:0 0 16px">Welcome, ' . htmlspecialchars($username) . '!</h2>
            <p style="color:#475569;font-size:14px;line-height:1.6;margin:0 0 24px">
                Thank you for creating your FundedControl account. Click the button below to verify your email address and start journaling your trades.
            </p>
            <div style="text-align:center;margin:24px 0">
                <a href="' . $verifyUrl . '" style="display:inline-block;background:#1A56DB;color:#FFFFFF;padding:14px 32px;border-radius:8px;font-size:14px;font-weight:600;text-decoration:none;letter-spacing:0.5px">
                    Verify My Email
                </a>
            </div>
            <p style="color:#6C7A8D;font-size:12px;line-height:1.6;margin:24px 0 0">
                If the button does not work, copy and paste this link:<br>
                <a href="' . $verifyUrl . '" style="color:#1A56DB;word-break:break-all">' . $verifyUrl . '</a>
            </p>
            <p style="color:#6C7A8D;font-size:12px;margin:16px 0 0">
                This link expires in 24 hours. If you did not create this account, ignore this email.
            </p>
        </div>
        <div style="background:#F1F5F9;padding:16px 30px;text-align:center">
            <p style="color:#6C7A8D;font-size:11px;margin:0">FundedControl — Professional Trading Journal</p>
        </div>
    </div>
</body>
</html>';

    return smtpSend($toEmail, $subject, $htmlBody);
}

/**
 * Send password reset email
 */
function sendPasswordResetEmail($toEmail, $username, $token) {
    $resetUrl = SITE_URL . '/reset-password.php?token=' . $token;
    
    $subject = 'Reset your FundedControl password';
    
    $htmlBody = '<!DOCTYPE html>
<html>
<head><meta charset="UTF-8"></head>
<body style="margin:0;padding:0;background:#FAFBFC;font-family:Arial,sans-serif">
    <div style="max-width:500px;margin:40px auto;background:#FFFFFF;border:1px solid #E2E8F0;border-radius:8px;overflow:hidden">
        <div style="background:#0B1D3A;padding:30px;text-align:center">
            <h1 style="color:#FFFFFF;font-size:22px;margin:0;letter-spacing:1px">FundedControl</h1>
        </div>
        <div style="padding:30px">
            <h2 style="color:#0B1D3A;font-size:18px;margin:0 0 16px">Password Reset</h2>
            <p style="color:#475569;font-size:14px;line-height:1.6;margin:0 0 24px">
                Hi ' . htmlspecialchars($username) . ', click below to choose a new password.
            </p>
            <div style="text-align:center;margin:24px 0">
                <a href="' . $resetUrl . '" style="display:inline-block;background:#1A56DB;color:#FFFFFF;padding:14px 32px;border-radius:8px;font-size:14px;font-weight:600;text-decoration:none">
                    Reset Password
                </a>
            </div>
            <p style="color:#6C7A8D;font-size:12px;margin:16px 0 0">
                This link expires in 1 hour. If you did not request this, ignore this email.
            </p>
        </div>
    </div>
</body>
</html>';

    return smtpSend($toEmail, $subject, $htmlBody);
}

/**
 * Validate email format
 */
function isValidEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

/**
 * Validate username (alphanumeric + underscore, 3-20 chars)
 */
function isValidUsername($username) {
    return preg_match('/^[a-zA-Z0-9_]{3,20}$/', $username);
}

/**
 * Validate password strength (min 8 chars)
 */
function isValidPassword($password) {
    return strlen($password) >= 8;
}
