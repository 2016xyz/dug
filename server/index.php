<?php
session_start();
require 'config.php';

// 如果已登录，跳转到仪表盘
if (isset($_SESSION['user_id'])) {
    header("Location: dashboard.php");
    exit;
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>登录 - Android 监控系统</title>
    <!-- Vue 3 -->
    <script src="https://unpkg.com/vue@3/dist/vue.global.js"></script>
    <!-- Element Plus -->
    <link rel="stylesheet" href="https://unpkg.com/element-plus/dist/index.css" />
    <script src="https://unpkg.com/element-plus"></script>
    <!-- jQuery (可选，用于兼容) -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <style>
        body {
            margin: 0;
            background: linear-gradient(135deg, #4A90E2 0%, #50E3C2 100%); /* Psychological: Calm & Trust */
            height: 100vh;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        .login-card {
            width: 400px;
            background: rgba(255, 255, 255, 0.95);
            border-radius: 12px;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
            padding: 2rem;
        }
        .login-title {
            text-align: center;
            margin-bottom: 2rem;
            color: #2c3e50;
            font-weight: 600;
        }
        .captcha-img {
            cursor: pointer;
            height: 40px;
            border-radius: 4px;
            vertical-align: middle;
        }
    </style>
</head>
<body>
    <div id="app">
        <el-card class="login-card">
            <h2 class="login-title">系统登录</h2>
            <el-form :model="form" label-position="top" @submit.prevent="handleLogin">
                <el-form-item label="用户名">
                    <el-input v-model="form.username" prefix-icon="User" placeholder="请输入用户名"></el-input>
                </el-form-item>
                <el-form-item label="密码">
                    <el-input v-model="form.password" type="password" prefix-icon="Lock" placeholder="请输入密码" show-password></el-input>
                </el-form-item>
                <el-form-item label="验证码">
                    <el-row :gutter="10">
                        <el-col :span="14">
                            <el-input v-model="form.captcha" placeholder="4位验证码"></el-input>
                        </el-col>
                        <el-col :span="10">
                            <img :src="captchaUrl" class="captcha-img" @click="refreshCaptcha" title="点击刷新" />
                        </el-col>
                    </el-row>
                </el-form-item>
                <el-form-item>
                    <el-button type="primary" :loading="loading" @click="handleLogin" style="width: 100%; margin-top: 1rem;">
                        登录
                    </el-button>
                </el-form-item>
            </el-form>
        </el-card>
    </div>

    <footer style="margin-top: 2rem; color: rgba(255,255,255,0.8); font-size: 0.9rem;">
        <p>
            <a href="https://github.com/2016xyz/dug" target="_blank" style="color: rgba(255,255,255,0.9); text-decoration: none;">GitHub: dug</a> 
            | Version 1.0.1
        </p>
    </footer>

    <script>
        const { createApp, ref } = Vue;
        const { ElMessage } = ElementPlus;

        createApp({
            setup() {
                const form = ref({
                    username: '',
                    password: '',
                    captcha: ''
                });
                const loading = ref(false);
                const captchaUrl = ref('captcha.php?' + Date.now());

                const refreshCaptcha = () => {
                    captchaUrl.value = 'captcha.php?' + Date.now();
                };

                const handleLogin = async () => {
                    if (!form.value.username || !form.value.password || !form.value.captcha) {
                        ElMessage.warning('请填写完整信息');
                        return;
                    }

                    loading.value = true;
                    try {
                        const response = await fetch('login_api.php', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json' },
                            body: JSON.stringify(form.value)
                        });
                        const data = await response.json();

                        if (data.status === 'success') {
                            ElMessage.success('登录成功');
                            setTimeout(() => {
                                window.location.href = 'dashboard.php';
                            }, 500);
                        } else {
                            ElMessage.error(data.message || '登录失败');
                            refreshCaptcha();
                        }
                    } catch (error) {
                        ElMessage.error('网络错误');
                    } finally {
                        loading.value = false;
                    }
                };

                return {
                    form,
                    loading,
                    captchaUrl,
                    refreshCaptcha,
                    handleLogin
                };
            }
        }).use(ElementPlus).mount('#app');
    </script>
</body>
</html>
