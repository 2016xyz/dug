<?php
session_start();
require 'config.php';

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
    <title>监控仪表盘</title>
    <!-- Vue 3 -->
    <script src="https://unpkg.com/vue@3/dist/vue.global.js"></script>
    <!-- Element Plus -->
    <link rel="stylesheet" href="https://unpkg.com/element-plus/dist/index.css" />
    <script src="https://unpkg.com/element-plus"></script>
    <script src="https://unpkg.com/@element-plus/icons-vue"></script>
    <style>
        body { margin: 0; background-color: #f5f7fa; font-family: 'Segoe UI', sans-serif; }
        .header { background: #fff; box-shadow: 0 2px 4px rgba(0,0,0,0.05); padding: 0 2rem; height: 60px; display: flex; align-items: center; justify-content: space-between; }
        .header h1 { font-size: 1.2rem; color: #303133; margin: 0; }
        .container { max-width: 1200px; margin: 2rem auto; padding: 0 1rem; }
        .device-card { margin-bottom: 1rem; cursor: pointer; transition: all 0.3s; }
        .device-card:hover { transform: translateY(-2px); box-shadow: 0 4px 12px rgba(0,0,0,0.1); }
        .status-badge { display: inline-block; width: 10px; height: 10px; border-radius: 50%; margin-right: 5px; }
        .status-online { background-color: #67C23A; }
        .status-offline { background-color: #909399; }
        .app-tag { margin-right: 5px; margin-bottom: 5px; }
    </style>
</head>
<body>
    <div id="app">
        <header class="header">
            <h1><el-icon style="vertical-align: middle; margin-right: 8px;"><Monitor /></el-icon> {{ siteTitle }}</h1>
            <div>
                <el-button type="info" plain size="small" @click="goToLogs" :icon="Document">日志</el-button>
                <el-button type="info" plain size="small" @click="goToSettings" :icon="Setting">设置</el-button>
                <el-button type="danger" plain size="small" @click="logout">退出</el-button>
            </div>
        </header>

        <main class="container">
            <el-row :gutter="20" style="margin-bottom: 20px;">
                <el-col :span="24">
                    <el-card>
                        <div style="display: flex; justify-content: space-between; align-items: center;">
                            <span>在线设备: {{ onlineCount }} / {{ devices.length }}</span>
                            <el-button type="primary" :icon="Refresh" circle @click="fetchDevices" :loading="loading"></el-button>
                        </div>
                    </el-card>
                </el-col>
            </el-row>

            <el-empty v-if="devices.length === 0 && !loading" description="暂无设备连接"></el-empty>

            <el-row :gutter="20">
                <el-col :xs="24" :sm="12" :md="8" v-for="device in devices" :key="device.imei">
                    <el-card class="device-card" @click="goToDetail(device.imei)">
                        <template #header>
                            <div style="display: flex; justify-content: space-between; align-items: center;">
                                <span style="font-weight: bold;">
                                    <span class="status-badge" :class="isOnline(device) ? 'status-online' : 'status-offline'"></span>
                                    {{ device.remark ? device.remark : (device.model || 'Unknown Device') }}
                                </span>
                                <el-tag size="small">{{ device.last_ip }}</el-tag>
                            </div>
                        </template>
                        <div style="font-size: 0.9rem; color: #606266; line-height: 1.8;">
                            <div v-if="device.remark" style="color: #909399; font-size: 0.8rem;">型号: {{ device.manufacturer }} {{ device.model }}</div>
                            <div>IMEI: {{ device.imei }}</div>
                            <div>系统: Android {{ device.android_version }}</div>
                            <div>电量: <el-progress :percentage="device.battery_level || 0" :status="getBatteryStatus(device.battery_level)" style="width: 100px; display: inline-block; vertical-align: middle;"></el-progress></div>
                            <div>更新: {{ formatTime(device.last_update) }}</div>
                        </div>
                    </el-card>
                </el-col>
            </el-row>
        </main>
    </div>

    <script>
        const { createApp, ref, computed, onMounted } = Vue;
        const { ElMessage } = ElementPlus;
        const icons = ElementPlusIconsVue;

        const app = createApp({
            setup() {
                const devices = ref([]);
                const loading = ref(false);
                const siteTitle = ref('设备监控中心');

                const fetchSettings = async () => {
                    try {
                        const res = await fetch('settings_api.php');
                        const data = await res.json();
                        if (data.status === 'success' && data.data.site_title) {
                            siteTitle.value = data.data.site_title;
                            document.title = data.data.site_title;
                        }
                    } catch(e) {}
                };

                const fetchDevices = async () => {
                    loading.value = true;
                    try {
                        const res = await fetch('get_devices.php');
                        const data = await res.json();
                        if (data.status === 'success') {
                            devices.value = data.data;
                        }
                    } catch (e) {
                        ElMessage.error('获取设备列表失败');
                    } finally {
                        loading.value = false;
                    }
                };

                const isOnline = (device) => {
                    if (!device.last_update) return false;
                    const last = new Date(device.last_update).getTime();
                    const now = Date.now();
                    // 默认60秒，允许 20秒 的网络延迟缓冲
                    const interval = parseInt(device.upload_interval) || 60000;
                    return (now - last) < (interval + 20000); 
                };

                const onlineCount = computed(() => {
                    return devices.value.filter(d => isOnline(d)).length;
                });

                const formatTime = (str) => {
                    if (!str) return '-';
                    return new Date(str).toLocaleString();
                };

                const getBatteryStatus = (level) => {
                    if (level <= 20) return 'exception';
                    if (level <= 50) return 'warning';
                    return 'success';
                };

                const goToDetail = (imei) => {
                    window.location.href = `device.php?imei=${encodeURIComponent(imei)}`;
                };

                const goToSettings = () => {
                    window.location.href = 'settings.php';
                };
                
                const goToLogs = () => {
                    window.location.href = 'server_logs.php';
                };

                const logout = () => {
                    window.location.href = 'logout.php';
                };

                onMounted(() => {
                    fetchSettings();
                    fetchDevices();
                    setInterval(fetchDevices, 10000); // 10秒自动刷新
                });

                return {
                    devices,
                    loading,
                    siteTitle,
                    fetchDevices,
                    isOnline,
                    onlineCount,
                    formatTime,
                    getBatteryStatus,
                    goToDetail,
                    goToSettings,
                    goToLogs,
                    logout,
                    Refresh: icons.Refresh,
                    Monitor: icons.Monitor,
                    Setting: icons.Setting,
                    Document: icons.Document
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
