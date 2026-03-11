<?php
// check_offline.php
// 建议通过 Cron 每分钟运行一次: * * * * * php /path/to/check_offline.php

require_once 'config.php';
require_once 'ip_helper.php';
require_once 'settings_helper.php';
require_once 'smtp.php';

// 检查字段是否存在
try {
    $pdo->query("SELECT is_offline_alerted FROM devices LIMIT 1");
} catch (Exception $e) {
    $pdo->exec("ALTER TABLE devices ADD COLUMN is_offline_alerted TINYINT(1) DEFAULT 0");
}

// 获取设置
$settings = get_settings($pdo);
$threshold = intval($settings['offline_threshold'] ?? 10); // 分钟
$alert_email = $settings['alert_email'] ?? '';
$smtp_host = $settings['smtp_host'] ?? '';
$smtp_port = $settings['smtp_port'] ?? 465;
$smtp_user = $settings['smtp_user'] ?? '';
$smtp_pass = $settings['smtp_pass'] ?? '';

if (!$alert_email || !$smtp_host) {
    // 记录错误日志
    log_server($pdo, 'ERROR', "离线检测任务失败: SMTP 未配置");
    die("SMTP not configured.\n");
}

// 2. 查找离线且未报警的设备
// 逻辑：离线时间超过 全局阈值 且 超过 2倍设备上传间隔
$sql = "SELECT * FROM devices 
        WHERE last_update < DATE_SUB(NOW(), INTERVAL GREATEST(?, CEIL(IFNULL(upload_interval, 60000) / 60000 * 2)) MINUTE) 
        AND is_offline_alerted = 0";
$stmt = $pdo->prepare($sql);
$stmt->execute([$threshold]);
$offline_devices = $stmt->fetchAll();

if (count($offline_devices) > 0) {
    $mailer = new Smtp($smtp_host, $smtp_port, $smtp_user, $smtp_pass);
    
    foreach ($offline_devices as $device) {
        $name = $device['remark'] ? $device['remark'] : ($device['model'] ?: $device['imei']);
        $subject = "设备离线报警: " . $name;
        $body = "您的设备 <b>" . $name . "</b> (IMEI: {$device['imei']}) 已离线超过 {$threshold} 分钟。<br>";
        $body .= "最后在线时间: " . $device['last_update'] . "<br>";
        $body .= "最后IP: " . $device['last_ip'];

        if ($mailer->send($alert_email, $subject, $body)) {
            // 标记已发送
            $update = $pdo->prepare("UPDATE devices SET is_offline_alerted = 1 WHERE imei = ?");
            $update->execute([$device['imei']]);
            
            log_server($pdo, 'WARNING', "设备 {$name} ({$device['imei']}) 离线报警邮件已发送至 {$alert_email}");
            echo "Alert sent for {$device['imei']}\n";
        } else {
            log_server($pdo, 'ERROR', "设备 {$name} ({$device['imei']}) 离线报警邮件发送失败");
            echo "Failed to send alert for {$device['imei']}\n";
        }
    }
} else {
    // echo "No new offline devices.\n";
}

// 3. 重置上线设备的报警状态
$stmt = $pdo->prepare("SELECT imei, model, remark FROM devices WHERE last_update > DATE_SUB(NOW(), INTERVAL ? MINUTE) AND is_offline_alerted = 1");
$stmt->execute([$threshold]);
$online_devices = $stmt->fetchAll();

if (count($online_devices) > 0) {
    $pdo->exec("UPDATE devices SET is_offline_alerted = 0 WHERE last_update > DATE_SUB(NOW(), INTERVAL $threshold MINUTE)");
    foreach ($online_devices as $device) {
        $name = $device['remark'] ? $device['remark'] : ($device['model'] ?: $device['imei']);
        log_server($pdo, 'INFO', "设备 {$name} ({$device['imei']}) 已重新上线，报警状态重置");
    }
}

?>
