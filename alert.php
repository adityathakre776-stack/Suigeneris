<?php
/**
 * Emergency Alert API
 * - POST /alert.php - Log new alert
 * - GET /alert.php - Get alert history
 */

require_once 'config.php';

$user = getAuthUser();
$method = $_SERVER['REQUEST_METHOD'];

switch ($method) {
    case 'GET':
        getAlerts($user);
        break;
    case 'POST':
        createAlert($user);
        break;
    default:
        sendError('Method not allowed', 405);
}

function getAlerts($user) {
    $db = getDB();
    $limit = min((int)($_GET['limit'] ?? 50), 100);
    
    $stmt = $db->prepare("SELECT * FROM alerts WHERE user_id = ? ORDER BY created_at DESC LIMIT ?");
    $stmt->execute([$user['id'], $limit]);
    $alerts = $stmt->fetchAll();
    
    sendResponse(['alerts' => $alerts]);
}

function createAlert($user) {
    $data = getRequestBody();
    
    $db = getDB();
    $stmt = $db->prepare("
        INSERT INTO alerts (user_id, trigger_source, latitude, longitude, address, sms_sent, call_made) 
        VALUES (?, ?, ?, ?, ?, ?, ?)
    ");
    $stmt->execute([
        $user['id'],
        $data['trigger_source'] ?? 'UNKNOWN',
        $data['latitude'] ?? null,
        $data['longitude'] ?? null,
        $data['address'] ?? null,
        $data['sms_sent'] ?? false,
        $data['call_made'] ?? false
    ]);
    
    sendResponse([
        'message' => 'Alert logged',
        'id' => $db->lastInsertId()
    ]);
}
