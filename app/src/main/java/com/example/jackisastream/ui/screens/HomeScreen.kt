package com.example.jackisastream.ui.screens

import androidx.compose.animation.core.LinearEasing
import androidx.compose.animation.core.animateFloat
import androidx.compose.animation.core.infiniteRepeatable
import androidx.compose.animation.core.rememberInfiniteTransition
import androidx.compose.animation.core.tween
import androidx.compose.foundation.Image
import androidx.compose.foundation.background
import androidx.compose.foundation.clickable
import androidx.compose.foundation.horizontalScroll
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.PaddingValues
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.Spacer
import androidx.compose.foundation.layout.aspectRatio
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.layout.width
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.lazy.items
import androidx.compose.foundation.rememberScrollState
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.Close
import androidx.compose.material.icons.filled.Notifications
import androidx.compose.material.icons.filled.PlayArrow
import androidx.compose.material.icons.filled.Refresh
import androidx.compose.material.icons.filled.Search
import androidx.compose.material3.Button
import androidx.compose.material3.ButtonDefaults
import androidx.compose.material3.Card
import androidx.compose.material3.CardDefaults
import androidx.compose.material3.ExperimentalMaterial3Api
import androidx.compose.material3.FilterChip
import androidx.compose.material3.FilterChipDefaults
import androidx.compose.material3.Icon
import androidx.compose.material3.IconButton
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.OutlinedTextField
import androidx.compose.material3.OutlinedTextFieldDefaults
import androidx.compose.material3.Surface
import androidx.compose.material3.Text
import androidx.compose.material3.TopAppBar
import androidx.compose.material3.TopAppBarDefaults
import androidx.compose.runtime.Composable
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.setValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.draw.rotate
import androidx.compose.ui.graphics.Brush
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.layout.ContentScale
import androidx.compose.ui.platform.testTag
import androidx.compose.ui.res.painterResource
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.style.TextOverflow
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import coil.compose.AsyncImage
import com.example.jackisastream.R
import com.example.jackisastream.model.LiveStream
import com.example.jackisastream.model.User
import com.example.jackisastream.model.Video
import com.example.jackisastream.ui.components.VideoFeedCard
import com.example.jackisastream.ui.theme.AccentCyan
import com.example.jackisastream.ui.theme.DarkBackground
import com.example.jackisastream.ui.theme.DarkSurfaceElevated
import com.example.jackisastream.ui.theme.LiveBadgeColor
import com.example.jackisastream.ui.theme.PrimaryRed
import com.example.jackisastream.ui.theme.TextMuted
import com.example.jackisastream.ui.theme.TextPrimary
import com.example.jackisastream.ui.theme.TextSecondary

@OptIn(ExperimentalMaterial3Api::class)
@Composable
fun HomeScreen(
    videos: List<Video>,
    currentUser: User?,
    selectedCategory: String,
    searchQuery: String,
    featuredLiveStream: LiveStream?,
    isSyncing: Boolean,
    syncStatusMessage: String,
    onRefreshSync: () -> Unit,
    onCategorySelected: (String) -> Unit,
    onSearchQueryChanged: (String) -> Unit,
    onVideoClick: (Video) -> Unit,
    onLiveClick: (LiveStream) -> Unit,
    onOpenProfile: () -> Unit,
    onOpenAuth: () -> Unit,
    modifier: Modifier = Modifier
) {
    var isSearchActive by remember { mutableStateOf(false) }

    val categories = listOf(
        "All", "Technology and Innovation", "News & Politics", "Music", "Gaming", "Entertainment", "Film & Animation", "Education"
    )

    val filteredVideos = remember(videos, selectedCategory, searchQuery) {
        videos.filter { video ->
            val matchCategory = selectedCategory == "All" ||
                video.category.contains(selectedCategory, ignoreCase = true) ||
                selectedCategory.contains(video.category, ignoreCase = true)
            val matchSearch = searchQuery.isBlank() ||
                video.title.contains(searchQuery, ignoreCase = true) ||
                video.channelTitle.contains(searchQuery, ignoreCase = true)
            matchCategory && matchSearch
        }
    }

    val infiniteTransition = rememberInfiniteTransition()
    val rotation by infiniteTransition.animateFloat(
        initialValue = 0f,
        targetValue = 360f,
        animationSpec = infiniteRepeatable(
            animation = tween(1000, easing = LinearEasing)
        )
    )

    Column(
        modifier = modifier
            .fillMaxSize()
            .background(DarkBackground)
    ) {
        // Top App Bar
        TopAppBar(
            title = {
                if (isSearchActive) {
                    OutlinedTextField(
                        value = searchQuery,
                        onValueChange = onSearchQueryChanged,
                        placeholder = { Text("Search videos & streams...", color = TextSecondary, fontSize = 13.sp) },
                        colors = OutlinedTextFieldDefaults.colors(
                            focusedTextColor = TextPrimary,
                            unfocusedTextColor = TextPrimary,
                            focusedBorderColor = PrimaryRed,
                            unfocusedBorderColor = Color.Transparent,
                            focusedContainerColor = DarkSurfaceElevated,
                            unfocusedContainerColor = DarkSurfaceElevated
                        ),
                        shape = RoundedCornerShape(20.dp),
                        singleLine = true,
                        trailingIcon = {
                            IconButton(onClick = {
                                onSearchQueryChanged("")
                                isSearchActive = false
                            }) {
                                Icon(Icons.Default.Close, contentDescription = "Clear Search", tint = TextSecondary)
                            }
                        },
                        modifier = Modifier
                            .fillMaxWidth()
                            .height(48.dp)
                            .testTag("home_search_input")
                    )
                } else {
                    Row(
                        verticalAlignment = Alignment.CenterVertically,
                        modifier = Modifier.clickable { onCategorySelected("All") }
                    ) {
                        Image(
                            painter = painterResource(id = R.drawable.app_icon_fg),
                            contentDescription = "Jackisa Stream Logo",
                            modifier = Modifier
                                .size(32.dp)
                                .clip(RoundedCornerShape(6.dp))
                        )
                        Spacer(modifier = Modifier.width(8.dp))
                        Text(
                            text = "Jackisa Stream",
                            fontSize = 18.sp,
                            fontWeight = FontWeight.Bold,
                            color = TextPrimary
                        )
                    }
                }
            },
            actions = {
                if (!isSearchActive) {
                    IconButton(
                        onClick = { isSearchActive = true },
                        modifier = Modifier.testTag("home_search_toggle")
                    ) {
                        Icon(Icons.Default.Search, contentDescription = "Search", tint = TextPrimary)
                    }

                    if (currentUser != null) {
                        IconButton(
                            onClick = onOpenProfile,
                            modifier = Modifier
                                .padding(end = 4.dp)
                                .testTag("home_profile_avatar")
                        ) {
                            val avatarModel: Any = currentUser.avatarUrl ?: currentUser.avatarRes ?: R.drawable.avatar_1
                            AsyncImage(
                                model = avatarModel,
                                placeholder = painterResource(id = R.drawable.avatar_1),
                                error = painterResource(id = R.drawable.avatar_1),
                                contentDescription = "User Profile",
                                contentScale = ContentScale.Crop,
                                modifier = Modifier
                                    .size(32.dp)
                                    .clip(CircleShape)
                            )
                        }
                    } else {
                        Button(
                            onClick = onOpenAuth,
                            colors = ButtonDefaults.buttonColors(containerColor = PrimaryRed),
                            shape = RoundedCornerShape(16.dp),
                            contentPadding = PaddingValues(horizontal = 12.dp, vertical = 6.dp),
                            modifier = Modifier
                                .padding(end = 8.dp)
                                .testTag("home_login_button")
                        ) {
                            Text("Sign In", fontSize = 12.sp, fontWeight = FontWeight.Bold)
                        }
                    }
                }
            },
            colors = TopAppBarDefaults.topAppBarColors(containerColor = MaterialTheme.colorScheme.surface)
        )

        // Live Sync Status Strip (jackisa.com backend connection)
        Row(
            modifier = Modifier
                .fillMaxWidth()
                .background(Color(0xFF0F172A))
                .padding(horizontal = 14.dp, vertical = 6.dp),
            verticalAlignment = Alignment.CenterVertically,
            horizontalArrangement = Arrangement.SpaceBetween
        ) {
            Row(
                verticalAlignment = Alignment.CenterVertically,
                modifier = Modifier.weight(1f)
            ) {
                Box(
                    modifier = Modifier
                        .size(8.dp)
                        .background(
                            if (isSyncing) AccentCyan else Color(0xFF10B981),
                            CircleShape
                        )
                )
                Spacer(modifier = Modifier.width(8.dp))
                Text(
                    text = if (isSyncing) "Syncing with jackisa.com..." else syncStatusMessage,
                    color = TextSecondary,
                    fontSize = 11.sp,
                    maxLines = 1,
                    overflow = TextOverflow.Ellipsis
                )
            }

            IconButton(
                onClick = onRefreshSync,
                modifier = Modifier.size(24.dp).testTag("refresh_sync_button")
            ) {
                Icon(
                    imageVector = Icons.Default.Refresh,
                    contentDescription = "Refresh feed",
                    tint = AccentCyan,
                    modifier = Modifier
                        .size(16.dp)
                        .then(if (isSyncing) Modifier.rotate(rotation) else Modifier)
                )
            }
        }

        // Categories Chip Row
        Row(
            modifier = Modifier
                .fillMaxWidth()
                .background(MaterialTheme.colorScheme.surface)
                .horizontalScroll(rememberScrollState())
                .padding(horizontal = 12.dp, vertical = 6.dp),
            horizontalArrangement = Arrangement.spacedBy(8.dp)
        ) {
            categories.forEach { cat ->
                val isSelected = cat == selectedCategory
                FilterChip(
                    selected = isSelected,
                    onClick = { onCategorySelected(cat) },
                    label = {
                        Text(
                            text = if (cat == "Technology and Innovation") "Tech" else cat,
                            fontSize = 12.sp,
                            fontWeight = if (isSelected) FontWeight.Bold else FontWeight.Normal
                        )
                    },
                    colors = FilterChipDefaults.filterChipColors(
                        selectedContainerColor = PrimaryRed,
                        selectedLabelColor = Color.White,
                        containerColor = DarkSurfaceElevated,
                        labelColor = TextSecondary
                    ),
                    border = null,
                    modifier = Modifier.testTag("category_chip_$cat")
                )
            }
        }

        // Main Feed List
        LazyColumn(
            modifier = Modifier
                .fillMaxSize()
                .testTag("home_video_feed"),
            contentPadding = PaddingValues(12.dp),
            verticalArrangement = Arrangement.spacedBy(16.dp)
        ) {
            // Featured Live Broadcast Banner
            if (featuredLiveStream != null && (selectedCategory == "All" || selectedCategory == "Gaming" || selectedCategory == "Music") && searchQuery.isBlank()) {
                item {
                    Card(
                        modifier = Modifier
                            .fillMaxWidth()
                            .clickable { onLiveClick(featuredLiveStream) }
                            .testTag("featured_live_banner"),
                        shape = RoundedCornerShape(14.dp),
                        colors = CardDefaults.cardColors(containerColor = DarkSurfaceElevated)
                    ) {
                        Box(
                            modifier = Modifier
                                .fillMaxWidth()
                                .aspectRatio(16f / 9f)
                                .background(Color(0xFF0F172A))
                        ) {
                            val bannerModel: Any = featuredLiveStream.thumbnailUrl ?: if (featuredLiveStream.thumbnailRes != 0) featuredLiveStream.thumbnailRes else R.drawable.hero_stream_banner
                            AsyncImage(
                                model = bannerModel,
                                placeholder = painterResource(id = R.drawable.hero_stream_banner),
                                error = painterResource(id = R.drawable.hero_stream_banner),
                                contentDescription = "Featured Live Stream",
                                contentScale = ContentScale.Crop,
                                modifier = Modifier.fillMaxSize()
                            )

                            Box(
                                modifier = Modifier
                                    .fillMaxSize()
                                    .background(
                                        Brush.verticalGradient(
                                            listOf(
                                                Color.Black.copy(alpha = 0.3f),
                                                Color.Black.copy(alpha = 0.85f)
                                            )
                                        )
                                    )
                            )

                            // Top LIVE Badge
                            Row(
                                modifier = Modifier
                                    .align(Alignment.TopStart)
                                    .padding(12.dp),
                                verticalAlignment = Alignment.CenterVertically
                            ) {
                                Surface(
                                    shape = RoundedCornerShape(4.dp),
                                    color = LiveBadgeColor
                                ) {
                                    Text(
                                        text = "FEATURED LIVE",
                                        color = Color.White,
                                        fontSize = 10.sp,
                                        fontWeight = FontWeight.ExtraBold,
                                        modifier = Modifier.padding(horizontal = 8.dp, vertical = 4.dp)
                                    )
                                }
                                Spacer(modifier = Modifier.width(8.dp))
                                Surface(
                                    shape = RoundedCornerShape(4.dp),
                                    color = Color.Black.copy(alpha = 0.7f)
                                ) {
                                    Text(
                                        text = "${featuredLiveStream.viewerCount} Viewers",
                                        color = Color.White,
                                        fontSize = 11.sp,
                                        modifier = Modifier.padding(horizontal = 6.dp, vertical = 3.dp)
                                    )
                                }
                            }

                            // Bottom Banner Info
                            Row(
                                modifier = Modifier
                                    .align(Alignment.BottomCenter)
                                    .fillMaxWidth()
                                    .padding(12.dp),
                                horizontalArrangement = Arrangement.SpaceBetween,
                                verticalAlignment = Alignment.CenterVertically
                            ) {
                                Column(modifier = Modifier.weight(1f)) {
                                    Text(
                                        text = featuredLiveStream.title,
                                        color = Color.White,
                                        fontSize = 14.sp,
                                        fontWeight = FontWeight.Bold,
                                        maxLines = 1,
                                        overflow = TextOverflow.Ellipsis
                                    )
                                    Text(
                                        text = "${featuredLiveStream.streamerName} • ${featuredLiveStream.category}",
                                        color = TextSecondary,
                                        fontSize = 12.sp
                                    )
                                }

                                Button(
                                    onClick = { onLiveClick(featuredLiveStream) },
                                    colors = ButtonDefaults.buttonColors(containerColor = PrimaryRed),
                                    shape = RoundedCornerShape(20.dp),
                                    modifier = Modifier.testTag("join_live_button")
                                ) {
                                    Icon(
                                        imageVector = Icons.Default.PlayArrow,
                                        contentDescription = "Watch",
                                        modifier = Modifier.size(16.dp)
                                    )
                                    Spacer(modifier = Modifier.width(4.dp))
                                    Text("Watch Live", fontSize = 12.sp, fontWeight = FontWeight.Bold)
                                }
                            }
                        }
                    }
                }
            }

            // Video Feed Items
            items(filteredVideos, key = { it.id }) { video ->
                VideoFeedCard(
                    video = video,
                    onClick = { onVideoClick(video) }
                )
            }
        }
    }
}
