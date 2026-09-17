# Booqable Rental Theme

A standalone WordPress block (FSE) theme for rental, staging, and equipment-booking businesses. Built to pair with the free **[Booqable Rental Plugin](https://wordpress.org/plugins/booqable-rental-reservations/)** and its companion **Booqable Rental Theme Core** plugin (live category sync + native Gutenberg blocks) — but the theme itself has no hard dependency on either; it degrades to a clean, generic business theme if neither is installed.

No parent theme required — this is a full fork, not a child theme.

## Requirements

- WordPress 6.7+
- PHP 7.4+
- Recommended: [Booqable Rental Plugin](https://wordpress.org/plugins/booqable-rental-reservations/) + **Booqable Rental Theme Core** (bundled in this repo's `wp-content/plugins/`)

## Pages this theme is designed around

| Page | Purpose |
|---|---|
| Home | AIDA landing page: hero → category grid → before/after gallery (GSAP) → testimonials → CTA |
| Services | Package/accordion breakdown |
| Inventory & Booking | Availability + live catalog (via Booqable Rental Theme Core's blocks) |
| About | Brand story, service area |
| Contact | Contact info + consultation CTA |

None of these are hardcoded templates — they're regular Pages built from the patterns below, so every word of copy is editable in the block editor.

## Design system (`theme.json`)

- **Colors**: `ink`, `ivory`, `sand`, `terracotta`, `sage`, `cream` — swap the hex values in `theme.json` to re-skin the whole site.
- **Type**: Bricolage Grotesque (headings) + Plus Jakarta Sans (body), loaded from Google Fonts in `functions.php`.
- **Spacing scale**: `10`–`80` (0.5rem–12rem), used throughout instead of one-off pixel values.

## Patterns (`patterns/`)

Registered under the `Rental Business` category in the inserter (`booqable-rental-theme/*`):

`hero` · `bento-services` · `accordion-packages` · `gallery-pin` · `testimonials` · `cta-footer`

Each is a self-contained `core/html` block using the `.sc-*` classes in `assets/css/custom.css` — copy one, tweak the markup, and it behaves like any other pattern.

## Motion (`assets/js/motion.js`)

GSAP + ScrollTrigger (loaded from cdnjs), used for: the pinned before/after gallery, bento card hover physics, the horizontal packages accordion, and the testimonial carousel. Only ever queries our own `.sc-*` classes — never touches markup owned by the Booqable Rental Plugin's widget.

## A note on `.sc-container`

Full-bleed sections use `alignfull` (so their background can bleed edge-to-edge), but WordPress auto-applies a negative margin to `alignfull` elements to cancel the theme's root padding (`useRootPaddingAwareAlignments`). Putting your own `max-width + margin: auto` centering directly on an `alignfull` element fights that rule and loses (same specificity, WP's wins on source order). Every pattern here instead nests a plain `.sc-container` div **one level inside** the `alignfull` section — a non-aligned element is never touched by WP's align rules, so its centering just works. Keep this pattern for any new full-bleed section you add.

## Extending

- **New page type**: add a template under `templates/`, following the existing ones (all reference `parts/header.html` / `parts/footer.html`).
- **New reusable section**: add a pattern under `patterns/` with a `Slug: booqable-rental-theme/your-slug` header — it's auto-discovered, no registration code needed.
- **New taxonomy/CPT/blocks/business logic**: put it in the **Booqable Rental Theme Core** plugin, not here — this theme should stay swappable without losing data (see that plugin's README for its architecture).
