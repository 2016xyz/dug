<?php
// toggle_gps.php
session_start();
require 'config.php';
require 'ip_helper.php';
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$imei = $input['imei'] ?? '';
$enabled = $input['enabled'] ?? false;

if ($imei) {
    try {
        // 更新数据库状态
        $stmt = $pdo->prepare("UPDATE devices SET gps_enabled = ?, pending_command = ? WHERE imei = ?");
        $command = $enabled ? 'gps_start' : 'gps_stop';
        $stmt->execute([$enabled ? 1 : 0, $command, $imei]);
        
        $action = $enabled ? '开启' : '关闭';
        log_server($pdo, 'INFO', "管理员 ({$_SESSION['user_id']}) 发送 GPS {$action} 指令给设备 {$imei}");
        
        echo json_encode(['status' => 'success', 'message' => "GPS " . ($enabled ? "enabled" : "disabled")]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
} else {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Invalid parameters']);
}
?>
