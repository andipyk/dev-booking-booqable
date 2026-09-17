# Booqable Quick Setup (5 Minutes)

Three steps to get renting.

## Step 1 — Add your first product

1. Click **Inventory** in the left sidebar → **Add a new product**
2. Enter the product name and upload a photo (or use Booqable's built-in image search)
3. Choose a **tracking method**:
   - **Trackable** — individual items with serial numbers
   - **Bulk** — quantity-based (e.g. 10 chairs)
4. Choose a **pricing method** — fixed price per day is the simplest start
5. Save, then click **Add stock items** to tell Booqable how many units you have

> ⚠️ Note: Tracking method and product type can't be changed after saving — choose carefully.

## Step 2 — Set pricing

Pricing is already enabled on your account. Two things to configure:

- **Per-product price** — set directly on each product during creation (or edit it after)
- **Pricing structures** *(optional)* — go to Settings → Pricing → Pricing structure templates to create tiered rates (daily, weekly, monthly discounts) and reuse them across products

For taxes: go to **Settings → Taxes** to add a tax rate — your account is set to **exclusive tax** (tax added on top of the price).

## Step 3 — Accept payments

**Stripe** is the fastest path:

1. Go to **Settings → Payment providers**
2. Click **Connect with Stripe** → complete the Stripe setup
3. Go to **Settings → Checkout → Payments in checkout** to choose how much to charge at booking (full, partial, or deposit only)

Other options — **PayPal** and **Adyen** — are available via the App Store if you prefer.

> Next step: add your first product and you're ready to create an order.

---

# Integrasi Booqable dengan WordPress

Ya, Booqable bisa diintegrasikan dengan WordPress lewat plugin resmi.

**Syarat awal:** sudah punya akun WordPress dan minimal satu produk di inventori Booqable.

## Langkah 1 — Install plugin Booqable di WordPress

1. Login ke dashboard WordPress
2. Klik **Plugins → Add New**
3. Cari "Booqable" → **Install Now** → **Activate Plugin**

## Langkah 2 — Hubungkan akun Booqable

1. Di Booqable, buka **Settings → Website integration → WordPress plugin**
2. Salin **Company ID**
3. Di WordPress, buka **Settings → Booqable** → tempel Company ID tersebut

## Langkah 3 — Tampilkan produk di website

Gunakan shortcode berikut di halaman WordPress:

| Tujuan | Shortcode |
|---|---|
| Daftar semua produk | `[booqable_list]` |
| Satu produk (kartu/detail/tombol) | Salin dari halaman produk di Booqable |
| Date picker | `[booqable_datepicker]` |

Cara menambahkan: di Editor WordPress, tambahkan blok **Shortcode**, lalu tempel kodenya.

> 💡 Tips: Jika komponen Booqable tidak muncul, biasanya karena plugin caching WordPress. Coba kosongkan cache atau nonaktifkan sementara plugin caching-nya.

Kalau kamu pakai **WooCommerce**, ada cara khusus untuk menambahkan tombol Booqable ke produk WooCommerce-mu juga.
