Kalau target Anda **bukan cuma lolos ThemeForest, tetapi juga membuat theme WordPress yang bisa dijual di luar Envato**, saya justru menyarankan memakai **standar Envato + WordPress.org + best practice engineering** sebagai baseline.

Envato sendiri saat ini membagi requirement WordPress Theme menjadi 6 bagian: General, Features, Theme Plugins, Coding, Security, dan Gutenberg. ([Envato Author Support][1])

## 1. Prinsip paling penting: Theme ≠ Plugin

Ini mungkin **aturan arsitektur paling penting** yang perlu Anda tiru.

### Theme menangani

* Layout
* Typography
* Colors
* Header
* Footer
* Navigation
* Blog layout
* Archive
* Single post
* Page templates
* WooCommerce styling
* Block styling
* Design system

### Plugin menangani

* Custom Post Types
* Custom Taxonomies
* Forms
* SEO
* Analytics
* Custom fields
* Shortcodes
* Widgets
* Business logic
* Custom blocks
* Dashboard functionality

Envato secara eksplisit menerapkan prinsip ini: sesuatu yang **hilang ketika user mengganti theme** umumnya masuk wilayah plugin. ([Envato Author Support][2])

**Contoh buruk:**

```text
my-theme/
├── inc/
│   ├── custom-post-type.php
│   ├── seo.php
│   ├── analytics.php
│   ├── contact-form.php
│   └── shortcodes.php
```

Lebih baik:

```text
my-theme/
├── style.css
├── functions.php
├── templates/
├── parts/
├── patterns/
└── assets/

my-theme-core/
├── custom-post-types/
├── custom-blocks/
├── forms/
└── integrations/
```

Ini membuat produk Anda **lebih maintainable dan portable**.

---

# 2. Jangan membuat theme terlalu "mengunci" user

Ini kesalahan yang sering dilakukan theme marketplace.

Misalnya:

```php
update_option('my_theme_logo', ...);
```

padahal WordPress sudah memiliki Site Logo.

Atau membuat:

```text
My Theme Settings
 ├── Logo
 ├── Site Title
 ├── Tagline
 ├── Homepage
 ├── Posts per page
 └── Site icon
```

padahal sebagian sudah tersedia di WordPress.

Envato meminta theme menggunakan core functionality apabila functionality tersebut sudah tersedia di WordPress. ([Envato Author Support][3])

### Prinsip yang saya sarankan:

> **Extend WordPress, don't fight WordPress.**

---

# 3. Gunakan WordPress native API

Jangan membuat sistem sendiri kalau WordPress sudah punya.

Contoh:

### Menu

Gunakan:

```php
register_nav_menus();
wp_nav_menu();
```

bukan sistem menu custom.

### Logo

Gunakan:

```php
add_theme_support('custom-logo');
```

### Title

Gunakan:

```php
add_theme_support('title-tag');
```

bukan:

```php
<title><?php wp_title(); ?></title>
```

### Thumbnail

Gunakan:

```php
add_theme_support('post-thumbnails');
```

### Assets

Gunakan:

```php
wp_enqueue_style();
wp_enqueue_script();
```

Envato secara eksplisit mewajibkan enqueue API dan melarang praktik seperti memasukkan library WordPress sendiri dari CDN jika library tersebut sudah disediakan WordPress. ([Envato Author Support][4])

---

# 4. Struktur theme yang saya rekomendasikan

Kalau Anda ingin membuat theme premium modern:

```text
mytheme/
│
├── style.css
├── functions.php
├── theme.json
├── screenshot.png
├── readme.txt
│
├── assets/
│   ├── css/
│   ├── js/
│   ├── fonts/
│   └── images/
│
├── inc/
│   ├── setup.php
│   ├── enqueue.php
│   ├── template-functions.php
│   ├── template-tags.php
│   └── compatibility/
│
├── templates/
│   ├── index.html
│   ├── single.html
│   ├── page.html
│   ├── archive.html
│   ├── search.html
│   └── 404.html
│
├── parts/
│   ├── header.html
│   ├── footer.html
│   ├── post.html
│   └── sidebar.html
│
├── patterns/
│   ├── hero.php
│   ├── about.php
│   ├── testimonials.php
│   └── cta.php
│
└── languages/
```

Kalau Anda membuat **block/FSE theme**, saya lebih menyarankan pendekatan ini daripada theme klasik yang penuh PHP template.

---

# 5. `theme.json` harus menjadi pusat Design System

Ini salah satu hal yang menurut saya sangat layak Anda tiru untuk produk commercial.

Contoh:

```json
{
  "version": 3,
  "settings": {
    "color": {
      "palette": [
        {
          "slug": "primary",
          "color": "#2563eb",
          "name": "Primary"
        },
        {
          "slug": "secondary",
          "color": "#0f172a",
          "name": "Secondary"
        }
      ]
    },
    "typography": {
      "fontSizes": [
        {
          "slug": "small",
          "size": "14px",
          "name": "Small"
        },
        {
          "slug": "medium",
          "size": "18px",
          "name": "Medium"
        }
      ]
    }
  }
}
```

Keuntungannya:

```text
Theme
   ↓
Design Tokens
   ↓
Colors
Typography
Spacing
Layout
Buttons
Blocks
   ↓
Consistent UI
```

Jadi Anda tidak punya:

```css
.hero-title { ... }

.about-title { ... }

.contact-title { ... }

.service-title { ... }
```

dengan style yang berbeda-beda.

---

# 6. Gutenberg wajib Anda anggap serius

Jangan membuat theme yang hanya bagus di Elementor.

Envato sekarang memiliki requirement Gutenberg tersendiri dan core blocks harus mendapatkan styling yang sesuai dengan design theme. ([Envato Author Support][5])

Minimal test:

```text
Paragraph
Heading
Image
Gallery
Quote
List
Button
Columns
Group
Cover
Separator
Table
Video
Audio
Latest Posts
Categories
Search
Navigation
```

Pastikan semuanya tidak terlihat seperti:

> "WordPress default component ditempel ke theme."

---

# 7. Jangan hardcode content

**Bad:**

```php
<h2>Grow Your Business</h2>

<p>
We help businesses grow...
</p>
```

di template theme.

Lebih baik:

```php
the_title();
the_content();
```

atau block/content dari editor.

Envato juga mensyaratkan content/demo content tidak di-hardcode ke template files. ([Envato Author Support][3])

---

# 8. Performance harus menjadi selling point

Ini penting kalau Anda ingin menjual **di luar Envato**.

Jangan membuat theme seperti:

```text
Theme
+ jQuery
+ Bootstrap
+ Slider library
+ Animation library
+ Icon library
+ Font library
+ 10 CSS files
+ 15 JS files
```

kemudian semua halaman memuat semuanya.

Lebih baik:

```text
Page
 ↓
Required CSS
 ↓
Required JS
```

Contoh:

```php
if (is_page_template('templates/contact.php')) {
    wp_enqueue_script(
        'contact',
        get_template_directory_uri() . '/assets/js/contact.js',
        [],
        '1.0.0',
        true
    );
}
```

### Target internal yang saya sarankan

Untuk theme premium:

```text
LCP       < 2.5s
INP       < 200ms
CLS       < 0.1
```

Dan usahakan demo site mendapatkan hasil PageSpeed yang bagus **tanpa plugin caching khusus**.

Itu bisa menjadi marketing:

> Lightweight WordPress Theme
> Built for Core Web Vitals
> No jQuery dependency
> Optimized assets

---

# 9. Security

Minimal:

```php
esc_html()
esc_attr()
esc_url()
wp_kses_post()
sanitize_text_field()
absint()
```

Contoh:

```php
$title = get_option('my_title');

echo esc_html($title);
```

Bukan:

```php
echo $title;
```

Envato secara eksplisit mengharuskan validasi terhadap data dari user maupun external API. ([Envato Author Support][6])

Dan jangan pernah:

```php
base64_decode()
eval()
```

untuk menyembunyikan/obfuscate code. Envato melarang obfuscation semacam ini. ([Envato Author Support][4])

---

# 10. Jangan bundling plugin sembarangan

Misalnya theme Anda membutuhkan:

```text
Elementor
WooCommerce
Contact Form 7
Rank Math
ACF
```

Jangan otomatis memasukkan semua plugin tersebut ke ZIP theme.

Lebih baik:

```text
Required:
✓ WooCommerce

Recommended:
✓ Elementor
✓ Contact Form 7
```

Envato membolehkan prompting untuk plugin dari WordPress.org, tetapi ada batasan terhadap memasukkan plugin pihak ketiga secara langsung. ([Envato Author Support][2])

---

# 11. Buat companion plugin

Ini menurut saya **best practice paling penting untuk Anda tiru**.

Misalnya produk Anda:

```text
Digikuy Business Theme
```

Struktur produknya:

```text
digikuy-business-theme
        │
        ├── Presentation
        │
        ├── Design System
        │
        ├── Templates
        │
        └── Gutenberg
             │
             ↓
digikuy-core
        │
        ├── CPT
        ├── Testimonials
        ├── Team
        ├── Services
        ├── Projects
        └── Integrations
```

Ketika user mengganti theme:

```text
Theme ❌
```

tetapi:

```text
Services
Testimonials
Projects
Data
```

tetap ada.

**Itulah arsitektur WordPress yang sehat.**

---

# 12. Demo Import harus sangat bagus

Ini salah satu faktor yang menurut saya bisa membuat theme Anda lebih mudah dijual.

User membeli theme bukan hanya karena:

> "bagus."

Tetapi karena:

> "Saya bisa mendapatkan website seperti demo dalam 5 menit."

Ideal:

```text
Install Theme
      ↓
Install Required Plugins
      ↓
Import Demo
      ↓
Choose Homepage
      ↓
Done
```

Bahkan lebih bagus:

```text
Starter Sites

Business
Agency
Restaurant
Portfolio
SaaS
Construction
Real Estate
```

Satu codebase → banyak demo.

---

# 13. Documentation jangan disepelekan

Envato mensyaratkan documentation yang menjelaskan installation, customization, usage dan credits; dokumentasi harus dalam bahasa Inggris dan dapat berupa HTML/PDF. ([Envato Author Support][7])

Saya justru akan membuat:

```text
docs/
├── getting-started
├── installation
├── demo-import
├── customization
├── header
├── footer
├── typography
├── colors
├── Gutenberg
├── WooCommerce
├── Elementor
├── FAQ
└── troubleshooting
```

Dan online:

```text
docs.yourtheme.com
```

Ini akan sangat membantu ketika menjual langsung.

---

# 14. Buat "child-theme friendly"

Jangan membuat user harus edit core theme.

Misalnya:

```text
mytheme/
```

dan:

```text
mytheme-child/
```

User dapat override:

```text
single.php
archive.php
template-parts/
```

Ini sangat penting untuk developer customer.

---

# 15. Jangan hanya test "website saya terlihat bagus"

Buat **QA matrix**.

Contoh:

| Test          | Status |
| ------------- | ------ |
| WP latest     | ✅      |
| PHP 8.2       | ✅      |
| PHP 8.3       | ✅      |
| Gutenberg     | ✅      |
| WooCommerce   | ✅      |
| Mobile        | ✅      |
| Tablet        | ✅      |
| Chrome        | ✅      |
| Safari        | ✅      |
| Firefox       | ✅      |
| Accessibility | ✅      |
| RTL           | ✅      |
| Translation   | ✅      |
| Multisite     | ✅      |
| Debug mode    | ✅      |
| No plugin     | ✅      |
| Elementor     | ✅      |
| Child theme   | ✅      |

WordPress sendiri merekomendasikan testing terhadap basic functionality, accessibility, performance, compatibility dan responsiveness. ([WordPress Developer Resources][8])

---

# 16. Gunakan tools otomatis

Untuk development saya akan membuat pipeline:

```text
Git
 ↓
PHP Code Standards
 ↓
Theme Check
 ↓
PHPCS
 ↓
PHPStan
 ↓
JS Lint
 ↓
Build
 ↓
WP Playground / Docker
 ↓
E2E Test
 ↓
ZIP
```

WordPress sendiri merekomendasikan **Theme Check, Debug Bar, Query Monitor dan tools debugging lainnya** untuk pengembangan theme. ([WordPress Developer Resources][9])

---

# 17. Accessibility = competitive advantage

Minimal:

```text
Semantic HTML
Keyboard navigation
Focus states
ARIA where appropriate
Color contrast
Alt text
Form labels
Heading hierarchy
Skip link
```

Jangan hanya:

```html
<div onclick="...">
```

kalau sebenarnya bisa:

```html
<button>
```

WordPress juga menjadikan accessibility sebagai bagian penting dari theme development, termasuk keyboard operation, headings, links, forms, contrast, dan resizable text. ([WordPress Developer Resources][10])

---

# 18. Translation ready

Jangan:

```php
echo "Read More";
```

Gunakan:

```php
esc_html_e(
    'Read More',
    'mytheme'
);
```

atau:

```php
printf(
    esc_html__('Read More', 'mytheme')
);
```

Sehingga:

```text
English
Indonesia
German
French
Spanish
Japanese
```

bisa diterjemahkan.

---

# 19. Product design yang saya sarankan untuk Anda

Kalau saya yang membuat produk ThemeForest sekarang, saya **tidak akan membuat "Multipurpose WordPress Theme" generik**.

Saya akan membuat:

> **Niche WordPress Theme**

Contoh:

```text
Real Estate
Restaurant
Construction
Law Firm
Agency
SaaS
Medical
Hotel
Local Business
Portfolio
```

Misalnya:

### "EstatePro"

```text
EstatePro
│
├── Real Estate Homepage
├── Agency Homepage
├── Property Listing
├── Property Detail
├── Agent
├── Search
├── Blog
├── About
├── Contact
└── Pricing
```

Lalu:

```text
EstatePro Theme
        +
EstatePro Core Plugin
        +
EstatePro Starter Sites
        +
EstatePro Documentation
```

Itu jauh lebih mudah diposisikan sebagai produk.

---

# 20. Dan ini yang paling penting untuk strategi Anda

Saya akan membangun theme dengan **"Envato-independent architecture."**

Artinya:

```text
                 ┌───────────────┐
                 │ WordPress Core│
                 └───────┬───────┘
                         │
                ┌────────▼────────┐
                │   Your Theme    │
                │   UI/Design     │
                └────────┬────────┘
                         │
                ┌────────▼────────┐
                │ Companion Plugin│
                │  Functionality  │
                └────────┬────────┘
                         │
          ┌──────────────┼──────────────┐
          ↓              ↓              ↓
      ThemeForest    Your Website    Client Work
```

Jadi **ThemeForest hanya salah satu distribution channel**, bukan tempat produk Anda bergantung.

Bisa dijual melalui:

```text
ThemeForest
     ↓
Gumroad / Lemon Squeezy
     ↓
Website sendiri
     ↓
Direct client
     ↓
Freelance project
     ↓
Agency licensing
```

---

## Standard yang saya sarankan Anda jadikan "Definition of Done"

Sebelum theme dianggap siap dijual:

```text
ARCHITECTURE
☑ Theme = presentation
☑ Plugin = functionality
☑ No vendor lock-in
☑ Child-theme friendly

WORDPRESS
☑ Core APIs
☑ Gutenberg
☑ theme.json
☑ WP coding standards
☑ Translation ready
☑ Accessibility

SECURITY
☑ Sanitization
☑ Validation
☑ Escaping
☑ Nonce
☑ Capability checks
☑ No obfuscation

PERFORMANCE
☑ Minimal dependencies
☑ Conditional assets
☑ Optimized images
☑ No unnecessary JS
☑ Core Web Vitals

UX
☑ One-click/demo import
☑ Starter sites
☑ Customizer/Site Editor
☑ Responsive
☑ Clear settings

DEVELOPER UX
☑ Hooks
☑ Filters
☑ Child theme support
☑ Clean code
☑ Documentation

COMMERCIAL
☑ Beautiful demo
☑ Multiple demos
☑ Screenshots
☑ Video
☑ Documentation
☑ Changelog
☑ Support system
☑ Update mechanism
```

**Kalau tujuan Anda adalah menghasilkan produk yang bisa dijual berkali-kali, saya akan lebih memilih standar ini daripada sekadar "theme yang lolos ThemeForest".** Envato sendiri menyebut requirement mereka sebagai *minimum standard of quality*, bukan keseluruhan definisi theme yang bagus. ([Envato Author Support][1])

Dan untuk Anda yang sudah biasa dengan **Next.js/Tailwind**, ada satu hal penting: jangan menerjemahkan pola Next.js secara mentah ke WordPress. Gunakan kemampuan frontend Anda untuk membuat **design system + Gutenberg/theme.json + performant templates**, sementara tetap mengikuti lifecycle dan API WordPress.

Kalau Anda mau membuat **theme pertama untuk dijual**, saya bisa lanjutkan dengan membuatkan **"WordPress Theme Product Blueprint 2026"**: stack yang dipakai, struktur folder lengkap, `theme.json`, coding standard, plugin architecture, demo importer, licensing, update mechanism, QA checklist, sampai **strategi memilih niche yang peluang jualannya paling bagus di ThemeForest + direct sale**.

[1]: https://help.author.envato.com/hc/en-us/articles/360000472383-WordPress-Theme-Requirements-Start-here?utm_source=chatgpt.com "WordPress Theme Requirements - Start here! – Envato Author Support | Help Center"
[2]: https://help.author.envato.com/hc/en-us/articles/360000481223-WordPress-Theme-Requirements-Part-3-Theme-Plugins?utm_source=chatgpt.com "WordPress Theme Requirements Part 3 - Theme Plugins – Envato Author Support | Help Center"
[3]: https://help.author.envato.com/hc/en-us/articles/360000480723-WordPress-Theme-Requirements-Part-2-Features?utm_source=chatgpt.com "WordPress Theme Requirements Part 2 -  Features – Envato Author Support | Help Center"
[4]: https://help.author.envato.com/hc/en-us/articles/360000479946-WordPress-Theme-Requirements-Part-4-Coding?utm_source=chatgpt.com "WordPress Theme Requirements Part 4 - Coding – Envato Author Support | Help Center"
[5]: https://help.author.envato.com/hc/en-us/articles/360020255992-WordPress-Theme-Requirements-Part-6-Gutenberg?utm_source=chatgpt.com "WordPress Theme Requirements Part 6 - Gutenberg – Envato Author Support | Help Center"
[6]: https://help.author.envato.com/hc/en-us/articles/360000481243-WordPress-Theme-Requirements-Part-5-Theme-Security?utm_source=chatgpt.com "WordPress Theme Requirements Part 5 - Theme Security – Envato Author Support | Help Center"
[7]: https://help.author.envato.com/hc/en-us/articles/360000470826-Themes-Item-Preparation-Technical-Requirements?utm_source=chatgpt.com "Themes Item Preparation & Technical Requirements – Envato Author Support | Help Center"
[8]: https://developer.wordpress.org/themes/advanced-topics/testing/?utm_source=chatgpt.com "Testing – Theme Handbook | Developer.WordPress.org"
[9]: https://developer.wordpress.org/themes/getting-started/tools-and-setup/?utm_source=chatgpt.com "Tools and Setup – Theme Handbook | Developer.WordPress.org"
[10]: https://developer.wordpress.org/themes/classic-themes/functionality/accessibility/?utm_source=chatgpt.com "Accessibility – Theme Handbook | Developer.WordPress.org"
