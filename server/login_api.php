<?php
// login_api.php
session_start();
require 'config.php';
require 'ip_helper.php';
header('Content-Type: application/json');

$input = json_decode(file_get_contents('php://input'), true);
$username = $input['username'] ?? '';
$password = $input['password'] ?? '';
$captcha = $input['captcha'] ?? '';

// 验证码校验
if (empty($_SESSION['captcha']) || strtolower($captcha) !== strtolower($_SESSION['captcha'])) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => '验证码错误']);
    exit;
}

// 登录校验
$stmt = $pdo->prepare("SELECT id, password FROM users WHERE username = ?");
$stmt->execute([$username]);
$user = $stmt->fetch();

if ($user && password_verify($password, $user['password'])) {
    $_SESSION['user_id'] = $user['id'];
    // 重置验证码
    unset($_SESSION['captcha']);
    
    // 记录登录日志
    $ip = $_SERVER['REMOTE_ADDR'];
    log_server($pdo, 'INFO', "用户 {$username} (ID: {$user['id']}) 登录成功，IP: {$ip}");
    
    echo json_encode(['status' => 'success']);
} else {
    // 记录失败日志
    $ip = $_SERVER['REMOTE_ADDR'];
    log_server($pdo, 'WARNING', "用户 {$username} 登录失败 (密码错误)，IP: {$ip}");
    
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => '用户名或密码错误']);
}
?>
