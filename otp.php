<?php
/**
 * Email OTP Verification API
 */

ini_set('display_errors', 0);
error_reporting(0);

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

require_once 'config.php';

// Gmail Config
define('GMAIL_USER', 'thakreaditya964@gmail.com');
define('GMAIL_PASS', 'oikmhbnkdxirswcs');

// Initialize database table ONCE
initDatabase();

$action = $_GET['action'] ?? '';

try {
    switch ($action) {
        case 'send':
            sendOTP();
            break;
        case 'verify':
            verifyOTP();
            break;
        default:
            echo json_encode(['success' => false, 'error' => 'Invalid action']);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
exit;

/**
 * Initialize OTP table only if needed
 */
function initDatabase() {
    static $initialized = false;
    if ($initialized) return;
    
    try {
        $db = getDB();
        
        // Check if table exists with correct structure
        $result = $db->query("SHOW TABLES LIKE 'otp_codes'");
        if ($result->rowCount() == 0) {
            $db->exec("CREATE TABLE otp_codes (
                id INT AUTO_INCREMENT PRIMARY KEY,
                email VARCHAR(255) NOT NULL,
                otp VARCHAR(6) NOT NULL,
                expires_at DATETIME NOT NULL,
                verified TINYINT DEFAULT 0,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_email (email)
            )");
        } else {
            // Check if email column exists
            $result = $db->query("SHOW COLUMNS FROM otp_codes LIKE 'email'");
            if ($result->rowCount() == 0) {
                // Old table with phone column - recreate
                $db->exec("DROP TABLE otp_codes");
                $db->exec("CREATE TABLE otp_codes (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    email VARCHAR(255) NOT NULL,
                    otp VARCHAR(6) NOT NULL,
                    expires_at DATETIME NOT NULL,
                    verified TINYINT DEFAULT 0,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    INDEX idx_email (email)
                )");
            }
        }
        $initialized = true;
    } catch (Exception $e) {
        // Ignore init errors
    }
}

/**
 * Send OTP
 */
function sendOTP() {
    $data = json_decode(file_get_contents('php://input'), true) ?? [];
    
    if (empty($data['email'])) {
        echo json_encode(['success' => false, 'error' => 'Email is required']);
        return;
    }
    
    $email = trim(strtolower($data['email']));
    
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        echo json_encode(['success' => false, 'error' => 'Invalid email']);
        return;
    }
    
    // Generate OTP
    $otp = str_pad(rand(0, 999999), 6, '0', STR_PAD_LEFT);
    $expiresAt = date('Y-m-d H:i:s', time() + 600); // 10 minutes
    
    try {
        $db = getDB();
        
        // Delete old OTPs for this email
        $stmt = $db->prepare("DELETE FROM otp_codes WHERE email = ?");
        $stmt->execute([$email]);
        
        // Insert new OTP
        $stmt = $db->prepare("INSERT INTO otp_codes (email, otp, expires_at, verified) VALUES (?, ?, ?, 0)");
        $stmt->execute([$email, $otp, $expiresAt]);
        
        // Send email
        $emailSent = sendEmail($email, $otp);
        
        if ($emailSent) {
            echo json_encode([
                'success' => true,
                'data' => [
                    'message' => 'Verification code sent to your email',
                    'email' => $email,
                    'expires_in' => 600
                ]
            ]);
        } else {
            echo json_encode(['success' => false, 'error' => 'Failed to send email']);
        }
        
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => 'Database error: ' . $e->getMessage()]);
    }
}

/**
 * Send Email via SMTP
 */
function sendEmail($to, $otp) {
    $subject = "Verification Code: $otp";
    
    $body = "
<!DOCTYPE html>
<html>
<body style='font-family: Arial; background: #1A1D21; padding: 20px;'>
<div style='max-width: 500px; margin: 0 auto; background: #2A2F35; border-radius: 20px; padding: 40px;'>
<h2 style='text-align: center; color: #1E90A5;'>Emergency Trigger</h2>
<p style='text-align: center; color: #F5F5F5;'>Your verification code is:</p>
<div style='background: #1E90A5; color: white; font-size: 32px; font-weight: bold; text-align: center; padding: 20px; border-radius: 12px; letter-spacing: 8px; margin: 20px 0;'>$otp</div>
<p style='color: #9CA3AF; text-align: center;'>Valid for 10 minutes. Do not share.</p>
</div>
</body>
</html>";
    
    $user = GMAIL_USER;
    $pass = GMAIL_PASS;
    
    // Try SMTP
    $smtp = @stream_socket_client(
        'ssl://smtp.gmail.com:465',
        $errno, $errstr, 30,
        STREAM_CLIENT_CONNECT,
        stream_context_create(['ssl' => ['verify_peer' => false, 'verify_peer_name' => false]])
    );
    
    if (!$smtp) {
        // Fallback to mail()
        $headers = "From: Emergency Trigger <$user>\r\n";
        $headers .= "MIME-Version: 1.0\r\n";
        $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
        return @mail($to, $subject, $body, $headers);
    }
    
    fgets($smtp, 1024);
    
    fwrite($smtp, "EHLO localhost\r\n");
    while (substr(fgets($smtp, 1024), 3, 1) === '-') {}
    
    fwrite($smtp, "AUTH LOGIN\r\n");
    fgets($smtp, 1024);
    
    fwrite($smtp, base64_encode($user) . "\r\n");
    fgets($smtp, 1024);
    
    fwrite($smtp, base64_encode($pass) . "\r\n");
    $auth = fgets($smtp, 1024);
    
    if (substr($auth, 0, 3) !== '235') {
        fclose($smtp);
        return false;
    }
    
    fwrite($smtp, "MAIL FROM:<$user>\r\n");
    fgets($smtp, 1024);
    
    fwrite($smtp, "RCPT TO:<$to>\r\n");
    fgets($smtp, 1024);
    
    fwrite($smtp, "DATA\r\n");
    fgets($smtp, 1024);
    
    $msg = "From: Emergency Trigger <$user>\r\n";
    $msg .= "To: $to\r\n";
    $msg .= "Subject: $subject\r\n";
    $msg .= "MIME-Version: 1.0\r\n";
    $msg .= "Content-Type: text/html; charset=UTF-8\r\n\r\n";
    $msg .= $body . "\r\n.\r\n";
    
    fwrite($smtp, $msg);
    $result = fgets($smtp, 1024);
    
    fwrite($smtp, "QUIT\r\n");
    fclose($smtp);
    
    return substr($result, 0, 3) === '250';
}

/**
 * Verify OTP
 */
function verifyOTP() {
    $data = json_decode(file_get_contents('php://input'), true) ?? [];
    
    if (empty($data['email']) || empty($data['otp'])) {
        echo json_encode(['success' => false, 'error' => 'Email and code required']);
        return;
    }
    
    $email = trim(strtolower($data['email']));
    $otp = trim($data['otp']);
    
    try {
        $db = getDB();
        
        // Find valid OTP
        $stmt = $db->prepare("
            SELECT id, otp, expires_at FROM otp_codes 
            WHERE email = ? AND verified = 0
            ORDER BY id DESC LIMIT 1
        ");
        $stmt->execute([$email]);
        $record = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$record) {
            echo json_encode(['success' => false, 'error' => 'No OTP found for this email']);
            return;
        }
        
        // Check if expired
        if (strtotime($record['expires_at']) < time()) {
            echo json_encode(['success' => false, 'error' => 'Code expired. Request new code.']);
            return;
        }
        
        // Check OTP match
        if ($record['otp'] !== $otp) {
            echo json_encode(['success' => false, 'error' => 'Wrong code. Please try again.']);
            return;
        }
        
        // Mark as verified
        $stmt = $db->prepare("UPDATE otp_codes SET verified = 1 WHERE id = ?");
        $stmt->execute([$record['id']]);
        
        // Mark user as verified
        $stmt = $db->prepare("UPDATE users SET is_verified = 1 WHERE LOWER(email) = ?");
        $stmt->execute([$email]);
        
        echo json_encode([
            'success' => true,
            'data' => [
                'message' => 'Email verified successfully!',
                'verified' => true
            ]
        ]);
        
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => 'Error: ' . $e->getMessage()]);
    }
}
