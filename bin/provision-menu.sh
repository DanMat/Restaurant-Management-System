#!/usr/bin/env bash
#
# Provision the Restaurant Menu onto a running Nimbus instance, using only
# Nimbus's public HTTP admin API — no Nimbus core changes, no internal classes.
# This is the app installing its own content model, proven end to end: the
# collections appear in the admin and the menu is served over the read API.
#
# It is deliberately idempotent-ish: it skips collections that already exist,
# so re-running is safe.
#
# Usage:
#   bin/provision-menu.sh
#   env: NIMBUS_URL   (default http://localhost:8080)
#        ADMIN_EMAIL / ADMIN_PASSWORD   (a Nimbus admin login)
set -euo pipefail

BASE="${NIMBUS_URL:-http://localhost:8080}"
EMAIL="${ADMIN_EMAIL:-admin@nimbus.test}"
PASSWORD="${ADMIN_PASSWORD:-password}"
JAR="$(mktemp)"
trap 'rm -f "$JAR"' EXIT

say()  { printf '\n\033[1m==> %s\033[0m\n' "$1"; }
pass() { printf '  \033[32m✓\033[0m %s\n' "$1"; }
fail() { printf '  \033[31m✗\033[0m %s\n' "$1"; exit 1; }

get()  { curl -sSL -b "$JAR" -c "$JAR" "$BASE$1"; }
postr(){ curl -sS -b "$JAR" -c "$JAR" -o /dev/null -w '%{http_code} %{redirect_url}' -X POST "$BASE$1" "${@:2}"; }
tok()  { get "$1" | grep -o 'name="_token" value="[^"]*"' | head -1 | cut -d'"' -f4; }
has()  { printf '%s' "$2" | grep -qF -- "$1"; }

say "Signing in to Nimbus at $BASE"
has 302 "$(postr /admin/login -d "_token=$(tok /admin/login)" -d "email=$EMAIL" -d "password=$PASSWORD")" \
    || fail "login failed — check ADMIN_EMAIL / ADMIN_PASSWORD"
pass "signed in"

# A collection exists if the collections index lists its handle.
collection_exists() { has "/admin/collections/$1/entries" "$(get /admin/collections)"; }

say "Categories collection"
if collection_exists categories; then
    pass "already present"
else
    has msg=created "$(postr /admin/collections \
        -d "_token=$(tok /admin/collections/new)" \
        -d "name=Categories" -d "handle=categories" -d "kind=collection" -d "icon=C" \
        -d "fields[0][label]=Name" -d "fields[0][handle]=name" -d "fields[0][type]=text")" \
        || fail "could not create categories"
    pass "created"
fi

say "Menu Items collection (price + category relation)"
if collection_exists menu_items; then
    pass "already present"
else
    has msg=created "$(postr /admin/collections \
        -d "_token=$(tok /admin/collections/new)" \
        -d "name=Menu Items" -d "handle=menu_items" -d "kind=collection" -d "icon=M" \
        -d "fields[0][label]=Price" -d "fields[0][handle]=price" -d "fields[0][type]=number" \
        -d "fields[1][label]=Category" -d "fields[1][handle]=category" -d "fields[1][type]=relation" -d "fields[1][target]=categories")" \
        || fail "could not create menu_items"
    pass "created"
fi

say "Seeding a sample menu"
seed_entry() { # collection, title, slug, extra -d args...
    local col="$1" title="$2" slug="$3"; shift 3
    if has "/$col/entries/" "$(get "/admin/collections/$col/entries")" && has "$slug" "$(get "/admin/collections/$col/entries")"; then
        pass "$title already present"; return
    fi
    has msg=created "$(postr "/admin/collections/$col/entries" \
        -d "_token=$(tok "/admin/collections/$col/entries/new")" \
        -d "title=$title" -d "slug=$slug" -d "status=published" "$@")" \
        && pass "$title" || fail "could not create $title"
}

seed_entry categories "Mains" "mains"
CATID="$(get '/admin/collections/categories/entries' | grep -o '/entries/[0-9]*/edit' | head -1 | grep -o '[0-9]*')"
seed_entry menu_items "Margherita Pizza" "margherita" -d "f[price]=12.50" -d "f[category]=$CATID"
seed_entry menu_items "Caesar Salad" "caesar" -d "f[price]=8.00" -d "f[category]=$CATID"

printf '\n\033[32m✓ Menu provisioned. Mint a token (php bin/nimbus token:create) and GET\n  %s/api/v1/collections/menu_items/entries\033[0m\n' "$BASE"
