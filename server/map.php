<?php
session_start();
require 'config.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit;
}

$imei = $_GET['imei'] ?? '';
if (!$imei) {
    die("IMEI required");
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>设备轨迹 - <?php echo htmlspecialchars($imei); ?></title>
    <!-- Vue 3 -->
    <script src="https://unpkg.com/vue@3/dist/vue.global.js"></script>
    <!-- Element Plus -->
    <link rel="stylesheet" href="https://unpkg.com/element-plus/dist/index.css" />
    <script src="https://unpkg.com/element-plus"></script>
    <style>
        body { margin: 0; padding: 0; font-family: 'Segoe UI', sans-serif; height: 100vh; display: flex; flex-direction: column; }
        #map { flex: 1; width: 100%; }
        .control-panel {
            position: absolute;
            top: 20px;
            left: 20px;
            z-index: 100;
            background: white;
            padding: 15px;
            border-radius: 8px;
            box-shadow: 0 2px 12px 0 rgba(0,0,0,0.1);
            width: 300px;
        }
    </style>
</head>
<body>
    <div id="app" style="height: 100%; display: flex; flex-direction: column;">
        <div class="control-panel">
            <h3>轨迹回放</h3>
            <el-form label-position="top">
                <el-form-item label="时间范围">
                    <el-radio-group v-model="timeRange" @change="fetchData">
                        <el-radio-button label="1h">1h</el-radio-button>
                        <el-radio-button label="6h">6h</el-radio-button>
                        <el-radio-button label="12h">12h</el-radio-button>
                        <el-radio-button label="24h">24h</el-radio-button>
                    </el-radio-group>
                </el-form-item>
                <el-button type="primary" @click="fetchData" :loading="loading" style="width: 100%">刷新</el-button>
                <el-button @click="goBack" style="width: 100%; margin-top: 10px; margin-left: 0;">返回详情页</el-button>
            </el-form>
            <div style="margin-top: 10px; font-size: 12px; color: #666;">
                点数: {{ points.length }}
            </div>
        </div>
        <div id="map"></div>
    </div>

    <script>
        const { createApp, ref, onMounted } = Vue;
        const { ElMessage } = ElementPlus;

        createApp({
            setup() {
                const imei = "<?php echo $imei; ?>";
                const timeRange = ref('1h');
                const loading = ref(false);
                const amapKey = ref('');
                const amapSecurityCode = ref('');
                const points = ref([]);
                
                let map = null;
                let polyline = null;
                let startMarker = null;
                let endMarker = null;

                const fetchSettings = async () => {
                    try {
                        const res = await fetch('settings_api.php');
                        const data = await res.json();
                        if (data.status === 'success') {
                            amapKey.value = data.data.amap_key;
                            amapSecurityCode.value = data.data.amap_security_code;
                            if (amapKey.value) {
                                loadAMap();
                            } else {
                                ElMessage.warning('请先在设置中配置高德地图Key');
                            }
                        }
                    } catch(e) {}
                };

                const loadAMap = () => {
                    window._AMapSecurityConfig = {
                        securityJsCode: amapSecurityCode.value,
                    };
                    const script = document.createElement('script');
                    script.src = `https://webapi.amap.com/maps?v=2.0&key=${amapKey.value}`;
                    script.onload = () => {
                        initMap();
                        fetchData();
                    };
                    document.head.appendChild(script);
                };

                const initMap = () => {
                    map = new AMap.Map('map', {
                        zoom: 11,
                        viewMode: '2D'
                    });
                };

                const fetchData = async () => {
                    if (!map) return;
                    loading.value = true;
                    try {
                        const res = await fetch(`get_device_detail.php?imei=${imei}&range=${timeRange.value}`);
                        const data = await res.json();
                        if (data.status === 'success') {
                            const logs = data.logs;
                            // Extract valid points
                            points.value = logs
                                .filter(l => l.gps_lat && l.gps_lng && Math.abs(l.gps_lat) > 0.1)
                                .map(l => [l.gps_lng, l.gps_lat]); // AMap: [lng, lat]
                            
                            drawPath();
                        }
                    } catch (e) {
                        ElMessage.error('获取轨迹失败');
                    } finally {
                        loading.value = false;
                    }
                };

                const drawPath = () => {
                    if (polyline) map.remove(polyline);
                    if (startMarker) map.remove(startMarker);
                    if (endMarker) map.remove(endMarker);

                    if (points.value.length === 0) {
                        ElMessage.info('该时间段无轨迹数据');
                        return;
                    }

                    polyline = new AMap.Polyline({
                        path: points.value,
                        strokeColor: "#3366FF", 
                        strokeWeight: 6,
                        strokeOpacity: 0.9,
                        zIndex: 50,
                        showDir: true
                    });
                    map.add(polyline);

                    // Start Point
                    startMarker = new AMap.Marker({
                        position: points.value[0],
                        title: '起点',
                        label: { content: '起点', offset: new AMap.Pixel(0, -20) }
                    });
                    map.add(startMarker);

                    // End Point
                    endMarker = new AMap.Marker({
                        position: points.value[points.value.length - 1],
                        title: '终点',
                        label: { content: '终点', offset: new AMap.Pixel(0, -20) }
                    });
                    map.add(endMarker);

                    map.setFitView([polyline, startMarker, endMarker]);
                };

                const goBack = () => {
                    window.location.href = `device.php?imei=${imei}`;
                };

                onMounted(() => {
                    fetchSettings();
                });

                return {
                    timeRange,
                    loading,
                    points,
                    fetchData,
                    goBack
                };
            }
        }).use(ElementPlus).mount('#app');
    </script>
    <footer style="text-align: center; padding: 10px; color: #999; font-size: 12px; position: fixed; bottom: 0; width: 100%; background: rgba(255,255,255,0.8); pointer-events: none;">
        <p style="pointer-events: auto;">
            <a href="https://github.com/2016xyz/dug" target="_blank" style="color: #666; text-decoration: none;">GitHub: dug</a> 
            | Version 1.0.1
        </p>
    </footer>
</body>
</html>
