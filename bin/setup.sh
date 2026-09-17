#!/usr/bin/env bash
# Bring the stack up and provision WordPress from an empty volume.
# Safe to re-run: every step checks for, or tolerates, an earlier run.
set -euo pipefail
cd "$(dirname "$0")/.."

if [ ! -f .env ]; then
  echo "Missing .env: copy .env.example to .env and fill in values first." >&2
  exit 1
fi

set -a
source .env
set +a

wp() { ./bin/wp "$@"; }

if [ ! -f wp-content/plugins/booqable-rental-theme-core/build/product-catalog/block.json ]; then
  echo "Block build missing: run 'npm ci && npm run build' in wp-content/plugins/booqable-rental-theme-core first." >&2
  exit 1
fi

echo "==> Building the shared WordPress image"
# A plain build without a provenance attestation: the same files give the same
# image ID in every project, so they all run one image.
docker build --quiet --provenance=false \
  -t wp-portfolio-wordpress:7.1-php8.4 docker/wordpress >/dev/null

echo "==> Starting containers"
docker compose up -d --wait

if wp core is-installed >/dev/null 2>&1; then
  echo "==> WordPress already installed, skipping core install"
else
  echo "==> Installing WordPress"
  wp core install \
    --url="$WP_URL" \
    --title="$WP_TITLE" \
    --admin_user="$WP_ADMIN_USER" \
    --admin_password="$WP_ADMIN_PASSWORD" \
    --admin_email="$WP_ADMIN_EMAIL" \
    --skip-email
fi

echo "==> Activating the project plugin and theme"
wp plugin activate booqable-rental-theme-core
wp theme activate booqable-rental-theme

echo "==> Removing the default clutter"
wp plugin delete akismet hello >/dev/null 2>&1 || true

echo "==> Pretty permalinks (clean REST URLs)"
wp rewrite structure '/%postname%/' --hard
wp rewrite flush --hard

cat <<EOF

==> Done.

    Site:        ${WP_URL}
    wp-admin:    ${WP_URL}/wp-admin  (user: ${WP_ADMIN_USER})
    Booqable:    wp-admin > Settings > Booqable Rental Core (access token)
    WP-CLI:      bin/wp <command>
    phpMyAdmin:  docker compose --profile tools up -d
                 then http://localhost:${PMA_PORT:-8081}

EOF
