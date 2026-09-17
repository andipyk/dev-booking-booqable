# Booking Booqable — WordPress (Docker)

Block-based rental theme (`booqable-rental-theme`) and Booqable API v4 integration plugin
(`booqable-rental-theme-core`). The local stack follows the same standard as other
WordPress projects on this machine: a single `wp-portfolio-wordpress:7.1-php8.4` image
for web and WP-CLI.

**Case study:** https://andipyk.github.io/dev-booking-booqable/

## Running

```bash
cp .env.example .env      # fill in the values (once)
./bin/setup.sh            # build image, start containers, activate plugin + theme
```

Open:
- **WordPress**: http://localhost:8080
- **phpMyAdmin**: `docker compose --profile tools up -d`, then http://localhost:8081
- **Booqable Token**: wp-admin → Settings → Booqable Rental Core (stored in the database,
  never sent to the browser)

`setup.sh` is safe to run again.

## Other commands

```bash
bin/wp plugin list                 # WP-CLI (temporary container, same image)
docker compose stop                # stop the stack (data is safe)
docker compose ps                  # check container status
docker compose logs -f wordpress   # view WordPress logs
docker compose down -v             # stop + DELETE database and core (full reset)
```

## Build blocks

The `build/` output is committed, so the stack can run without Node. After changing
`src/`:

```bash
cd wp-content/plugins/booqable-rental-theme-core
npm ci
npm run build
```

## Structure

```
compose.yaml          db + wordpress; wpcli (profile cli); phpmyadmin (profile tools)
docker/wordpress/     shared image: official image + phpredis + WP-CLI (don't change here)
bin/setup.sh          idempotent provisioning
bin/wp                WP-CLI wrapper
bin/capture-screens.sh           screenshot theme pages to docs/scratch/
bin/build-portfolio-assets.py    case study images from those screenshots
portfolio/            case study page (static HTML, GitHub Pages)
.github/workflows/    pages.yml deploys portfolio/ on every push to main
wp-content/
  plugins/booqable-rental-theme-core/   data + logic (Booqable API, cart, checkout, blocks)
  themes/booqable-rental-theme/         presentation only (FSE block theme)
```

Core WordPress lives in a Docker volume (`wp_data`), not in the repo.

## Changing ports

If `8080`/`8081` conflict, change `WP_PORT` / `PMA_PORT` in `.env`, then run
`docker compose up -d` again. Other project ports: 8090 (automation-wp),
8100 (booking-contact-general).
