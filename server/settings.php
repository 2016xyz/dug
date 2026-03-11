<?php
session_start();
require 'config.php';
require 'settings_helper.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit;
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>系统设置</title>
    <!-- Vue 3 -->
    <script src="https://unpkg.com/vue@3/dist/vue.global.js"></script>
    <!-- Element Plus -->
    <link rel="stylesheet" href="https://unpkg.com/element-plus/dist/index.css" />
    <script src="https://unpkg.com/element-plus"></script>
    <script src="https://unpkg.com/@element-plus/icons-vue"></script>
    <style>
        body { margin: 0; background-color: #f5f7fa; font-family: 'Segoe UI', sans-serif; }
        .header { background: #fff; box-shadow: 0 2px 4px rgba(0,0,0,0.05); padding: 0 2rem; height: 60px; display: flex; align-items: center; justify-content: space-between; }
        .container { max-width: 800px; margin: 2rem auto; padding: 0 1rem; }
    </style>
</head>
<body>
    <div id="app">
        <header class="header">
            <div style="display: flex; align-items: center;">
                <el-button @click="goBack" circle :icon="ArrowLeft" style="margin-right: 1rem;"></el-button>
                <h1 style="font-size: 1.2rem; margin: 0;">系统设置</h1>
            </div>
        </header>

        <main class="container">
            <el-form :model="form" label-width="150px" v-loading="loading">
                <el-card style="margin-bottom: 20px;">
                    <template #header>基本设置</template>
                    <el-form-item label="网站标题">
                        <el-input v-model="form.site_title"></el-input>
                    </el-form-item>
                    <el-form-item label="修改密码">
                        <el-input v-model="form.new_password" type="password" placeholder="留空则不修改"></el-input>
                    </el-form-item>
                </el-card>

                <el-card style="margin-bottom: 20px;">
                    <template #header>高德地图 API</template>
                    <el-alert title="需要申请高德地图 Web端 (JS API) Key" type="info" :closable="false" style="margin-bottom: 1rem;"></el-alert>
                    <el-form-item label="Web端 Key">
                        <el-input v-model="form.amap_key" placeholder="例如: f1c2..."></el-input>
                    </el-form-item>
                    <el-form-item label="安全密钥 (jscode)">
                        <el-input v-model="form.amap_security_code" placeholder="例如: 8a9b..."></el-input>
                    </el-form-item>
                </el-card>

                <el-card style="margin-bottom: 20px;">
                    <template #header>邮件报警 (SMTP)</template>
                    <el-form-item label="SMTP 服务器">
                        <el-input v-model="form.smtp_host" placeholder="smtp.example.com"></el-input>
                    </el-form-item>
                    <el-form-item label="SMTP 端口">
                        <el-input v-model="form.smtp_port" placeholder="465 (SSL) / 587 (TLS)"></el-input>
                    </el-form-item>
                    <el-form-item label="邮箱账号">
                        <el-input v-model="form.smtp_user"></el-input>
                    </el-form-item>
                    <el-form-item label="邮箱密码">
                        <el-input v-model="form.smtp_pass" type="password" show-password placeholder="授权码"></el-input>
                    </el-form-item>
                    <el-form-item label="接收报警邮箱">
                        <el-input v-model="form.alert_email"></el-input>
                    </el-form-item>
                    <el-form-item label="离线阈值 (分钟)">
                        <el-input-number v-model="form.offline_threshold" :min="1"></el-input-number>
                    </el-form-item>
                </el-card>

                <div style="text-align: center; margin-bottom: 40px;">
                    <el-button type="primary" size="large" @click="saveSettings" :loading="saving">保存设置</el-button>
                </div>
            </el-form>
        </main>
    </div>

    <script>
        const { createApp, ref, onMounted } = Vue;
        const { ElMessage } = ElementPlus;
        const icons = ElementPlusIconsVue;

        const app = createApp({
            setup() {
                const form = ref({
                    site_title: '',
                    new_password: '',
                    amap_key: '',
                    amap_security_code: '',
                    smtp_host: '',
                    smtp_port: 465,
                    smtp_user: '',
                    smtp_pass: '',
                    alert_email: '',
                    offline_threshold: 10
                });
                const loading = ref(false);
                const saving = ref(false);

                const fetchSettings = async () => {
                    loading.value = true;
                    try {
                        const res = await fetch('settings_api.php');
                        const data = await res.json();
                        if (data.status === 'success') {
                            // Merge
                            Object.keys(data.data).forEach(k => {
                                if (form.value.hasOwnProperty(k)) {
                                    form.value[k] = data.data[k];
                                }
                            });
                        }
                    } catch (e) {
                        ElMessage.error('加载设置失败');
                    } finally {
                        loading.value = false;
                    }
                };

                const saveSettings = async () => {
                    saving.value = true;
                    try {
                        const res = await fetch('settings_api.php', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json' },
                            body: JSON.stringify(form.value)
                        });
                        const data = await res.json();
                        if (data.status === 'success') {
                            ElMessage.success('保存成功');
                            form.value.new_password = ''; // clear
                        } else {
                            ElMessage.error(data.message);
                        }
                    } catch (e) {
                        ElMessage.error('保存失败');
                    } finally {
                        saving.value = false;
                    }
                };

                const goBack = () => {
                    window.location.href = 'dashboard.php';
                };

                onMounted(() => {
                    fetchSettings();
                });

                return {
                    form,
                    loading,
                    saving,
                    saveSettings,
                    goBack,
                    ArrowLeft: icons.ArrowLeft
                };
            }
        });

        for (const [key, component] of Object.entries(icons)) {
            app.component(key, component);
        }
        app.use(ElementPlus).mount('#app');
    </script>
    <footer style="text-align: center; padding: 20px; color: #999; font-size: 12px; margin-top: 40px;">
        <p>
            <a href="https://github.com/2016xyz/dug" target="_blank" style="color: #999; text-decoration: none;">GitHub: dug</a> 
            | Version 1.0.1
        </p>
    </footer>
</body>
</html>
