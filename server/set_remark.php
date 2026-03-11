<?php
// set_remark.php
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
$remark = $input['remark'] ?? '';

if ($imei) {
    try {
        $stmt = $pdo->prepare("UPDATE devices SET remark = ? WHERE imei = ?");
        $stmt->execute([$remark, $imei]);
        
        log_server($pdo, 'INFO', "管理员 ({$_SESSION['user_id']}) 修改了设备 {$imei} 的备注为: {$remark}");
        
        echo json_encode(['status' => 'success', 'message' => "Remark updated"]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
} else {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Invalid parameters']);
}
?>