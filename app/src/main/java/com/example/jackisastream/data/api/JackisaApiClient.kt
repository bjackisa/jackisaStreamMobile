package com.example.jackisastream.data.api

import android.text.Html
import android.util.Log
import com.example.jackisastream.BuildConfig
import com.example.jackisastream.R
import com.example.jackisastream.model.LiveStream
import com.example.jackisastream.model.User
import com.example.jackisastream.model.Video
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.withContext
import okhttp3.FormBody
import okhttp3.OkHttpClient
import okhttp3.Request
import org.json.JSONObject
import java.util.concurrent.TimeUnit

sealed class ApiResult<out T> {
    data class Success<out T>(val data: T) : ApiResult<T>()
    data class Error(val message: String) : ApiResult<Nothing>()
}

data class RemoteFeed(
    val featuredVideos: List<Video>,
    val topVideos: List<Video>,
    val latestVideos: List<Video>,
    val liveStreams: List<LiveStream>
)

class JackisaApiClient(
    private val baseUrl: String = BuildConfig.BASE_URL,
    private val defaultServerKey: String = BuildConfig.API_KEY
) {
    private val tag = "JackisaApiClient"

    private val client = OkHttpClient.Builder()
        .connectTimeout(15, TimeUnit.SECONDS)
        .readTimeout(20, TimeUnit.SECONDS)
        .writeTimeout(20, TimeUnit.SECONDS)
        .build()

    fun getServerKey(): String {
        return if (defaultServerKey.isNotBlank()) defaultServerKey else "1312a113c58715637a94437389326a49"
    }

    suspend fun getVideos(): ApiResult<RemoteFeed> = withContext(Dispatchers.IO) {
        try {
            val url = "$baseUrl/api/v1.0/?type=get_videos"
            val formBody = FormBody.Builder()
                .add("server_key", getServerKey())
                .build()

            val request = Request.Builder()
                .url(url)
                .post(formBody)
                .header("User-Agent", "JackisaStreamAndroid/1.0")
                .build()

            val response = client.newCall(request).execute()
            val body = response.body?.string() ?: ""

            if (!response.isSuccessful || body.isBlank()) {
                return@withContext ApiResult.Error("HTTP Error: ${response.code}")
            }

            val json = JSONObject(body)
            val apiStatus = json.optString("api_status", "")

            if (apiStatus != "200") {
                val errors = json.optJSONObject("errors")
                val errText = errors?.optString("error_text") ?: "Failed to fetch videos ($apiStatus)"
                return@withContext ApiResult.Error(errText)
            }

            val dataObj = json.optJSONObject("data") ?: return@withContext ApiResult.Error("No data in response")

            val featuredList = parseVideoList(dataObj.optJSONArray("featured"))
            val topList = parseVideoList(dataObj.optJSONArray("top"))
            val latestList = parseVideoList(dataObj.optJSONArray("latest"))
            val liveList = parseLiveList(dataObj.optJSONArray("live"))

            ApiResult.Success(
                RemoteFeed(
                    featuredVideos = featuredList,
                    topVideos = topList,
                    latestVideos = latestList,
                    liveStreams = liveList
                )
            )
        } catch (e: Exception) {
            Log.e(tag, "Error fetching videos", e)
            ApiResult.Error(e.message ?: "Network error connecting to $baseUrl")
        }
    }

    suspend fun login(username: String, pass: String): ApiResult<User> = withContext(Dispatchers.IO) {
        try {
            val url = "$baseUrl/api/v1.0/?type=login"
            val formBody = FormBody.Builder()
                .add("server_key", getServerKey())
                .add("username", username.trim())
                .add("password", pass)
                .build()

            val request = Request.Builder()
                .url(url)
                .post(formBody)
                .header("User-Agent", "JackisaStreamAndroid/1.0")
                .build()

            val response = client.newCall(request).execute()
            val body = response.body?.string() ?: ""

            val json = JSONObject(body)
            val apiStatus = json.optString("api_status", "")

            if (apiStatus == "200") {
                val data = json.optJSONObject("data")
                val userId = data?.optString("user_id") ?: "uid_${System.currentTimeMillis()}"
                val token = data?.optString("session_id") ?: data?.optString("cookie") ?: ""
                
                // Return authenticated user
                ApiResult.Success(
                    User(
                        id = userId,
                        username = username,
                        fullName = username.replaceFirstChar { it.uppercase() },
                        email = "$username@jackisa.com",
                        avatarRes = R.drawable.avatar_1,
                        subscriberCount = "1.5K",
                        balance = 250.0,
                        isPro = true,
                        isVerified = true,
                        sessionToken = token
                    )
                )
            } else {
                val errors = json.optJSONObject("errors")
                val errText = errors?.optString("error_text") ?: "Login failed ($apiStatus)"
                ApiResult.Error(errText)
            }
        } catch (e: Exception) {
            Log.e(tag, "Login error", e)
            ApiResult.Error(e.message ?: "Authentication failed")
        }
    }

    suspend fun register(
        username: String,
        email: String,
        pass: String,
        confirmPass: String
    ): ApiResult<String> = withContext(Dispatchers.IO) {
        try {
            val url = "$baseUrl/api/v1.0/?type=register"
            val formBody = FormBody.Builder()
                .add("server_key", getServerKey())
                .add("username", username.trim())
                .add("email", email.trim())
                .add("password", pass)
                .add("confirm_password", confirmPass)
                .add("gender", "male")
                .build()

            val request = Request.Builder()
                .url(url)
                .post(formBody)
                .header("User-Agent", "JackisaStreamAndroid/1.0")
                .build()

            val response = client.newCall(request).execute()
            val body = response.body?.string() ?: ""

            val json = JSONObject(body)
            val apiStatus = json.optString("api_status", "")

            if (apiStatus == "200") {
                val msg = json.optString("message", "Registration successful. You can now login.")
                ApiResult.Success(msg)
            } else {
                val errors = json.optJSONObject("errors")
                val errText = errors?.optString("error_text") ?: "Registration failed ($apiStatus)"
                ApiResult.Error(errText)
            }
        } catch (e: Exception) {
            Log.e(tag, "Registration error", e)
            ApiResult.Error(e.message ?: "Network error during registration")
        }
    }

    private fun parseVideoList(arr: org.json.JSONArray?): List<Video> {
        if (arr == null) return emptyList()
        val list = mutableListOf<Video>()
        for (i in 0 until arr.length()) {
            val obj = arr.optJSONObject(i) ?: continue
            val id = obj.optString("id", "")
            val rawTitle = obj.optString("title", "Untitled Video")
            val cleanTitle = cleanHtml(rawTitle)
            val rawDesc = obj.optString("description", "")
            val cleanDesc = cleanHtml(rawDesc)
            val videoLoc = obj.optString("video_location", "")
            val thumbnail = obj.optString("thumbnail", "")
            val duration = obj.optString("duration", "10:00")
            val views = obj.optLong("views", 0L)
            val timeAgo = obj.optString("time_ago", "Recently")
            val catName = obj.optString("category_name", "General")
            val videoId = obj.optString("video_id", id)

            val ownerObj = obj.optJSONObject("owner")
            val channelName = cleanHtml(ownerObj?.optString("name", "Jackisa Creator") ?: "Jackisa Creator")
            val avatarUrl = ownerObj?.optString("avatar", "")
            val subCount = ownerObj?.optString("subscribe_count", "12K") ?: "12K"
            val isVerified = ownerObj?.optInt("verified", 0) == 1
            val isSubscribed = ownerObj?.optInt("am_i_subscribed", 0) == 1

            val isLiked = obj.optInt("is_liked", 0) == 1
            val isDisliked = obj.optInt("is_disliked", 0) == 1

            val durationSeconds = parseDurationToSeconds(duration)

            list.add(
                Video(
                    id = "remote_$id",
                    title = cleanTitle,
                    description = cleanDesc,
                    channelTitle = channelName,
                    channelAvatarRes = R.drawable.avatar_1,
                    channelAvatarUrl = avatarUrl.ifBlank { null },
                    subscriberCount = subCount,
                    durationText = duration,
                    durationSeconds = durationSeconds,
                    viewsCount = views,
                    viewsFormatted = formatViews(views),
                    likesCount = (views * 0.08).toLong().coerceAtLeast(12L),
                    uploadDateText = timeAgo,
                    category = catName,
                    thumbnailRes = R.drawable.video_thumb_1,
                    thumbnailUrl = thumbnail.ifBlank { null },
                    videoUrl = videoLoc.ifBlank { null },
                    remoteVideoId = videoId,
                    isLive = false,
                    isLiked = isLiked,
                    isDisliked = isDisliked,
                    isSubscribed = isSubscribed,
                    isRemote = true,
                    isVerifiedChannel = isVerified,
                    attachedProductIds = listOf("prod_1", "prod_2")
                )
            )
        }
        return list
    }

    private fun parseLiveList(arr: org.json.JSONArray?): List<LiveStream> {
        if (arr == null) return emptyList()
        val list = mutableListOf<LiveStream>()
        for (i in 0 until arr.length()) {
            val obj = arr.optJSONObject(i) ?: continue
            val id = obj.optString("id", "")
            val rawTitle = obj.optString("title", "Live Broadcast")
            val cleanTitle = cleanHtml(rawTitle)
            val videoLoc = obj.optString("video_location", "")
            val thumbnail = obj.optString("thumbnail", "")
            val catName = obj.optString("category_name", "Live")

            val ownerObj = obj.optJSONObject("owner")
            val streamerName = cleanHtml(ownerObj?.optString("name", "Jackisa Live") ?: "Jackisa Live")
            val avatarUrl = ownerObj?.optString("avatar", "")

            val viewers = (1200..18500).random()

            list.add(
                LiveStream(
                    id = "live_remote_$id",
                    title = "🔴 $cleanTitle",
                    streamerName = streamerName,
                    streamerAvatarRes = R.drawable.avatar_1,
                    streamerAvatarUrl = avatarUrl.ifBlank { null },
                    category = catName,
                    viewerCount = viewers,
                    thumbnailRes = R.drawable.hero_stream_banner,
                    thumbnailUrl = thumbnail.ifBlank { null },
                    streamDurationText = "LIVE",
                    streamTag = "LIVE",
                    streamUrl = videoLoc.ifBlank { null },
                    isRemote = true
                )
            )
        }
        return list
    }

    private fun cleanHtml(text: String): String {
        return try {
            Html.fromHtml(text, Html.FROM_HTML_MODE_LEGACY).toString().trim()
        } catch (_: Exception) {
            text.replace("&#039;", "'")
                .replace("&quot;", "\"")
                .replace("&amp;", "&")
                .replace("&lt;", "<")
                .replace("&gt;", ">")
                .trim()
        }
    }

    private fun parseDurationToSeconds(duration: String): Int {
        val parts = duration.split(":")
        return when (parts.size) {
            3 -> (parts[0].toIntOrNull() ?: 0) * 3600 + (parts[1].toIntOrNull() ?: 0) * 60 + (parts[2].toIntOrNull() ?: 0)
            2 -> (parts[0].toIntOrNull() ?: 0) * 60 + (parts[1].toIntOrNull() ?: 0)
            1 -> parts[0].toIntOrNull() ?: 600
            else -> 600
        }.coerceAtLeast(60)
    }

    private fun formatViews(views: Long): String {
        return when {
            views >= 1_000_000 -> String.format("%.1fM views", views / 1_000_000.0)
            views >= 1_000 -> String.format("%.1fK views", views / 1_000.0)
            else -> "$views views"
        }
    }
}
