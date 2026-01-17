<?php
/**
 * 🚨 Emergency Trigger Dashboard
 * 
 * Real-time monitoring dashboard showing:
 * - APK connection status (online/offline)
 * - Heartbeat monitoring
 * - Emergency alerts
 * - Device information
 */

// Configuration
define('LOG_FILE', __DIR__ . '/emergency_log.json');
define('HEARTBEAT_FILE', __DIR__ . '/heartbeat.json');
define('NOTIFICATION_EMAIL', 'your-email@example.com');
define('HEARTBEAT_TIMEOUT', 60); // Seconds before device considered offline

// CORS headers
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, X-Emergency-Trigger, X-Heartbeat');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Handle different request types
$action = $_GET['action'] ?? '';

switch ($action) {
    case 'heartbeat':
        handleHeartbeat();
        break;
    case 'status':
        header('Content-Type: application/json');
        echo json_encode(getStatus());
        break;
    case 'alerts':
        header('Content-Type: application/json');
        echo json_encode(getAlerts());
        break;
    default:
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            handleTrigger();
        } else {
            showDashboard();
        }
}

/**
 * Handle heartbeat from Android app
 */
function handleHeartbeat() {
    header('Content-Type: application/json');
    
    $rawInput = file_get_contents('php://input');
    $data = json_decode($rawInput, true) ?? [];
    
    $heartbeat = [
        'device_id' => $data['device_id'] ?? 'Unknown',
        'device_name' => $data['device_name'] ?? 'Unknown Device',
        'app_version' => $data['app_version'] ?? '1.0',
        'battery_level' => $data['battery_level'] ?? -1,
        'is_charging' => $data['is_charging'] ?? false,
        'triggers_enabled' => $data['triggers_enabled'] ?? [],
        'ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'Unknown',
        'last_seen' => time(),
        'last_seen_readable' => date('Y-m-d H:i:s')
    ];
    
    // Save heartbeat
    $heartbeats = [];
    if (file_exists(HEARTBEAT_FILE)) {
        $heartbeats = json_decode(file_get_contents(HEARTBEAT_FILE), true) ?? [];
    }
    
    $heartbeats[$heartbeat['device_id']] = $heartbeat;
    file_put_contents(HEARTBEAT_FILE, json_encode($heartbeats, JSON_PRETTY_PRINT));
    
    echo json_encode([
        'success' => true,
        'message' => 'Heartbeat received',
        'server_time' => time()
    ]);
}

/**
 * Get system status
 */
function getStatus() {
    $heartbeats = [];
    if (file_exists(HEARTBEAT_FILE)) {
        $heartbeats = json_decode(file_get_contents(HEARTBEAT_FILE), true) ?? [];
    }
    
    $now = time();
    $devices = [];
    $onlineCount = 0;
    
    foreach ($heartbeats as $id => $hb) {
        $isOnline = ($now - $hb['last_seen']) < HEARTBEAT_TIMEOUT;
        if ($isOnline) $onlineCount++;
        
        $devices[] = [
            'device_id' => $id,
            'device_name' => $hb['device_name'],
            'is_online' => $isOnline,
            'last_seen' => $hb['last_seen'],
            'last_seen_ago' => $now - $hb['last_seen'],
            'battery_level' => $hb['battery_level'],
            'is_charging' => $hb['is_charging'],
            'triggers_enabled' => $hb['triggers_enabled'],
            'ip_address' => $hb['ip_address']
        ];
    }
    
    return [
        'online_devices' => $onlineCount,
        'total_devices' => count($devices),
        'devices' => $devices,
        'server_time' => $now
    ];
}

/**
 * Get alerts
 */
function getAlerts() {
    $alerts = [];
    if (file_exists(LOG_FILE)) {
        $alerts = json_decode(file_get_contents(LOG_FILE), true) ?? [];
    }
    return ['alerts' => $alerts];
}

/**
 * Handle incoming emergency trigger
 */
function handleTrigger() {
    header('Content-Type: application/json');
    
    $rawInput = file_get_contents('php://input');
    $data = json_decode($rawInput, true);
    
    if (!$data) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid JSON payload']);
        return;
    }
    
    $alert = [
        'id' => uniqid('alert_'),
        'trigger_source' => $data['trigger_source'] ?? 'UNKNOWN',
        'timestamp' => $data['timestamp'] ?? time() * 1000,
        'timestamp_readable' => date('Y-m-d H:i:s', ($data['timestamp'] ?? time() * 1000) / 1000),
        'device_id' => $data['device_id'] ?? 'Unknown',
        'ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'Unknown',
        'is_emergency' => $data['emergency'] ?? false,
        'received_at' => date('Y-m-d H:i:s')
    ];
    
    // Log alert
    $alerts = [];
    if (file_exists(LOG_FILE)) {
        $alerts = json_decode(file_get_contents(LOG_FILE), true) ?? [];
    }
    array_unshift($alerts, $alert);
    $alerts = array_slice($alerts, 0, 100);
    file_put_contents(LOG_FILE, json_encode($alerts, JSON_PRETTY_PRINT));
    
    echo json_encode([
        'success' => true,
        'message' => 'Emergency trigger received',
        'alert_id' => $alert['id']
    ]);
}

/**
 * Show dashboard
 */
function showDashboard() {
    $status = getStatus();
    $alertsData = getAlerts();
    $alerts = $alertsData['alerts'];
    
    $recentAlert = $alerts[0] ?? null;
    $isRecentAlert = $recentAlert && (time() - ($recentAlert['timestamp'] / 1000)) < 60;
    ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>🚨 Emergency Trigger Dashboard</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        :root {
            --bg-primary: #0a0a0f;
            --bg-secondary: #12121a;
            --bg-card: #1a1a24;
            --border: #2a2a3a;
            --text-primary: #ffffff;
            --text-secondary: #888899;
            --accent: #6366f1;
            --success: #10b981;
            --danger: #ef4444;
            --warning: #f59e0b;
            --info: #3b82f6;
        }
        
        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            background: var(--bg-primary);
            color: var(--text-primary);
            min-height: 100vh;
            padding: 20px;
        }
        
        .container {
            max-width: 1200px;
            margin: 0 auto;
        }
        
        /* Header */
        .header {
            text-align: center;
            margin-bottom: 30px;
            padding: 20px;
        }
        
        .header h1 {
            font-size: 2rem;
            font-weight: 700;
            margin-bottom: 8px;
            background: linear-gradient(135deg, #6366f1, #a855f7);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }
        
        .header p {
            color: var(--text-secondary);
            font-size: 0.9rem;
        }
        
        .live-indicator {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: rgba(16, 185, 129, 0.1);
            border: 1px solid rgba(16, 185, 129, 0.3);
            padding: 6px 14px;
            border-radius: 20px;
            font-size: 0.8rem;
            color: var(--success);
            margin-top: 12px;
        }
        
        .live-dot {
            width: 8px;
            height: 8px;
            background: var(--success);
            border-radius: 50%;
            animation: pulse 2s infinite;
        }
        
        @keyframes pulse {
            0%, 100% { opacity: 1; transform: scale(1); }
            50% { opacity: 0.5; transform: scale(1.2); }
        }
        
        /* Alert Banner */
        .alert-banner {
            background: linear-gradient(135deg, #dc2626 0%, #b91c1c 100%);
            border-radius: 16px;
            padding: 24px;
            margin-bottom: 24px;
            text-align: center;
            animation: alertPulse 1s infinite;
            display: none;
        }
        
        .alert-banner.active {
            display: block;
        }
        
        @keyframes alertPulse {
            0%, 100% { box-shadow: 0 0 0 0 rgba(239, 68, 68, 0.4); }
            50% { box-shadow: 0 0 0 15px rgba(239, 68, 68, 0); }
        }
        
        .alert-banner h2 {
            font-size: 1.5rem;
            margin-bottom: 8px;
        }
        
        /* Stats Grid */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 16px;
            margin-bottom: 24px;
        }
        
        .stat-card {
            background: var(--bg-card);
            border: 1px solid var(--border);
            border-radius: 16px;
            padding: 20px;
            text-align: center;
            transition: transform 0.2s, border-color 0.2s;
        }
        
        .stat-card:hover {
            transform: translateY(-2px);
            border-color: var(--accent);
        }
        
        .stat-icon {
            font-size: 2rem;
            margin-bottom: 8px;
        }
        
        .stat-value {
            font-size: 2rem;
            font-weight: 700;
            color: var(--accent);
        }
        
        .stat-value.online { color: var(--success); }
        .stat-value.offline { color: var(--danger); }
        .stat-value.warning { color: var(--warning); }
        
        .stat-label {
            color: var(--text-secondary);
            font-size: 0.85rem;
            margin-top: 4px;
        }
        
        /* Device Cards */
        .section-title {
            color: var(--text-secondary);
            font-size: 0.75rem;
            text-transform: uppercase;
            letter-spacing: 1.5px;
            margin-bottom: 16px;
            padding-left: 4px;
        }
        
        .devices-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 16px;
            margin-bottom: 32px;
        }
        
        .device-card {
            background: var(--bg-card);
            border: 1px solid var(--border);
            border-radius: 16px;
            padding: 20px;
            position: relative;
            overflow: hidden;
        }
        
        .device-card.online {
            border-color: rgba(16, 185, 129, 0.3);
        }
        
        .device-card.offline {
            border-color: rgba(239, 68, 68, 0.3);
            opacity: 0.7;
        }
        
        .device-status {
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 3px;
        }
        
        .device-status.online {
            background: linear-gradient(90deg, var(--success), #34d399);
        }
        
        .device-status.offline {
            background: linear-gradient(90deg, var(--danger), #f87171);
        }
        
        .device-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 16px;
        }
        
        .device-name {
            font-size: 1.1rem;
            font-weight: 600;
        }
        
        .device-badge {
            padding: 4px 10px;
            border-radius: 12px;
            font-size: 0.75rem;
            font-weight: 600;
        }
        
        .device-badge.online {
            background: rgba(16, 185, 129, 0.15);
            color: var(--success);
        }
        
        .device-badge.offline {
            background: rgba(239, 68, 68, 0.15);
            color: var(--danger);
        }
        
        .device-info {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 12px;
        }
        
        .device-info-item {
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .device-info-item .icon {
            width: 32px;
            height: 32px;
            background: rgba(99, 102, 241, 0.1);
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.9rem;
        }
        
        .device-info-item .value {
            font-size: 0.9rem;
        }
        
        .device-info-item .label {
            font-size: 0.7rem;
            color: var(--text-secondary);
        }
        
        .triggers-list {
            display: flex;
            gap: 6px;
            flex-wrap: wrap;
            margin-top: 12px;
            padding-top: 12px;
            border-top: 1px solid var(--border);
        }
        
        .trigger-badge {
            padding: 4px 8px;
            border-radius: 6px;
            font-size: 0.7rem;
            font-weight: 500;
        }
        
        .trigger-badge.voice { background: rgba(245, 158, 11, 0.15); color: var(--warning); }
        .trigger-badge.power { background: rgba(239, 68, 68, 0.15); color: var(--danger); }
        .trigger-badge.bluetooth { background: rgba(59, 130, 246, 0.15); color: var(--info); }
        
        /* No Devices */
        .no-devices {
            background: var(--bg-card);
            border: 1px dashed var(--border);
            border-radius: 16px;
            padding: 48px;
            text-align: center;
            margin-bottom: 32px;
        }
        
        .no-devices .icon {
            font-size: 3rem;
            margin-bottom: 16px;
            opacity: 0.5;
        }
        
        .no-devices h3 {
            margin-bottom: 8px;
            color: var(--text-primary);
        }
        
        .no-devices p {
            color: var(--text-secondary);
            font-size: 0.9rem;
        }
        
        /* Webhook Info */
        .webhook-card {
            background: var(--bg-card);
            border: 1px solid var(--border);
            border-radius: 16px;
            padding: 20px;
            margin-bottom: 24px;
        }
        
        .webhook-card h3 {
            font-size: 0.9rem;
            margin-bottom: 12px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .webhook-url {
            background: var(--bg-primary);
            border: 1px solid var(--border);
            border-radius: 8px;
            padding: 12px 16px;
            font-family: 'Consolas', monospace;
            font-size: 0.85rem;
            color: var(--success);
            word-break: break-all;
            cursor: pointer;
            transition: border-color 0.2s;
        }
        
        .webhook-url:hover {
            border-color: var(--accent);
        }
        
        /* Alerts Section */
        .alerts-section {
            margin-top: 24px;
        }
        
        .alert-card {
            background: var(--bg-card);
            border: 1px solid var(--border);
            border-radius: 12px;
            padding: 16px;
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            gap: 16px;
            transition: transform 0.2s;
        }
        
        .alert-card:hover {
            transform: translateX(4px);
        }
        
        .alert-icon {
            width: 44px;
            height: 44px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.2rem;
            flex-shrink: 0;
        }
        
        .alert-icon.voice { background: rgba(245, 158, 11, 0.15); }
        .alert-icon.power { background: rgba(239, 68, 68, 0.15); }
        .alert-icon.bluetooth { background: rgba(59, 130, 246, 0.15); }
        .alert-icon.test { background: rgba(139, 92, 246, 0.15); }
        
        .alert-content {
            flex-grow: 1;
        }
        
        .alert-source {
            font-weight: 600;
            margin-bottom: 2px;
        }
        
        .alert-meta {
            font-size: 0.8rem;
            color: var(--text-secondary);
            display: flex;
            gap: 12px;
        }
        
        .alert-time {
            color: var(--text-secondary);
            font-size: 0.8rem;
            text-align: right;
            min-width: 100px;
        }
        
        /* Empty State */
        .empty-alerts {
            text-align: center;
            padding: 48px;
            color: var(--text-secondary);
        }
        
        /* Footer */
        .footer {
            text-align: center;
            padding: 24px;
            color: var(--text-secondary);
            font-size: 0.8rem;
        }
        
        /* Responsive */
        @media (max-width: 768px) {
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }
            
            .devices-grid {
                grid-template-columns: 1fr;
            }
            
            .device-info {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Header -->
        <div class="header">
            <h1>🚨 Emergency Trigger Dashboard</h1>
            <p>Real-time APK monitoring & alert management</p>
            <div class="live-indicator">
                <div class="live-dot"></div>
                <span>Live • Auto-refreshes every 5s</span>
            </div>
        </div>
        
        <!-- Alert Banner -->
        <div class="alert-banner <?php echo $isRecentAlert ? 'active' : ''; ?>" id="alertBanner">
            <h2>🚨 EMERGENCY ALERT!</h2>
            <p id="alertInfo">
                <?php if ($isRecentAlert): ?>
                    <?php echo htmlspecialchars($recentAlert['trigger_source']); ?> triggered 
                    <?php echo round(time() - ($recentAlert['timestamp'] / 1000)); ?> seconds ago
                <?php endif; ?>
            </p>
        </div>
        
        <!-- Stats Grid -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon">📱</div>
                <div class="stat-value <?php echo $status['online_devices'] > 0 ? 'online' : 'offline'; ?>" id="onlineCount">
                    <?php echo $status['online_devices']; ?>
                </div>
                <div class="stat-label">Devices Online</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon">📊</div>
                <div class="stat-value"><?php echo $status['total_devices']; ?></div>
                <div class="stat-label">Total Devices</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon">🚨</div>
                <div class="stat-value warning"><?php echo count($alerts); ?></div>
                <div class="stat-label">Total Alerts</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon">⏱️</div>
                <div class="stat-value" style="font-size: 1rem;" id="lastAlertTime">
                    <?php echo $recentAlert ? date('H:i:s', $recentAlert['timestamp'] / 1000) : 'N/A'; ?>
                </div>
                <div class="stat-label">Last Alert</div>
            </div>
        </div>
        
        <!-- Webhook Info -->
        <div class="webhook-card">
            <h3>📡 Webhook URL (copy to app)</h3>
            <div class="webhook-url" onclick="copyWebhook(this)">
                <?php echo (isset($_SERVER['HTTPS']) ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'] . strtok($_SERVER['REQUEST_URI'], '?'); ?>
            </div>
        </div>
        
        <!-- Devices Section -->
        <div class="section-title">📱 Connected Devices</div>
        
        <?php if (empty($status['devices'])): ?>
            <div class="no-devices">
                <div class="icon">📱</div>
                <h3>No Devices Connected</h3>
                <p>Install the APK and configure the webhook URL to start monitoring.</p>
            </div>
        <?php else: ?>
            <div class="devices-grid" id="devicesGrid">
                <?php foreach ($status['devices'] as $device): ?>
                    <div class="device-card <?php echo $device['is_online'] ? 'online' : 'offline'; ?>">
                        <div class="device-status <?php echo $device['is_online'] ? 'online' : 'offline'; ?>"></div>
                        <div class="device-header">
                            <div class="device-name"><?php echo htmlspecialchars($device['device_name']); ?></div>
                            <div class="device-badge <?php echo $device['is_online'] ? 'online' : 'offline'; ?>">
                                <?php echo $device['is_online'] ? '● ONLINE' : '○ OFFLINE'; ?>
                            </div>
                        </div>
                        <div class="device-info">
                            <div class="device-info-item">
                                <div class="icon">🔋</div>
                                <div>
                                    <div class="value"><?php echo $device['battery_level'] >= 0 ? $device['battery_level'] . '%' : 'N/A'; ?></div>
                                    <div class="label">Battery<?php echo $device['is_charging'] ? ' ⚡' : ''; ?></div>
                                </div>
                            </div>
                            <div class="device-info-item">
                                <div class="icon">📍</div>
                                <div>
                                    <div class="value"><?php echo htmlspecialchars($device['ip_address']); ?></div>
                                    <div class="label">IP Address</div>
                                </div>
                            </div>
                            <div class="device-info-item">
                                <div class="icon">⏱️</div>
                                <div>
                                    <div class="value"><?php echo $device['last_seen_ago']; ?>s ago</div>
                                    <div class="label">Last Seen</div>
                                </div>
                            </div>
                        </div>
                        <?php if (!empty($device['triggers_enabled'])): ?>
                            <div class="triggers-list">
                                <?php foreach ($device['triggers_enabled'] as $trigger): ?>
                                    <span class="trigger-badge <?php echo strtolower($trigger); ?>">
                                        <?php echo $trigger; ?>
                                    </span>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
        
        <!-- Alerts Section -->
        <div class="alerts-section">
            <div class="section-title">📋 Recent Alerts</div>
            
            <?php if (empty($alerts)): ?>
                <div class="empty-alerts">
                    <p>No alerts received yet. Test the app to see alerts here.</p>
                </div>
            <?php else: ?>
                <div id="alertsList">
                    <?php foreach (array_slice($alerts, 0, 15) as $alert): ?>
                        <?php
                        $sourceClass = strtolower($alert['trigger_source']);
                        $icon = match($alert['trigger_source']) {
                            'VOICE' => '🎤',
                            'POWER_BUTTON' => '🔘',
                            'BLUETOOTH' => '📡',
                            'TEST' => '🧪',
                            default => '⚡'
                        };
                        ?>
                        <div class="alert-card">
                            <div class="alert-icon <?php echo $sourceClass; ?>">
                                <?php echo $icon; ?>
                            </div>
                            <div class="alert-content">
                                <div class="alert-source"><?php echo htmlspecialchars($alert['trigger_source']); ?></div>
                                <div class="alert-meta">
                                    <span>📱 <?php echo htmlspecialchars($alert['device_id']); ?></span>
                                    <span>🌐 <?php echo htmlspecialchars($alert['ip_address']); ?></span>
                                </div>
                            </div>
                            <div class="alert-time"><?php echo htmlspecialchars($alert['timestamp_readable']); ?></div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
        
        <!-- Footer -->
        <div class="footer">
            Emergency Trigger Dashboard v1.0 • Server time: <?php echo date('Y-m-d H:i:s'); ?>
        </div>
    </div>
    
    <script>
        // Copy webhook URL
        function copyWebhook(el) {
            navigator.clipboard.writeText(el.textContent.trim());
            el.style.borderColor = '#10b981';
            setTimeout(() => el.style.borderColor = '', 2000);
        }
        
        // Auto-refresh every 5 seconds
        setTimeout(() => location.reload(), 5000);
        
        // Update times dynamically
        setInterval(() => {
            const counter = document.getElementById('onlineCount');
            if (counter) {
                // Could use AJAX to update without full reload
            }
        }, 1000);
    </script>
</body>
</html>
    <?php
}
