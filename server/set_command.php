<?php
// set_command.php
session_start();
require 'config.php';
require 'ip_helper.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$imei = $input['imei'] ?? '';
$command = $input['command'] ?? '';

if ($imei && $command) {
    try {
        $stmt = $pdo->prepare("UPDATE devices SET pending_command = ? WHERE imei = ?");
        $stmt->execute([$command, $imei]);
        
        $cmd_desc = $command;
        if ($command == 'refresh_apps') $cmd_desc = '刷新应用列表';
        
        log_server($pdo, 'INFO', "管理员 ({$_SESSION['user_id']}) 发送指令 '{$cmd_desc}' 给设备 {$imei}");
        echo json_encode(['status' => 'success', 'message' => "Command '$command' set for $imei"]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
} else {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Invalid parameters']);
}
?>
