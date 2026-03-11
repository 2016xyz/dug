<?php
// get_devices.php
session_start();
require 'config.php';
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit;
}

try {
    $stmt = $pdo->query("SELECT d.*, 
                         (SELECT running_apps FROM logs WHERE imei = d.imei ORDER BY created_at DESC LIMIT 1) as running_apps,
                         (SELECT battery_level FROM logs WHERE imei = d.imei ORDER BY created_at DESC LIMIT 1) as battery_level
                         FROM devices d ORDER BY last_update DESC");
    $devices = $stmt->fetchAll();
    
    // 处理 JSON 字段
    foreach ($devices as &$device) {
        $device['running_apps'] = json_decode($device['running_apps'] ?? '[]', true);
        $device['installed_apps'] = json_decode($device['installed_apps'] ?? '[]', true);
    }
    
    echo json_encode(['status' => 'success', 'data' => $devices]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
?>
