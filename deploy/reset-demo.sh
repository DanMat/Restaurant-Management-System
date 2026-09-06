#!/bin/sh
# Reset the RAS demo to its seeded state — run hourly by cron (see deploy/DEPLOY.md).
#
# Runs INSIDE the RAS site's app container (it has vendor/, bin/nimbus, the plugin
# + CRM + theme, and the DB_* env). A full rebuild is the simplest guaranteed-fresh
# reset: drop the database, re-run migrations (core + plugin), recreate the admin,
# and re-seed. Because the DB is empty each time, the seed needs no idempotency.
set -eu

: "${DB_HOST:=db}"
: "${DB_NAME:=nimbus}"
: "${DB_USER:=nimbus}"
: "${DB_PASS:=}"
: "${ADMIN_EMAIL:=admin@ras.demo}"
: "${ADMIN_PASSWORD:=restaurant-demo}"

echo "[ras-reset] dropping and recreating ${DB_NAME}…"
mysql -h "$DB_HOST" -u "$DB_USER" -p"$DB_PASS" \
  -e "DROP DATABASE IF EXISTS \`${DB_NAME}\`; CREATE DATABASE \`${DB_NAME}\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"

echo "[ras-reset] migrating (core + plugins)…"
php bin/nimbus migrate

echo "[ras-reset] creating admin…"
php bin/nimbus install --email="$ADMIN_EMAIL" --password="$ADMIN_PASSWORD" --name="RAS Admin" || true

echo "[ras-reset] seeding demo…"
php deploy/seed-demo.php

echo "[ras-reset] done."
