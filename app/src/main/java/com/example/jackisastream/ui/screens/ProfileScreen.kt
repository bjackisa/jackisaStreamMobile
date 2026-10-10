package com.example.jackisastream.ui.screens

import androidx.compose.foundation.Image
import androidx.compose.foundation.background
import androidx.compose.foundation.clickable
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.Spacer
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.layout.width
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.lazy.items
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.ChevronRight
import androidx.compose.material.icons.filled.History
import androidx.compose.material.icons.filled.MonetizationOn
import androidx.compose.material.icons.filled.Description
import androidx.compose.material.icons.filled.Settings
import androidx.compose.material.icons.filled.ThumbUp
import androidx.compose.material.icons.filled.VideoLibrary
import androidx.compose.material3.Card
import androidx.compose.material3.CardDefaults
import androidx.compose.material3.HorizontalDivider
import androidx.compose.material3.Icon
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.Surface
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.layout.ContentScale
import androidx.compose.ui.platform.testTag
import androidx.compose.ui.res.painterResource
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import com.example.jackisastream.R
import com.example.jackisastream.model.Video
import com.example.jackisastream.ui.components.CompactVideoRowCard
import com.example.jackisastream.ui.theme.AccentCyan
import com.example.jackisastream.ui.theme.DarkBackground
import com.example.jackisastream.ui.theme.DarkSurfaceElevated
import com.example.jackisastream.ui.theme.PrimaryRed
import com.example.jackisastream.ui.theme.StakeholderPurple
import com.example.jackisastream.ui.theme.TextMuted
import com.example.jackisastream.ui.theme.TextPrimary
import com.example.jackisastream.ui.theme.TextSecondary
import com.example.jackisastream.ui.theme.VipGold

@Composable
fun ProfileScreen(
    videos: List<Video>,
    onSelectVideo: (Video) -> Unit,
    onNavigateToStakeholders: () -> Unit,
    onNavigateToStore: () -> Unit,
    modifier: Modifier = Modifier
) {
    val likedVideos = videos.filter { it.isLiked }
    val savedVideos = videos.filter { it.isSaved }

    LazyColumn(
        modifier = modifier
            .fillMaxSize()
            .background(DarkBackground)
            .padding(16.dp),
        verticalArrangement = Arrangement.spacedBy(16.dp)
    ) {
        // User Profile Header Card
        item {
            Card(
                modifier = Modifier.fillMaxWidth().testTag("profile_header_card"),
                shape = RoundedCornerShape(16.dp),
                colors = CardDefaults.cardColors(containerColor = DarkSurfaceElevated)
            ) {
                Column(modifier = Modifier.padding(16.dp)) {
                    Row(verticalAlignment = Alignment.CenterVertically) {
                        Image(
                            painter = painterResource(id = R.drawable.avatar_1),
                            contentDescription = "Profile",
                            contentScale = ContentScale.Crop,
                            modifier = Modifier
                                .size(64.dp)
                                .clip(CircleShape)
                        )
                        Spacer(modifier = Modifier.width(14.dp))
                        Column {
                            Row(verticalAlignment = Alignment.CenterVertically) {
                                Text(
                                    text = "Barack Daniel Jackisa",
                                    color = TextPrimary,
                                    fontSize = 17.sp,
                                    fontWeight = FontWeight.Bold
                                )
                                Spacer(modifier = Modifier.width(6.dp))
                                Surface(
                                    shape = RoundedCornerShape(4.dp),
                                    color = StakeholderPurple
                                ) {
                                    Text(
                                        text = "VIP CREATOR",
                                        color = androidx.compose.ui.graphics.Color.White,
                                        fontSize = 9.sp,
                                        fontWeight = FontWeight.ExtraBold,
                                        modifier = Modifier.padding(horizontal = 4.dp, vertical = 2.dp)
                                    )
                                }
                            }
                            Text(
                                text = "@jackisa • Creator & Stakeholder",
                                color = TextSecondary,
                                fontSize = 12.sp
                            )
                            Spacer(modifier = Modifier.height(4.dp))
                            Text(
                                text = "12.4K Subscribers • 48 Videos Published",
                                color = TextMuted,
                                fontSize = 11.sp
                            )
                        }
                    }
                }
            }
        }

        // Quick Navigation Tiles
        item {
            Card(
                modifier = Modifier.fillMaxWidth(),
                shape = RoundedCornerShape(12.dp),
                colors = CardDefaults.cardColors(containerColor = DarkSurfaceElevated)
            ) {
                Column {
                    Row(
                        modifier = Modifier
                            .fillMaxWidth()
                            .clickable { onNavigateToStakeholders() }
                            .padding(14.dp),
                        horizontalArrangement = Arrangement.SpaceBetween,
                        verticalAlignment = Alignment.CenterVertically
                    ) {
                        Row(verticalAlignment = Alignment.CenterVertically) {
                            Icon(Icons.Default.MonetizationOn, contentDescription = null, tint = VipGold)
                            Spacer(modifier = Modifier.width(10.dp))
                            Text("Stakeholder Equity & Dividend Ledger", color = TextPrimary, fontSize = 13.sp, fontWeight = FontWeight.SemiBold)
                        }
                        Icon(Icons.Default.ChevronRight, contentDescription = null, tint = TextMuted)
                    }

                    HorizontalDivider(color = DarkBackground)

                    Row(
                        modifier = Modifier
                            .fillMaxWidth()
                            .clickable { onNavigateToStore() }
                            .padding(14.dp),
                        horizontalArrangement = Arrangement.SpaceBetween,
                        verticalAlignment = Alignment.CenterVertically
                    ) {
                        Row(verticalAlignment = Alignment.CenterVertically) {
                            Icon(Icons.Default.Description, contentDescription = null, tint = AccentCyan)
                            Spacer(modifier = Modifier.width(10.dp))
                            Text("Jackisa Store Orders & Escrow Escrow", color = TextPrimary, fontSize = 13.sp, fontWeight = FontWeight.SemiBold)
                        }
                        Icon(Icons.Default.ChevronRight, contentDescription = null, tint = TextMuted)
                    }
                }
            }
        }

        // Liked Videos Section
        item {
            Row(
                modifier = Modifier.fillMaxWidth(),
                verticalAlignment = Alignment.CenterVertically
            ) {
                Icon(Icons.Default.ThumbUp, contentDescription = "Liked", tint = PrimaryRed, modifier = Modifier.size(18.dp))
                Spacer(modifier = Modifier.width(6.dp))
                Text(
                    text = "Liked Videos (${likedVideos.size})",
                    color = TextPrimary,
                    fontSize = 15.sp,
                    fontWeight = FontWeight.Bold
                )
            }
        }

        if (likedVideos.isEmpty()) {
            item {
                Text("No liked videos yet. Tap thumbs-up on any video to add.", color = TextMuted, fontSize = 12.sp)
            }
        } else {
            items(likedVideos, key = { it.id }) { vid ->
                CompactVideoRowCard(
                    video = vid,
                    onClick = { onSelectVideo(vid) }
                )
            }
        }

        // Saved / Watch Later Section
        item {
            Row(
                modifier = Modifier.fillMaxWidth(),
                verticalAlignment = Alignment.CenterVertically
            ) {
                Icon(Icons.Default.VideoLibrary, contentDescription = "Saved", tint = AccentCyan, modifier = Modifier.size(18.dp))
                Spacer(modifier = Modifier.width(6.dp))
                Text(
                    text = "Saved Playlist (${savedVideos.size})",
                    color = TextPrimary,
                    fontSize = 15.sp,
                    fontWeight = FontWeight.Bold
                )
            }
        }

        if (savedVideos.isEmpty()) {
            item {
                Text("No saved videos. Save videos for later viewing.", color = TextMuted, fontSize = 12.sp)
            }
        } else {
            items(savedVideos, key = { it.id }) { vid ->
                CompactVideoRowCard(
                    video = vid,
                    onClick = { onSelectVideo(vid) }
                )
            }
        }
    }
}
