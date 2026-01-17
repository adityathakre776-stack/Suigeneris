<?php
/**
 * 🚨 Emergency Trigger API
 * 
 * Additional API endpoints for the Emergency Trigger system
 */

header('Access-Control-Allow-Origin: *');
header('Content-Type: application/json');

$action = $_GET['action'] ?? '';

switch ($action) {
    case 'status':
        // Return system status
        echo json_encode([
            'status' => 'online',
            'timestamp' => time(),
            'php_version' => phpversion()
        ]);
        break;
        
    case 'alerts':
        // Return recent alerts
        $logFile = __DIR__ . '/emergency_log.json';
        $alerts = [];
        
        if (file_exists($logFile)) {
            $alerts = json_decode(file_get_contents($logFile), true) ?? [];
        }
        
        $limit = min((int)($_GET['limit'] ?? 10), 50);
        echo json_encode([
            'success' => true,
            'count' => count($alerts),
            'alerts' => array_slice($alerts, 0, $limit)
        ]);
        break;
        
    case 'clear':
        // Clear all alerts (requires secret key)
        $key = $_GET['key'] ?? '';
        if ($key !== 'your-secret-key') { // Change this!
            http_response_code(403);
            echo json_encode(['error' => 'Unauthorized']);
            break;
        }
        
        $logFile = __DIR__ . '/emergency_log.json';
        file_put_contents($logFile, '[]');
        echo json_encode(['success' => true, 'message' => 'Alerts cleared']);
        break;
        
    case 'test':
        // Test notification
        echo json_encode([
            'success' => true,
            'message' => 'Test successful',
            'received' => $_POST
        ]);
        break;
        
    default:
        echo json_encode([
            'service' => 'Emergency Trigger API',
            'version' => '1.0',
            'endpoints' => [
                '/api.php?action=status' => 'Get system status',
                '/api.php?action=alerts' => 'Get recent alerts',
                '/api.php?action=alerts&limit=20' => 'Get 20 recent alerts',
                '/emergency.php' => 'Webhook endpoint / Dashboard'
            ]
        ]);
}
