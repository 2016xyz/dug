<?php
// get_device_detail.php
session_start();
require 'config.php';
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit;
}

$imei = $_GET['imei'] ?? '';
if (!$imei) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'IMEI required']);
    exit;
}

try {
    // 设备信息
    $stmt = $pdo->prepare("SELECT * FROM devices WHERE imei = ?");
    $stmt->execute([$imei]);
    $device = $stmt->fetch();
    
    if (!$device) {
        http_response_code(404);
        echo json_encode(['status' => 'error', 'message' => 'Device not found']);
        exit;
    }
    
    $device['installed_apps'] = json_decode($device['installed_apps'] ?? '[]', true);

    // 日志数据 (最近100条或更多，支持时间筛选)
    $range = $_GET['range'] ?? '1h';
    $interval = 1; // hours
    switch ($range) {
        case '6h': $interval = 6; break;
        case '12h': $interval = 12; break;
        case '24h': $interval = 24; break;
        default: $interval = 1; break;
    }
    
    // 获取指定时间范围内的所有数据，限制最多1000条以防过多
    $stmt = $pdo->prepare("SELECT ram_usage, storage_usage, cpu_temp, battery_temp, battery_level, gps_lat, gps_lng, created_at FROM logs WHERE imei = ? AND created_at > DATE_SUB(NOW(), INTERVAL ? HOUR) ORDER BY created_at ASC LIMIT 1000");
    $stmt->execute([$imei, $interval]);
    $logs = $stmt->fetchAll();
    
    // 最近的应用
    $stmt = $pdo->prepare("SELECT running_apps FROM logs WHERE imei = ? ORDER BY created_at DESC LIMIT 1");
    $stmt->execute([$imei]);
    $latestAppLog = $stmt->fetch();
    $runningApps = json_decode($latestAppLog['running_apps'] ?? '[]', true);

    echo json_encode([
        'status' => 'success', 
        'device' => $device, 
        'logs' => $logs,
        'running_apps' => $runningApps
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
?>
