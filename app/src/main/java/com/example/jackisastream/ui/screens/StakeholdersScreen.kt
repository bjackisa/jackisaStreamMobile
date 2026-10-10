package com.example.jackisastream.ui.screens

import androidx.compose.foundation.background
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
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.AccountBalanceWallet
import androidx.compose.material.icons.filled.ArrowDownward
import androidx.compose.material.icons.filled.ArrowUpward
import androidx.compose.material.icons.filled.AutoGraph
import androidx.compose.material.icons.filled.MonetizationOn
import androidx.compose.material.icons.filled.PieChart
import androidx.compose.material.icons.filled.Publish
import androidx.compose.material.icons.filled.VerifiedUser
import androidx.compose.material3.Button
import androidx.compose.material3.ButtonDefaults
import androidx.compose.material3.Card
import androidx.compose.material3.CardDefaults
import androidx.compose.material3.Icon
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
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.platform.testTag
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import com.example.jackisastream.model.StakeholderState
import com.example.jackisastream.ui.theme.AccentCyan
import com.example.jackisastream.ui.theme.AccentGreen
import com.example.jackisastream.ui.theme.DarkBackground
import com.example.jackisastream.ui.theme.DarkSurfaceElevated
import com.example.jackisastream.ui.theme.PrimaryRed
import com.example.jackisastream.ui.theme.StakeholderPurple
import com.example.jackisastream.ui.theme.TextMuted
import com.example.jackisastream.ui.theme.TextPrimary
import com.example.jackisastream.ui.theme.TextSecondary
import com.example.jackisastream.ui.theme.VipGold

@Composable
fun StakeholdersScreen(
    state: StakeholderState,
    onRequestWithdrawal: (Double, String) -> Boolean,
    onOpenUploadDialog: () -> Unit,
    modifier: Modifier = Modifier
) {
    var showWithdrawDialog by remember { mutableStateOf(false) }
    var withdrawAmountText by remember { mutableStateOf("") }
    var withdrawDestination by remember { mutableStateOf("JPesa Mobile Wallet") }
    var withdrawSuccessMsg by remember { mutableStateOf<String?>(null) }

    Column(
        modifier = modifier
            .fillMaxSize()
            .background(DarkBackground)
    ) {
        // Top Header
        Row(
            modifier = Modifier
                .fillMaxWidth()
                .background(MaterialTheme.colorScheme.surface)
                .padding(horizontal = 16.dp, vertical = 14.dp),
            horizontalArrangement = Arrangement.SpaceBetween,
            verticalAlignment = Alignment.CenterVertically
        ) {
            Column {
                Row(verticalAlignment = Alignment.CenterVertically) {
                    Icon(
                        imageVector = Icons.Default.MonetizationOn,
                        contentDescription = "Stakeholders",
                        tint = StakeholderPurple,
                        modifier = Modifier.size(22.dp)
                    )
                    Spacer(modifier = Modifier.width(6.dp))
                    Text(
                        text = "Stakeholders & Studio",
                        color = TextPrimary,
                        fontSize = 20.sp,
                        fontWeight = FontWeight.Bold
                    )
                }
                Text(
                    text = "Dividend ledger, growth metrics & creator studio",
                    color = TextSecondary,
                    fontSize = 12.sp
                )
            }

            Button(
                onClick = onOpenUploadDialog,
                colors = ButtonDefaults.buttonColors(containerColor = PrimaryRed),
                shape = RoundedCornerShape(20.dp),
                modifier = Modifier.testTag("open_upload_video_button")
            ) {
                Icon(Icons.Default.Publish, contentDescription = "Upload", modifier = Modifier.size(16.dp))
                Spacer(modifier = Modifier.width(4.dp))
                Text("Upload", fontSize = 12.sp, fontWeight = FontWeight.Bold)
            }
        }

        LazyColumn(
            modifier = Modifier
                .fillMaxSize()
                .padding(16.dp),
            verticalArrangement = Arrangement.spacedBy(16.dp)
        ) {
            // Withdrawal notification if executed
            if (withdrawSuccessMsg != null) {
                item {
                    Surface(
                        shape = RoundedCornerShape(8.dp),
                        color = AccentGreen.copy(alpha = 0.2f),
                        modifier = Modifier.fillMaxWidth()
                    ) {
                        Text(
                            text = withdrawSuccessMsg!!,
                            color = AccentGreen,
                            fontSize = 12.sp,
                            fontWeight = FontWeight.SemiBold,
                            modifier = Modifier.padding(12.dp)
                        )
                    }
                }
            }

            // Overview Portfolio Card
            item {
                Card(
                    modifier = Modifier.fillMaxWidth().testTag("stakeholder_portfolio_card"),
                    shape = RoundedCornerShape(14.dp),
                    colors = CardDefaults.cardColors(containerColor = DarkSurfaceElevated)
                ) {
                    Column(modifier = Modifier.padding(16.dp)) {
                        Row(
                            modifier = Modifier.fillMaxWidth(),
                            horizontalArrangement = Arrangement.SpaceBetween,
                            verticalAlignment = Alignment.CenterVertically
                        ) {
                            Text(
                                text = "Your Available Dividend Balance",
                                color = TextSecondary,
                                fontSize = 12.sp
                            )
                            Surface(
                                shape = RoundedCornerShape(4.dp),
                                color = StakeholderPurple.copy(alpha = 0.2f)
                            ) {
                                Text(
                                    text = "3,250 Units (3.25%)",
                                    color = StakeholderPurple,
                                    fontSize = 11.sp,
                                    fontWeight = FontWeight.Bold,
                                    modifier = Modifier.padding(horizontal = 6.dp, vertical = 2.dp)
                                )
                            }
                        }

                        Spacer(modifier = Modifier.height(6.dp))

                        Text(
                            text = "$${state.availableBalance} USD",
                            color = AccentCyan,
                            fontSize = 28.sp,
                            fontWeight = FontWeight.ExtraBold
                        )

                        Spacer(modifier = Modifier.height(14.dp))

                        Row(
                            modifier = Modifier.fillMaxWidth(),
                            horizontalArrangement = Arrangement.spacedBy(10.dp)
                        ) {
                            Button(
                                onClick = { showWithdrawDialog = true },
                                colors = ButtonDefaults.buttonColors(containerColor = PrimaryRed),
                                shape = RoundedCornerShape(20.dp),
                                modifier = Modifier
                                    .weight(1f)
                                    .height(38.dp)
                                    .testTag("withdraw_funds_button")
                            ) {
                                Icon(Icons.Default.AccountBalanceWallet, contentDescription = "Withdraw", modifier = Modifier.size(16.dp))
                                Spacer(modifier = Modifier.width(4.dp))
                                Text("Withdraw Funds", fontSize = 12.sp, fontWeight = FontWeight.Bold)
                            }
                        }
                    }
                }
            }

            // Performance & Pool Split Metrics
            item {
                Row(
                    modifier = Modifier.fillMaxWidth(),
                    horizontalArrangement = Arrangement.spacedBy(10.dp)
                ) {
                    // Distributable Pool Card
                    Card(
                        modifier = Modifier.weight(1f),
                        shape = RoundedCornerShape(12.dp),
                        colors = CardDefaults.cardColors(containerColor = DarkSurfaceElevated)
                    ) {
                        Column(modifier = Modifier.padding(12.dp)) {
                            Row(verticalAlignment = Alignment.CenterVertically) {
                                Icon(Icons.Default.PieChart, contentDescription = "Pool", tint = VipGold, modifier = Modifier.size(16.dp))
                                Spacer(modifier = Modifier.width(4.dp))
                                Text("Daily Pool", color = TextSecondary, fontSize = 11.sp)
                            }
                            Spacer(modifier = Modifier.height(4.dp))
                            Text("$${state.totalDistributablePool}", color = TextPrimary, fontSize = 16.sp, fontWeight = FontWeight.Bold)
                            Text("Split: ${state.distributableRatio}", color = TextMuted, fontSize = 10.sp)
                        }
                    }

                    // GPI Growth Score Card
                    Card(
                        modifier = Modifier.weight(1f),
                        shape = RoundedCornerShape(12.dp),
                        colors = CardDefaults.cardColors(containerColor = DarkSurfaceElevated)
                    ) {
                        Column(modifier = Modifier.padding(12.dp)) {
                            Row(verticalAlignment = Alignment.CenterVertically) {
                                Icon(Icons.Default.AutoGraph, contentDescription = "GPI", tint = AccentGreen, modifier = Modifier.size(16.dp))
                                Spacer(modifier = Modifier.width(4.dp))
                                Text("GPI Score", color = TextSecondary, fontSize = 11.sp)
                            }
                            Spacer(modifier = Modifier.height(4.dp))
                            Text("${state.gpiGrowthScore} / 100", color = AccentGreen, fontSize = 16.sp, fontWeight = FontWeight.Bold)
                            Text("Unit: $${state.unitPrice} USD", color = TextMuted, fontSize = 10.sp)
                        }
                    }
                }
            }

            // Append-Only Financial Ledger Title
            item {
                Row(
                    modifier = Modifier.fillMaxWidth(),
                    horizontalArrangement = Arrangement.SpaceBetween,
                    verticalAlignment = Alignment.CenterVertically
                ) {
                    Row(verticalAlignment = Alignment.CenterVertically) {
                        Icon(Icons.Default.VerifiedUser, contentDescription = "Ledger", tint = AccentCyan, modifier = Modifier.size(18.dp))
                        Spacer(modifier = Modifier.width(6.dp))
                        Text("Financial Ledger (Append-Only)", color = TextPrimary, fontSize = 15.sp, fontWeight = FontWeight.Bold)
                    }
                    Text("Trigger Protected", color = TextMuted, fontSize = 10.sp)
                }
            }

            // Ledger Entries
            items(state.ledgerHistory, key = { it.id }) { entry ->
                Card(
                    modifier = Modifier.fillMaxWidth().testTag("ledger_entry_${entry.id}"),
                    shape = RoundedCornerShape(10.dp),
                    colors = CardDefaults.cardColors(containerColor = DarkSurfaceElevated)
                ) {
                    Row(
                        modifier = Modifier
                            .fillMaxWidth()
                            .padding(12.dp),
                        verticalAlignment = Alignment.CenterVertically
                    ) {
                        Icon(
                            imageVector = if (entry.isCredit) Icons.Default.ArrowDownward else Icons.Default.ArrowUpward,
                            contentDescription = if (entry.isCredit) "Credit" else "Debit",
                            tint = if (entry.isCredit) AccentGreen else PrimaryRed,
                            modifier = Modifier.size(24.dp)
                        )
                        Spacer(modifier = Modifier.width(10.dp))
                        Column(modifier = Modifier.weight(1f)) {
                            Text(text = entry.type, color = TextPrimary, fontSize = 13.sp, fontWeight = FontWeight.SemiBold)
                            Text(text = "${entry.dateText} • ${entry.notes}", color = TextSecondary, fontSize = 11.sp)
                        }
                        Column(horizontalAlignment = Alignment.End) {
                            Text(
                                text = "${if (entry.isCredit) "+" else "-"}$${entry.amount}",
                                color = if (entry.isCredit) AccentGreen else PrimaryRed,
                                fontSize = 13.sp,
                                fontWeight = FontWeight.Bold
                            )
                            Text(text = entry.status, color = TextMuted, fontSize = 10.sp)
                        }
                    }
                }
            }
        }

        // Withdrawal Dialog Modal
        if (showWithdrawDialog) {
            Box(
                modifier = Modifier
                    .fillMaxSize()
                    .background(Color.Black.copy(alpha = 0.7f))
                    .padding(24.dp),
                contentAlignment = Alignment.Center
            ) {
                Card(
                    shape = RoundedCornerShape(16.dp),
                    colors = CardDefaults.cardColors(containerColor = DarkSurfaceElevated),
                    modifier = Modifier.fillMaxWidth()
                ) {
                    Column(modifier = Modifier.padding(16.dp)) {
                        Text("Withdraw Dividend Earnings", color = TextPrimary, fontSize = 16.sp, fontWeight = FontWeight.Bold)
                        Spacer(modifier = Modifier.height(4.dp))
                        Text("Available balance: $${state.availableBalance} USD", color = TextSecondary, fontSize = 12.sp)

                        Spacer(modifier = Modifier.height(12.dp))

                        OutlinedTextField(
                            value = withdrawAmountText,
                            onValueChange = { withdrawAmountText = it },
                            label = { Text("Amount (USD)") },
                            colors = OutlinedTextFieldDefaults.colors(
                                focusedTextColor = TextPrimary,
                                unfocusedTextColor = TextPrimary,
                                focusedBorderColor = PrimaryRed,
                                unfocusedBorderColor = TextMuted
                            ),
                            singleLine = true,
                            modifier = Modifier.fillMaxWidth().testTag("withdraw_amount_input")
                        )

                        Spacer(modifier = Modifier.height(10.dp))

                        OutlinedTextField(
                            value = withdrawDestination,
                            onValueChange = { withdrawDestination = it },
                            label = { Text("Payout Gateway / Destination") },
                            colors = OutlinedTextFieldDefaults.colors(
                                focusedTextColor = TextPrimary,
                                unfocusedTextColor = TextPrimary,
                                focusedBorderColor = PrimaryRed,
                                unfocusedBorderColor = TextMuted
                            ),
                            singleLine = true,
                            modifier = Modifier.fillMaxWidth()
                        )

                        Spacer(modifier = Modifier.height(16.dp))

                        Row(
                            modifier = Modifier.fillMaxWidth(),
                            horizontalArrangement = Arrangement.End
                        ) {
                            Button(
                                onClick = { showWithdrawDialog = false },
                                colors = ButtonDefaults.buttonColors(containerColor = DarkBackground),
                                modifier = Modifier.padding(end = 8.dp)
                            ) {
                                Text("Cancel")
                            }

                            Button(
                                onClick = {
                                    val amt = withdrawAmountText.toDoubleOrNull() ?: 0.0
                                    val success = onRequestWithdrawal(amt, withdrawDestination)
                                    if (success) {
                                        withdrawSuccessMsg = "Successfully initiated withdrawal of $$amt USD to $withdrawDestination."
                                        showWithdrawDialog = false
                                        withdrawAmountText = ""
                                    }
                                },
                                colors = ButtonDefaults.buttonColors(containerColor = PrimaryRed),
                                modifier = Modifier.testTag("confirm_withdraw_button")
                            ) {
                                Text("Confirm Payout")
                            }
                        }
                    }
                }
            }
        }
    }
}
