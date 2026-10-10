package com.example.jackisastream.data

import com.example.jackisastream.R
import com.example.jackisastream.data.api.ApiResult
import com.example.jackisastream.data.api.JackisaApiClient
import com.example.jackisastream.model.CartItem
import com.example.jackisastream.model.ChatBadge
import com.example.jackisastream.model.ChatMessage
import com.example.jackisastream.model.Comment
import com.example.jackisastream.model.LedgerEntry
import com.example.jackisastream.model.LiveStream
import com.example.jackisastream.model.StakeholderState
import com.example.jackisastream.model.StoreOrder
import com.example.jackisastream.model.StoreProduct
import com.example.jackisastream.model.User
import com.example.jackisastream.model.Video
import kotlinx.coroutines.CoroutineScope
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.SupervisorJob
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.asStateFlow
import kotlinx.coroutines.flow.update
import kotlinx.coroutines.launch
import java.text.SimpleDateFormat
import java.util.Date
import java.util.Locale

class JackisaRepository(
    private val apiClient: JackisaApiClient = JackisaApiClient()
) {
    private val scope = CoroutineScope(SupervisorJob() + Dispatchers.Main)

    private val _currentUser = MutableStateFlow<User?>(
        User(
            id = "user_101",
            username = "jackisa",
            fullName = "Daniel Jackisa",
            email = "daniel@jackisa.com",
            avatarRes = R.drawable.avatar_1,
            subscriberCount = "14.2K",
            balance = 1250.40,
            isPro = true,
            isVerified = true
        )
    )
    val currentUser: StateFlow<User?> = _currentUser.asStateFlow()

    private val _isSyncing = MutableStateFlow(false)
    val isSyncing: StateFlow<Boolean> = _isSyncing.asStateFlow()

    private val _syncStatusMessage = MutableStateFlow("Connecting to jackisa.com...")
    val syncStatusMessage: StateFlow<String> = _syncStatusMessage.asStateFlow()

    private val _videos = MutableStateFlow<List<Video>>(emptyList())
    val videos: StateFlow<List<Video>> = _videos.asStateFlow()

    private val _activeVideo = MutableStateFlow<Video?>(null)
    val activeVideo: StateFlow<Video?> = _activeVideo.asStateFlow()

    private val _videoComments = MutableStateFlow<Map<String, List<Comment>>>(emptyMap())
    val videoComments: StateFlow<Map<String, List<Comment>>> = _videoComments.asStateFlow()

    private val _liveStreams = MutableStateFlow<List<LiveStream>>(emptyList())
    val liveStreams: StateFlow<List<LiveStream>> = _liveStreams.asStateFlow()

    private val _activeLiveStream = MutableStateFlow<LiveStream?>(null)
    val activeLiveStream: StateFlow<LiveStream?> = _activeLiveStream.asStateFlow()

    private val _liveChatMessages = MutableStateFlow<List<ChatMessage>>(emptyList())
    val liveChatMessages: StateFlow<List<ChatMessage>> = _liveChatMessages.asStateFlow()

    private val _storeProducts = MutableStateFlow<List<StoreProduct>>(emptyList())
    val storeProducts: StateFlow<List<StoreProduct>> = _storeProducts.asStateFlow()

    private val _cartItems = MutableStateFlow<List<CartItem>>(emptyList())
    val cartItems: StateFlow<List<CartItem>> = _cartItems.asStateFlow()

    private val _orders = MutableStateFlow<List<StoreOrder>>(emptyList())
    val orders: StateFlow<List<StoreOrder>> = _orders.asStateFlow()

    private val _stakeholderState = MutableStateFlow(StakeholderState())
    val stakeholderState: StateFlow<StakeholderState> = _stakeholderState.asStateFlow()

    private val _selectedCategory = MutableStateFlow("All")
    val selectedCategory: StateFlow<String> = _selectedCategory.asStateFlow()

    private val _searchQuery = MutableStateFlow("")
    val searchQuery: StateFlow<String> = _searchQuery.asStateFlow()

    init {
        loadInitialData()
        syncWithRemoteServer()
    }

    fun syncWithRemoteServer() {
        scope.launch {
            _isSyncing.value = true
            _syncStatusMessage.value = "Fetching live feed from jackisa.com..."
            when (val result = apiClient.getVideos()) {
                is ApiResult.Success -> {
                    val feed = result.data
                    val remoteVideos = (feed.latestVideos + feed.featuredVideos + feed.topVideos).distinctBy { it.title }
                    if (remoteVideos.isNotEmpty()) {
                        // Prepend remote videos, retaining existing seed videos for comprehensive discovery
                        _videos.update { localList ->
                            (remoteVideos + localList).distinctBy { it.id }
                        }
                        if (_activeVideo.value == null || _activeVideo.value?.isRemote != true) {
                            _activeVideo.value = remoteVideos.first()
                        }
                    }

                    if (feed.liveStreams.isNotEmpty()) {
                        _liveStreams.update { localLive ->
                            (feed.liveStreams + localLive).distinctBy { it.title }
                        }
                        if (_activeLiveStream.value == null || _activeLiveStream.value?.isRemote != true) {
                            _activeLiveStream.value = feed.liveStreams.first()
                        }
                    }
                    _syncStatusMessage.value = "Connected to jackisa.com (${remoteVideos.size} live videos synced)"
                }
                is ApiResult.Error -> {
                    _syncStatusMessage.value = "Offline cache active (${result.message})"
                }
            }
            _isSyncing.value = false
        }
    }

    suspend fun login(username: String, pass: String): String? {
        _isSyncing.value = true
        val res = apiClient.login(username, pass)
        _isSyncing.value = false
        return when (res) {
            is ApiResult.Success -> {
                _currentUser.value = res.data
                null
            }
            is ApiResult.Error -> {
                res.message
            }
        }
    }

    suspend fun register(username: String, email: String, pass: String, confirmPass: String): String? {
        _isSyncing.value = true
        val res = apiClient.register(username, email, pass, confirmPass)
        _isSyncing.value = false
        return when (res) {
            is ApiResult.Success -> null
            is ApiResult.Error -> res.message
        }
    }

    fun loginAsDemoUser(username: String = "Jackisa Streamer") {
        _currentUser.value = User(
            id = "demo_${System.currentTimeMillis()}",
            username = username.lowercase().replace(" ", ""),
            fullName = username,
            email = "${username.lowercase().replace(" ", "")}@jackisa.com",
            avatarRes = R.drawable.avatar_1,
            subscriberCount = "24.8K",
            balance = 1450.00,
            isPro = true,
            isVerified = true
        )
    }

    fun logout() {
        _currentUser.value = null
    }

    private fun loadInitialData() {
        val initialProducts = listOf(
            StoreProduct(
                id = "prod_1",
                title = "Jackisa Official Streamer Hoodie",
                description = "Ultra-soft cotton blend hoodie with embroidered Jackisa Stream broadcast emblem. Perfect for marathon streaming sessions.",
                price = 49.99,
                category = "Apparel",
                rating = 4.9f,
                reviewCount = 128,
                stockCount = 42,
                imageRes = R.drawable.merch_prod_1,
                sellerChannel = "Jackisa Official",
                escrowReturnDays = 14
            ),
            StoreProduct(
                id = "prod_2",
                title = "Studio RGB Dynamic Microphone",
                description = "Cardioid studio-grade condenser microphone with noise cancellation, one-touch mute, and custom RGB lighting.",
                price = 89.50,
                category = "Electronics",
                rating = 4.8f,
                reviewCount = 94,
                stockCount = 18,
                imageRes = R.drawable.merch_prod_2,
                sellerChannel = "TechForge Studio",
                escrowReturnDays = 30
            ),
            StoreProduct(
                id = "prod_3",
                title = "Creator Pro LUTs & Motion Pack",
                description = "Over 60 color grading LUTs and 120 animated stream alerts & lower thirds ready for streaming software.",
                price = 24.00,
                category = "Digital",
                rating = 5.0f,
                reviewCount = 210,
                stockCount = 999,
                imageRes = R.drawable.merch_prod_3,
                sellerChannel = "VisualVortex",
                escrowReturnDays = 7,
                isDigital = true
            )
        )
        _storeProducts.value = initialProducts

        val initialVideos = listOf(
            Video(
                id = "vid_1",
                title = "Global Esports Grand Championship - Final Match Highlights",
                description = "Watch the most intense clutch moments and game-winning plays from the 2026 World Finals! Full replay with tactical breakdown and commentary.",
                channelTitle = "Jackisa Esports",
                channelAvatarRes = R.drawable.avatar_1,
                subscriberCount = "1.4M",
                durationText = "24:15",
                durationSeconds = 1455,
                viewsCount = 342000L,
                viewsFormatted = "342K views",
                likesCount = 28400L,
                uploadDateText = "2 hours ago",
                category = "Gaming",
                thumbnailRes = R.drawable.video_thumb_1,
                adBreaksSeconds = listOf(0, 180, 540, 960),
                attachedProductIds = listOf("prod_1", "prod_2")
            ),
            Video(
                id = "vid_2",
                title = "Building Next-Gen Multiplatform Apps with Kotlin & Jetpack Compose",
                description = "Complete in-depth masterclass on modern reactive UI architecture, state synchronization, clean design patterns, and fluid animations.",
                channelTitle = "CodeStream Hub",
                channelAvatarRes = R.drawable.avatar_2,
                subscriberCount = "480K",
                durationText = "18:40",
                durationSeconds = 1120,
                viewsCount = 96400L,
                viewsFormatted = "96K views",
                likesCount = 12500L,
                uploadDateText = "1 day ago",
                category = "Technology and Innovation",
                thumbnailRes = R.drawable.video_thumb_2,
                attachedProductIds = listOf("prod_3")
            ),
            Video(
                id = "vid_3",
                title = "Cinematic Night City Tour - 4K Ultra Soundscape",
                description = "Immersive atmospheric journey through neon streets, night markets, and skyline rooftops. Binaural 3D audio experience.",
                channelTitle = "Aura Visuals",
                channelAvatarRes = R.drawable.avatar_3,
                subscriberCount = "890K",
                durationText = "15:20",
                durationSeconds = 920,
                viewsCount = 520000L,
                viewsFormatted = "520K views",
                likesCount = 44200L,
                uploadDateText = "3 days ago",
                category = "Entertainment",
                thumbnailRes = R.drawable.video_thumb_3,
                attachedProductIds = listOf("prod_1")
            ),
            Video(
                id = "vid_4",
                title = "Lo-Fi Beats Live & Chill Mix - Focus, Study & Relax",
                description = "Smooth analog warmth and lo-fi hip hop vibes to keep you company while working, coding, or relaxing. Continuous mix.",
                channelTitle = "ChillStation FM",
                channelAvatarRes = R.drawable.avatar_4,
                subscriberCount = "2.1M",
                durationText = "45:00",
                durationSeconds = 2700,
                viewsCount = 1250000L,
                viewsFormatted = "1.2M views",
                likesCount = 89000L,
                uploadDateText = "5 days ago",
                category = "Music",
                thumbnailRes = R.drawable.video_thumb_4,
                attachedProductIds = listOf("prod_1", "prod_3")
            ),
            Video(
                id = "vid_5",
                title = "Deep Tech Breakdown: The Architecture of Decentralized Video CDN",
                description = "How video packets travel from creator camera to thousands of concurrent viewers with sub-second latency and edge caching.",
                channelTitle = "TechForge Studio",
                channelAvatarRes = R.drawable.avatar_1,
                subscriberCount = "310K",
                durationText = "21:05",
                durationSeconds = 1265,
                viewsCount = 74300L,
                viewsFormatted = "74K views",
                likesCount = 8400L,
                uploadDateText = "1 week ago",
                category = "Technology and Innovation",
                thumbnailRes = R.drawable.video_thumb_5,
                attachedProductIds = listOf("prod_2")
            ),
            Video(
                id = "vid_6",
                title = "Indie Game Creator Showcase: Behind the Scenes of 'Starlight Horizon'",
                description = "Exclusive interview with the solo developer of this year's most viral sci-fi exploration game. Artwork, mechanics, and soundtrack.",
                channelTitle = "IndiePulse",
                channelAvatarRes = R.drawable.avatar_2,
                subscriberCount = "620K",
                durationText = "32:10",
                durationSeconds = 1930,
                viewsCount = 188000L,
                viewsFormatted = "188K views",
                likesCount = 19500L,
                uploadDateText = "2 weeks ago",
                category = "Gaming",
                thumbnailRes = R.drawable.video_thumb_6,
                attachedProductIds = listOf("prod_2", "prod_3")
            )
        )
        _videos.value = initialVideos
        _activeVideo.value = initialVideos.first()

        val sampleComments = mapOf(
            "vid_1" to listOf(
                Comment("c1", "Alex Vance", R.drawable.avatar_1, null, "That comeback in round 5 was purely historic! Absolutely unreal gameplay.", "25 mins ago", 342, true),
                Comment("c2", "Sarah Connor", R.drawable.avatar_2, null, "The stream bitrate and video quality on Jackisa Stream are butter smooth!", "1 hour ago", 189),
                Comment("c3", "Liam Tech", R.drawable.avatar_3, null, "Best esports broadcast production this year. Those slow-mo replays are top tier.", "2 hours ago", 75)
            ),
            "vid_2" to listOf(
                Comment("c4", "David K", R.drawable.avatar_4, null, "Clear, concise, and production-ready code examples. Subscribed immediately!", "3 hours ago", 98),
                Comment("c5", "Elena Rostova", R.drawable.avatar_1, null, "The explanation of state hoisting and lifecycle in Compose saved my project.", "5 hours ago", 64)
            )
        )
        _videoComments.value = sampleComments

        val initialLives = listOf(
            LiveStream("live_1", "🔴 Global Showdown 2026: Championship Semifinals LIVE", "Jackisa Esports", R.drawable.avatar_1, null, "Gaming", 28450, R.drawable.hero_stream_banner, null, "02:14:30"),
            LiveStream("live_2", "🔴 Late Night Synthwave & Coding Jam Session", "ChillStation FM", R.drawable.avatar_4, null, "Music", 4920, R.drawable.video_thumb_4, null, "01:05:12"),
            LiveStream("live_3", "🔴 Interactive Q&A: The Future of Stream Monetization & Stakeholders", "Jackisa Official", R.drawable.avatar_2, null, "Tech", 1840, R.drawable.video_thumb_5, null, "00:48:19")
        )
        _liveStreams.value = initialLives
        _activeLiveStream.value = initialLives.first()

        _liveChatMessages.value = listOf(
            ChatMessage("m1", "ProGamer99", "Let's GOOO! Hyped for the stream!", ChatBadge.VIP),
            ChatMessage("m2", "Elena_Stream", "Jackisa Stream live bitrate is so crisp!", ChatBadge.VIEWER),
            ChatMessage("m3", "Jackisa Moderator", "Welcome everyone! Keep the chat friendly and enjoy the show.", ChatBadge.MODERATOR),
            ChatMessage("m4", "Marcus Aurelius", "Sent 10.00 USD! Big support for the team!", ChatBadge.STAKEHOLDER, tipAmount = 10.0),
            ChatMessage("m5", "CyberNinja", "That play was insane!! 🔥🔥🔥", ChatBadge.VIEWER)
        )

        val initialLedger = listOf(
            LedgerEntry("led_001", "Today 01:00 UTC", "Daily Dividend (Wave 1)", 86.40, true, "Settled", "Revenue distribution run based on 3,250 units"),
            LedgerEntry("led_002", "Yesterday 01:00 UTC", "Daily Dividend (Wave 1)", 92.10, true, "Settled", "Automated distribution run - 60% pool split"),
            LedgerEntry("led_003", "3 days ago", "Store Escrow Commission", 28.50, true, "Settled", "10% commission on creator merch orders"),
            LedgerEntry("led_004", "5 days ago", "Withdrawal to Bank/Wallet", 250.00, false, "Completed", "Processed payout via JPesa payout gateway")
        )
        _stakeholderState.update { it.copy(ledgerHistory = initialLedger) }

        _orders.value = listOf(
            StoreOrder(
                orderId = "JS-ORD-9821",
                items = listOf(CartItem(initialProducts[0], 1)),
                totalAmount = 49.99,
                dateText = "Oct 08, 2026",
                status = "Escrow Held",
                escrowReleaseDateText = "Oct 22, 2026 (14-day Escrow)",
                paymentMethod = "JPesa Digital Wallet",
                receiptCode = "RCP-2026-9821"
            )
        )
    }

    fun selectCategory(category: String) {
        _selectedCategory.value = category
    }

    fun setSearchQuery(query: String) {
        _searchQuery.value = query
    }

    fun setActiveVideo(video: Video) {
        _activeVideo.value = video
    }

    fun setActiveLiveStream(stream: LiveStream) {
        _activeLiveStream.value = stream
    }

    fun toggleLikeVideo(videoId: String) {
        _videos.update { list ->
            list.map { v ->
                if (v.id == videoId) {
                    val newLiked = !v.isLiked
                    val newCount = if (newLiked) v.likesCount + 1 else (v.likesCount - 1).coerceAtLeast(0)
                    v.copy(isLiked = newLiked, isDisliked = false, likesCount = newCount)
                } else v
            }
        }
        if (_activeVideo.value?.id == videoId) {
            _activeVideo.value = _videos.value.firstOrNull { it.id == videoId }
        }
    }

    fun toggleDislikeVideo(videoId: String) {
        _videos.update { list ->
            list.map { v ->
                if (v.id == videoId) {
                    val newDisliked = !v.isDisliked
                    val newLikesCount = if (v.isLiked) (v.likesCount - 1).coerceAtLeast(0) else v.likesCount
                    v.copy(isDisliked = newDisliked, isLiked = false, likesCount = newLikesCount)
                } else v
            }
        }
        if (_activeVideo.value?.id == videoId) {
            _activeVideo.value = _videos.value.firstOrNull { it.id == videoId }
        }
    }

    fun toggleSubscribe(videoId: String) {
        _videos.update { list ->
            list.map { v ->
                if (v.id == videoId) {
                    v.copy(isSubscribed = !v.isSubscribed)
                } else v
            }
        }
        if (_activeVideo.value?.id == videoId) {
            _activeVideo.value = _videos.value.firstOrNull { it.id == videoId }
        }
    }

    fun toggleSaveVideo(videoId: String) {
        _videos.update { list ->
            list.map { v ->
                if (v.id == videoId) {
                    v.copy(isSaved = !v.isSaved)
                } else v
            }
        }
        if (_activeVideo.value?.id == videoId) {
            _activeVideo.value = _videos.value.firstOrNull { it.id == videoId }
        }
    }

    fun addComment(videoId: String, text: String) {
        if (text.isBlank()) return
        val user = _currentUser.value
        val newComment = Comment(
            id = "c_${System.currentTimeMillis()}",
            authorName = user?.fullName ?: "You",
            authorAvatarRes = user?.avatarRes ?: R.drawable.avatar_1,
            authorAvatarUrl = user?.avatarUrl,
            text = text.trim(),
            timestampText = "Just now",
            likesCount = 0
        )
        _videoComments.update { map ->
            val list = map[videoId]?.toMutableList() ?: mutableListOf()
            list.add(0, newComment)
            map + (videoId to list)
        }
    }

    fun sendLiveChatMessage(senderName: String, text: String, badge: ChatBadge = ChatBadge.VIEWER) {
        if (text.isBlank()) return
        val newMsg = ChatMessage(
            id = "msg_${System.currentTimeMillis()}",
            senderName = senderName,
            text = text.trim(),
            badge = badge,
            timestamp = "Just now"
        )
        _liveChatMessages.update { it + newMsg }
    }

    fun sendTip(amount: Double) {
        val user = _currentUser.value
        val newMsg = ChatMessage(
            id = "tip_${System.currentTimeMillis()}",
            senderName = user?.fullName ?: "You",
            text = "Sent a tip of $${String.format(Locale.US, "%.2f", amount)} USD to support the stream! 🚀",
            badge = ChatBadge.STAKEHOLDER,
            timestamp = "Just now",
            tipAmount = amount
        )
        _liveChatMessages.update { it + newMsg }
    }

    fun addToCart(product: StoreProduct) {
        _cartItems.update { current ->
            val existing = current.find { it.product.id == product.id }
            if (existing != null) {
                current.map {
                    if (it.product.id == product.id) it.copy(quantity = it.quantity + 1)
                    else it
                }
            } else {
                current + CartItem(product, 1)
            }
        }
    }

    fun updateCartQuantity(productId: String, delta: Int) {
        _cartItems.update { current ->
            current.mapNotNull { item ->
                if (item.product.id == productId) {
                    val newQ = item.quantity + delta
                    if (newQ > 0) item.copy(quantity = newQ) else null
                } else item
            }
        }
    }

    fun clearCart() {
        _cartItems.value = emptyList()
    }

    fun checkoutCart(paymentMethod: String): StoreOrder? {
        val items = _cartItems.value
        if (items.isEmpty()) return null

        val total = items.sumOf { it.product.price * it.quantity }
        val sdf = SimpleDateFormat("MMM dd, yyyy", Locale.US)
        val todayStr = sdf.format(Date())
        val orderNum = (1000..9999).random()

        val newOrder = StoreOrder(
            orderId = "JS-ORD-$orderNum",
            items = items,
            totalAmount = total,
            dateText = todayStr,
            status = "Escrow Held",
            escrowReleaseDateText = "Protected 14-day Escrow",
            paymentMethod = paymentMethod,
            receiptCode = "RCP-2026-$orderNum"
        )

        _orders.update { listOf(newOrder) + it }
        _cartItems.value = emptyList()
        return newOrder
    }

    fun requestWithdrawal(amount: Double, destination: String): Boolean {
        val current = _stakeholderState.value
        if (amount <= 0 || amount > current.availableBalance) return false

        val sdf = SimpleDateFormat("MMM dd, yyyy", Locale.US)
        val todayStr = sdf.format(Date())
        val newEntry = LedgerEntry(
            id = "w_${System.currentTimeMillis()}",
            dateText = todayStr,
            type = "Withdrawal to $destination",
            amount = amount,
            isCredit = false,
            status = "Processing",
            notes = "Payout initiated to $destination via JPesa Gateway"
        )

        _stakeholderState.update {
            it.copy(
                availableBalance = it.availableBalance - amount,
                ledgerHistory = listOf(newEntry) + it.ledgerHistory
            )
        }
        return true
    }

    fun uploadVideo(
        title: String,
        description: String,
        category: String,
        tags: String
    ): Video {
        val user = _currentUser.value
        val newVideo = Video(
            id = "vid_${System.currentTimeMillis()}",
            title = title,
            description = description,
            channelTitle = user?.fullName ?: "Your Channel",
            channelAvatarRes = user?.avatarRes ?: R.drawable.avatar_1,
            channelAvatarUrl = user?.avatarUrl,
            subscriberCount = user?.subscriberCount ?: "12.4K",
            durationText = "12:30",
            durationSeconds = 750,
            viewsCount = 1L,
            viewsFormatted = "1 view",
            likesCount = 0L,
            uploadDateText = "Just now",
            category = category,
            thumbnailRes = R.drawable.video_thumb_1,
            adBreaksSeconds = listOf(0, 180),
            attachedProductIds = listOf("prod_1")
        )
        _videos.update { listOf(newVideo) + it }
        return newVideo
    }
}
