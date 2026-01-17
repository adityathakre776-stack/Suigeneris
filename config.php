<?php
/**
 * Database Configuration
 */

define('DB_HOST', 'localhost');
define('DB_NAME', 'emergency_app');
define('DB_USER', 'root');
define('DB_PASS', '');

// JWT Secret for tokens
define('JWT_SECRET', 'your-super-secret-key-change-this-in-production');
define('TOKEN_EXPIRY', 30 * 24 * 60 * 60); // 30 days

/**
 * Get database connection
 */
function getDB() {
    static $pdo = null;
    
    if ($pdo === null) {
        try {
            $pdo = new PDO(
                "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
                DB_USER,
                DB_PASS,
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false
                ]
            );
        } catch (PDOException $e) {
            sendError('Database connection failed', 500);
            exit;
        }
    }
    
    return $pdo;
}

/**
 * Send JSON response
 */
function sendResponse($data, $code = 200) {
    http_response_code($code);
    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'data' => $data
    ]);
    exit;
}

/**
 * Send error response
 */
function sendError($message, $code = 400) {
    http_response_code($code);
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'error' => $message
    ]);
    exit;
}

/**
 * Generate auth token
 */
function generateToken($userId) {
    $token = bin2hex(random_bytes(32));
    $expiresAt = date('Y-m-d H:i:s', time() + TOKEN_EXPIRY);
    
    $db = getDB();
    $stmt = $db->prepare("INSERT INTO sessions (user_id, token, device_info, ip_address, expires_at) VALUES (?, ?, ?, ?, ?)");
    $stmt->execute([
        $userId,
        $token,
        $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown',
        $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0',
        $expiresAt
    ]);
    
    return $token;
}

/**
 * Verify auth token and get user
 */
function verifyToken($token) {
    $db = getDB();
    
    $stmt = $db->prepare("
        SELECT s.user_id, u.* 
        FROM sessions s 
        JOIN users u ON s.user_id = u.id 
        WHERE s.token = ? AND s.expires_at > NOW()
    ");
    $stmt->execute([$token]);
    $user = $stmt->fetch();
    
    if (!$user) {
        return null;
    }
    
    return $user;
}

/**
 * Get authenticated user from request
 */
function getAuthUser() {
    $headers = getallheaders();
    $token = null;
    
    // Check Authorization header
    if (isset($headers['Authorization'])) {
        $token = str_replace('Bearer ', '', $headers['Authorization']);
    }
    
    // Check query parameter
    if (!$token && isset($_GET['token'])) {
        $token = $_GET['token'];
    }
    
    if (!$token) {
        sendError('Authentication required', 401);
    }
    
    $user = verifyToken($token);
    if (!$user) {
        sendError('Invalid or expired token', 401);
    }
    
    return $user;
}

/**
 * Get request body as JSON
 */
function getRequestBody() {
    $body = file_get_contents('php://input');
    return json_decode($body, true) ?? [];
}

// Enable CORS
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}
