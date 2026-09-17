# Booqable Rental Theme Core

Companion plugin for the **Booqable Rental Theme**. Owns everything that should survive a theme switch: the Booqable API connection, live category/product caching, the real-time webhook receiver, and native Gutenberg blocks. The theme is presentation only — this plugin is data + functionality.

**No third-party Booqable plugin required.** This talks directly to the Booqable API (v4) — no client-side widget script, no `[booqable_*]` shortcodes. You get full control over the markup/design, in exchange for building booking/checkout yourself (see Roadmap below — that part isn't built yet).

---

## Settings → Booqable Rental Core

`wp-admin/options-general.php?page=booqable-rental-core`

### Company Slug

The subdomain in your Booqable login URL — if you sign in at `yourcompany.booqable.com`, enter `yourcompany`.

### Access Token

1. Go to `https://{your-company}.booqable.com/employees/current`
2. Under **Access Tokens**, create a new one and copy it immediately — Booqable only shows it once
3. Paste it here and click **Save**

Security notes:
- This field **never re-displays the saved value** — reopening the page always shows a placeholder, never the real token. Leave it blank on Save to keep the existing token unchanged.
- Stored as a plain WP option (`booqable_core_access_token`), read only server-side by `Client.php` — never sent to the browser.
- Check **Remove the saved token** to delete it entirely.

### Test Connection

Calls `GET /api/4/collections?page[size]=1` and reports Booqable's real response.

**If it fails**, the message shown is Booqable's own error detail. The two we've hit in practice:

| Message | Meaning | Fix |
|---|---|---|
| `Could not find an (active) authentication method` | The token doesn't correspond to a valid, active token *for the company slug configured above* — usually generated while logged into a **different** Booqable company, or since revoked. | Log into `{that exact company}.booqable.com/employees/current`, confirm the slug in the URL bar matches "Company Slug" above, generate a fresh token there. |
| HTTP `401` with no detail | Token missing/malformed. | Re-paste it — check for accidental whitespace or a copy-paste that dropped characters. |

### Checkout & Payment

- **Payment Instructions** — rich text (bank transfer details, a QRIS image via "Add Media", anything) shown to visitors right after they check out. No manual currency field needed here or anywhere else — see Currency below.
- **Order Hold Duration (hours)** — how long an unpaid order (cart or checked-out) can hold real inventory before the background sweep auto-cancels it and releases the stock. Default 24.

### Webhook (real-time sync)

A unique, unguessable URL (`/wp-json/booqable-core/v1/webhook/{secret}`) that flushes the 15-minute category cache instantly instead of waiting for it to expire. Paste it into Booqable's webhook settings. **Regenerate Webhook URL** rotates the secret if it ever leaks.

⚠️ The exact request-signing scheme Booqable's webhooks use hasn't been verified against a live payload yet (see `class-webhook.php`). Until confirmed, the endpoint trusts the secret-in-URL alone.

---

## Blocks

Both under the **Widgets** category in the inserter, both fully dynamic (PHP-rendered, live data — nothing hardcoded).

### Booqable Category Grid (`booqable-core/category-grid`)

A gapless bento grid built from your live Booqable collections (`GET /api/4/collections`), cached 15 minutes (or invalidated instantly via the webhook). Add, rename, or reorder collections in Booqable and the grid updates with no code or content changes here.

- **Attributes**: number of categories to show, inventory page link base.
- **Not connected yet?** Visitors see nothing (no broken layout); logged-in admins see an inline "not connected — Fix in Settings →" notice.

### Booqable Product Catalog (`booqable-core/product-catalog`)

A grid of live product groups (`GET /api/4/product_groups`) — photo, name, price + billing period. Optionally scoped to one Collection via a dropdown in the Inspector Controls (backed by the small public `GET /booqable-core/v1/collections` REST route this plugin registers, so the editor never needs the access token to populate it).

- Each card resolves the real, bookable Product id behind its Product Group (cached alongside collections) and renders a **"Book" button** that adds it to the visitor's cart. A group with more than one Product (variants) renders a disabled "Choose options" button instead — no variant picker yet, see the `building-booqable-ecommerce` skill's `known-gaps.md`.
- Same graceful degradation as Category Grid: nothing shown to visitors when not connected, an admin-only notice instead.

---

## Cart

The cart is plain state in the visitor's own browser (`localStorage`, key `bq_cart_v1`) — **not** a Booqable `Order`. This replaced an earlier design where every add/qty/remove was a live call to Booqable (an `Order` created on the first "Book" click); real usage showed that cost 1-5s per click and made the cart feel broken. Booqable now only hears from the visitor twice:

1. A read-only stock check, `GET /availability?product_id=&from=&till=` (`Availability_Rest`, public, no side effects in Booqable) — fired once in the background right after an item is added (to learn its real max), and again whenever dates change. Never blocks anything the visitor sees.
2. `POST /checkout` — the one moment the cart becomes a real Order (see Checkout below).

Every add/qty/remove/date interaction is instant because none of it calls an API — `assets/js/cart.js` reads and writes `localStorage` directly and re-renders synchronously.

**Trade-offs, decided explicitly with the project owner (not silent):**
- Stock isn't held in Booqable until Checkout, so two visitors can have the same item in their own browser-side cart at once. Booqable doesn't hard-reject an over-booked `book_product` call — it flags the resulting order `location_shortage`, surfaced on the confirmation screen.
- The total shown while building the cart is a **client-side estimate** (unit price × quantity, no duration math, no tax/discount rules) — labeled as such in the UI. The authoritative total only exists once, right after Checkout.

`Cart_Widget` enqueues `assets/js/cart.js` + `assets/css/cart.css` and prints a floating cart button (fixed bottom-right, `#bq-cart-toggle`) plus the (empty, JS-filled) drawer markup — both plugin-rendered, not part of the theme's header, so the cart keeps working regardless of which theme/header is active.

---

## Checkout

There's no card payment form. **Stripe doesn't support Indonesia-based Booqable accounts** (confirmed on the account this project runs against), and Booqable's `payment_methods` schema has no other provider usable here — so Checkout is a manual/offline flow, and it's also the one place the browser-side cart actually reaches Booqable:

1. Visitor clicks "Checkout" in the cart drawer, enters name/email/phone.
2. `POST /booqable-core/v1/checkout` (`Checkout_Rest` → `Checkout`) — the whole cart (dates + lines) travels in this one request, since nothing about it lives server-side before now. It creates the Order, books every line (`Client::book_product()`), finds-or-creates a Customer (deduped by email), attaches it, and transitions the order `new` → `reserved` — the status that actually finalizes availability and makes the order visible in the store owner's own Booqable dashboard. If a line fails to book, the partial order is canceled rather than left dangling.
3. The confirmation screen shows the real order number, the authoritative total, a shortage warning if `location_shortage` came back true, and the payment instructions configured at Settings → Booqable Rental Core (bank transfer details, a QRIS image, etc. — a `wp_editor()` field, so any content is possible).
4. Staff verifies the transfer and records payment **directly in the Booqable dashboard** — this plugin intentionally has no separate "mark as paid" admin screen, since Booqable's own dashboard already has one.

An unpaid order doesn't hold inventory forever: `Order_Expiry` (WP-Cron, every 30 minutes) auto-cancels `new`/`reserved` orders older than Settings → "Order Hold Duration (hours)" (default 24) that don't already show a payment.

Public REST routes, all under `/wp-json/booqable-core/v1/`:

| Route | Method | Auth | Purpose |
|---|---|---|---|
| `/availability` | GET | none | Per-product stock count (`product_id`, `from`, `till`) — see Availability below. |
| `/checkout` | POST | `X-WP-Nonce` | The whole cart in one request: `name`, `email`, `phone`, `starts_at`, `stops_at`, `lines[{product_id, quantity}]`. Creates the order, books everything, attaches the customer, reserves it. |

---

## Availability

The cart drawer includes a Start/End date picker (`PATCH /booqable-core/v1/cart/dates`) — changing dates updates the cart order's rental window, and confirmed live, cascades to every item already in the cart automatically. A public `GET /booqable-core/v1/availability?product_id=&from=&till=` route also exposes per-product stock counts (uses the account's first Location — no multi-location UI yet).

## Currency

Every price shown (catalog cards, cart drawer, mini-cart total) uses the store's **real Booqable currency** — `Client::get_company()` reads it from `GET /companies/current`, cached (`Company_Cache`) and formatted via a `Money` helper (PHP's `intl`/`NumberFormatter`, correct decimals and symbol per currency). No manual "set your currency" settings field needed or provided.

## Roadmap (not built yet)

Booking used to be entirely the Booqable widget's job. Replacing it means building, in order:

1. ~~Catalog (products + categories)~~ — **done**, this file describes it above.
2. ~~Availability~~ — **done**, see the Availability section above.
3. ~~Cart~~ — **done**, see the Cart section above.
4. ~~Checkout & payment~~ — **done**, see the Checkout section above. Manual/offline payment (no card provider available for this account) rather than the originally-envisioned `Payment Authorizations`/`Payment Charges` card flow.

## Development

```bash
npm install
npm run build      # compiles src/ → build/ (what register_block_type() actually loads)
npm run start       # watch mode
```

Block source lives in `src/<block-name>/` (`block.json`, `render.php`, `index.js`, optionally `style.scss`). Adding a new block is just adding a new folder — `Blocks::register()` auto-discovers every `build/*/block.json`.

`build/` is committed (end users installing a zip don't run npm); `node_modules/` is not.

## Architecture

```
booqable-rental-theme-core.php   composition root — requires + boots every class below
includes/
  class-client.php               Booqable API v4 HTTP client (Bearer token, JSON:API)
                                  — collections, products, orders, order_fulfillments,
                                    lines, locations, inventory_availabilities
  class-collections-cache.php    15-min transient cache around Client::get_collections()
  class-products-cache.php       same pattern, around Client::get_products()
  class-product-groups-cache.php same pattern, around Client::get_product_groups() —
                                  keyed per (collection, limit), no single flat list to filter
  class-company-cache.php        same pattern (1-hour TTL), around Client::get_company()
  class-money.php                 formats *_in_cents using the real Booqable currency
  class-webhook.php               REST receiver that flushes all three caches instantly
  class-settings.php               Settings → Booqable Rental Core admin page
  class-blocks.php                 registers every build/*/block.json + the editor's
                                    collections-dropdown REST helper
  class-availability-rest.php      public read-only stock-check route (no order/cart side effects)
  class-cart-widget.php            enqueues assets/js/cart.js + prints the drawer shell
  class-checkout.php               creates the order, books every line, attaches customer, reserves
  class-checkout-rest.php          public REST proxy for checkout (nonce-gated, whole cart in one call)
  class-order-expiry.php           WP-Cron: auto-cancels stale unpaid new/reserved orders
src/, build/                       category-grid, product-catalog
assets/js/cart.js, assets/css/cart.css   the cart itself — plain localStorage state, not part of the block build
```
