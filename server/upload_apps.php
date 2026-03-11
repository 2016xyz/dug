<?php
// upload_apps.php
header('Content-Type: application/json');
require 'config.php';

require 'ip_helper.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['status' => 'error', 'message' => 'Method not allowed']);
    exit;
}

$input = file_get_contents('php://input');
$data = json_decode($input, true);

if (!$data || !isset($data['imei']) || !isset($data['installed_apps'])) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Invalid data']);
    exit;
}

$imei = $data['imei'];
$apps = $data['installed_apps']; // JSON string or array

// 如果上传的是数组，转为JSON字符串
if (is_array($apps)) {
    $apps = json_encode($apps);
}

try {
    $stmt = $pdo->prepare("UPDATE devices SET installed_apps = ?, last_update = NOW() WHERE imei = ?");
    $stmt->execute([$apps, $imei]);

    $app_count = is_array(json_decode($apps, true)) ? count(json_decode($apps, true)) : 0;
    
    // 获取设备备注
    $stmt = $pdo->prepare("SELECT remark, model FROM devices WHERE imei = ?");
    $stmt->execute([$imei]);
    $dev_info = $stmt->fetch();
    $dev_name = ($dev_info && $dev_info['remark']) ? $dev_info['remark'] : $imei;

    log_server($pdo, 'INFO', "设备 {$dev_name} ({$imei}) 已更新安装应用列表，共 {$app_count} 个应用");
    echo json_encode(['status' => 'success']);
} catch (Exception $e) {
    http_response_code(500);
    log_server($pdo, 'ERROR', "更新应用列表失败 ({$imei}): " . $e->getMessage());
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
?>
