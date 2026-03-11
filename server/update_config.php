<?php
// update_config.php
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
$interval = $input['upload_interval'] ?? null;

if ($imei && $interval !== null) {
    // Validate interval (min 1000ms, max 1 day?)
    $interval = (int)$interval;
    if ($interval < 1000) $interval = 1000; // Min 1 second

    try {
        $stmt = $pdo->prepare("UPDATE devices SET upload_interval = ? WHERE imei = ?");
        $stmt->execute([$interval, $imei]);
        
        log_server($pdo, 'INFO', "管理员 ({$_SESSION['user_id']}) 更新设备 {$imei} 上传间隔为 {$interval}ms");
        
        echo json_encode(['status' => 'success', 'message' => "Upload interval updated"]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
} else {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Invalid parameters']);
}
?>
