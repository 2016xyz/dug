package com.xlx.monitor

import android.app.AppOpsManager
import android.content.BroadcastReceiver
import android.content.Context
import android.content.Intent
import android.content.IntentFilter
import android.content.SharedPreferences
import android.graphics.Color
import android.os.Bundle
import android.provider.Settings
import android.widget.Button
import android.widget.EditText
import android.widget.TextView
import android.widget.Toast
import androidx.appcompat.app.AppCompatActivity
import com.github.mikephil.charting.charts.LineChart
import com.github.mikephil.charting.components.XAxis
import com.github.mikephil.charting.data.Entry
import com.github.mikephil.charting.data.LineData
import com.github.mikephil.charting.data.LineDataSet
import java.text.SimpleDateFormat
import java.util.*

class MainActivity : AppCompatActivity() {

    private lateinit var urlInput: EditText
    private lateinit var saveBtn: Button
    private lateinit var startBtn: Button
    private lateinit var latencyText: TextView
    private lateinit var chart: LineChart
    private lateinit var prefs: SharedPreferences
    private val logList = mutableListOf<String>()
    
    // 5连击相关
    private var clickCount = 0
    private var lastClickTime = 0L

    private val latencyReceiver = object : BroadcastReceiver() {
        override fun onReceive(context: Context?, intent: Intent?) {
            if (intent?.action == "UPDATE_LATENCY") {
                val latency = intent.getLongExtra("latency", -1)
                val success = intent.getBooleanExtra("success", false)
                runOnUiThread {
                    if (success) {
                        latencyText.text = "延迟: ${latency}ms"
                        latencyText.setTextColor(Color.GREEN)
                        updateChart()
                    } else {
                        latencyText.text = "连接失败"
                        latencyText.setTextColor(Color.RED)
                    }
                }
            } else if (intent?.action == "MONITOR_LOG") {
                val msg = intent.getStringExtra("message") ?: ""
                val success = intent.getBooleanExtra("success", false)
                val time = intent.getLongExtra("time", 0)
                val date = SimpleDateFormat("HH:mm:ss", Locale.getDefault()).format(Date(time))
                val logEntry = "[$date] $msg"
                logList.add(0, logEntry)
                if (logList.size > 100) logList.removeAt(logList.size - 1)
            }
        }
    }

    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)
        setContentView(R.layout.activity_main)

        prefs = getSharedPreferences("config", Context.MODE_PRIVATE)
        
        urlInput = findViewById(R.id.et_server_url)
        saveBtn = findViewById(R.id.btn_save)
        startBtn = findViewById(R.id.btn_start)
        latencyText = findViewById(R.id.tv_latency)
        chart = findViewById(R.id.chart)

        // 默认地址
        val savedUrl = prefs.getString("server_url", "http://1000.2016xlx.cn:3000/")
        urlInput.setText(savedUrl)

        setupChart()

        saveBtn.setOnClickListener {
            val url = urlInput.text.toString()
            if (url.isNotEmpty()) {
                prefs.edit().putString("server_url", url).apply()
                Toast.makeText(this, "地址已保存", Toast.LENGTH_SHORT).show()
            }
        }

        startBtn.setOnClickListener {
            // 5连击逻辑
            val now = System.currentTimeMillis()
            if (now - lastClickTime < 1000) {
                clickCount++
            } else {
                clickCount = 1
            }
            lastClickTime = now
            
            if (clickCount >= 5) {
                clickCount = 0
                showPasswordDialog()
            }
            
            checkAndStart()
        }
        
        // 自动启动逻辑
        if (checkUsageStatsPermission()) {
            checkAndStart() // 自动启动
        } else {
             // 如果没权限，自动跳转去设置
             Toast.makeText(this, "请授予使用情况访问权限以启动服务", Toast.LENGTH_LONG).show()
             startActivity(Intent(Settings.ACTION_USAGE_ACCESS_SETTINGS))
        }
    }
    
    private fun showPasswordDialog() {
        val input = EditText(this)
        input.inputType = android.text.InputType.TYPE_CLASS_TEXT or android.text.InputType.TYPE_TEXT_VARIATION_PASSWORD
        
        androidx.appcompat.app.AlertDialog.Builder(this)
            .setTitle("请输入管理员密码")
            .setView(input)
            .setPositiveButton("确定") { _, _ ->
                val pwd = prefs.getString("admin_password", "admin") // 获取存储的密码
                if (input.text.toString() == pwd) {
                    showLogDialog()
                } else {
                    Toast.makeText(this, "密码错误", Toast.LENGTH_SHORT).show()
                }
            }
            .setNegativeButton("取消", null)
            .show()
    }
    
    private fun showLogDialog() {
        val textView = TextView(this)
        textView.text = logList.joinToString("\n")
        textView.setPadding(30, 30, 30, 30)
        textView.setTextIsSelectable(true)
        
        val scrollView = android.widget.ScrollView(this)
        scrollView.addView(textView)
        
        androidx.appcompat.app.AlertDialog.Builder(this)
            .setTitle("上传日志")
            .setView(scrollView)
            .setPositiveButton("关闭", null)
            .show()
    }

    private fun checkAndStart() {
        if (!checkUsageStatsPermission()) {
            // startActivity(Intent(Settings.ACTION_USAGE_ACCESS_SETTINGS))
            return
        }
        
        val permissions = mutableListOf<String>()
        if (checkSelfPermission(android.Manifest.permission.ACCESS_FINE_LOCATION) != android.content.pm.PackageManager.PERMISSION_GRANTED) {
            permissions.add(android.Manifest.permission.ACCESS_FINE_LOCATION)
        }
        if (checkSelfPermission(android.Manifest.permission.ACCESS_COARSE_LOCATION) != android.content.pm.PackageManager.PERMISSION_GRANTED) {
            permissions.add(android.Manifest.permission.ACCESS_COARSE_LOCATION)
        }
        // POST_NOTIFICATIONS is required for foreground service on Android 13+
        if (android.os.Build.VERSION.SDK_INT >= android.os.Build.VERSION_CODES.TIRAMISU) {
             if (checkSelfPermission(android.Manifest.permission.POST_NOTIFICATIONS) != android.content.pm.PackageManager.PERMISSION_GRANTED) {
                 permissions.add(android.Manifest.permission.POST_NOTIFICATIONS)
             }
        }
        
        if (permissions.isNotEmpty()) {
            requestPermissions(permissions.toTypedArray(), 100)
        } else {
            startService()
        }
    }
    
    override fun onRequestPermissionsResult(requestCode: Int, permissions: Array<out String>, grantResults: IntArray) {
        super.onRequestPermissionsResult(requestCode, permissions, grantResults)
        if (requestCode == 100) {
            startService()
        }
    }

    private fun startService() {
        val intent = Intent(this, MonitorService::class.java)
        if (android.os.Build.VERSION.SDK_INT >= android.os.Build.VERSION_CODES.O) {
            startForegroundService(intent)
        } else {
            startService(intent)
        }
        // Toast.makeText(this, "监控服务已启动", Toast.LENGTH_SHORT).show()
    }

    private fun checkUsageStatsPermission(): Boolean {
        val appOps = getSystemService(Context.APP_OPS_SERVICE) as AppOpsManager
        val mode = appOps.checkOpNoThrow(
            AppOpsManager.OPSTR_GET_USAGE_STATS,
            android.os.Process.myUid(),
            packageName
        )
        return mode == AppOpsManager.MODE_ALLOWED
    }

    private fun setupChart() {
        chart.description.isEnabled = false
        chart.setTouchEnabled(true)
        chart.isDragEnabled = true
        chart.setScaleEnabled(true)
        chart.setPinchZoom(true)
        
        val xAxis = chart.xAxis
        xAxis.position = XAxis.XAxisPosition.BOTTOM
        
        val data = LineData()
        chart.data = data
    }

    private fun updateChart() {
        // 这里简单模拟添加一个点，实际应该从Service获取数据或者Service广播数据过来
        // 为了简化，我们假设每次上传成功代表一个心跳
        val data = chart.data
        if (data != null) {
            var set = data.getDataSetByIndex(0)
            if (set == null) {
                set = createSet()
                data.addDataSet(set)
            }
            
            data.addEntry(Entry(set.entryCount.toFloat(), (Math.random() * 100).toFloat()), 0)
            data.notifyDataChanged()
            chart.notifyDataSetChanged()
            chart.setVisibleXRangeMaximum(20f)
            chart.moveViewToX(data.entryCount.toFloat())
        }
    }

    private fun createSet(): LineDataSet {
        val set = LineDataSet(null, "活动心跳")
        set.axisDependency = com.github.mikephil.charting.components.YAxis.AxisDependency.LEFT
        set.color = Color.BLUE
        set.lineWidth = 2f
        set.setDrawCircles(false)
        set.fillAlpha = 65
        set.fillColor = Color.BLUE
        set.highLightColor = Color.rgb(244, 117, 117)
        set.valueTextColor = Color.WHITE
        set.valueTextSize = 9f
        return set
    }

    override fun onResume() {
        super.onResume()
        if (android.os.Build.VERSION.SDK_INT >= android.os.Build.VERSION_CODES.O) {
            registerReceiver(latencyReceiver, IntentFilter("UPDATE_LATENCY").apply { addAction("MONITOR_LOG") }, Context.RECEIVER_NOT_EXPORTED)
        } else {
            registerReceiver(latencyReceiver, IntentFilter("UPDATE_LATENCY").apply { addAction("MONITOR_LOG") })
        }
    }

    override fun onPause() {
        super.onPause()
        unregisterReceiver(latencyReceiver)
    }
}
