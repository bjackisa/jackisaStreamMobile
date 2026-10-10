package com.example.jackisastream.ui.screens

import androidx.compose.animation.AnimatedVisibility
import androidx.compose.foundation.Image
import androidx.compose.foundation.background
import androidx.compose.foundation.clickable
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
import androidx.compose.foundation.lazy.LazyRow
import androidx.compose.foundation.lazy.items
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.Add
import androidx.compose.material.icons.filled.CheckCircle
import androidx.compose.material.icons.filled.Close
import androidx.compose.material.icons.filled.Delete
import androidx.compose.material.icons.filled.Description
import androidx.compose.material.icons.filled.Remove
import androidx.compose.material.icons.filled.Security
import androidx.compose.material.icons.filled.ShoppingBag
import androidx.compose.material.icons.filled.ShoppingCart
import androidx.compose.material.icons.filled.Star
import androidx.compose.material3.Button
import androidx.compose.material3.ButtonDefaults
import androidx.compose.material3.Card
import androidx.compose.material3.CardDefaults
import androidx.compose.material3.HorizontalDivider
import androidx.compose.material3.FilterChip
import androidx.compose.material3.FilterChipDefaults
import androidx.compose.material3.Icon
import androidx.compose.material3.IconButton
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.OutlinedButton
import androidx.compose.material3.Surface
import androidx.compose.material3.Tab
import androidx.compose.material3.TabRow
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableIntStateOf
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
import com.example.jackisastream.model.CartItem
import com.example.jackisastream.model.StoreOrder
import com.example.jackisastream.model.StoreProduct
import com.example.jackisastream.ui.theme.AccentCyan
import com.example.jackisastream.ui.theme.AccentGreen
import com.example.jackisastream.ui.theme.DarkBackground
import com.example.jackisastream.ui.theme.DarkSurfaceElevated
import com.example.jackisastream.ui.theme.PrimaryRed
import com.example.jackisastream.ui.theme.TextMuted
import com.example.jackisastream.ui.theme.TextPrimary
import com.example.jackisastream.ui.theme.TextSecondary
import com.example.jackisastream.ui.theme.VipGold
import java.util.Locale

@Composable
fun StoreScreen(
    products: List<StoreProduct>,
    cartItems: List<CartItem>,
    orders: List<StoreOrder>,
    onAddToCart: (StoreProduct) -> Unit,
    onUpdateQuantity: (String, Int) -> Unit,
    onClearCart: () -> Unit,
    onCheckout: (String) -> StoreOrder?,
    modifier: Modifier = Modifier
) {
    var selectedTab by remember { mutableIntStateOf(0) } // 0: Products, 1: Cart, 2: Orders
    var selectedCategory by remember { mutableStateOf("All") }
    var selectedProductForDetail by remember { mutableStateOf<StoreProduct?>(null) }
    var selectedOrderForReceipt by remember { mutableStateOf<StoreOrder?>(null) }
    var showCheckoutSuccess by remember { mutableStateOf<StoreOrder?>(null) }

    val categories = listOf("All", "Apparel", "Electronics", "Digital")
    val filteredProducts = remember(products, selectedCategory) {
        if (selectedCategory == "All") products
        else products.filter { it.category.equals(selectedCategory, ignoreCase = true) }
    }

    val cartTotal = remember(cartItems) {
        cartItems.sumOf { it.product.price * it.quantity }
    }
    // JPesa 3% platform escrow processing fee
    val jpesaFee = remember(cartTotal) {
        String.format(Locale.US, "%.2f", cartTotal * 0.03).toDoubleOrNull() ?: 0.0
    }
    val finalTotal = cartTotal + jpesaFee

    Column(
        modifier = modifier
            .fillMaxSize()
            .background(DarkBackground)
    ) {
        // Store Header
        Row(
            modifier = Modifier
                .fillMaxWidth()
                .background(MaterialTheme.colorScheme.surface)
                .padding(horizontal = 16.dp, vertical = 12.dp),
            horizontalArrangement = Arrangement.SpaceBetween,
            verticalAlignment = Alignment.CenterVertically
        ) {
            Column {
                Row(verticalAlignment = Alignment.CenterVertically) {
                    Icon(
                        imageVector = Icons.Default.ShoppingBag,
                        contentDescription = "Jackisa Store",
                        tint = VipGold,
                        modifier = Modifier.size(22.dp)
                    )
                    Spacer(modifier = Modifier.width(6.dp))
                    Text(
                        text = "Jackisa Store",
                        color = TextPrimary,
                        fontSize = 20.sp,
                        fontWeight = FontWeight.Bold
                    )
                }
                Text(
                    text = "Official creator merch, electronics & digital goods",
                    color = TextSecondary,
                    fontSize = 12.sp
                )
            }

            // Cart Shortcut Button with Badge
            Surface(
                shape = RoundedCornerShape(20.dp),
                color = if (cartItems.isNotEmpty()) PrimaryRed else DarkSurfaceElevated,
                modifier = Modifier
                    .clickable { selectedTab = 1 }
                    .testTag("store_cart_badge_btn")
            ) {
                Row(
                    modifier = Modifier.padding(horizontal = 12.dp, vertical = 6.dp),
                    verticalAlignment = Alignment.CenterVertically
                ) {
                    Icon(
                        imageVector = Icons.Default.ShoppingCart,
                        contentDescription = "Cart",
                        tint = Color.White,
                        modifier = Modifier.size(16.dp)
                    )
                    Spacer(modifier = Modifier.width(4.dp))
                    Text(
                        text = "${cartItems.sumOf { it.quantity }}",
                        color = Color.White,
                        fontSize = 12.sp,
                        fontWeight = FontWeight.Bold
                    )
                }
            }
        }

        // Navigation Tabs: Products / Cart / My Orders
        TabRow(
            selectedTabIndex = selectedTab,
            containerColor = MaterialTheme.colorScheme.surface,
            contentColor = PrimaryRed
        ) {
            Tab(
                selected = selectedTab == 0,
                onClick = { selectedTab = 0 },
                text = { Text("Catalogue", fontSize = 13.sp, fontWeight = FontWeight.SemiBold) },
                modifier = Modifier.testTag("tab_catalogue")
            )
            Tab(
                selected = selectedTab == 1,
                onClick = { selectedTab = 1 },
                text = { Text("Cart (${cartItems.size})", fontSize = 13.sp, fontWeight = FontWeight.SemiBold) },
                modifier = Modifier.testTag("tab_cart")
            )
            Tab(
                selected = selectedTab == 2,
                onClick = { selectedTab = 2 },
                text = { Text("My Orders (${orders.size})", fontSize = 13.sp, fontWeight = FontWeight.SemiBold) },
                modifier = Modifier.testTag("tab_orders")
            )
        }

        when (selectedTab) {
            0 -> {
                // Products Catalogue
                Column(modifier = Modifier.fillMaxSize()) {
                    // Category Chips
                    LazyRow(
                        modifier = Modifier
                            .fillMaxWidth()
                            .padding(horizontal = 14.dp, vertical = 8.dp),
                        horizontalArrangement = Arrangement.spacedBy(8.dp)
                    ) {
                        items(categories) { cat ->
                            val isSelected = cat == selectedCategory
                            FilterChip(
                                selected = isSelected,
                                onClick = { selectedCategory = cat },
                                label = { Text(cat, fontSize = 12.sp) },
                                colors = FilterChipDefaults.filterChipColors(
                                    selectedContainerColor = PrimaryRed,
                                    selectedLabelColor = Color.White,
                                    containerColor = DarkSurfaceElevated,
                                    labelColor = TextSecondary
                                ),
                                border = null
                            )
                        }
                    }

                    // Escrow Protection Guarantee Banner
                    Surface(
                        modifier = Modifier
                            .fillMaxWidth()
                            .padding(horizontal = 14.dp, vertical = 4.dp),
                        shape = RoundedCornerShape(8.dp),
                        color = Color(0xFF0F2338)
                    ) {
                        Row(
                            modifier = Modifier.padding(10.dp),
                            verticalAlignment = Alignment.CenterVertically
                        ) {
                            Icon(Icons.Default.Security, contentDescription = "Escrow", tint = AccentCyan, modifier = Modifier.size(20.dp))
                            Spacer(modifier = Modifier.width(8.dp))
                            Column {
                                Text(
                                    text = "Jackisa Store Buyer Escrow",
                                    color = AccentCyan,
                                    fontSize = 12.sp,
                                    fontWeight = FontWeight.Bold
                                )
                                Text(
                                    text = "Payments held safely in escrow until you receive and verify items.",
                                    color = TextSecondary,
                                    fontSize = 11.sp
                                )
                            }
                        }
                    }

                    // Product Cards
                    LazyColumn(
                        modifier = Modifier
                            .fillMaxSize()
                            .testTag("store_product_list"),
                        contentPadding = PaddingValues(14.dp),
                        verticalArrangement = Arrangement.spacedBy(14.dp)
                    ) {
                        items(filteredProducts, key = { it.id }) { product ->
                            Card(
                                modifier = Modifier
                                    .fillMaxWidth()
                                    .clickable { selectedProductForDetail = product }
                                    .testTag("store_product_card_${product.id}"),
                                shape = RoundedCornerShape(12.dp),
                                colors = CardDefaults.cardColors(containerColor = DarkSurfaceElevated)
                            ) {
                                Row(
                                    modifier = Modifier
                                        .fillMaxWidth()
                                        .padding(12.dp),
                                    verticalAlignment = Alignment.CenterVertically
                                ) {
                                    Image(
                                        painter = painterResource(id = product.imageRes),
                                        contentDescription = product.title,
                                        contentScale = ContentScale.Crop,
                                        modifier = Modifier
                                            .size(80.dp)
                                            .clip(RoundedCornerShape(8.dp))
                                    )

                                    Spacer(modifier = Modifier.width(12.dp))

                                    Column(modifier = Modifier.weight(1f)) {
                                        Text(
                                            text = product.title,
                                            color = TextPrimary,
                                            fontSize = 14.sp,
                                            fontWeight = FontWeight.Bold,
                                            maxLines = 1,
                                            overflow = TextOverflow.Ellipsis
                                        )
                                        Spacer(modifier = Modifier.height(2.dp))
                                        Text(
                                            text = "By ${product.sellerChannel} • ${product.category}",
                                            color = TextSecondary,
                                            fontSize = 11.sp
                                        )

                                        Row(
                                            verticalAlignment = Alignment.CenterVertically,
                                            modifier = Modifier.padding(vertical = 4.dp)
                                        ) {
                                            Icon(Icons.Default.Star, contentDescription = "Rating", tint = VipGold, modifier = Modifier.size(13.dp))
                                            Spacer(modifier = Modifier.width(2.dp))
                                            Text("${product.rating} (${product.reviewCount})", color = TextSecondary, fontSize = 11.sp)
                                            Spacer(modifier = Modifier.width(8.dp))
                                            Text("${product.stockCount} in stock", color = AccentGreen, fontSize = 11.sp)
                                        }

                                        Row(
                                            modifier = Modifier.fillMaxWidth(),
                                            horizontalArrangement = Arrangement.SpaceBetween,
                                            verticalAlignment = Alignment.CenterVertically
                                        ) {
                                            Text(
                                                text = "$${product.price} USD",
                                                color = AccentCyan,
                                                fontSize = 15.sp,
                                                fontWeight = FontWeight.Bold
                                            )

                                            Button(
                                                onClick = { onAddToCart(product) },
                                                colors = ButtonDefaults.buttonColors(containerColor = PrimaryRed),
                                                shape = RoundedCornerShape(16.dp),
                                                modifier = Modifier
                                                    .height(32.dp)
                                                    .testTag("add_to_cart_${product.id}")
                                            ) {
                                                Icon(Icons.Default.Add, contentDescription = "Add", modifier = Modifier.size(14.dp))
                                                Spacer(modifier = Modifier.width(2.dp))
                                                Text("Add to Cart", fontSize = 11.sp, fontWeight = FontWeight.Bold)
                                            }
                                        }
                                    }
                                }
                            }
                        }
                    }
                }
            }

            1 -> {
                // Shopping Cart View
                if (cartItems.isEmpty()) {
                    Box(
                        modifier = Modifier.fillMaxSize(),
                        contentAlignment = Alignment.Center
                    ) {
                        Column(horizontalAlignment = Alignment.CenterHorizontally) {
                            Icon(Icons.Default.ShoppingCart, contentDescription = "Empty", tint = TextMuted, modifier = Modifier.size(64.dp))
                            Spacer(modifier = Modifier.height(12.dp))
                            Text("Your Cart is Empty", color = TextPrimary, fontSize = 16.sp, fontWeight = FontWeight.Bold)
                            Spacer(modifier = Modifier.height(6.dp))
                            Text("Explore creator merch and add products to start shopping.", color = TextSecondary, fontSize = 12.sp)
                            Spacer(modifier = Modifier.height(16.dp))
                            Button(
                                onClick = { selectedTab = 0 },
                                colors = ButtonDefaults.buttonColors(containerColor = PrimaryRed),
                                shape = RoundedCornerShape(20.dp)
                            ) {
                                Text("Browse Products")
                            }
                        }
                    }
                } else {
                    LazyColumn(
                        modifier = Modifier
                            .fillMaxSize()
                            .padding(14.dp)
                            .testTag("cart_items_list"),
                        verticalArrangement = Arrangement.spacedBy(12.dp)
                    ) {
                        items(cartItems, key = { it.product.id }) { item ->
                            Card(
                                modifier = Modifier.fillMaxWidth(),
                                shape = RoundedCornerShape(10.dp),
                                colors = CardDefaults.cardColors(containerColor = DarkSurfaceElevated)
                            ) {
                                Row(
                                    modifier = Modifier
                                        .fillMaxWidth()
                                        .padding(10.dp),
                                    verticalAlignment = Alignment.CenterVertically
                                ) {
                                    Image(
                                        painter = painterResource(id = item.product.imageRes),
                                        contentDescription = item.product.title,
                                        contentScale = ContentScale.Crop,
                                        modifier = Modifier
                                            .size(60.dp)
                                            .clip(RoundedCornerShape(6.dp))
                                    )
                                    Spacer(modifier = Modifier.width(10.dp))
                                    Column(modifier = Modifier.weight(1f)) {
                                        Text(text = item.product.title, color = TextPrimary, fontSize = 13.sp, fontWeight = FontWeight.Bold, maxLines = 1)
                                        Text(text = "$${item.product.price} USD each", color = TextSecondary, fontSize = 11.sp)
                                        Text(text = "Subtotal: $${String.format(Locale.US, "%.2f", item.product.price * item.quantity)}", color = AccentCyan, fontSize = 12.sp, fontWeight = FontWeight.SemiBold)
                                    }

                                    // Quantity controls (+ / -)
                                    Row(verticalAlignment = Alignment.CenterVertically) {
                                        IconButton(
                                            onClick = { onUpdateQuantity(item.product.id, -1) },
                                            modifier = Modifier.size(32.dp).testTag("decrease_qty_${item.product.id}")
                                        ) {
                                            Icon(Icons.Default.Remove, contentDescription = "Decrease", tint = TextPrimary)
                                        }
                                        Text(
                                            text = "${item.quantity}",
                                            color = TextPrimary,
                                            fontSize = 13.sp,
                                            fontWeight = FontWeight.Bold,
                                            modifier = Modifier.padding(horizontal = 6.dp)
                                        )
                                        IconButton(
                                            onClick = { onUpdateQuantity(item.product.id, 1) },
                                            modifier = Modifier.size(32.dp).testTag("increase_qty_${item.product.id}")
                                        ) {
                                            Icon(Icons.Default.Add, contentDescription = "Increase", tint = TextPrimary)
                                        }
                                    }
                                }
                            }
                        }

                        // Order Breakdown & Checkout Card
                        item {
                            Card(
                                modifier = Modifier.fillMaxWidth(),
                                shape = RoundedCornerShape(12.dp),
                                colors = CardDefaults.cardColors(containerColor = DarkSurfaceElevated)
                            ) {
                                Column(modifier = Modifier.padding(14.dp)) {
                                    Text("Order Summary", color = TextPrimary, fontSize = 14.sp, fontWeight = FontWeight.Bold)
                                    Spacer(modifier = Modifier.height(10.dp))

                                    Row(modifier = Modifier.fillMaxWidth(), horizontalArrangement = Arrangement.SpaceBetween) {
                                        Text("Items Subtotal", color = TextSecondary, fontSize = 12.sp)
                                        Text("$${String.format(Locale.US, "%.2f", cartTotal)} USD", color = TextPrimary, fontSize = 12.sp)
                                    }
                                    Spacer(modifier = Modifier.height(4.dp))
                                    Row(modifier = Modifier.fillMaxWidth(), horizontalArrangement = Arrangement.SpaceBetween) {
                                        Text("JPesa Escrow Fee (3%)", color = TextSecondary, fontSize = 12.sp)
                                        Text("$${String.format(Locale.US, "%.2f", jpesaFee)} USD", color = TextPrimary, fontSize = 12.sp)
                                    }

                                    HorizontalDivider(color = DarkBackground, modifier = Modifier.padding(vertical = 8.dp))

                                    Row(modifier = Modifier.fillMaxWidth(), horizontalArrangement = Arrangement.SpaceBetween) {
                                        Text("Total Due", color = TextPrimary, fontSize = 14.sp, fontWeight = FontWeight.Bold)
                                        Text("$${String.format(Locale.US, "%.2f", finalTotal)} USD", color = AccentCyan, fontSize = 16.sp, fontWeight = FontWeight.Bold)
                                    }

                                    Spacer(modifier = Modifier.height(14.dp))

                                    Button(
                                        onClick = {
                                            val created = onCheckout("JPesa Wallet")
                                            if (created != null) {
                                                showCheckoutSuccess = created
                                                selectedTab = 2 // Move to orders
                                            }
                                        },
                                        colors = ButtonDefaults.buttonColors(containerColor = PrimaryRed),
                                        shape = RoundedCornerShape(20.dp),
                                        modifier = Modifier
                                            .fillMaxWidth()
                                            .height(44.dp)
                                            .testTag("checkout_button")
                                    ) {
                                        Icon(Icons.Default.Security, contentDescription = "Pay", modifier = Modifier.size(16.dp))
                                        Spacer(modifier = Modifier.width(6.dp))
                                        Text("Pay with Escrow ($${String.format(Locale.US, "%.2f", finalTotal)})", fontWeight = FontWeight.Bold, fontSize = 13.sp)
                                    }
                                }
                            }
                        }
                    }
                }
            }

            2 -> {
                // My Orders View
                LazyColumn(
                    modifier = Modifier
                        .fillMaxSize()
                        .padding(14.dp)
                        .testTag("my_orders_list"),
                    verticalArrangement = Arrangement.spacedBy(12.dp)
                ) {
                    if (showCheckoutSuccess != null) {
                        item {
                            Surface(
                                shape = RoundedCornerShape(10.dp),
                                color = AccentGreen.copy(alpha = 0.2f),
                                modifier = Modifier.fillMaxWidth()
                            ) {
                                Row(
                                    modifier = Modifier.padding(12.dp),
                                    verticalAlignment = Alignment.CenterVertically
                                ) {
                                    Icon(Icons.Default.CheckCircle, contentDescription = "Success", tint = AccentGreen)
                                    Spacer(modifier = Modifier.width(10.dp))
                                    Column {
                                        Text("Order Placed Successfully!", color = TextPrimary, fontWeight = FontWeight.Bold, fontSize = 13.sp)
                                        Text("Receipt ${showCheckoutSuccess?.receiptCode} generated. Escrow active.", color = TextSecondary, fontSize = 11.sp)
                                    }
                                }
                            }
                        }
                    }

                    items(orders, key = { it.orderId }) { order ->
                        Card(
                            modifier = Modifier.fillMaxWidth(),
                            shape = RoundedCornerShape(10.dp),
                            colors = CardDefaults.cardColors(containerColor = DarkSurfaceElevated)
                        ) {
                            Column(modifier = Modifier.padding(12.dp)) {
                                Row(
                                    modifier = Modifier.fillMaxWidth(),
                                    horizontalArrangement = Arrangement.SpaceBetween,
                                    verticalAlignment = Alignment.CenterVertically
                                ) {
                                    Text(text = order.orderId, color = TextPrimary, fontWeight = FontWeight.Bold, fontSize = 13.sp)
                                    Surface(
                                        shape = RoundedCornerShape(4.dp),
                                        color = if (order.status == "Delivered") AccentGreen else VipGold.copy(alpha = 0.2f)
                                    ) {
                                        Text(
                                            text = order.status,
                                            color = if (order.status == "Delivered") Color.Black else VipGold,
                                            fontSize = 11.sp,
                                            fontWeight = FontWeight.Bold,
                                            modifier = Modifier.padding(horizontal = 6.dp, vertical = 2.dp)
                                        )
                                    }
                                }

                                Spacer(modifier = Modifier.height(6.dp))
                                Text(
                                    text = "${order.items.size} item(s) • Total: $${order.totalAmount} USD",
                                    color = AccentCyan,
                                    fontSize = 13.sp,
                                    fontWeight = FontWeight.SemiBold
                                )
                                Text(
                                    text = "Date: ${order.dateText} • ${order.escrowReleaseDateText}",
                                    color = TextSecondary,
                                    fontSize = 11.sp
                                )

                                Spacer(modifier = Modifier.height(8.dp))

                                OutlinedButton(
                                    onClick = { selectedOrderForReceipt = order },
                                    shape = RoundedCornerShape(16.dp),
                                    modifier = Modifier
                                        .height(32.dp)
                                        .testTag("view_receipt_${order.orderId}")
                                ) {
                                    Icon(Icons.Default.Description, contentDescription = "Receipt", modifier = Modifier.size(14.dp))
                                    Spacer(modifier = Modifier.width(4.dp))
                                    Text("View Invoice & Receipt", fontSize = 11.sp)
                                }
                            }
                        }
                    }
                }
            }
        }

        // Receipt Modal Dialog
        if (selectedOrderForReceipt != null) {
            val order = selectedOrderForReceipt!!
            Box(
                modifier = Modifier
                    .fillMaxSize()
                    .background(Color.Black.copy(alpha = 0.7f))
                    .clickable { selectedOrderForReceipt = null }
                    .padding(24.dp),
                contentAlignment = Alignment.Center
            ) {
                Card(
                    modifier = Modifier
                        .fillMaxWidth()
                        .clickable(enabled = false) {},
                    shape = RoundedCornerShape(16.dp),
                    colors = CardDefaults.cardColors(containerColor = DarkSurfaceElevated)
                ) {
                    Column(modifier = Modifier.padding(18.dp)) {
                        Row(
                            modifier = Modifier.fillMaxWidth(),
                            horizontalArrangement = Arrangement.SpaceBetween,
                            verticalAlignment = Alignment.CenterVertically
                        ) {
                            Text("Official Digital Receipt", color = TextPrimary, fontSize = 16.sp, fontWeight = FontWeight.Bold)
                            IconButton(onClick = { selectedOrderForReceipt = null }, modifier = Modifier.size(28.dp)) {
                                Icon(Icons.Default.Close, contentDescription = "Close", tint = TextPrimary)
                            }
                        }
                        HorizontalDivider(color = DarkBackground, modifier = Modifier.padding(vertical = 8.dp))
                        Text("Doc Number: ${order.receiptCode}", color = AccentCyan, fontSize = 12.sp, fontWeight = FontWeight.Bold)
                        Text("Order ID: ${order.orderId}", color = TextSecondary, fontSize = 11.sp)
                        Text("Payment: ${order.paymentMethod}", color = TextSecondary, fontSize = 11.sp)
                        Text("Escrow Protection: Active (14 days return policy)", color = VipGold, fontSize = 11.sp)
                        Spacer(modifier = Modifier.height(10.dp))
                        order.items.forEach { item ->
                            Row(modifier = Modifier.fillMaxWidth(), horizontalArrangement = Arrangement.SpaceBetween) {
                                Text("${item.quantity}x ${item.product.title}", color = TextPrimary, fontSize = 12.sp, maxLines = 1, modifier = Modifier.weight(1f))
                                Text("$${item.product.price * item.quantity}", color = TextPrimary, fontSize = 12.sp, fontWeight = FontWeight.Bold)
                            }
                        }
                        HorizontalDivider(color = DarkBackground, modifier = Modifier.padding(vertical = 8.dp))
                        Row(modifier = Modifier.fillMaxWidth(), horizontalArrangement = Arrangement.SpaceBetween) {
                            Text("Total Paid", color = TextPrimary, fontSize = 14.sp, fontWeight = FontWeight.Bold)
                            Text("$${order.totalAmount} USD", color = AccentCyan, fontSize = 15.sp, fontWeight = FontWeight.Bold)
                        }
                    }
                }
            }
        }
    }
}
