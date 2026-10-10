package com.example.jackisastream.model

data class User(
    val id: String,
    val username: String,
    val fullName: String,
    val email: String,
    val avatarUrl: String? = null,
    val avatarRes: Int? = null,
    val coverUrl: String? = null,
    val subscriberCount: String = "12.4K",
    val balance: Double = 1250.40,
    val isPro: Boolean = true,
    val isVerified: Boolean = true,
    val sessionToken: String? = null
)

data class Video(
    val id: String,
    val title: String,
    val description: String,
    val channelTitle: String,
    val channelAvatarRes: Int = 0,
    val channelAvatarUrl: String? = null,
    val subscriberCount: String = "1.2M",
    val durationText: String = "10:00",
    val durationSeconds: Int = 600,
    val viewsCount: Long = 0L,
    val viewsFormatted: String = "0 views",
    val likesCount: Long = 0L,
    val uploadDateText: String = "Recently",
    val category: String = "General",
    val thumbnailRes: Int = 0,
    val thumbnailUrl: String? = null,
    val videoUrl: String? = null,
    val remoteVideoId: String? = null,
    val isLive: Boolean = false,
    val viewerCount: Int = 0,
    val isLiked: Boolean = false,
    val isDisliked: Boolean = false,
    val isSubscribed: Boolean = false,
    val isSaved: Boolean = false,
    val isRemote: Boolean = false,
    val isVerifiedChannel: Boolean = false,
    val adBreaksSeconds: List<Int> = listOf(0, 180, 420),
    val attachedProductIds: List<String> = emptyList()
)

data class Comment(
    val id: String,
    val authorName: String,
    val authorAvatarRes: Int = 0,
    val authorAvatarUrl: String? = null,
    val text: String,
    val timestampText: String,
    val likesCount: Int = 0,
    val isLiked: Boolean = false
)

data class LiveStream(
    val id: String,
    val title: String,
    val streamerName: String,
    val streamerAvatarRes: Int = 0,
    val streamerAvatarUrl: String? = null,
    val category: String = "Live",
    val viewerCount: Int = 0,
    val thumbnailRes: Int = 0,
    val thumbnailUrl: String? = null,
    val streamDurationText: String = "LIVE",
    val streamTag: String = "LIVE",
    val streamUrl: String? = null,
    val isRemote: Boolean = false
)

enum class ChatBadge {
    BROADCASTER, MODERATOR, VIP, STAKEHOLDER, VIEWER
}

data class ChatMessage(
    val id: String,
    val senderName: String,
    val text: String,
    val badge: ChatBadge = ChatBadge.VIEWER,
    val timestamp: String = "now",
    val tipAmount: Double = 0.0
)

data class StoreProduct(
    val id: String,
    val title: String,
    val description: String,
    val price: Double,
    val currency: String = "USD",
    val category: String,
    val rating: Float,
    val reviewCount: Int,
    val stockCount: Int,
    val imageRes: Int,
    val sellerChannel: String,
    val escrowReturnDays: Int = 14,
    val isDigital: Boolean = false
)

data class CartItem(
    val product: StoreProduct,
    val quantity: Int
)

data class StoreOrder(
    val orderId: String,
    val items: List<CartItem>,
    val totalAmount: Double,
    val dateText: String,
    val status: String, // "Processing", "Escrow Held", "Shipped", "Completed"
    val escrowReleaseDateText: String,
    val paymentMethod: String,
    val receiptCode: String
)

data class LedgerEntry(
    val id: String,
    val dateText: String,
    val type: String,
    val amount: Double,
    val isCredit: Boolean,
    val status: String = "Settled",
    val notes: String
)

data class StakeholderState(
    val totalDistributablePool: Double = 18450.75,
    val reservePoolAmount: Double = 12300.50,
    val totalShares: Long = 100000L,
    val userShares: Long = 3250L,
    val unitPrice: Double = 4.82,
    val gpiGrowthScore: Double = 87.4,
    val distributableRatio: String = "60 / 40",
    val pendingDividends: Double = 602.15,
    val lifetimeEarnings: Double = 4120.90,
    val availableBalance: Double = 1250.40,
    val ledgerHistory: List<LedgerEntry> = emptyList()
)
