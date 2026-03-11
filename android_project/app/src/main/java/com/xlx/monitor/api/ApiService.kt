package com.xlx.monitor.api

import com.xlx.monitor.data.ApiResponse
import com.xlx.monitor.data.InstalledAppsData
import com.xlx.monitor.data.MonitorData
import retrofit2.Response
import retrofit2.Retrofit
import retrofit2.converter.gson.GsonConverterFactory
import retrofit2.http.Body
import retrofit2.http.POST

interface ApiService {
    @POST("api.php")
    suspend fun uploadData(@Body data: MonitorData): Response<ApiResponse>

    @POST("upload_apps.php")
    suspend fun uploadInstalledApps(@Body data: InstalledAppsData): Response<ApiResponse>
}

object NetworkManager {
    private var retrofit: Retrofit? = null
    private var currentUrl: String = ""

    fun getService(baseUrl: String): ApiService {
        // 确保URL以 / 结尾
        val safeUrl = if (baseUrl.endsWith("/")) baseUrl else "$baseUrl/"
        
        if (retrofit == null || currentUrl != safeUrl) {
            currentUrl = safeUrl
            try {
                retrofit = Retrofit.Builder()
                    .baseUrl(currentUrl)
                    .addConverterFactory(GsonConverterFactory.create())
                    .build()
            } catch (e: Exception) {
                // 如果URL非法，返回旧的或者抛出异常，这里简单处理
                throw IllegalArgumentException("Invalid URL: $baseUrl")
            }
        }
        return retrofit!!.create(ApiService::class.java)
    }
}
