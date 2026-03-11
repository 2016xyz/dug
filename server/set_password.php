<?php
// set_password.php
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
$new_password = $input['password'] ?? '';

if ($imei && $new_password) {
    try {
        // 更新数据库中的密码
        $stmt = $pdo->prepare("UPDATE devices SET admin_password = ?, pending_command = ? WHERE imei = ?");
        // 将新密码作为指令的一部分发送，格式: set_pass:new_password
        $command = "set_pass:" . $new_password;
        $stmt->execute([$new_password, $command, $imei]);
        
        log_server($pdo, 'INFO', "管理员 ({$_SESSION['user_id']}) 修改了设备 {$imei} 的密码");
        
        echo json_encode(['status' => 'success', 'message' => "Password update command sent for $imei"]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
} else {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Invalid parameters']);
}
?>
