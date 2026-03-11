<?php
session_start();
require 'config.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit;
}

$imei = $_GET['imei'] ?? '';
if (!$imei) {
    header("Location: dashboard.php");
    exit;
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>设备详情</title>
    <!-- Vue 3 -->
    <script src="https://unpkg.com/vue@3/dist/vue.global.js"></script>
    <!-- Element Plus -->
    <link rel="stylesheet" href="https://unpkg.com/element-plus/dist/index.css" />
    <script src="https://unpkg.com/element-plus"></script>
    <script src="https://unpkg.com/@element-plus/icons-vue"></script>
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    
    <style>
        body { margin: 0; background-color: #f5f7fa; font-family: 'Segoe UI', sans-serif; }
        .header { background: #fff; box-shadow: 0 2px 4px rgba(0,0,0,0.05); padding: 0 2rem; height: 60px; display: flex; align-items: center; justify-content: space-between; }
        .container { max-width: 1400px; margin: 2rem auto; padding: 0 1rem; }
        .card-header { display: flex; justify-content: space-between; align-items: center; }
        .info-item { margin-bottom: 1rem; color: #606266; font-size: 0.9rem; }
        .info-label { font-weight: bold; color: #303133; margin-right: 8px; }
        .chart-container { height: 300px; }
        #map { height: 400px; width: 100%; border-radius: 4px; z-index: 1; }
        .app-list-container { max-height: 400px; overflow-y: auto; }
    </style>
</head>
<body>
    <div id="app">
        <header class="header">
            <div style="display: flex; align-items: center;">
                <el-button @click="goBack" circle :icon="ArrowLeft" style="margin-right: 1rem;"></el-button>
                <h1 style="font-size: 1.2rem; margin: 0;">设备详情: {{ imei }}</h1>
            </div>
            <div>
                <el-button type="primary" size="small" @click="refreshData" :loading="loading" :icon="Refresh">刷新</el-button>
            </div>
        </header>

        <main class="container">
            <!-- 设备基本信息与控制 -->
            <el-row :gutter="20" style="margin-bottom: 20px;">
                <el-col :span="24">
                    <el-card>
                        <template #header>
                            <div class="card-header">
                                <span>基本信息</span>
                                <div>
                                    <el-button type="info" size="small" @click="showPasswordDialog" :icon="Lock">修改设备密码</el-button>
                                    <el-button type="primary" size="small" @click="showRemarkDialog" :icon="Edit">修改备注</el-button>
                                    <el-button type="warning" size="small" @click="showIntervalDialog" :icon="Setting">修改上传间隔</el-button>
                                    <el-tag :type="device.gps_enabled ? 'success' : 'info'" style="margin-right: 10px;">GPS追踪: {{ device.gps_enabled ? '开启' : '关闭' }}</el-tag>
                                    <el-switch v-model="device.gps_enabled" @change="toggleGps" active-text="开启GPS" inactive-text="关闭GPS" :loading="gpsLoading"></el-switch>
                                </div>
                            </div>
                        </template>
                        <el-descriptions border :column="4">
                            <el-descriptions-item label="备注">{{ device.remark || '-' }}</el-descriptions-item>
                            <el-descriptions-item label="上传间隔">{{ formatDuration(device.upload_interval) }}</el-descriptions-item>
                            <el-descriptions-item label="型号">{{ device.manufacturer }} {{ device.model }}</el-descriptions-item>
                            <el-descriptions-item label="系统版本">Android {{ device.android_version }} (SDK {{ device.sdk_version }})</el-descriptions-item>
                            <el-descriptions-item label="IP地址">{{ device.last_ip }}</el-descriptions-item>
                            <el-descriptions-item label="网络类型">
                                <el-tag :type="device.network_type === 'WiFi' ? 'success' : (device.network_type === 'Mobile' ? 'warning' : 'info')">
                                    {{ device.network_type || 'Unknown' }}
                                </el-tag>
                            </el-descriptions-item>
                            <el-descriptions-item label="内存">
                                {{ formatBytes(device.total_ram * (logs.length > 0 ? logs[logs.length-1].ram_usage/100 : 0)) }} / {{ formatBytes(device.total_ram) }}
                                ({{ logs.length > 0 ? logs[logs.length-1].ram_usage.toFixed(1) + '%' : '-' }})
                            </el-descriptions-item>
                            <el-descriptions-item label="存储">
                                {{ formatBytes(device.total_storage * (logs.length > 0 ? logs[logs.length-1].storage_usage/100 : 0)) }} / {{ formatBytes(device.total_storage) }}
                                ({{ logs.length > 0 ? logs[logs.length-1].storage_usage.toFixed(1) + '%' : '-' }})
                            </el-descriptions-item>
                            <el-descriptions-item label="CPU温度">{{ logs.length > 0 ? logs[logs.length-1].cpu_temp.toFixed(1) + '°C' : '-' }}</el-descriptions-item>
                            <el-descriptions-item label="电池温度">{{ logs.length > 0 ? logs[logs.length-1].battery_temp.toFixed(1) + '°C' : '-' }}</el-descriptions-item>
                            <el-descriptions-item label="当前密码">{{ device.admin_password || 'admin' }}</el-descriptions-item>
                            <el-descriptions-item label="最后更新">{{ formatTime(device.last_update) }}</el-descriptions-item>
                        </el-descriptions>
                    </el-card>

                    <!-- Interval Dialog -->
                    <el-dialog v-model="intervalDialogVisible" title="设置上传间隔" width="30%">
                        <el-form>
                            <el-form-item label="间隔时间 (秒)">
                                <el-input-number v-model="newIntervalSeconds" :min="1" :max="86400"></el-input-number>
                                <div style="font-size: 12px; color: #999; margin-top: 5px;">
                                    设置设备向服务端上传数据的频率。建议 60秒 或更长以省电。
                                </div>
                            </el-form-item>
                        </el-form>
                        <template #footer>
                            <span class="dialog-footer">
                                <el-button @click="intervalDialogVisible = false">取消</el-button>
                                <el-button type="primary" @click="saveInterval">确定</el-button>
                            </span>
                        </template>
                    </el-dialog>
                </el-col>
            </el-row>

            <!-- 地图与GPS轨迹 -->
            <el-row :gutter="20" style="margin-bottom: 20px;">
                <el-col :span="24">
                    <el-card>
                        <template #header>
                            <div class="card-header">
                                <span>GPS 实时位置与轨迹</span>
                                <div>
                                    <span v-if="device.last_gps_lat" style="font-size: 0.8rem; color: #999; margin-right: 10px;">
                                        最新坐标: {{ device.last_gps_lat }}, {{ device.last_gps_lng }}
                                    </span>
                                    <el-button type="primary" size="small" @click="viewMap">查看大图轨迹</el-button>
                                </div>
                            </div>
                        </template>
                        <!-- <div id="map"></div> -->
                        <div style="height: 100px; display: flex; align-items: center; justify-content: center; background: #f0f2f5; color: #909399;">
                            点击右上角"查看大图轨迹"以查看详细地图
                        </div>
                    </el-card>
                </el-col>
            </el-row>

            <!-- 图表区域 -->
            <el-row :gutter="20" style="margin-bottom: 20px;">
                <el-col :span="24">
                    <el-card>
                        <div style="display: flex; justify-content: flex-end; margin-bottom: 10px;">
                            <el-radio-group v-model="timeRange" size="small" @change="fetchData">
                                <el-radio-button label="1h">1小时</el-radio-button>
                                <el-radio-button label="6h">6小时</el-radio-button>
                                <el-radio-button label="12h">12小时</el-radio-button>
                                <el-radio-button label="24h">24小时</el-radio-button>
                            </el-radio-group>
                        </div>
                    </el-card>
                </el-col>
            </el-row>

            <el-row :gutter="20" style="margin-bottom: 20px;">
                <el-col :md="8" :sm="24">
                    <el-card>
                        <template #header>内存与存储 (%)</template>
                        <div class="chart-container">
                            <canvas id="usageChart"></canvas>
                        </div>
                    </el-card>
                </el-col>
                <el-col :md="8" :sm="24">
                    <el-card>
                        <template #header>温度 (°C)</template>
                        <div class="chart-container">
                            <canvas id="tempChart"></canvas>
                        </div>
                    </el-card>
                </el-col>
                <el-col :md="8" :sm="24">
                    <el-card>
                        <template #header>电量 (%)</template>
                        <div class="chart-container">
                            <canvas id="batteryChart"></canvas>
                        </div>
                    </el-card>
                </el-col>
            </el-row>

            <!-- 应用列表 -->
            <el-row :gutter="20">
                <el-col :span="12">
                    <el-card>
                        <template #header>
                            <div class="card-header">
                                <span>活跃应用 (Top 20)</span>
                            </div>
                        </template>
                        <el-table :data="runningApps" style="width: 100%" height="400">
                            <el-table-column prop="name" label="应用名称" width="150"></el-table-column>
                            <el-table-column prop="package" label="包名" width="180"></el-table-column>
                            <el-table-column label="最后使用" width="180">
                                <template #default="scope">
                                    {{ formatTime(scope.row.last_used) }}
                                </template>
                            </el-table-column>
                            <el-table-column label="昨日时长" width="120">
                                <template #default="scope">
                                    {{ formatDuration(scope.row.yesterday_time) }}
                                </template>
                            </el-table-column>
                            <el-table-column label="今日时长" width="120">
                                <template #default="scope">
                                    {{ formatDuration(scope.row.total_time) }}
                                </template>
                            </el-table-column>
                            <el-table-column label="流量 (下载/上传)" width="150">
                                <template #default="scope">
                                    <span v-if="scope.row.traffic_rx">
                                        {{ formatBytes(scope.row.traffic_rx) }} / {{ formatBytes(scope.row.traffic_tx) }}
                                    </span>
                                    <span v-else>-</span>
                                </template>
                            </el-table-column>
                            <el-table-column label="存储占用">
                                <template #default="scope">
                                    <span v-if="scope.row.storage_usage">{{ formatBytes(scope.row.storage_usage) }}</span>
                                    <span v-else>-</span>
                                </template>
                            </el-table-column>
                        </el-table>
                    </el-card>
                </el-col>
                <el-col :span="12">
                    <el-card>
                        <template #header>
                            <div class="card-header">
                                <span>已安装应用 ({{ installedApps.length }})</span>
                                <el-button type="warning" size="small" @click="refreshApps" :loading="appLoading">刷新列表</el-button>
                            </div>
                        </template>
                        <div class="app-list-container">
                             <el-table :data="paginatedInstalledApps" style="width: 100%">
                                <el-table-column label="应用信息">
                                    <template #default="scope">
                                        <div>
                                            <strong>{{ scope.row.name }}</strong>
                                            <div style="font-size: 0.8rem; color: #999;">{{ scope.row.package }}</div>
                                        </div>
                                    </template>
                                </el-table-column>
                                <el-table-column label="大小" width="100">
                                    <template #default="scope">
                                        {{ formatBytes(scope.row.size) }}
                                    </template>
                                </el-table-column>
                            </el-table>
                            <el-pagination
                                style="margin-top: 10px;"
                                layout="prev, pager, next"
                                :total="installedApps.length"
                                :page-size="20"
                                @current-change="handlePageChange"
                            />
                        </div>
                    </el-card>
                </el-col>
            </el-row>
        </main>
    </div>

    <script>
        const { createApp, ref, onMounted, computed, watch } = Vue;
        const { ElMessage } = ElementPlus;
        const icons = ElementPlusIconsVue;

        const app = createApp({
            setup() {
                const imei = "<?php echo $imei; ?>";
                const device = ref({});
                const logs = ref([]);
                const runningApps = ref([]);
                const installedApps = ref([]);
                const loading = ref(false);
                const gpsLoading = ref(false);
                const appLoading = ref(false);
                const intervalDialogVisible = ref(false);
                const newIntervalSeconds = ref(60);
                const currentPage = ref(1);
                const timeRange = ref('1h');
                
                // Settings
                const amapKey = ref('');
                const amapSecurityCode = ref('');

                // Charts
                let usageChart = null;
                let tempChart = null;
                let batteryChart = null;
                // Map
                // let map = null;
                // let polyline = null;
                // let marker = null;
                
                const fetchSettings = async () => {
                    try {
                        const res = await fetch('settings_api.php');
                        const data = await res.json();
                        if (data.status === 'success') {
                            amapKey.value = data.data.amap_key;
                            // amapSecurityCode.value = data.data.amap_security_code;
                            
                            // Load AMap Script
                            // if (amapKey.value) {
                            //    loadAMap();
                            // }
                        }
                    } catch(e) {}
                };

                // const loadAMap = () => {
                //    window._AMapSecurityConfig = {
                //        securityJsCode: amapSecurityCode.value,
                //    };
                //    const script = document.createElement('script');
                //    script.src = `https://webapi.amap.com/maps?v=2.0&key=${amapKey.value}`;
                //    script.onload = () => {
                //        updateMap();
                //    };
                //    document.head.appendChild(script);
                // };

                const fetchData = async () => {
                    loading.value = true;
                    try {
                        const res = await fetch(`get_device_detail.php?imei=${imei}&range=${timeRange.value}`);
                        const data = await res.json();
                        if (data.status === 'success') {
                            device.value = data.device;
                            // 转换 boolean
                            device.value.gps_enabled = !!parseInt(device.value.gps_enabled);
                            // 转换数字
                            device.value.total_ram = parseInt(device.value.total_ram || 0);
                            device.value.total_storage = parseInt(device.value.total_storage || 0);
                            device.value.upload_interval = parseInt(device.value.upload_interval || 60000);
                            
                            logs.value = data.logs;
                            runningApps.value = data.running_apps || [];
                            installedApps.value = data.device.installed_apps || [];
                            // 按大小降序排序
                            installedApps.value.sort((a, b) => (b.size || 0) - (a.size || 0));
                            
                            updateCharts();
                            // updateMap();
                        }
                    } catch (e) {
                        ElMessage.error('获取数据失败');
                    } finally {
                        loading.value = false;
                    }
                };

                const viewMap = () => {
                    window.open(`map.php?imei=${imei}`, '_blank');
                };

                const updateCharts = () => {
                    const labels = logs.value.map(l => new Date(l.created_at).toLocaleTimeString());
                    const ramData = logs.value.map(l => l.ram_usage);
                    const storageData = logs.value.map(l => l.storage_usage);
                    const cpuTemp = logs.value.map(l => l.cpu_temp);
                    const battTemp = logs.value.map(l => l.battery_temp);
                    const battLevel = logs.value.map(l => l.battery_level);

                    if (usageChart) usageChart.destroy();
                    usageChart = new Chart(document.getElementById('usageChart'), {
                        type: 'line',
                        data: {
                            labels: labels,
                            datasets: [
                                { label: '内存 (%)', data: ramData, borderColor: '#4CAF50', fill: true, backgroundColor: 'rgba(76, 175, 80, 0.1)' },
                                { label: '存储 (%)', data: storageData, borderColor: '#2196F3', fill: true, backgroundColor: 'rgba(33, 150, 243, 0.1)' }
                            ]
                        },
                        options: { responsive: true, maintainAspectRatio: false, scales: { y: { min: 0, max: 100 } } }
                    });

                    if (tempChart) tempChart.destroy();
                    tempChart = new Chart(document.getElementById('tempChart'), {
                        type: 'line',
                        data: {
                            labels: labels,
                            datasets: [
                                { label: 'CPU (°C)', data: cpuTemp, borderColor: '#FF5722', tension: 0.4 },
                                { label: '电池 (°C)', data: battTemp, borderColor: '#FFC107', tension: 0.4 }
                            ]
                        },
                        options: { responsive: true, maintainAspectRatio: false }
                    });

                    if (batteryChart) batteryChart.destroy();
                    batteryChart = new Chart(document.getElementById('batteryChart'), {
                        type: 'line',
                        data: {
                            labels: labels,
                            datasets: [
                                { label: '电量 (%)', data: battLevel, borderColor: '#9C27B0', fill: true, backgroundColor: 'rgba(156, 39, 176, 0.1)' }
                            ]
                        },
                        options: { responsive: true, maintainAspectRatio: false, scales: { y: { min: 0, max: 100 } } }
                    });
                };

                const updateMap = () => {
                    if (typeof AMap === 'undefined' || !amapKey.value) return;

                    if (!map) {
                        map = new AMap.Map('map', {
                            zoom: 13,
                            center: [116.397428, 39.90923], // Default Beijing
                            viewMode: '2D'
                        });
                    }

                    // 提取所有有效的 GPS 点
                    const latlngs = logs.value
                        .filter(l => l.gps_lat && l.gps_lng && l.gps_lat !== 0)
                        .map(l => [l.gps_lng, l.gps_lat]); // AMap uses [lng, lat]

                    if (latlngs.length > 0) {
                        if (polyline) map.remove(polyline);
                        polyline = new AMap.Polyline({
                            path: latlngs,
                            strokeColor: "#FF33FF", 
                            strokeWeight: 6,
                            strokeOpacity: 0.9,
                            zIndex: 50,
                        });
                        map.add(polyline);

                        // 最新位置标记
                        const lastPoint = latlngs[latlngs.length - 1];
                        if (marker) map.remove(marker);
                        marker = new AMap.Marker({
                            position: lastPoint,
                            title: '最新位置'
                        });
                        map.add(marker);

                        map.setFitView([polyline, marker]);
                    } else if (device.value.last_gps_lat) {
                         // 如果没有轨迹但有最后位置
                        const point = [device.value.last_gps_lng, device.value.last_gps_lat];
                        map.setCenter(point);
                        map.setZoom(15);
                        if (marker) map.remove(marker);
                        marker = new AMap.Marker({
                            position: point,
                            title: '最新位置'
                        });
                        map.add(marker);
                    }
                };

                const toggleGps = async (val) => {
                    gpsLoading.value = true;
                    try {
                        const res = await fetch('toggle_gps.php', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json' },
                            body: JSON.stringify({ imei: imei, enabled: val })
                        });
                        const data = await res.json();
                        if (data.status === 'success') {
                            ElMessage.success(val ? '已发送开启GPS指令' : '已发送关闭GPS指令');
                        } else {
                            ElMessage.error('操作失败');
                            device.value.gps_enabled = !val; // revert
                        }
                    } catch (e) {
                        ElMessage.error('网络错误');
                        device.value.gps_enabled = !val; // revert
                    } finally {
                        gpsLoading.value = false;
                    }
                };
                
                const refreshApps = async () => {
                     appLoading.value = true;
                     try {
                         const res = await fetch('set_command.php', {
                             method: 'POST',
                             headers: { 'Content-Type': 'application/json' },
                             body: JSON.stringify({ imei: imei, command: 'refresh_apps' })
                         });
                         const data = await res.json();
                         if (data.status === 'success') {
                             ElMessage.success('指令已发送，请稍后刷新页面查看');
                         } else {
                             ElMessage.error(data.message);
                         }
                     } catch(e) {
                         ElMessage.error('请求失败');
                     } finally {
                         appLoading.value = false;
                     }
                };

                const showPasswordDialog = () => {
                    const newPass = prompt("请输入新的设备管理员密码 (留空取消):", "");
                    if (newPass !== null && newPass.trim() !== "") {
                        setPassword(newPass.trim());
                    }
                };

                const showRemarkDialog = () => {
                    const newRemark = prompt("请输入设备备注 (留空取消):", device.value.remark || "");
                    if (newRemark !== null) {
                        setRemark(newRemark.trim());
                    }
                };

                const showIntervalDialog = () => {
                    const currentMs = device.value.upload_interval || 60000;
                    newIntervalSeconds.value = Math.floor(currentMs / 1000);
                    intervalDialogVisible.value = true;
                };

                const saveInterval = () => {
                    const intervalMs = newIntervalSeconds.value * 1000;
                    setUploadInterval(intervalMs);
                    intervalDialogVisible.value = false;
                };

                const setUploadInterval = async (interval) => {
                    try {
                        const res = await fetch('update_config.php', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json' },
                            body: JSON.stringify({ imei: imei, upload_interval: interval })
                        });
                        const data = await res.json();
                        if (data.status === 'success') {
                            ElMessage.success('上传间隔已更新');
                            device.value.upload_interval = interval;
                        } else {
                            ElMessage.error(data.message);
                        }
                    } catch(e) {
                        ElMessage.error('请求失败');
                    }
                };

                const setRemark = async (remark) => {
                    try {
                        const res = await fetch('set_remark.php', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json' },
                            body: JSON.stringify({ imei: imei, remark: remark })
                        });
                        const data = await res.json();
                        if (data.status === 'success') {
                            ElMessage.success('备注已更新');
                            device.value.remark = remark;
                        } else {
                            ElMessage.error(data.message);
                        }
                    } catch(e) {
                        ElMessage.error('请求失败');
                    }
                };

                const setPassword = async (newPass) => {
                     try {
                         const res = await fetch('set_password.php', {
                             method: 'POST',
                             headers: { 'Content-Type': 'application/json' },
                             body: JSON.stringify({ imei: imei, password: newPass })
                         });
                         const data = await res.json();
                         if (data.status === 'success') {
                             ElMessage.success('修改密码指令已发送，设备将在下次连接时更新');
                             device.value.admin_password = newPass; // 乐观更新
                         } else {
                             ElMessage.error(data.message);
                         }
                     } catch(e) {
                         ElMessage.error('请求失败');
                     }
                };

                const formatTime = (ts) => {
                    if (!ts) return '-';
                    // 如果是时间戳(数字)
                    if (typeof ts === 'number') return new Date(ts).toLocaleString();
                    // 如果是字符串
                    return new Date(ts).toLocaleString();
                };

                const formatBytes = (bytes) => {
                    if (bytes === 0 || !bytes) return '0 B';
                    const k = 1024;
                    const sizes = ['B', 'KB', 'MB', 'GB', 'TB'];
                    const i = Math.floor(Math.log(bytes) / Math.log(k));
                    return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
                };

                const formatDuration = (ms) => {
                    if (!ms) return '0s';
                    const seconds = Math.floor(ms / 1000);
                    const h = Math.floor(seconds / 3600);
                    const m = Math.floor((seconds % 3600) / 60);
                    const s = seconds % 60;
                    return (h > 0 ? h + 'h ' : '') + (m > 0 ? m + 'm ' : '') + s + 's';
                };

                const goBack = () => {
                    window.location.href = 'dashboard.php';
                };
                
                const paginatedInstalledApps = computed(() => {
                    const start = (currentPage.value - 1) * 20;
                    return installedApps.value.slice(start, start + 20);
                });
                
                const handlePageChange = (val) => {
                    currentPage.value = val;
                };

                onMounted(() => {
                    fetchData();
                    // 自动刷新 (每30秒更新一次图表和位置)
                    setInterval(fetchData, 30000);
                });

                return {
                    imei,
                    device,
                    logs,
                    runningApps,
                    installedApps,
                    loading,
                    gpsLoading,
                    appLoading,
                    toggleGps,
                    viewMap,
                    refreshData: fetchData,
                    refreshApps,
                    formatTime,
                    formatBytes,
                    formatDuration,
                    goBack,
                    showPasswordDialog,
                    showRemarkDialog,
                    showIntervalDialog,
                    intervalDialogVisible,
                    newIntervalSeconds,
                    saveInterval,
                    paginatedInstalledApps,
                    timeRange,
                    currentPage,
                    handlePageChange,
                    Refresh: icons.Refresh,
                    ArrowLeft: icons.ArrowLeft,
                    Lock: icons.Lock,
                    Edit: icons.Edit,
                    Setting: icons.Setting
                };
            }
        });

        for (const [key, component] of Object.entries(icons)) {
            app.component(key, component);
        }
        app.use(ElementPlus).mount('#app');
    </script>
    <footer style="text-align: center; padding: 20px; color: #999; font-size: 12px;">
        <p>
            <a href="https://github.com/2016xyz/dug" target="_blank" style="color: #999; text-decoration: none;">GitHub: dug</a> 
            | Version 1.0.1
        </p>
    </footer>
</body>
</html>
