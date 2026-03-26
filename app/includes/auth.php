
<?php
/**
 * FundedControl — Auth Helper (v3.3.0 Phase 4)
 * Session management, email verification, token generation
 */

/**
 * Generate a secure random token for email verification
 */
function generateToken($length = 32) {
    return bin2hex(random_bytes($length));
}

/**
 * Send verification email to new user
 * Uses PHP mail() on Namecheap shared hosting
 * SMTP credentials in config.php
 */
function sendVerificationEmail($toEmail, $username, $token) {
    $verifyUrl = SITE_URL . '/verify.php?token=' . $token;
    
    $subject = 'Verify your FundedControl account';
    
    $htmlBody = '
    <!DOCTYPE html>
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
                    If the button does not work, copy and paste this link into your browser:<br>
                    <a href="' . $verifyUrl . '" style="color:#1A56DB;word-break:break-all">' . $verifyUrl . '</a>
                </p>
                <p style="color:#6C7A8D;font-size:12px;margin:16px 0 0">
                    This link expires in 24 hours. If you did not create this account, you can safely ignore this email.
                </p>
            </div>
            <div style="background:#F1F5F9;padding:16px 30px;text-align:center">
                <p style="color:#6C7A8D;font-size:11px;margin:0">
                    FundedControl — Professional Trading Journal<br>
                    <a href="' . SITE_URL . '" style="color:#1A56DB;text-decoration:none">fundedcontrol.com</a>
                </p>
            </div>
        </div>
    </body>
    </html>';

    $headers  = "MIME-Version: 1.0\r\n";
    $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
    $headers .= "From: FundedControl <" . MAIL_FROM . ">\r\n";
    $headers .= "Reply-To: " . MAIL_FROM . "\r\n";
    $headers .= "X-Mailer: FundedControl/3.3.0\r\n";

    return mail($toEmail, $subject, $htmlBody, $headers);
}

/**
 * Send password reset email
 */
function sendPasswordResetEmail($toEmail, $username, $token) {
    $resetUrl = SITE_URL . '/reset-password.php?token=' . $token;
    
    $subject = 'Reset your FundedControl password';
    
    $htmlBody = '
    <!DOCTYPE html>
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
                    Hi ' . htmlspecialchars($username) . ', we received a request to reset your password. Click the button below to choose a new password.
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

    $headers  = "MIME-Version: 1.0\r\n";
    $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
    $headers .= "From: FundedControl <" . MAIL_FROM . ">\r\n";
    $headers .= "Reply-To: " . MAIL_FROM . "\r\n";

    return mail($toEmail, $subject, $htmlBody, $headers);
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
