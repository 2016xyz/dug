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
    <title>系统日志</title>
    <!-- Vue 3 -->
    <script src="https://unpkg.com/vue@3/dist/vue.global.js"></script>
    <!-- Element Plus -->
    <link rel="stylesheet" href="https://unpkg.com/element-plus/dist/index.css" />
    <script src="https://unpkg.com/element-plus"></script>
    <script src="https://unpkg.com/@element-plus/icons-vue"></script>
    <style>
        body { margin: 0; background-color: #f5f7fa; font-family: 'Segoe UI', sans-serif; }
        .header { background: #fff; box-shadow: 0 2px 4px rgba(0,0,0,0.05); padding: 0 2rem; height: 60px; display: flex; align-items: center; justify-content: space-between; }
        .container { max-width: 1200px; margin: 2rem auto; padding: 0 1rem; }
    </style>
</head>
<body>
    <div id="app">
        <header class="header">
            <div style="display: flex; align-items: center;">
                <el-button @click="goBack" circle :icon="ArrowLeft" style="margin-right: 1rem;"></el-button>
                <h1 style="font-size: 1.2rem; margin: 0;">服务端日志</h1>
            </div>
            <div>
                <el-button type="primary" size="small" @click="fetchLogs" :loading="loading" :icon="Refresh">刷新</el-button>
            </div>
        </header>

        <main class="container">
            <el-card>
                <el-table :data="logs" style="width: 100%" v-loading="loading">
                    <el-table-column prop="id" label="ID" width="80"></el-table-column>
                    <el-table-column prop="level" label="级别" width="100">
                        <template #default="scope">
                            <el-tag :type="getLevelType(scope.row.level)">{{ scope.row.level }}</el-tag>
                        </template>
                    </el-table-column>
                    <el-table-column prop="message" label="消息内容"></el-table-column>
                    <el-table-column prop="ip" label="来源IP" width="150"></el-table-column>
                    <el-table-column prop="created_at" label="时间" width="180"></el-table-column>
                </el-table>
                <div style="margin-top: 20px; display: flex; justify-content: flex-end;">
                    <el-pagination
                        background
                        layout="prev, pager, next"
                        :total="total"
                        :page-size="20"
                        v-model:current-page="currentPage"
                        @current-change="handlePageChange"
                    />
                </div>
            </el-card>
        </main>
    </div>

    <script>
        const { createApp, ref, onMounted } = Vue;
        const { ElMessage } = ElementPlus;
        const icons = ElementPlusIconsVue;

        const app = createApp({
            setup() {
                const logs = ref([]);
                const loading = ref(false);
                const currentPage = ref(1);
                const total = ref(0);

                const fetchLogs = async () => {
                    loading.value = true;
                    try {
                        const res = await fetch(`get_server_logs.php?page=${currentPage.value}`);
                        const data = await res.json();
                        if (data.status === 'success') {
                            logs.value = data.data;
                            total.value = data.total;
                        }
                    } catch (e) {
                        ElMessage.error('获取日志失败');
                    } finally {
                        loading.value = false;
                    }
                };

                const handlePageChange = (val) => {
                    currentPage.value = val;
                    fetchLogs();
                };

                const getLevelType = (level) => {
                    switch (level) {
                        case 'ERROR': return 'danger';
                        case 'WARN': 
                        case 'WARNING': return 'warning';
                        default: return 'info';
                    }
                };

                const goBack = () => {
                    window.location.href = 'dashboard.php';
                };

                onMounted(() => {
                    fetchLogs();
                });

                return {
                    logs,
                    loading,
                    currentPage,
                    total,
                    fetchLogs,
                    handlePageChange,
                    getLevelType,
                    goBack,
                    Refresh: icons.Refresh,
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
