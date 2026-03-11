# dug (Android Monitor System)

![Version](https://img.shields.io/badge/version-1.0.1-blue.svg)
![PHP](https://img.shields.io/badge/PHP-8.0%2B-purple.svg)
![Android](https://img.shields.io/badge/Android-8.0%2B-green.svg)

**dug** 是一个轻量级、自托管的 Android 设备监控系统。它包含一个 Android 客户端和一个 PHP 服务端，旨在帮助用户实时追踪设备状态、位置、应用使用情况等信息。

系统界面采用现代化的“平静蓝”与“治愈绿”心理学配色，提供优雅且无感的操作体验。

## ✨ 主要功能

*   **📊 实时仪表盘**：一目了然地查看所有在线/离线设备状态。
*   **📍 精准定位**：集成高德地图，支持实时位置追踪与历史轨迹回放。
*   **📱 设备详情**：
    *   **硬件信息**：型号、安卓版本、SDK、IMEI。
    *   **状态监控**：电量、CPU/电池温度、内存与存储使用率。
    *   **网络信息**：实时 IP 地址、网络类型 (WiFi/4G/5G)。
*   **📦 应用管理**：
    *   获取设备已安装应用列表（按大小排序）。
    *   监控当前运行的活跃应用。
*   **⚡ 远程控制**：
    *   **省电模式**：可远程调整数据上传间隔（秒级设置）。
    *   **GPS 开关**：远程开启或关闭设备 GPS 定位以节省电量。
    *   **安全锁**：远程修改设备端管理密码。
    *   **指令下发**：支持发送自定义指令（如刷新应用列表）。
*   **🛡️ 安全审计**：
    *   完整的服务端操作日志（登录、指令下发、配置修改）。
    *   设备端受密码保护的日志查看功能。

## 🚀 快速开始

### 服务端部署 (PHP)

1.  **环境要求**：
    *   PHP 7.4 或更高版本 (推荐 8.0+)。
    *   MySQL 5.7 或 MariaDB。
    *   Web 服务器 (Nginx/Apache)。

2.  **安装步骤**：
    *   将 `server` 目录下的所有文件上传至您的 Web 服务器根目录。
    *   确保 `server` 目录具有写入权限（用于生成配置文件）。
    *   在浏览器中访问 `http://您的域名/install.php`。
    *   按照安装向导输入数据库信息和管理员账号，点击“开始安装”。
    *   安装完成后，系统会自动锁定安装程序并跳转至登录页。

### 客户端安装 (Android)

1.  **编译 APK**：
    *   使用 Android Studio 打开 `android_project` 目录。
    *   修改 `MonitorService.kt` 中的 `BASE_URL` 为您的服务端地址。
    *   构建并生成 APK (`Build > Build Bundle(s) / APK(s) > Build APK(s)`).

2.  **配置与运行**：
    *   在目标 Android 设备上安装 APK。
    *   打开应用，授予必要的权限（位置、后台运行、自启动等）。
    *   点击“启动服务”，设备即开始向服务端上报数据。

## 📸 截图预览

*(此处可添加仪表盘、设备详情页、地图轨迹页的截图)*

## 🛠️ 技术栈

*   **服务端**：PHP (原生), MySQL, Vue 3, Element Plus
*   **客户端**：Kotlin, Retrofit, Coroutines, MPAndroidChart

## 📝 版本历史

*   **v1.0.1** (2026-03-11)
    *   UI 全面升级：采用心理学配色（平静蓝/治愈绿）。
    *   新增：上传间隔支持秒级设置，AJAX 无感刷新。
    *   优化：GPS 省电逻辑，仅在需要时开启定位。
    *   新增：页脚版权与版本信息。

## 🤝 贡献

欢迎提交 Issue 或 Pull Request 来改进本项目！

## 📄 许可证

本项目采用 MIT 许可证。详情请参阅 [LICENSE](LICENSE) 文件。

---
<p align="center">Made with ❤️ by <a href="https://github.com/2016xyz">2016xyz</a></p>
