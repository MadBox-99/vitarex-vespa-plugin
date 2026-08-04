#!/usr/bin/env bash
#
# kornyezet-visszaall.sh — a teszt adatbázis visszaállítása az eredeti másolatból.
#
# Csak az adatbázist írja felül; a WordPress fájlok, a pluginok és a szkriptek
# a helyükön maradnak. Akkor hasznos, ha egy teszt adatot módosított, és tiszta
# lappal akarsz újrakezdeni — ez sokkal gyorsabb, mint a teljes újraépítés.
#
# Használat:
#   ./kornyezet-visszaall.sh
#
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd -P)"

CEL_DIR="${CEL_DIR:-$HOME/Herd/fodisz-teszt}"
CEL_DB="${CEL_DB:-fodisz_teszt}"
FORRAS_DB="${FORRAS_DB:-fodisz_vespa}"
HERD_PHP="${HERD_PHP:-$HOME/Library/Application Support/Herd/bin/php83}"
WP_CLI="${WP_CLI:-$HOME/.local/bin/wp}"

HOSZT="$(basename "$CEL_DIR").test"

my()  { mysql --protocol=TCP -h 127.0.0.1 -u root "$@"; }
wpc() { "$HERD_PHP" "$WP_CLI" --path="$CEL_DIR" "$@"; }

if ! my -e "USE \`$FORRAS_DB\`" >/dev/null 2>&1; then
    echo "HIBA: a forras adatbazis ('$FORRAS_DB') nem letezik." >&2
    exit 1
fi

echo "==> $CEL_DB visszaallitasa a(z) $FORRAS_DB masolatabol"
my -e "DROP DATABASE IF EXISTS \`$CEL_DB\`;"
my -e "CREATE DATABASE \`$CEL_DB\` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci;"
mysqldump --protocol=TCP -h 127.0.0.1 -u root --single-transaction --routines --triggers \
    --column-statistics=0 "$FORRAS_DB" 2>/dev/null | my "$CEL_DB"

# A dump az eles beallitasokat hozza vissza, ezert ezeket ujra at kell irni,
# kulonben az oldal a vespa.fodisz.hu cimre iranyitana at.
echo "==> Helyi beallitasok visszairasa"
wpc option update siteurl "http://$HOSZT" >/dev/null
wpc option update home "http://$HOSZT" >/dev/null
wpc option update blogname "FODISZ VESPA - helyi teszt" >/dev/null
wpc option update active_plugins '[]' --format=json >/dev/null

for PLUGIN in "$CEL_DIR"/wp-content/plugins/*/; do
    NEV="$(basename "$PLUGIN")"
    case "$NEV" in
        akismet|hello) continue ;;
    esac
    wpc plugin activate "$NEV" >/dev/null 2>&1 || echo "   ($NEV nem aktivalhato)"
done

wpc theme activate twentytwentythree >/dev/null 2>&1 || true

echo "==> Helyi adminisztrator"
wpc user create helyi_admin helyi_admin@localhost.test --role=administrator \
    --user_pass=teszt123 >/dev/null 2>&1 \
    && echo "   helyi_admin / teszt123" \
    || echo "   a helyi_admin mar letezik"

echo
echo "Kesz. http://$HOSZT/wp-admin/"
