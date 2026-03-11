<?php
// api.php
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

if (!$data) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Invalid JSON']);
    exit;
}

// 提取数据
$imei = $data['imei'] ?? null;
$ram = $data['ram'] ?? 0;
$storage = $data['storage'] ?? 0;
$cpu_temp = $data['cpu_temp'] ?? 0;
$battery_temp = $data['battery_temp'] ?? 0;
$battery_level = $data['battery_level'] ?? 0;
$ram_total = $data['ram_total'] ?? 0;
$ram_used = $data['ram_used'] ?? 0;
$storage_total = $data['storage_total'] ?? 0;
$storage_used = $data['storage_used'] ?? 0;
$gps_lat = $data['gps_lat'] ?? null;
$gps_lng = $data['gps_lng'] ?? null;
$network_type = $data['network_type'] ?? 'Unknown';
$apps = $data['apps'] ?? [];

// 设备信息 (可能为空)
$model = $data['model'] ?? '';
$manufacturer = $data['manufacturer'] ?? '';
$android_version = $data['android_version'] ?? '';
$sdk_version = $data['sdk_version'] ?? 0;

if (!$imei) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'IMEI required']);
    exit;
}

try {
    $client_ip = get_client_ip();

    // 1. 更新设备列表
    // 检查是否有待执行指令和配置
    $stmt = $pdo->prepare("SELECT pending_command, upload_interval, gps_enabled FROM devices WHERE imei = ?");
    $stmt->execute([$imei]);
    $device_row = $stmt->fetch();
    
    $command = $device_row['pending_command'] ?? null;
    $upload_interval = $device_row['upload_interval'] ?? 60000;
    $gps_enabled = isset($device_row['gps_enabled']) ? (bool)$device_row['gps_enabled'] : false;

    // 清空指令 (如果已发送)
    if ($command) {
        $stmt = $pdo->prepare("UPDATE devices SET pending_command = NULL WHERE imei = ?");
        $stmt->execute([$imei]);
        log_server($pdo, 'INFO', "指令 '{$command}' 已发送至设备 {$imei}");
    }

    // 更新设备状态
    $sql = "INSERT INTO devices (imei, model, manufacturer, android_version, sdk_version, last_ip, last_gps_lat, last_gps_lng, network_type, total_ram, total_storage, last_update) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW()) 
            ON DUPLICATE KEY UPDATE 
            last_ip = VALUES(last_ip), 
            network_type = VALUES(network_type),
            total_ram = VALUES(total_ram),
            total_storage = VALUES(total_storage),
            last_update = NOW()";
    
    // 只有当有GPS数据时才更新GPS字段，避免覆盖为NULL
    if ($gps_lat !== null) {
        $sql .= ", last_gps_lat = VALUES(last_gps_lat), last_gps_lng = VALUES(last_gps_lng)";
    }
    // 更新设备基本信息
    if ($model) {
        $sql .= ", model = VALUES(model), manufacturer = VALUES(manufacturer), android_version = VALUES(android_version), sdk_version = VALUES(sdk_version)";
    }

    $stmt = $pdo->prepare($sql);
    $stmt->execute([$imei, $model, $manufacturer, $android_version, $sdk_version, $client_ip, $gps_lat, $gps_lng, $network_type, $ram_total, $storage_total]);

    // 2. 插入日志
    $stmt = $pdo->prepare("INSERT INTO logs (imei, ram_usage, storage_usage, cpu_temp, battery_temp, battery_level, gps_lat, gps_lng, network_type, running_apps, created_at) 
                          VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())");
    $stmt->execute([
        $imei,
        $ram,
        $storage,
        $cpu_temp,
        $battery_temp,
        $battery_level,
        $gps_lat,
        $gps_lng,
        $network_type,
        json_encode($apps)
    ]);
    
    // 记录详细的上传日志
    $ram_percent = number_format($ram, 1);
    $storage_percent = number_format($storage, 1);
    $cpu_t = number_format($cpu_temp, 1);
    $bat_t = number_format($battery_temp, 1);
    $gps_info = ($gps_lat && $gps_lng) ? "GPS: {$gps_lat},{$gps_lng}" : "GPS: 无信号";
    $app_count = count($apps);
    
    // 获取设备备注或型号
    $stmt = $pdo->prepare("SELECT remark, model FROM devices WHERE imei = ?");
    $stmt->execute([$imei]);
    $dev_info = $stmt->fetch();
    $dev_name = ($dev_info && $dev_info['remark']) ? $dev_info['remark'] : ($model ?: $imei);
    
    $log_msg = "设备 [{$dev_name}] ({$imei}) 上传数据成功。IP: {$client_ip}, 网络: {$network_type}, 电量: {$battery_level}%, 内存: {$ram_percent}%, 存储: {$storage_percent}%, 温度: CPU {$cpu_t}°C / 电池 {$bat_t}°C, 运行应用: {$app_count}个, {$gps_info}";
    log_server($pdo, 'INFO', $log_msg);

    // 返回指令和配置给客户端
    $response = [
        'status' => 'success', 
        'timestamp' => time(), 
        'command' => $command,
        'config' => [
            'upload_interval' => (int)$upload_interval,
            'gps_enabled' => (bool)$gps_enabled
        ]
    ];
    echo json_encode($response);

} catch (Exception $e) {
    http_response_code(500);
    log_server($pdo, 'ERROR', "API 错误: " . $e->getMessage());
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
?>
