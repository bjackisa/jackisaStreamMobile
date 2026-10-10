package com.example.jackisastream.ui

import androidx.activity.compose.BackHandler
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.padding
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.Home
import androidx.compose.material.icons.filled.LiveTv
import androidx.compose.material.icons.filled.MonetizationOn
import androidx.compose.material.icons.filled.Person
import androidx.compose.material.icons.filled.ShoppingBag
import androidx.compose.material3.Icon
import androidx.compose.material3.NavigationBar
import androidx.compose.material3.NavigationBarItem
import androidx.compose.material3.NavigationBarItemDefaults
import androidx.compose.material3.Scaffold
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.collectAsState
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableIntStateOf
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.setValue
import androidx.compose.ui.Modifier
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.platform.testTag
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.unit.sp
import com.example.jackisastream.data.JackisaRepository
import com.example.jackisastream.model.StoreProduct
import com.example.jackisastream.model.Video
import com.example.jackisastream.ui.screens.HomeScreen
import com.example.jackisastream.ui.screens.LiveScreen
import com.example.jackisastream.ui.screens.ProfileScreen
import com.example.jackisastream.ui.screens.StakeholdersScreen
import com.example.jackisastream.ui.screens.StoreScreen
import com.example.jackisastream.ui.screens.StudioUploadDialog
import com.example.jackisastream.ui.screens.WatchScreen
import com.example.jackisastream.ui.theme.DarkSurface
import com.example.jackisastream.ui.theme.DarkSurfaceElevated
import com.example.jackisastream.ui.theme.PrimaryRed
import com.example.jackisastream.ui.theme.TextMuted
import com.example.jackisastream.ui.theme.TextPrimary

@Composable
fun MainApp(
    repository: JackisaRepository = remember { JackisaRepository() }
) {
    val videos by repository.videos.collectAsState()
    val activeVideo by repository.activeVideo.collectAsState()
    val videoCommentsMap by repository.videoComments.collectAsState()
    val liveStreams by repository.liveStreams.collectAsState()
    val activeLiveStream by repository.activeLiveStream.collectAsState()
    val liveChatMessages by repository.liveChatMessages.collectAsState()
    val storeProducts by repository.storeProducts.collectAsState()
    val cartItems by repository.cartItems.collectAsState()
    val orders by repository.orders.collectAsState()
    val stakeholderState by repository.stakeholderState.collectAsState()
    val selectedCategory by repository.selectedCategory.collectAsState()
    val searchQuery by repository.searchQuery.collectAsState()

    var currentTab by remember { mutableIntStateOf(0) } // 0: Home, 1: Live, 2: Store, 3: Stakeholders, 4: Profile
    var isWatchingVideo by remember { mutableStateOf(false) }
    var showUploadDialog by remember { mutableStateOf(false) }

    // Intercept back navigation when watching a video
    BackHandler(enabled = isWatchingVideo) {
        isWatchingVideo = false
    }

    Scaffold(
        bottomBar = {
            if (!isWatchingVideo) {
                NavigationBar(
                    containerColor = DarkSurface,
                    modifier = Modifier.testTag("main_bottom_nav")
                ) {
                    val items = listOf(
                        Triple("Home", Icons.Default.Home, 0),
                        Triple("Live", Icons.Default.LiveTv, 1),
                        Triple("Store", Icons.Default.ShoppingBag, 2),
                        Triple("Stakeholders", Icons.Default.MonetizationOn, 3),
                        Triple("Library", Icons.Default.Person, 4)
                    )

                    items.forEach { (label, icon, index) ->
                        val selected = currentTab == index
                        NavigationBarItem(
                            selected = selected,
                            onClick = { currentTab = index },
                            icon = {
                                Icon(
                                    imageVector = icon,
                                    contentDescription = label
                                )
                            },
                            label = {
                                Text(
                                    text = label,
                                    fontSize = 10.sp,
                                    fontWeight = if (selected) FontWeight.Bold else FontWeight.Normal
                                )
                            },
                            colors = NavigationBarItemDefaults.colors(
                                selectedIconColor = Color.White,
                                selectedTextColor = PrimaryRed,
                                indicatorColor = PrimaryRed,
                                unselectedIconColor = TextMuted,
                                unselectedTextColor = TextMuted
                            ),
                            modifier = Modifier.testTag("nav_item_$label")
                        )
                    }
                }
            }
        }
    ) { innerPadding ->
        Box(
            modifier = Modifier
                .fillMaxSize()
                .padding(innerPadding)
        ) {
            if (isWatchingVideo && activeVideo != null) {
                val currentVid = activeVideo!!
                val comments = videoCommentsMap[currentVid.id] ?: emptyList()

                WatchScreen(
                    video = currentVid,
                    allVideos = videos,
                    comments = comments,
                    storeProducts = storeProducts,
                    onToggleLike = { repository.toggleLikeVideo(it) },
                    onToggleDislike = { repository.toggleDislikeVideo(it) },
                    onToggleSubscribe = { repository.toggleSubscribe(it) },
                    onToggleSave = { repository.toggleSaveVideo(it) },
                    onAddComment = { vidId, text -> repository.addComment(vidId, text) },
                    onSelectVideo = { nextVid ->
                        repository.setActiveVideo(nextVid)
                    },
                    onNavigateToStoreProduct = { product ->
                        isWatchingVideo = false
                        currentTab = 2 // Switch to store tab
                    },
                    onBack = { isWatchingVideo = false }
                )
            } else {
                when (currentTab) {
                    0 -> {
                        HomeScreen(
                            videos = videos,
                            selectedCategory = selectedCategory,
                            searchQuery = searchQuery,
                            featuredLiveStream = liveStreams.firstOrNull(),
                            onCategorySelected = { repository.selectCategory(it) },
                            onSearchQueryChanged = { repository.setSearchQuery(it) },
                            onVideoClick = { video ->
                                repository.setActiveVideo(video)
                                isWatchingVideo = true
                            },
                            onLiveClick = { stream ->
                                repository.setActiveLiveStream(stream)
                                currentTab = 1 // Switch to Live
                            },
                            onOpenProfile = { currentTab = 4 }
                        )
                    }
                    1 -> {
                        LiveScreen(
                            liveStreams = liveStreams,
                            activeLiveStream = activeLiveStream,
                            chatMessages = liveChatMessages,
                            onSelectStream = { stream ->
                                repository.setActiveLiveStream(stream)
                            },
                            onSendMessage = { text ->
                                repository.sendLiveChatMessage("You", text)
                            },
                            onSendTip = { amount ->
                                repository.sendTip(amount)
                            },
                            onGoLiveClick = {
                                showUploadDialog = true
                            }
                        )
                    }
                    2 -> {
                        StoreScreen(
                            products = storeProducts,
                            cartItems = cartItems,
                            orders = orders,
                            onAddToCart = { repository.addToCart(it) },
                            onUpdateQuantity = { prodId, delta -> repository.updateCartQuantity(prodId, delta) },
                            onClearCart = { repository.clearCart() },
                            onCheckout = { method -> repository.checkoutCart(method) }
                        )
                    }
                    3 -> {
                        StakeholdersScreen(
                            state = stakeholderState,
                            onRequestWithdrawal = { amt, dest ->
                                repository.requestWithdrawal(amt, dest)
                            },
                            onOpenUploadDialog = { showUploadDialog = true }
                        )
                    }
                    4 -> {
                        ProfileScreen(
                            videos = videos,
                            onSelectVideo = { video ->
                                repository.setActiveVideo(video)
                                isWatchingVideo = true
                            },
                            onNavigateToStakeholders = { currentTab = 3 },
                            onNavigateToStore = { currentTab = 2 }
                        )
                    }
                }
            }

            // Creator Studio Upload Dialog
            if (showUploadDialog) {
                StudioUploadDialog(
                    onDismiss = { showUploadDialog = false },
                    onUploadVideo = { title, desc, cat, tags ->
                        val uploaded = repository.uploadVideo(title, desc, cat, tags)
                        repository.setActiveVideo(uploaded)
                        isWatchingVideo = true
                        uploaded
                    }
                )
            }
        }
    }
}
