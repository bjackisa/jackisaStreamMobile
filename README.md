# Jackisa Stream Mobile

An advanced Android video streaming and creator ecosystem app rewritten in Kotlin and Jetpack Compose, preserving all business logic from the original Jackisa Stream platform.

## Features

- **Video Streaming & Discovery**:
  - Trending and category feed (Gaming, Tech, Music, Movies, News, Education).
  - High-definition video player with custom scrubber, timestamp, speed adjustment (1x–2x), fast-forward/rewind, fullscreen mode, and playback settings.
  - Video Monetization & Ad Schedule: Implements `PT_AdBreakSchedule` with skippable pre-roll and mid-roll ad cue points.
  - Engagement: Instant Like/Dislike counters, Channel Subscriptions, Save to Playlists, and Share stream link.
  - Creator Merch Strip (`PT_StoreWatchMerch`): Seamlessly showcases products attached to videos.
  - Real-time Comments: Expandable interactive discussion feed with comment creation and liking.

- **Livestream Broadcasts**:
  - Live broadcast directory with active viewer counts and live badges.
  - Full-featured Live Broadcast Room with real-time interactive chat.
  - Chat Badges (Broadcaster, Moderator, VIP, Stakeholder) and animated Super Tip sending.

- **Jackisa Store (Commerce & Escrow)**:
  - Product catalog covering Apparel, Electronics, Digital Packs, and Memberships.
  - Shopping Cart with quantity controls and transparent JPesa escrow service fee (3%).
  - 14-day Escrow buyer protection workflow.
  - Digital Receipts & Invoices (QUO/INV/RCP document generation).
  - Order status tracking (Processing, Escrow Held, Shipped, Delivered).

- **Stakeholders & Dividend Ledger**:
  - Automated revenue distribution engine (60/40 distributable pool ratio).
  - Stakeholder unit holdings and percentage equity calculations.
  - GPI Growth metrics, S-curve position, and unit valuation tracking.
  - Append-only trigger-protected financial ledger recording daily dividend accruals and payouts.
  - Withdrawal request workflow via JPesa Mobile Wallet and payout gateways.

- **Creator Studio & Uploads**:
  - Video upload sheet with title, description, category, and search tags.
  - Video monetization toggle linking uploads to the stakeholder revenue sharing pool.

- **User Profile & Library**:
  - Channel stats, liked videos, saved watch playlists, and quick navigation shortcuts.

## Tech Stack

- **Target Framework**: Android (Kotlin, Jetpack Compose)
- **UI Architecture**: Material Design 3 (M3) with custom cinematic dark palette
- **Reactive State**: Kotlin StateFlow & MVVM Repository Pattern
- **Build System**: Gradle 9.3.1 with Android Gradle Plugin 9.1.1 (built-in Kotlin support)
- **Image Loading**: Coil Compose
- **Target SDK**: Android 36 (minSdk 24)
