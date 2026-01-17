<?php
/**
 * User Settings API
 * - GET /settings.php - Get settings
 * - POST /settings.php - Update settings
 */

require_once 'config.php';

$user = getAuthUser();
$method = $_SERVER['REQUEST_METHOD'];

switch ($method) {
    case 'GET':
        getSettings($user);
        break;
    case 'POST':
        updateSettings($user);
        break;
    default:
        sendError('Method not allowed', 405);
}

function getSettings($user) {
    $db = getDB();
    $stmt = $db->prepare("SELECT * FROM user_settings WHERE user_id = ?");
    $stmt->execute([$user['id']]);
    $settings = $stmt->fetch();
    
    if (!$settings) {
        // Create default settings
        $stmt = $db->prepare("INSERT INTO user_settings (user_id) VALUES (?)");
        $stmt->execute([$user['id']]);
        
        $stmt = $db->prepare("SELECT * FROM user_settings WHERE user_id = ?");
        $stmt->execute([$user['id']]);
        $settings = $stmt->fetch();
    }
    
    sendResponse(['settings' => $settings]);
}

function updateSettings($user) {
    $data = getRequestBody();
    $db = getDB();
    
    // Ensure settings exist
    $stmt = $db->prepare("SELECT id FROM user_settings WHERE user_id = ?");
    $stmt->execute([$user['id']]);
    if (!$stmt->fetch()) {
        $stmt = $db->prepare("INSERT INTO user_settings (user_id) VALUES (?)");
        $stmt->execute([$user['id']]);
    }
    
    $updates = [];
    $params = [];
    
    $allowedFields = ['voice_enabled', 'power_enabled', 'sms_enabled', 'call_enabled', 
                      'emergency_call_number', 'webhook_url', 'trigger_phrase', 'trigger_count'];
    
    foreach ($allowedFields as $field) {
        if (isset($data[$field])) {
            $updates[] = "$field = ?";
            $params[] = $data[$field];
        }
    }
    
    if (!empty($updates)) {
        $params[] = $user['id'];
        $sql = "UPDATE user_settings SET " . implode(', ', $updates) . " WHERE user_id = ?";
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
    }
    
    sendResponse(['message' => 'Settings updated']);
}
