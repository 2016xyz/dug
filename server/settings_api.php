<?php
session_start();
require 'config.php';
require 'ip_helper.php';
require 'settings_helper.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $settings = get_settings($pdo);
    // 隐藏敏感信息
    $settings['smtp_pass'] = $settings['smtp_pass'] ? '******' : '';
    echo json_encode(['status' => 'success', 'data' => $settings]);
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    
    // 修改密码
    if (!empty($input['new_password'])) {
        $hash = password_hash($input['new_password'], PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
        $stmt->execute([$hash, $_SESSION['user_id']]);
        log_server($pdo, 'INFO', "管理员 ({$_SESSION['user_id']}) 修改了后台登录密码");
    }
    
    // 更新设置
    $fields = ['site_title', 'amap_key', 'amap_security_code', 'smtp_host', 'smtp_port', 'smtp_user', 'alert_email', 'offline_threshold'];
    $updated_fields = [];
    foreach ($fields as $f) {
        if (isset($input[$f])) {
            update_setting($pdo, $f, $input[$f]);
            $updated_fields[] = $f;
        }
    }
    
    // 只有当提供了非掩码密码时才更新 SMTP 密码
    if (!empty($input['smtp_pass']) && $input['smtp_pass'] !== '******') {
        update_setting($pdo, 'smtp_pass', $input['smtp_pass']);
        $updated_fields[] = 'smtp_pass';
    }
    
    if (!empty($updated_fields)) {
        log_server($pdo, 'INFO', "管理员 ({$_SESSION['user_id']}) 更新了系统设置: " . implode(', ', $updated_fields));
    }
    
    echo json_encode(['status' => 'success', 'message' => '设置已保存']);
}
?>
