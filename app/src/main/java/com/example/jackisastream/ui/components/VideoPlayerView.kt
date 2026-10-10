package com.example.jackisastream.ui.components

import android.annotation.SuppressLint
import android.webkit.WebChromeClient
import android.webkit.WebView
import android.webkit.WebViewClient
import androidx.compose.animation.AnimatedVisibility
import androidx.compose.animation.fadeIn
import androidx.compose.animation.fadeOut
import androidx.compose.foundation.background
import androidx.compose.foundation.clickable
import androidx.compose.foundation.interaction.MutableInteractionSource
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.Spacer
import androidx.compose.foundation.layout.aspectRatio
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.layout.width
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.FastForward
import androidx.compose.material.icons.filled.FastRewind
import androidx.compose.material.icons.filled.Fullscreen
import androidx.compose.material.icons.filled.FullscreenExit
import androidx.compose.material.icons.filled.Pause
import androidx.compose.material.icons.filled.PlayArrow
import androidx.compose.material.icons.filled.PlayCircle
import androidx.compose.material.icons.filled.Settings
import androidx.compose.material.icons.filled.Videocam
import androidx.compose.material3.ButtonDefaults
import androidx.compose.material3.Icon
import androidx.compose.material3.IconButton
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.OutlinedButton
import androidx.compose.material3.Slider
import androidx.compose.material3.SliderDefaults
import androidx.compose.material3.Surface
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableFloatStateOf
import androidx.compose.runtime.mutableIntStateOf
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.setValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.graphics.Brush
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.layout.ContentScale
import androidx.compose.ui.platform.testTag
import androidx.compose.ui.res.painterResource
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import androidx.compose.ui.viewinterop.AndroidView
import coil.compose.AsyncImage
import com.example.jackisastream.R
import com.example.jackisastream.model.Video
import com.example.jackisastream.ui.theme.AccentCyan
import com.example.jackisastream.ui.theme.PrimaryRed
import kotlinx.coroutines.delay

@SuppressLint("SetJavaScriptEnabled")
@Composable
fun VideoPlayerView(
    video: Video,
    modifier: Modifier = Modifier,
    isFullscreen: Boolean = false,
    onToggleFullscreen: () -> Unit = {}
) {
    var isPlaying by remember(video.id) { mutableStateOf(true) }
    var currentSeconds by remember(video.id) { mutableFloatStateOf(0f) }
    var showControls by remember { mutableStateOf(true) }
    var playbackSpeed by remember { mutableStateOf("1.0x") }
    var showSettingsDialog by remember { mutableStateOf(false) }

    // Toggle between embedded stream playback and native custom scrub interface
    var useWebStreamPlayer by remember(video.id) {
        mutableStateOf(!video.videoUrl.isNullOrBlank() && (video.videoUrl.contains("youtube") || video.videoUrl.contains("embed") || video.videoUrl.endsWith(".mp4")))
    }

    // Pre-roll ad break simulation (first 5 seconds shows skippable ad for non-web stream or demo)
    var adActive by remember(video.id) { mutableStateOf(!useWebStreamPlayer) }
    var adSecondsLeft by remember(video.id) { mutableIntStateOf(5) }

    LaunchedEffect(video.id, adActive) {
        if (adActive) {
            while (adSecondsLeft > 0) {
                delay(1000)
                adSecondsLeft--
            }
        }
    }

    // Auto-advance video time when playing and ad finished
    LaunchedEffect(video.id, isPlaying, adActive, useWebStreamPlayer) {
        if (!adActive && !useWebStreamPlayer) {
            while (isPlaying && currentSeconds < video.durationSeconds) {
                delay(1000)
                currentSeconds += 1f
            }
        }
    }

    // Auto-hide controls after 4 seconds
    LaunchedEffect(showControls, isPlaying) {
        if (showControls && isPlaying) {
            delay(4000)
            showControls = false
        }
    }

    Box(
        modifier = modifier
            .fillMaxWidth()
            .aspectRatio(if (isFullscreen) 2.0f else 16f / 9f)
            .background(Color.Black)
            .testTag("video_player_container")
    ) {
        if (useWebStreamPlayer && !video.videoUrl.isNullOrBlank()) {
            // Live / Embedded Stream Player
            AndroidView(
                factory = { context ->
                    WebView(context).apply {
                        settings.javaScriptEnabled = true
                        settings.domStorageEnabled = true
                        settings.mediaPlaybackRequiresUserGesture = false
                        webChromeClient = WebChromeClient()
                        webViewClient = WebViewClient()
                        val targetUrl = video.videoUrl.let {
                            if (it.contains("youtube.com/watch?v=")) {
                                val vId = it.substringAfter("watch?v=").substringBefore("&")
                                "https://www.youtube.com/embed/$vId?autoplay=1"
                            } else it
                        }
                        loadUrl(targetUrl)
                    }
                },
                modifier = Modifier.fillMaxSize()
            )

            // Switcher pill in top right corner to switch to scrub mode
            Surface(
                shape = RoundedCornerShape(20.dp),
                color = Color.Black.copy(alpha = 0.7f),
                modifier = Modifier
                    .align(Alignment.TopEnd)
                    .padding(8.dp)
                    .clickable { useWebStreamPlayer = false }
            ) {
                Row(
                    modifier = Modifier.padding(horizontal = 8.dp, vertical = 4.dp),
                    verticalAlignment = Alignment.CenterVertically
                ) {
                    Icon(
                        imageVector = Icons.Default.PlayCircle,
                        contentDescription = "Switch to Native Controls",
                        tint = AccentCyan,
                        modifier = Modifier.size(14.dp)
                    )
                    Spacer(modifier = Modifier.width(4.dp))
                    Text(
                        text = "Native Scrub",
                        color = Color.White,
                        fontSize = 10.sp,
                        fontWeight = FontWeight.Bold
                    )
                }
            }
        } else {
            // Native Video Player Surface
            val thumbModel: Any = video.thumbnailUrl ?: if (video.thumbnailRes != 0) video.thumbnailRes else R.drawable.video_thumb_1
            AsyncImage(
                model = thumbModel,
                placeholder = painterResource(id = if (video.thumbnailRes != 0) video.thumbnailRes else R.drawable.video_thumb_1),
                error = painterResource(id = if (video.thumbnailRes != 0) video.thumbnailRes else R.drawable.video_thumb_1),
                contentDescription = "Video playback frame",
                contentScale = ContentScale.Crop,
                modifier = Modifier
                    .fillMaxSize()
                    .clickable(
                        interactionSource = remember { MutableInteractionSource() },
                        indication = null
                    ) {
                        showControls = !showControls
                    }
            )

            // Gradient dark vignette for controls contrast
            Box(
                modifier = Modifier
                    .fillMaxSize()
                    .background(
                        Brush.verticalGradient(
                            colors = listOf(
                                Color.Black.copy(alpha = 0.7f),
                                Color.Transparent,
                                Color.Black.copy(alpha = 0.85f)
                            )
                        )
                    )
                    .clickable(
                        interactionSource = remember { MutableInteractionSource() },
                        indication = null
                    ) {
                        showControls = !showControls
                    }
            )

            // Pre-roll Ad Banner Overlay if ad is active
            if (adActive) {
                Box(
                    modifier = Modifier
                        .fillMaxSize()
                        .background(Color.Black.copy(alpha = 0.45f))
                        .padding(12.dp)
                ) {
                    // Ad Badge Top Left
                    Surface(
                        shape = RoundedCornerShape(4.dp),
                        color = Color(0xFFF59E0B),
                        modifier = Modifier.align(Alignment.TopStart)
                    ) {
                        Text(
                            text = "AD • 1 of 1",
                            fontSize = 11.sp,
                            fontWeight = FontWeight.Bold,
                            color = Color.Black,
                            modifier = Modifier.padding(horizontal = 8.dp, vertical = 4.dp)
                        )
                    }

                    // Skip Ad Button Bottom Right
                    Row(
                        modifier = Modifier
                            .align(Alignment.BottomEnd)
                            .padding(bottom = 8.dp),
                        verticalAlignment = Alignment.CenterVertically
                    ) {
                        if (adSecondsLeft > 0) {
                            Surface(
                                shape = RoundedCornerShape(20.dp),
                                color = Color.Black.copy(alpha = 0.7f),
                                modifier = Modifier.padding(end = 8.dp)
                            ) {
                                Text(
                                    text = "Skip in ${adSecondsLeft}s",
                                    color = Color.White,
                                    fontSize = 12.sp,
                                    modifier = Modifier.padding(horizontal = 12.dp, vertical = 6.dp)
                                )
                            }
                        } else {
                            OutlinedButton(
                                onClick = { adActive = false },
                                colors = ButtonDefaults.outlinedButtonColors(
                                    containerColor = PrimaryRed,
                                    contentColor = Color.White
                                ),
                                shape = RoundedCornerShape(20.dp),
                                modifier = Modifier.testTag("skip_ad_button")
                            ) {
                                Text("Skip Ad ➔", fontWeight = FontWeight.Bold, fontSize = 12.sp)
                            }
                        }
                    }
                }
            }

            // Overlay Controls
            AnimatedVisibility(
                visible = showControls && !adActive,
                enter = fadeIn(),
                exit = fadeOut(),
                modifier = Modifier.fillMaxSize()
            ) {
                Box(modifier = Modifier.fillMaxSize()) {
                    // Top Bar: Title & Web Stream Switcher / Quality
                    Row(
                        modifier = Modifier
                            .fillMaxWidth()
                            .padding(horizontal = 16.dp, vertical = 8.dp)
                            .align(Alignment.TopCenter),
                        horizontalArrangement = Arrangement.SpaceBetween,
                        verticalAlignment = Alignment.CenterVertically
                    ) {
                        Text(
                            text = video.title,
                            color = Color.White,
                            fontSize = 13.sp,
                            fontWeight = FontWeight.Medium,
                            maxLines = 1,
                            modifier = Modifier.weight(1f)
                        )
                        Spacer(modifier = Modifier.width(8.dp))

                        if (!video.videoUrl.isNullOrBlank()) {
                            Surface(
                                shape = RoundedCornerShape(20.dp),
                                color = PrimaryRed.copy(alpha = 0.85f),
                                modifier = Modifier
                                    .padding(end = 6.dp)
                                    .clickable { useWebStreamPlayer = true }
                            ) {
                                Row(
                                    modifier = Modifier.padding(horizontal = 8.dp, vertical = 4.dp),
                                    verticalAlignment = Alignment.CenterVertically
                                ) {
                                    Icon(
                                        imageVector = Icons.Default.Videocam,
                                        contentDescription = "Live Video Stream",
                                        tint = Color.White,
                                        modifier = Modifier.size(14.dp)
                                    )
                                    Spacer(modifier = Modifier.width(4.dp))
                                    Text(
                                        text = "Stream",
                                        color = Color.White,
                                        fontSize = 10.sp,
                                        fontWeight = FontWeight.Bold
                                    )
                                }
                            }
                        }

                        IconButton(
                            onClick = { showSettingsDialog = !showSettingsDialog },
                            modifier = Modifier.size(36.dp).testTag("video_settings_button")
                        ) {
                            Icon(
                                imageVector = Icons.Default.Settings,
                                contentDescription = "Playback settings",
                                tint = Color.White
                            )
                        }
                    }

                    // Center Controls: Rewind, Play/Pause, Fast-Forward
                    Row(
                        modifier = Modifier
                            .align(Alignment.Center)
                            .padding(horizontal = 24.dp),
                        horizontalArrangement = Arrangement.spacedBy(24.dp),
                        verticalAlignment = Alignment.CenterVertically
                    ) {
                        IconButton(
                            onClick = {
                                currentSeconds = (currentSeconds - 10f).coerceAtLeast(0f)
                            },
                            modifier = Modifier
                                .size(44.dp)
                                .background(Color.Black.copy(alpha = 0.5f), CircleShape)
                                .testTag("rewind_10s_button")
                        ) {
                            Icon(
                                imageVector = Icons.Default.FastRewind,
                                contentDescription = "Rewind 10 seconds",
                                tint = Color.White
                            )
                        }

                        IconButton(
                            onClick = { isPlaying = !isPlaying },
                            modifier = Modifier
                                .size(60.dp)
                                .background(PrimaryRed, CircleShape)
                                .testTag("play_pause_button")
                        ) {
                            Icon(
                                imageVector = if (isPlaying) Icons.Default.Pause else Icons.Default.PlayArrow,
                                contentDescription = if (isPlaying) "Pause" else "Play",
                                tint = Color.White,
                                modifier = Modifier.size(36.dp)
                            )
                        }

                        IconButton(
                            onClick = {
                                currentSeconds = (currentSeconds + 10f).coerceAtMost(video.durationSeconds.toFloat())
                            },
                            modifier = Modifier
                                .size(44.dp)
                                .background(Color.Black.copy(alpha = 0.5f), CircleShape)
                                .testTag("forward_10s_button")
                        ) {
                            Icon(
                                imageVector = Icons.Default.FastForward,
                                contentDescription = "Forward 10 seconds",
                                tint = Color.White
                            )
                        }
                    }

                    // Bottom Bar: Progress Slider, Timestamp, Fullscreen
                    Column(
                        modifier = Modifier
                            .fillMaxWidth()
                            .align(Alignment.BottomCenter)
                            .padding(horizontal = 16.dp, vertical = 6.dp)
                    ) {
                        Slider(
                            value = currentSeconds,
                            onValueChange = { currentSeconds = it },
                            valueRange = 0f..video.durationSeconds.toFloat(),
                            colors = SliderDefaults.colors(
                                thumbColor = PrimaryRed,
                                activeTrackColor = PrimaryRed,
                                inactiveTrackColor = Color.White.copy(alpha = 0.3f)
                            ),
                            modifier = Modifier
                                .fillMaxWidth()
                                .height(20.dp)
                                .testTag("video_progress_slider")
                        )

                        Row(
                            modifier = Modifier.fillMaxWidth(),
                            horizontalArrangement = Arrangement.SpaceBetween,
                            verticalAlignment = Alignment.CenterVertically
                        ) {
                            val currMin = (currentSeconds.toInt() / 60)
                            val currSec = (currentSeconds.toInt() % 60)
                            val timeStr = String.format("%02d:%02d / %s", currMin, currSec, video.durationText)

                            Row(verticalAlignment = Alignment.CenterVertically) {
                                Text(
                                    text = timeStr,
                                    color = Color.White,
                                    fontSize = 12.sp,
                                    fontWeight = FontWeight.SemiBold
                                )
                                Spacer(modifier = Modifier.width(8.dp))
                                Surface(
                                    shape = RoundedCornerShape(4.dp),
                                    color = Color(0xFF1E293B)
                                ) {
                                    Text(
                                        text = playbackSpeed,
                                        color = AccentCyan,
                                        fontSize = 11.sp,
                                        fontWeight = FontWeight.Bold,
                                        modifier = Modifier
                                            .clickable {
                                                playbackSpeed = when (playbackSpeed) {
                                                    "1.0x" -> "1.25x"
                                                    "1.25x" -> "1.5x"
                                                    "1.5x" -> "2.0x"
                                                    else -> "1.0x"
                                                }
                                            }
                                            .padding(horizontal = 6.dp, vertical = 2.dp)
                                    )
                                }
                            }

                            Row(verticalAlignment = Alignment.CenterVertically) {
                                IconButton(
                                    onClick = onToggleFullscreen,
                                    modifier = Modifier.size(32.dp).testTag("fullscreen_button")
                                ) {
                                    Icon(
                                        imageVector = if (isFullscreen) Icons.Default.FullscreenExit else Icons.Default.Fullscreen,
                                        contentDescription = "Toggle Fullscreen",
                                        tint = Color.White
                                    )
                                }
                            }
                        }
                    }
                }
            }
        }
    }
}
