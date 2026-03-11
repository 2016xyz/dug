<?php
// config.php

// 检查是否已安装
if (!file_exists(__DIR__ . '/install.lock')) {
    // 如果没有安装，且当前不是 install.php，则跳转
    if (basename($_SERVER['PHP_SELF']) != 'install.php') {
        header("Location: install.php");
        exit;
    }
}

// 如果已安装，加载配置
if (file_exists(__DIR__ . '/db_config.php')) {
    require __DIR__ . '/db_config.php';

    try {
        $pdo = new PDO("mysql:host=$db_host;dbname=$db_name;charset=utf8mb4", $db_user, $db_pass);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        // 如果连接失败，可能是配置错误，或者是数据库被删了
        // 这里可以选择跳转回安装页，或者显示错误
        die("Database Connection Failed: " . $e->getMessage() . " <br>Please check db_config.php or reinstall.");
    }
}
?>