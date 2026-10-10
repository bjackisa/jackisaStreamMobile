# AGENTS.md

Jackisa Stream is a PHP video app (a PlayTube derivative). This file records
project-specific knowledge for future sessions.

## Environment

- Repo root: `/workspace/project/jackisastream`. Branch `main` (shallow clone).
- The active frontend theme is **playtag only** (`themes/playtag`). Do not touch
  other themes.
- Local test DB: MySQL at `127.0.0.1`, user `ptuser`, database `pt_test`
  (password in `config.php` as `$sql_db_pass`). Import the feature schema with:
  `mysql -h127.0.0.1 -uptuser -p pt_test < sql/live_imports_and_stakeholders.sql`
- PHP lint everything with `php -l <file>` (templates under
  `themes/playtag/layout/**` are PHP and must lint too).

### Database credentials workflow (never commit local credentials)

`config.php` holds the production DB credentials and must stay that way on
`main` and in every pushed branch. To work locally:

1. Before running anything, point `config.php` at the local test DB
   (`$sql_db_user = "ptuser"`, `$sql_db_pass = "ptpass"`,
   `$sql_db_name = "pt_test"`). Agents may edit it freely for this.
2. Do all local work, tests and migrations against `pt_test`.
3. Before committing **or** pushing, restore the production values
   (`u388105230_plusmaincp` for both user and database). If the file is
   currently modified only for local testing, run
   `git checkout -- config.php` to reset it to the committed production
   values, then verify with `git diff config.php` (must be empty).
4. Never include `config.php` in a commit whose only change is credentials,
   and never push local credentials to `origin`.


## Conventions

- All theme templates are **CRLF**. When editing them with tools that normalize
  line endings, convert back to CRLF before committing or the diff balloons.
- `users.registered` is stored as `YYYY/M` (e.g. `2026/7`), not `YYYY-MM`.
  Use `gmdate('Y').'/'.intval(gmdate('m'))` when filtering by registration month.
- Money and share maths use BCMath decimal strings at 10 dp. Use the
  `PT_StakeDec/Add/Sub/Mul/Div/Cmp` helpers; never `float`.
- Admin API endpoints live in `ajax/ap.php` (`aj/ap/...`). This file is unusual:
  it does **not** auto-emit `$data` at the end, so each handler must
  `header('Content-Type: application/json'); echo json_encode($data); exit();`.
  `ajax/admin.php` also has no tail emit.
- Route dispatch: `index.php` includes `sources/<link1>/content.php`. AJAX
  dispatch: `ajax.php` includes `ajax/<type>.php` and emits `$data`.

## Accounting immutability

Financial tables are append-only, enforced by `BEFORE DELETE/UPDATE` triggers
in `sql/live_imports_and_stakeholders.sql`:

`live_imports`, `videos`, `stake_earnings`, `stakeholder_share_events`,
`stakeholder_metric_values`, `stakeholder_periods`, `stake_revenue_entries`,
`stake_withdrawals`, `withdrawal_requests`.

Server-side code that legitimately needs to delete/update these rows must wrap
the call in `PT_DbGuardedRun(function () { ... })`, which sets
`@pt_allow_immutable = 1` for the duration. Any unguarded write raises SQLSTATE
45000 and the exception is swallowed, so the write silently no-ops.

Note: `bank_receipts` has update/delete triggers too, but the admin
approve/decline receipt flow (`ajax/ap.php` `approve_receipt` / `delete_receipt`)
is wrapped in `PT_DbGuardedRun`. New code touching these tables must do the same.

## Livestream imports

- `assets/includes/live_streams.php`: `PT_LiveParseSource`, `PT_LiveImportSource`,
  `PT_LiveRefreshImports`, `PT_LiveEndImport`. Runs from `cronjob.php` and the
  live/watch feeds.
- YouTube imports keep the recording after the stream ends (`live_source_ref`).
  Remote/generic imports are deleted when the stream ends.
- YouTube status needs `yt_api` in config; when status is unknown
  (no API key or timeout) `PT_LiveRefreshImports` keeps the heartbeat warm
  (`live_time = now`) so a 24/7 stream stays in the feed instead of going stale.
- Imported lives reuse the Agora plumbing: they set `stream_name`,
  `live_source`, and refresh `live_time` as a heartbeat, which makes the feed
  show them as live. `watch/agora.html` must skip them (guarded with
  `empty($pt->get_video->is_imported_live)`) so it never joins a bogus Agora
  channel.
- The admin stale-live reaper (`admin-panel/autoload.php`, the
  `live_time <= time() - 11` queries) must exclude `live_source != ''`. Imported
  YouTube/remote lives refresh their heartbeat server-side (cron, live feed,
  watch page) rather than via the Agora client ping, so leaving them in that
  query deletes them roughly a minute after import.
- On the watch page (`sources/watch/content.php`) a YouTube import exposes its
  recording id through the `youtube` field (`$get_video->youtube = live_source_ref`)
  so the template's `empty($get_video->youtube)` guards render it with the same
  YouTube embed as an imported YouTube video. It is also flagged
  `live_import_media`/`live_import_embed` so it never falls through to the
  generic import iframe. The remote URL stored in `videos.video_location` went
  through `PT_Secure` (HTML-encoded) at insert, so the watch page
  `html_entity_decode`s it before the player/probe sees it.
- Remote (non-YouTube) imports that are a direct HLS/mp4 file play through the
  player the user selected (`player_type`); other remote imports are an iframe.
- Every source keeps its ad slots (the watch page no longer blanks `$vast_url`/
  `$ad_*` for plain YouTube embeds). Sources are split by how they render:
  - Native sources (uploads, imports that play through a real `<video>`, and an
    imported YouTube live when `youtube_player` is off) keep the stock
    mediaelement/fluidPlayer/videojs preroll plus `watch/ima`.
  - Third-party iframe sources (YouTube embed player, Vimeo, DailyMotion,
    OK.ru, Facebook, Twitch, generic `embed`, and remote imports that fall back
    to an iframe) get a platform pre-roll overlay instead. `$pt->ad_iframe`
    flags these; it also suppresses the static content image overlay and the
    `watch/ima` include so nothing double-fires.
- Ad cadence lives in `PT_AdBreakSchedule()` (`assets/includes/functions_general.php`,
  with `PT_DurationToSeconds()`): the pre-roll always plays, mid-rolls are
  spaced evenly and stepped by duration (none under 4 min, +1 per ~4–8 min up
  to 5), and live streams get no mid-rolls. The schedule is exposed as
  `$pt->ad_breaks` / `ad_breaks_js`. The template's `PT_AD` JS object renders
  the same skippable video/image ad as the pre-roll overlay at each break
  (re-resolving the `<video>` each tick, alternating video/image), pausing and
  resuming the content.
- A YouTube import (plain video or imported live) is rendered by mediaelement
  as a YouTube **iframe**, not a `<video>`, so the `PT_AD` mid-roll ticker must
  read/pause/resume it over the YouTube iframe API postMessage bridge
  (`pt_ad_yt` / `ptAdYtFrame()` / `ptAdYtSend()` / `ptAdYtListen()` in
  `themes/playtag/layout/watch/content.html`). A bare `youtube_player=on` embed
  only reports its position after it is sent a `listening` handshake, so the
  embed URL carries `enablejsapi=1` and the template sends the handshake on
  `readyToListen`/`onReady`. Shared watch scripts that assume a `<video>` exists
  (e.g. the `timeupdate` handler) must guard against `null`.

## Stakeholders

- `assets/includes/stakeholders.php` holds the share engine and dividend
  ledger. Config keys live in `config` (`stake_*`), metric definitions in
  `stake_gpi_scores` / `stake_growth_metrics`.
- The engine is **fully automatic**. `stake_cron.php` calls
  `PT_StakeRunDailyEngine()` and is meant to run once or twice a day (for
  example `0 1,13 * * * php /path/to/stake_cron.php`). It is deliberately NOT
  in `cronjob.php`, which fires every few minutes. An optional `YYYY-MM-DD`
  argument backfills a specific UTC day. It recognises revenue, splits it
  60/40 (`stake_distributable_ratio`), sets the reserve, measures the growth
  position (S-curve, GPI), prices the unit, issues loyalty units, applies
  dilution and pays whole-currency dividends to 10 dp. The only human input is
  the number of shares a stakeholder holds (`PT_StakeSetHolderUnits`).
- **A settled day is frozen by run_key, so late revenue needs a repair.** A day
  settled before its wallet/Store revenue was recorded keeps its old
  `net_revenue` (often 0) and a 0 dividend pool for ever: `PT_StakeRunDailyEngine`
  and `PT_StakeCatchUpDailyEngine` both skip days that already have a run row.
  `PT_StakeReconcileSettledDays()` recomputes every closed day's recognised
  revenue and, for each day whose revenue has since grown, pays the shortfall's
  dividend through `PT_StakeSettleDailyPool` and tops the run row up. It is
  idempotent, never claws money back, and is wired into `stake_cron.php` (last
  1000 days). `stake_backfill.php` runs the same repair over the whole history
  (`php stake_backfill.php`, optional `YYYY-MM-DD`, optional `--dry-run`).
  Corrections use a `<day>~<wave>` accrual key so the append-only ledger never
  collides; `tests/stake_reconcile_test.php` proves the pool still equals
  `sum(accrual) - sum(debit)`. Do not delete `stake_revenue_entries`: its
  trigger blocks all deletes (unlike the other stake tables, no guard bypass).
- One `stake_daily_runs` row per day, keyed by `run_key` (YYYY-MM-DD). The run
  is idempotent; `stake_daily_accruals` is unique on `(run_key,
  stakeholder_id)`. The daily pool is reconciled so `sum(accrual) == pool`
  exactly (A1) and shares sum to 1 (13.3).
- Money and units are decimal strings at 10 dp (`PT_StakeDec/Add/Sub/Mul/Div/
  Cmp`); never `float`. Display always uses the global currency via
  `PT_StakeCurrency()` / `PT_StakeCurrencySymbol()` (from `payment_currency`
  plus `currency_symbol_array`); never a hard-coded `$`.
- `PT_StakeRefreshAllPercents()` recomputes every holder's `current_percent`
  after any units change, because percentages are relative to the whole pool.
- Admin UI: `admin-panel/pages/stakeholders/content.html` (look up a user by
  username, then add with a share count). AJAX: `ajax/ap.php`
  `stakeholder_action` (`user_lookup`, `add`, `set_shares`, `revoke`,
  `run_engine`, `save_settings`). The old manual `recalc` / `settle` actions
  are gone.

## Jackisa Store

A self-contained commerce layer (products + services, orders + bookings) added
on top of the core app. Schema lives in a **separate** migration,
`sql/jackisa_store.sql`; never edit the existing migrated SQL files.

- Engine: `assets/includes/store.php` (all `PT_Store*` helpers). Cron:
  `store_cron.php` (JPesa reconciliation, escrow release, order close) — run it
  on its own schedule, like `stake_cron.php`, not from `cronjob.php`.
- AJAX: `ajax/store.php` (`aj/store/<action>`), actions `accept_terms`,
  `create_store`, `save_product`, `delete_product`, `save_membership`,
  `attach`, `detach`, `attach_publish`, `my_products`, `slots`, `order`,
  `pay_jpesa`, `pay_wallet`, `cancel_order`, `set_order_status`, `review`,
  `document`. Admin: `ajax/ap.php` `store_admin` (`product_status`,
  `category_rate`, `flag_error`, superadmin only).
- Frontend (playtag only): `themes/playtag/layout/store/**` (storefront,
  product page, seller dashboard, order page, partials), a Store tab on the
  timeline (`timeline/pages/store.html`), merch strip on watch and the editor
  picker (`PT_StoreWatchMerch`, `PT_StoreEditorAttach`), `js/store.js`.
- Discoverability: the Store tab on a profile is shown to the owner even
  before a shop exists (`$pt->profile_has_store` in `sources/timeline/content.php`);
  visitors only see it once the seller is active. Sellers manage the catalogue
  from Settings → Store (`settings/store.html`, `sources/settings/content.php`
  via `PT_StoreDashboardData`), the account dropdown (`header/loggedin.html`)
  and the storefront dashboard.
- Double-entry ledger: `store_ledger_entries` is append-only (immutability
  triggers, same `@pt_allow_immutable` guard convention). Every journal must
  balance to 10 dp; `PT_StoreLedgerPost` rejects otherwise. Superadmins flag a
  transaction as an error via `store_ledger_flags` without touching rows.
- Escrow: seller funds are credited but held for the category's return window
  (`release_at`); only released earnings count toward the creator's available
  balance and are then eligible for the existing withdrawal flow.
- Documents: quotation, invoice, payment credit note, receipt, cancellation
  credit note, refund credit note (`store_documents`, numbers QUO/INV/PCN/
  RCP/CCN/RCN). Rendered by `PT_StoreRenderDocument` from
  `themes/playtag/layout/emails/store-*.html`.
- Documents convert to PDF with the **bundled pure-PHP dompdf** library
  (`assets/libs/dompdf`, vendored and force-added; `vendor/` is gitignored, so
  it is `git add -f`). No headless Chromium/shell is needed. `PT_StoreHtmlToPdf`
  renders, `PT_StoreDocumentPdf` caches per document under `assets/store_pdf/`,
  and both the View/Download endpoint (`ajax/store.php` `document`) and
  `PT_StoreEmailDocument` (PDF attachment) use that one file. dompdf has no
  flexbox, so `PT_StorePdfHtml` injects a PDF-only stylesheet turning the
  templates' `.head`/`.parties`/`.auth` flex rows into table layout. dompdf 3.x
  needs **PHP 7.4+**; `PT_StorePdfBrowser()` returns `''` below that and the
  caller falls back to HTML.
- JPesa service fee: `PT_StoreJPesaFeeSplit($amount)` grosses up to
  `amount/(1-rate/100)` **rounded to whole cents** (`PT_StoreRoundCents`, bcmath
  half-up — no float), matching the wallet/subscription refill's
  `round($amount / 0.97, 2)` in `ajax/wallet.php`. Rate is
  `store_jpesa_fee_percent` (default 3%). Keep it in cents: full 10-dp output
  drifts from what actually reaches JPesa and from the wallet.
- Consent: sellers and buyers must accept the Store ToS / Refund Policy,
  Stream ToS and Privacy Policy (`PT_StoreHasAcceptedTerms`,
  `store_terms_acceptances`) before listing or ordering.
- Products: multiple images, variants, discounts (percent/amount), delivery
  fees, per-category commission (3–15%) and return flag/window, optional
  admin approval before going live, memberships/subscriber benefits.
- Tests: `tests/store_lifecycle_test.php` exercises the whole lifecycle and
  asserts the ledger stays balanced. It disables SMTP (`smtp_or_mail=none`)
  so document email rendering is tested without real sends.
- Templates under `themes/playtag` are CRLF; newly added Store templates were
  normalised to CRLF.
