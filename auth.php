<?php
/**
 * User Authentication API
 * - POST /auth.php?action=register - Register new user
 * - POST /auth.php?action=login - Login user
 * - GET /auth.php?action=profile - Get user profile
 * - POST /auth.php?action=logout - Logout user
 */

require_once 'config.php';

$action = $_GET['action'] ?? '';

switch ($action) {
    case 'register':
        handleRegister();
        break;
    case 'login':
        handleLogin();
        break;
    case 'profile':
        handleProfile();
        break;
    case 'update':
        handleUpdate();
        break;
    case 'logout':
        handleLogout();
        break;
    default:
        sendError('Invalid action', 400);
}

/**
 * Register new user
 */
function handleRegister() {
    $data = getRequestBody();
    
    // Validate required fields
    if (empty($data['name']) || empty($data['phone']) || empty($data['password'])) {
        sendError('Name, phone and password are required');
    }
    
    $name = trim($data['name']);
    $phone = preg_replace('/[^0-9+]/', '', $data['phone']);
    $email = trim($data['email'] ?? '');
    $password = password_hash($data['password'], PASSWORD_BCRYPT);
    
    // Validate phone
    if (strlen($phone) < 10) {
        sendError('Invalid phone number');
    }
    
    $db = getDB();
    
    // Check if phone already exists
    $stmt = $db->prepare("SELECT id FROM users WHERE phone = ?");
    $stmt->execute([$phone]);
    if ($stmt->fetch()) {
        sendError('Phone number already registered');
    }
    
    // Check if email already exists
    if (!empty($email)) {
        $stmt = $db->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$email]);
        if ($stmt->fetch()) {
            sendError('Email already registered');
        }
    }
    
    // Create user
    $stmt = $db->prepare("INSERT INTO users (name, phone, email, password) VALUES (?, ?, ?, ?)");
    $stmt->execute([$name, $phone, $email ?: null, $password]);
    $userId = $db->lastInsertId();
    
    // Create default settings
    $stmt = $db->prepare("INSERT INTO user_settings (user_id) VALUES (?)");
    $stmt->execute([$userId]);
    
    // Generate token
    $token = generateToken($userId);
    
    // Update last login
    $stmt = $db->prepare("UPDATE users SET last_login = NOW() WHERE id = ?");
    $stmt->execute([$userId]);
    
    sendResponse([
        'message' => 'Registration successful',
        'token' => $token,
        'user' => [
            'id' => $userId,
            'name' => $name,
            'phone' => $phone,
            'email' => $email
        ]
    ]);
}

/**
 * Login user
 */
function handleLogin() {
    $data = getRequestBody();
    
    if (empty($data['phone']) || empty($data['password'])) {
        sendError('Phone and password are required');
    }
    
    $phone = preg_replace('/[^0-9+]/', '', $data['phone']);
    
    $db = getDB();
    $stmt = $db->prepare("SELECT * FROM users WHERE phone = ?");
    $stmt->execute([$phone]);
    $user = $stmt->fetch();
    
    if (!$user || !password_verify($data['password'], $user['password'])) {
        sendError('Invalid phone or password', 401);
    }
    
    if (!$user['is_active']) {
        sendError('Account is disabled', 403);
    }
    
    // Generate token
    $token = generateToken($user['id']);
    
    // Update last login
    $stmt = $db->prepare("UPDATE users SET last_login = NOW() WHERE id = ?");
    $stmt->execute([$user['id']]);
    
    sendResponse([
        'message' => 'Login successful',
        'token' => $token,
        'user' => [
            'id' => $user['id'],
            'name' => $user['name'],
            'phone' => $user['phone'],
            'email' => $user['email'],
            'profile_pic' => $user['profile_pic']
        ]
    ]);
}

/**
 * Get user profile
 */
function handleProfile() {
    $user = getAuthUser();
    
    $db = getDB();
    
    // Get settings
    $stmt = $db->prepare("SELECT * FROM user_settings WHERE user_id = ?");
    $stmt->execute([$user['id']]);
    $settings = $stmt->fetch() ?: [];
    
    // Get emergency contacts
    $stmt = $db->prepare("SELECT * FROM emergency_contacts WHERE user_id = ? ORDER BY is_primary DESC, name ASC");
    $stmt->execute([$user['id']]);
    $contacts = $stmt->fetchAll();
    
    sendResponse([
        'user' => [
            'id' => $user['id'],
            'name' => $user['name'],
            'phone' => $user['phone'],
            'email' => $user['email'],
            'profile_pic' => $user['profile_pic'],
            'is_verified' => (bool)$user['is_verified'],
            'created_at' => $user['created_at']
        ],
        'settings' => $settings,
        'contacts' => $contacts
    ]);
}

/**
 * Update user profile
 */
function handleUpdate() {
    $user = getAuthUser();
    $data = getRequestBody();
    
    $db = getDB();
    
    $updates = [];
    $params = [];
    
    if (isset($data['name'])) {
        $updates[] = "name = ?";
        $params[] = trim($data['name']);
    }
    
    if (isset($data['email'])) {
        $updates[] = "email = ?";
        $params[] = trim($data['email']);
    }
    
    if (!empty($updates)) {
        $params[] = $user['id'];
        $sql = "UPDATE users SET " . implode(', ', $updates) . " WHERE id = ?";
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
    }
    
    sendResponse(['message' => 'Profile updated']);
}

/**
 * Logout user
 */
function handleLogout() {
    $headers = getallheaders();
    $token = str_replace('Bearer ', '', $headers['Authorization'] ?? '');
    
    if ($token) {
        $db = getDB();
        $stmt = $db->prepare("DELETE FROM sessions WHERE token = ?");
        $stmt->execute([$token]);
    }
    
    sendResponse(['message' => 'Logged out successfully']);
}
