package com.example.jackisastream.ui.screens

import androidx.compose.animation.AnimatedVisibility
import androidx.compose.foundation.Image
import androidx.compose.foundation.background
import androidx.compose.foundation.clickable
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
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.lazy.LazyRow
import androidx.compose.foundation.lazy.items
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.automirrored.filled.ArrowBack
import androidx.compose.material.icons.automirrored.filled.Send
import androidx.compose.material.icons.filled.Bookmark
import androidx.compose.material.icons.filled.BookmarkBorder
import androidx.compose.material.icons.filled.Close
import androidx.compose.material.icons.filled.Share
import androidx.compose.material.icons.filled.ShoppingBag
import androidx.compose.material.icons.filled.ThumbDown
import androidx.compose.material.icons.filled.ThumbDownOffAlt
import androidx.compose.material.icons.filled.ThumbUp
import androidx.compose.material.icons.filled.ThumbUpOffAlt
import androidx.compose.material3.Button
import androidx.compose.material3.ButtonDefaults
import androidx.compose.material3.Card
import androidx.compose.material3.CardDefaults
import androidx.compose.material3.HorizontalDivider
import androidx.compose.material3.Icon
import androidx.compose.material3.IconButton
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.OutlinedTextField
import androidx.compose.material3.OutlinedTextFieldDefaults
import androidx.compose.material3.Surface
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.setValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
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
import com.example.jackisastream.model.Comment
import com.example.jackisastream.model.StoreProduct
import com.example.jackisastream.model.Video
import com.example.jackisastream.ui.components.CompactVideoRowCard
import com.example.jackisastream.ui.components.VideoPlayerView
import com.example.jackisastream.ui.theme.AccentCyan
import com.example.jackisastream.ui.theme.DarkBackground
import com.example.jackisastream.ui.theme.DarkSurfaceElevated
import com.example.jackisastream.ui.theme.PrimaryRed
import com.example.jackisastream.ui.theme.TextMuted
import com.example.jackisastream.ui.theme.TextPrimary
import com.example.jackisastream.ui.theme.TextSecondary
import com.example.jackisastream.ui.theme.VipGold

@Composable
fun WatchScreen(
    video: Video,
    allVideos: List<Video>,
    comments: List<Comment>,
    storeProducts: List<StoreProduct>,
    onToggleLike: (String) -> Unit,
    onToggleDislike: (String) -> Unit,
    onToggleSubscribe: (String) -> Unit,
    onToggleSave: (String) -> Unit,
    onAddComment: (String, String) -> Unit,
    onSelectVideo: (Video) -> Unit,
    onNavigateToStoreProduct: (StoreProduct) -> Unit,
    onBack: () -> Unit,
    modifier: Modifier = Modifier
) {
    var showCommentsSheet by remember { mutableStateOf(false) }
    var newCommentText by remember { mutableStateOf("") }
    var showShareNotification by remember { mutableStateOf(false) }

    val attachedProducts = remember(video.attachedProductIds, storeProducts) {
        storeProducts.filter { it.id in video.attachedProductIds }
    }

    val relatedVideos = remember(video.id, allVideos) {
        allVideos.filter { it.id != video.id }
    }

    Column(
        modifier = modifier
            .fillMaxSize()
            .background(DarkBackground)
    ) {
        // Video Player Container
        Box(modifier = Modifier.fillMaxWidth()) {
            VideoPlayerView(
                video = video,
                modifier = Modifier.fillMaxWidth()
            )

            // Back button overlay
            IconButton(
                onClick = onBack,
                modifier = Modifier
                    .padding(8.dp)
                    .align(Alignment.TopStart)
                    .size(36.dp)
                    .background(Color.Black.copy(alpha = 0.5f), CircleShape)
                    .testTag("watch_back_button")
            ) {
                Icon(
                    imageVector = Icons.AutoMirrored.Filled.ArrowBack,
                    contentDescription = "Back",
                    tint = Color.White
                )
            }
        }

        // Scrollable Video Details & Content
        LazyColumn(
            modifier = Modifier
                .weight(1f)
                .fillMaxWidth()
                .padding(horizontal = 14.dp),
            verticalArrangement = Arrangement.spacedBy(14.dp)
        ) {
            item {
                Spacer(modifier = Modifier.height(6.dp))
                // Title
                Text(
                    text = video.title,
                    color = TextPrimary,
                    fontSize = 17.sp,
                    fontWeight = FontWeight.Bold,
                    lineHeight = 22.sp
                )

                Spacer(modifier = Modifier.height(4.dp))

                // Metadata row
                Text(
                    text = "${video.viewsFormatted} • ${video.uploadDateText} • ${video.category}",
                    color = TextSecondary,
                    fontSize = 12.sp
                )
            }

            // Interactive Action Buttons Row (Like, Dislike, Share, Save)
            item {
                Row(
                    modifier = Modifier.fillMaxWidth(),
                    horizontalArrangement = Arrangement.spacedBy(8.dp),
                    verticalAlignment = Alignment.CenterVertically
                ) {
                    // Like / Dislike pill group
                    Surface(
                        shape = RoundedCornerShape(20.dp),
                        color = DarkSurfaceElevated
                    ) {
                        Row(verticalAlignment = Alignment.CenterVertically) {
                            Row(
                                modifier = Modifier
                                    .clickable { onToggleLike(video.id) }
                                    .padding(horizontal = 12.dp, vertical = 8.dp)
                                    .testTag("like_button"),
                                verticalAlignment = Alignment.CenterVertically
                            ) {
                                Icon(
                                    imageVector = if (video.isLiked) Icons.Default.ThumbUp else Icons.Default.ThumbUpOffAlt,
                                    contentDescription = "Like",
                                    tint = if (video.isLiked) PrimaryRed else TextPrimary,
                                    modifier = Modifier.size(18.dp)
                                )
                                Spacer(modifier = Modifier.width(6.dp))
                                Text(
                                    text = "${video.likesCount}",
                                    color = if (video.isLiked) PrimaryRed else TextPrimary,
                                    fontSize = 12.sp,
                                    fontWeight = FontWeight.Bold
                                )
                            }

                            Box(
                                modifier = Modifier
                                    .height(18.dp)
                                    .width(1.dp)
                                    .background(TextMuted.copy(alpha = 0.5f))
                            )

                            IconButton(
                                onClick = { onToggleDislike(video.id) },
                                modifier = Modifier
                                    .size(36.dp)
                                    .testTag("dislike_button")
                            ) {
                                Icon(
                                    imageVector = if (video.isDisliked) Icons.Default.ThumbDown else Icons.Default.ThumbDownOffAlt,
                                    contentDescription = "Dislike",
                                    tint = if (video.isDisliked) PrimaryRed else TextPrimary,
                                    modifier = Modifier.size(18.dp)
                                )
                            }
                        }
                    }

                    // Share
                    Surface(
                        shape = RoundedCornerShape(20.dp),
                        color = DarkSurfaceElevated,
                        modifier = Modifier.clickable { showShareNotification = true }
                    ) {
                        Row(
                            modifier = Modifier.padding(horizontal = 14.dp, vertical = 8.dp),
                            verticalAlignment = Alignment.CenterVertically
                        ) {
                            Icon(
                                imageVector = Icons.Default.Share,
                                contentDescription = "Share",
                                tint = TextPrimary,
                                modifier = Modifier.size(18.dp)
                            )
                            Spacer(modifier = Modifier.width(6.dp))
                            Text("Share", color = TextPrimary, fontSize = 12.sp, fontWeight = FontWeight.SemiBold)
                        }
                    }

                    // Save to Library
                    Surface(
                        shape = RoundedCornerShape(20.dp),
                        color = DarkSurfaceElevated,
                        modifier = Modifier.clickable { onToggleSave(video.id) }
                    ) {
                        Row(
                            modifier = Modifier.padding(horizontal = 14.dp, vertical = 8.dp),
                            verticalAlignment = Alignment.CenterVertically
                        ) {
                            Icon(
                                imageVector = if (video.isSaved) Icons.Default.Bookmark else Icons.Default.BookmarkBorder,
                                contentDescription = "Save video",
                                tint = if (video.isSaved) AccentCyan else TextPrimary,
                                modifier = Modifier.size(18.dp)
                            )
                            Spacer(modifier = Modifier.width(6.dp))
                            Text(
                                text = if (video.isSaved) "Saved" else "Save",
                                color = if (video.isSaved) AccentCyan else TextPrimary,
                                fontSize = 12.sp,
                                fontWeight = FontWeight.SemiBold
                            )
                        }
                    }
                }

                // Share alert feedback
                if (showShareNotification) {
                    Surface(
                        shape = RoundedCornerShape(8.dp),
                        color = PrimaryRed.copy(alpha = 0.2f),
                        modifier = Modifier
                            .fillMaxWidth()
                            .padding(top = 8.dp)
                    ) {
                        Row(
                            modifier = Modifier.padding(horizontal = 12.dp, vertical = 8.dp),
                            horizontalArrangement = Arrangement.SpaceBetween,
                            verticalAlignment = Alignment.CenterVertically
                        ) {
                            Text(
                                text = "✓ Video link copied: https://jackisa.com/watch/${video.remoteVideoId ?: video.id}",
                                color = TextPrimary,
                                fontSize = 12.sp
                            )
                            IconButton(onClick = { showShareNotification = false }, modifier = Modifier.size(24.dp)) {
                                Icon(Icons.Default.Close, contentDescription = "Dismiss", tint = TextPrimary, modifier = Modifier.size(16.dp))
                            }
                        }
                    }
                }
            }

            // Channel Bar (Avatar, Channel Name, Subscribed)
            item {
                Row(
                    modifier = Modifier
                        .fillMaxWidth()
                        .background(DarkSurfaceElevated, RoundedCornerShape(12.dp))
                        .padding(12.dp),
                    horizontalArrangement = Arrangement.SpaceBetween,
                    verticalAlignment = Alignment.CenterVertically
                ) {
                    Row(
                        modifier = Modifier.weight(1f),
                        verticalAlignment = Alignment.CenterVertically
                    ) {
                        val avatarModel: Any = video.channelAvatarUrl ?: if (video.channelAvatarRes != 0) video.channelAvatarRes else R.drawable.avatar_1
                        AsyncImage(
                            model = avatarModel,
                            placeholder = painterResource(id = R.drawable.avatar_1),
                            error = painterResource(id = R.drawable.avatar_1),
                            contentDescription = video.channelTitle,
                            contentScale = ContentScale.Crop,
                            modifier = Modifier
                                .size(44.dp)
                                .clip(CircleShape)
                        )
                        Spacer(modifier = Modifier.width(12.dp))
                        Column {
                            Text(
                                text = video.channelTitle,
                                color = TextPrimary,
                                fontSize = 14.sp,
                                fontWeight = FontWeight.Bold
                            )
                            Text(
                                text = "${video.subscriberCount} subscribers",
                                color = TextSecondary,
                                fontSize = 12.sp
                            )
                        }
                    }

                    Button(
                        onClick = { onToggleSubscribe(video.id) },
                        colors = ButtonDefaults.buttonColors(
                            containerColor = if (video.isSubscribed) DarkBackground else PrimaryRed,
                            contentColor = Color.White
                        ),
                        shape = RoundedCornerShape(20.dp),
                        modifier = Modifier.testTag("subscribe_button")
                    ) {
                        Text(
                            text = if (video.isSubscribed) "Subscribed ✓" else "Subscribe",
                            fontSize = 12.sp,
                            fontWeight = FontWeight.Bold
                        )
                    }
                }
            }

            // Video Description
            item {
                Surface(
                    shape = RoundedCornerShape(10.dp),
                    color = DarkSurfaceElevated.copy(alpha = 0.5f),
                    modifier = Modifier.fillMaxWidth()
                ) {
                    Text(
                        text = video.description,
                        color = TextSecondary,
                        fontSize = 12.sp,
                        lineHeight = 17.sp,
                        modifier = Modifier.padding(12.dp)
                    )
                }
            }

            // Creator Merch Strip (PT_StoreWatchMerch from AGENTS.md)
            if (attachedProducts.isNotEmpty()) {
                item {
                    Column(modifier = Modifier.fillMaxWidth()) {
                        Row(
                            verticalAlignment = Alignment.CenterVertically,
                            modifier = Modifier.padding(vertical = 4.dp)
                        ) {
                            Icon(
                                imageVector = Icons.Default.ShoppingBag,
                                contentDescription = "Store Merch",
                                tint = VipGold,
                                modifier = Modifier.size(18.dp)
                            )
                            Spacer(modifier = Modifier.width(6.dp))
                            Text(
                                text = "Creator Merch & Products (${attachedProducts.size})",
                                color = TextPrimary,
                                fontSize = 14.sp,
                                fontWeight = FontWeight.Bold
                            )
                        }

                        LazyRow(
                            horizontalArrangement = Arrangement.spacedBy(10.dp),
                            modifier = Modifier.fillMaxWidth().testTag("watch_merch_strip")
                        ) {
                            items(attachedProducts, key = { it.id }) { product ->
                                Card(
                                    modifier = Modifier
                                        .width(200.dp)
                                        .clickable { onNavigateToStoreProduct(product) }
                                        .testTag("merch_card_${product.id}"),
                                    shape = RoundedCornerShape(10.dp),
                                    colors = CardDefaults.cardColors(containerColor = DarkSurfaceElevated)
                                ) {
                                    Column(modifier = Modifier.padding(8.dp)) {
                                        Image(
                                            painter = painterResource(id = product.imageRes),
                                            contentDescription = product.title,
                                            contentScale = ContentScale.Crop,
                                            modifier = Modifier
                                                .fillMaxWidth()
                                                .height(90.dp)
                                                .clip(RoundedCornerShape(8.dp))
                                        )
                                        Spacer(modifier = Modifier.height(6.dp))
                                        Text(
                                            text = product.title,
                                            color = TextPrimary,
                                            fontSize = 12.sp,
                                            fontWeight = FontWeight.SemiBold,
                                            maxLines = 1,
                                            overflow = TextOverflow.Ellipsis
                                        )
                                        Row(
                                            modifier = Modifier.fillMaxWidth(),
                                            horizontalArrangement = Arrangement.SpaceBetween,
                                            verticalAlignment = Alignment.CenterVertically
                                        ) {
                                            Text(
                                                text = "$${product.price} USD",
                                                color = AccentCyan,
                                                fontSize = 13.sp,
                                                fontWeight = FontWeight.Bold
                                            )
                                            Surface(
                                                shape = RoundedCornerShape(12.dp),
                                                color = PrimaryRed
                                            ) {
                                                Text(
                                                    text = "Buy",
                                                    color = Color.White,
                                                    fontSize = 10.sp,
                                                    fontWeight = FontWeight.Bold,
                                                    modifier = Modifier.padding(horizontal = 8.dp, vertical = 3.dp)
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

            // Comments Preview / Expand
            item {
                Card(
                    modifier = Modifier
                        .fillMaxWidth()
                        .clickable { showCommentsSheet = !showCommentsSheet }
                        .testTag("comments_preview_card"),
                    shape = RoundedCornerShape(10.dp),
                    colors = CardDefaults.cardColors(containerColor = DarkSurfaceElevated)
                ) {
                    Column(modifier = Modifier.padding(12.dp)) {
                        Row(
                            modifier = Modifier.fillMaxWidth(),
                            horizontalArrangement = Arrangement.SpaceBetween,
                            verticalAlignment = Alignment.CenterVertically
                        ) {
                            Text(
                                text = "Comments • ${comments.size}",
                                color = TextPrimary,
                                fontSize = 14.sp,
                                fontWeight = FontWeight.Bold
                            )
                            Text(
                                text = if (showCommentsSheet) "Hide ▲" else "View All ▼",
                                color = AccentCyan,
                                fontSize = 12.sp,
                                fontWeight = FontWeight.SemiBold
                            )
                        }

                        if (comments.isNotEmpty() && !showCommentsSheet) {
                            Spacer(modifier = Modifier.height(8.dp))
                            val topCommentAvatar: Any = topComment.authorAvatarUrl ?: if (topComment.authorAvatarRes != 0) topComment.authorAvatarRes else R.drawable.avatar_1
                            Row(verticalAlignment = Alignment.CenterVertically) {
                                AsyncImage(
                                    model = topCommentAvatar,
                                    placeholder = painterResource(id = R.drawable.avatar_1),
                                    error = painterResource(id = R.drawable.avatar_1),
                                    contentDescription = topComment.authorName,
                                    contentScale = ContentScale.Crop,
                                    modifier = Modifier
                                        .size(24.dp)
                                        .clip(CircleShape)
                                )
                                Spacer(modifier = Modifier.width(8.dp))
                                Text(
                                    text = topComment.text,
                                    color = TextSecondary,
                                    fontSize = 12.sp,
                                    maxLines = 1,
                                    overflow = TextOverflow.Ellipsis
                                )
                            }
                        }
                    }
                }
            }

            // Expanded Comments List & Input
            if (showCommentsSheet) {
                item {
                    Column(
                        modifier = Modifier
                            .fillMaxWidth()
                            .background(DarkSurfaceElevated, RoundedCornerShape(10.dp))
                            .padding(12.dp)
                    ) {
                        // Input to add a new comment
                        Row(
                            modifier = Modifier.fillMaxWidth(),
                            verticalAlignment = Alignment.CenterVertically
                        ) {
                            OutlinedTextField(
                                value = newCommentText,
                                onValueChange = { newCommentText = it },
                                placeholder = { Text("Add a comment...", fontSize = 12.sp, color = TextMuted) },
                                colors = OutlinedTextFieldDefaults.colors(
                                    focusedTextColor = TextPrimary,
                                    unfocusedTextColor = TextPrimary,
                                    focusedBorderColor = PrimaryRed,
                                    unfocusedBorderColor = Color.Transparent,
                                    focusedContainerColor = DarkBackground,
                                    unfocusedContainerColor = DarkBackground
                                ),
                                shape = RoundedCornerShape(20.dp),
                                singleLine = true,
                                modifier = Modifier
                                    .weight(1f)
                                    .height(48.dp)
                                    .testTag("add_comment_input")
                            )

                            Spacer(modifier = Modifier.width(8.dp))

                            IconButton(
                                onClick = {
                                    if (newCommentText.isNotBlank()) {
                                        onAddComment(video.id, newCommentText)
                                        newCommentText = ""
                                    }
                                },
                                modifier = Modifier
                                    .size(38.dp)
                                    .background(PrimaryRed, CircleShape)
                                    .testTag("submit_comment_button")
                            ) {
                                Icon(
                                    imageVector = Icons.AutoMirrored.Filled.Send,
                                    contentDescription = "Post Comment",
                                    tint = Color.White,
                                    modifier = Modifier.size(16.dp)
                                )
                            }
                        }

                        Spacer(modifier = Modifier.height(12.dp))

                        comments.forEach { c ->
                            Row(
                                modifier = Modifier
                                    .fillMaxWidth()
                                    .padding(vertical = 6.dp),
                                verticalAlignment = Alignment.Top
                            ) {
                                val authorAvatar: Any = c.authorAvatarUrl ?: if (c.authorAvatarRes != 0) c.authorAvatarRes else R.drawable.avatar_1
                                AsyncImage(
                                    model = authorAvatar,
                                    placeholder = painterResource(id = R.drawable.avatar_1),
                                    error = painterResource(id = R.drawable.avatar_1),
                                    contentDescription = c.authorName,
                                    contentScale = ContentScale.Crop,
                                    modifier = Modifier
                                        .size(32.dp)
                                        .clip(CircleShape)
                                )
                                Spacer(modifier = Modifier.width(10.dp))
                                Column(modifier = Modifier.weight(1f)) {
                                    Row(verticalAlignment = Alignment.CenterVertically) {
                                        Text(text = c.authorName, color = TextPrimary, fontSize = 12.sp, fontWeight = FontWeight.Bold)
                                        Spacer(modifier = Modifier.width(6.dp))
                                        Text(text = c.timestampText, color = TextMuted, fontSize = 10.sp)
                                    }
                                    Spacer(modifier = Modifier.height(2.dp))
                                    Text(text = c.text, color = TextSecondary, fontSize = 12.sp)
                                }
                            }
                        }
                    }
                }
            }

            // Up Next Section Header
            item {
                Text(
                    text = "Up Next",
                    color = TextPrimary,
                    fontSize = 15.sp,
                    fontWeight = FontWeight.Bold
                )
            }

            // Related Videos List
            items(relatedVideos, key = { it.id }) { rel ->
                CompactVideoRowCard(
                    video = rel,
                    onClick = { onSelectVideo(rel) }
                )
            }

            item {
                Spacer(modifier = Modifier.height(24.dp))
            }
        }
    }
}
