<?php
// install.php

if (file_exists('install.lock')) {
    header("Location: index.php");
    exit;
}

$message = "";
$messageType = ""; // success or error

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $db_host = $_POST['db_host'] ?? 'localhost';
    $db_name = $_POST['db_name'] ?? 'android_monitor';
    $db_user = $_POST['db_user'] ?? 'root';
    $db_pass = $_POST['db_pass'] ?? '';
    $admin_user = $_POST['admin_user'] ?? 'admin';
    $admin_pass = $_POST['admin_pass'] ?? 'admin';

    try {
        // 1. 尝试连接数据库服务器（不指定库名）
        $pdo = new PDO("mysql:host=$db_host;charset=utf8mb4", $db_user, $db_pass);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        // 2. 创建数据库
        $pdo->exec("CREATE DATABASE IF NOT EXISTS `$db_name` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        
        // 3. 切换到新数据库
        $pdo->exec("USE `$db_name`");

        // 4. 创建表
        // users
        $pdo->exec("CREATE TABLE IF NOT EXISTS users (
            id INT AUTO_INCREMENT PRIMARY KEY,
            username VARCHAR(50) NOT NULL UNIQUE,
            password VARCHAR(255) NOT NULL
        )");

        // devices
        $pdo->exec("CREATE TABLE IF NOT EXISTS devices (
            imei VARCHAR(100) PRIMARY KEY,
            model VARCHAR(100) DEFAULT '',
            manufacturer VARCHAR(100) DEFAULT '',
            android_version VARCHAR(20) DEFAULT '',
            sdk_version INT DEFAULT 0,
            last_ip VARCHAR(50),
            last_gps_lat DOUBLE DEFAULT NULL,
            last_gps_lng DOUBLE DEFAULT NULL,
            gps_enabled TINYINT(1) DEFAULT 0, -- GPS开关
            upload_interval INT DEFAULT 60000, -- 上传间隔(毫秒)
            network_type VARCHAR(20) DEFAULT 'Unknown', -- 网络类型
            installed_apps LONGTEXT, -- 存储所有安装应用的JSON
            pending_command VARCHAR(50) DEFAULT NULL, -- 待执行指令
            admin_password VARCHAR(50) DEFAULT 'admin', -- 设备管理员密码
            is_offline_alerted TINYINT(1) DEFAULT 0,
            total_ram BIGINT DEFAULT 0,
            total_storage BIGINT DEFAULT 0,
            remark VARCHAR(255) DEFAULT '', -- 设备备注
            last_update DATETIME DEFAULT CURRENT_TIMESTAMP
        )");

        // logs
        $pdo->exec("CREATE TABLE IF NOT EXISTS logs (
            id INT AUTO_INCREMENT PRIMARY KEY,
            imei VARCHAR(100),
            ram_usage FLOAT,
            storage_usage FLOAT,
            cpu_temp FLOAT,
            battery_temp FLOAT,
            battery_level INT DEFAULT 0,
            gps_lat DOUBLE DEFAULT NULL,
            gps_lng DOUBLE DEFAULT NULL,
            network_type VARCHAR(20),
            running_apps TEXT,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            INDEX (imei),
            INDEX (created_at)
        )");
        
        // server_logs
        $pdo->exec("CREATE TABLE IF NOT EXISTS server_logs (
            id INT AUTO_INCREMENT PRIMARY KEY,
            level VARCHAR(20) DEFAULT 'INFO', -- INFO, WARN, ERROR
            message TEXT,
            ip VARCHAR(50),
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            INDEX (created_at)
        )");

        // settings
        $pdo->exec("CREATE TABLE IF NOT EXISTS settings (
            `key` VARCHAR(50) PRIMARY KEY,
            `value` TEXT
        )");

        // 初始化默认设置
        $stmt = $pdo->prepare("INSERT IGNORE INTO settings (`key`, `value`) VALUES (?, ?)");
        $defaults = [
            'site_title' => 'Android 监控系统',
            'amap_key' => '', // 高德地图 Web JS Key
            'amap_security_code' => '', // 高德地图 Web 安全密钥
            'smtp_host' => 'smtp.example.com',
            'smtp_port' => '587',
            'smtp_user' => 'user@example.com',
            'smtp_pass' => 'password',
            'smtp_from' => 'user@example.com',
            'smtp_enabled' => '0'
        ];
        
        foreach ($defaults as $k => $v) {
            $stmt->execute([$k, $v]);
        }

        // 创建管理员账户
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE username = ?");
        $stmt->execute([$admin_user]);
        if ($stmt->fetchColumn() == 0) {
            $hashed_pass = password_hash($admin_pass, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("INSERT INTO users (username, password) VALUES (?, ?)");
            $stmt->execute([$admin_user, $hashed_pass]);
        }

        // 写入 install.lock
        file_put_contents('install.lock', 'Installed on ' . date('Y-m-d H:i:s'));
        
        // 创建配置文件 db_config.php
        $config_content = "<?php\n" .
                          "// db_config.php - Auto generated\n" .
                          "\$db_host = '$db_host';\n" .
                          "\$db_name = '$db_name';\n" .
                          "\$db_user = '$db_user';\n" .
                          "\$db_pass = '$db_pass';\n";
        file_put_contents('db_config.php', $config_content);

        $message = "安装成功！请删除 install.php 文件以确保安全。";
        $messageType = "success";

    } catch (PDOException $e) {
        $message = "数据库错误: " . $e->getMessage();
        $messageType = "error";
    }
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <title>安装向导 - Android 监控系统</title>
    <style>
        body { font-family: sans-serif; display: flex; justify-content: center; align-items: center; height: 100vh; background-color: #f0f2f5; margin: 0; }
        .card { background: white; padding: 2rem; border-radius: 8px; box-shadow: 0 4px 12px rgba(0,0,0,0.1); width: 400px; }
        h1 { text-align: center; color: #333; }
        .form-group { margin-bottom: 1rem; }
        label { display: block; margin-bottom: 0.5rem; color: #666; }
        input { width: 100%; padding: 0.5rem; border: 1px solid #ddd; border-radius: 4px; box-sizing: border-box; }
        button { width: 100%; padding: 0.75rem; background-color: #409EFF; color: white; border: none; border-radius: 4px; cursor: pointer; font-size: 1rem; }
        button:hover { background-color: #66b1ff; }
        .message { margin-bottom: 1rem; padding: 0.75rem; border-radius: 4px; text-align: center; }
        .success { background-color: #f0f9eb; color: #67c23a; }
        .error { background-color: #fef0f0; color: #f56c6c; }
    </style>
</head>
<body>
    <div class="card">
        <h1>系统安装</h1>
        <?php if ($message): ?>
            <div class="message <?php echo $messageType; ?>"><?php echo $message; ?></div>
            <?php if ($messageType == 'success'): ?>
                <p style="text-align: center;"><a href="index.php">前往登录页</a></p>
            <?php endif; ?>
        <?php endif; ?>

        <?php if ($messageType != 'success'): ?>
        <form method="POST">
            <div class="form-group">
                <label>数据库主机</label>
                <input type="text" name="db_host" value="localhost" required>
            </div>
            <div class="form-group">
                <label>数据库用户名</label>
                <input type="text" name="db_user" value="root" required>
            </div>
            <div class="form-group">
                <label>数据库密码</label>
                <input type="password" name="db_pass">
            </div>
            <div class="form-group">
                <label>数据库名</label>
                <input type="text" name="db_name" value="android_monitor" required>
            </div>
            <hr>
            <div class="form-group">
                <label>管理员用户名</label>
                <input type="text" name="admin_user" value="admin" required>
            </div>
            <div class="form-group">
                <label>管理员密码</label>
                <input type="password" name="admin_pass" value="admin" required>
            </div>
            <button type="submit">开始安装</button>
        </form>
        <?php endif; ?>
    </div>
</body>
</html>
