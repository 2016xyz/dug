package com.xlx.monitor.data

import com.google.gson.annotations.SerializedName

data class MonitorData(
    @SerializedName("imei") val imei: String,
    @SerializedName("model") val model: String,
    @SerializedName("manufacturer") val manufacturer: String,
    @SerializedName("android_version") val androidVersion: String,
    @SerializedName("sdk_version") val sdkVersion: Int,
    
    @SerializedName("ram") val ram: Float, // %
    @SerializedName("storage") val storage: Float, // %
    
    @SerializedName("ram_total") val ramTotal: Long,
    @SerializedName("ram_used") val ramUsed: Long,
    @SerializedName("storage_total") val storageTotal: Long,
    @SerializedName("storage_used") val storageUsed: Long,
    
    @SerializedName("cpu_temp") val cpuTemp: Float, // C
    @SerializedName("battery_temp") val batteryTemp: Float, // C
    @SerializedName("battery_level") val batteryLevel: Int, // %
    
    @SerializedName("gps_lat") val gpsLat: Double?,
    @SerializedName("gps_lng") val gpsLng: Double?,
    
    @SerializedName("network_type") val networkType: String,
    
    @SerializedName("apps") val apps: List<AppInfo>
)

data class InstalledAppsData(
    @SerializedName("imei") val imei: String,
    @SerializedName("installed_apps") val installedApps: List<InstalledAppInfo>
)

data class InstalledAppInfo(
    @SerializedName("name") val name: String,
    @SerializedName("package") val packageName: String,
    @SerializedName("size") val size: Long
)

data class AppInfo(
    @SerializedName("name") val name: String,
    @SerializedName("package") val packageName: String,
    @SerializedName("last_used") val lastTimeUsed: Long, // timestamp
    @SerializedName("total_time") val totalTimeInForeground: Long, // ms
    @SerializedName("yesterday_time") val yesterdayTimeInForeground: Long, // ms
    @SerializedName("traffic_rx") val trafficRx: Long, // bytes
    @SerializedName("traffic_tx") val trafficTx: Long, // bytes
    @SerializedName("storage_usage") val storageUsage: Long // bytes
)

data class ApiResponse(
    @SerializedName("status") val status: String,
    @SerializedName("message") val message: String?,
    @SerializedName("command") val command: String?,
    @SerializedName("config") val config: DeviceConfig?
)

data class DeviceConfig(
    @SerializedName("upload_interval") val uploadInterval: Long?,
    @SerializedName("gps_enabled") val gpsEnabled: Boolean?
)
