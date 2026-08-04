#!/usr/bin/env bash
#
# kornyezet-epit.sh — helyi WordPress teszt környezet felépítése Herd alatt.
#
# Nulláról felépíti a ~/Herd/fodisz-teszt oldalt: WordPress core, adatbázis az
# éles dumpból, mindkét plugin bekötve, mintaadat, teszt-szkriptek.
#
# Használat:
#   ./kornyezet-epit.sh              # felépít (meglévőt újraépít)
#   ./kornyezet-epit.sh --help
#
# Környezeti változók:
#   CEL_DIR       a WordPress könyvtára          (alap: ~/Herd/fodisz-teszt)
#   CEL_DB        a teszt adatbázis neve         (alap: fodisz_teszt)
#   FORRAS_DB     a klónozandó adatbázis          (alap: fodisz_vespa)
#   WP_VERZIO     a telepítendő WordPress verzió  (alap: 6.8.3)
#   HERD_PHP      a használandó PHP bináris
#
set -euo pipefail

if [[ "${1:-}" == "--help" ]]; then
    sed -n '2,19p' "$0"
    exit 0
fi

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd -P)"
PLUGIN_DIR="$(cd "$SCRIPT_DIR/../.." && pwd -P)"

CEL_DIR="${CEL_DIR:-$HOME/Herd/fodisz-teszt}"
CEL_DB="${CEL_DB:-fodisz_teszt}"
FORRAS_DB="${FORRAS_DB:-fodisz_vespa}"
WP_VERZIO="${WP_VERZIO:-6.8.3}"
HERD_PHP="${HERD_PHP:-$HOME/Library/Application Support/Herd/bin/php83}"
SZAMLALO_DIR="${SZAMLALO_DIR:-$HOME/Herd/fodisz-megtekintes-szamlalo}"
WP_CLI="${WP_CLI:-$HOME/.local/bin/wp}"

HOSZT="$(basename "$CEL_DIR").test"

my()  { mysql --protocol=TCP -h 127.0.0.1 -u root "$@"; }
wpc() { "$HERD_PHP" "$WP_CLI" --path="$CEL_DIR" "$@"; }
lep() { echo; echo "==> $*"; }

# --- Előfeltételek ----------------------------------------------------------

lep "Előfeltételek ellenőrzése"

if [[ ! -x "$HERD_PHP" ]]; then
    echo "HIBA: nincs PHP itt: $HERD_PHP (allitsd be a HERD_PHP valtozot)" >&2
    exit 1
fi

if ! my -e "SELECT 1" >/dev/null 2>&1; then
    echo "HIBA: a MySQL nem erheto el a 127.0.0.1:3306 cimen. Indul a Herd adatbazis-szolgaltatasa?" >&2
    exit 1
fi

if ! my -e "USE \`$FORRAS_DB\`" >/dev/null 2>&1; then
    echo "HIBA: a(z) '$FORRAS_DB' adatbazis nem letezik." >&2
    echo "      Toltsd be eloszor az eles dumpot, pl.:" >&2
    echo "      mysql -h 127.0.0.1 -u root -e \"CREATE DATABASE $FORRAS_DB\"" >&2
    echo "      mysql -h 127.0.0.1 -u root $FORRAS_DB < $PLUGIN_DIR/fodisz_vespa.sql" >&2
    exit 1
fi

if [[ ! -x "$WP_CLI" ]]; then
    lep "wp-cli telepitese ide: $WP_CLI"
    mkdir -p "$(dirname "$WP_CLI")"
    curl -sSL -o "$WP_CLI" https://raw.githubusercontent.com/wp-cli/builds/gh-pages/phar/wp-cli.phar
    chmod +x "$WP_CLI"
fi

# --- WordPress core ---------------------------------------------------------

lep "WordPress $WP_VERZIO letoltese ide: $CEL_DIR"
mkdir -p "$CEL_DIR"

if [[ -f "$CEL_DIR/wp-load.php" ]]; then
    echo "Mar van WordPress a helyen, a letoltest kihagyom."
else
    wpc core download --version="$WP_VERZIO" --locale=hu_HU
fi

# --- Adatbázis --------------------------------------------------------------

lep "Adatbazis klonozasa: $FORRAS_DB -> $CEL_DB"
my -e "DROP DATABASE IF EXISTS \`$CEL_DB\`;"
my -e "CREATE DATABASE \`$CEL_DB\` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci;"
mysqldump --protocol=TCP -h 127.0.0.1 -u root --single-transaction --routines --triggers \
    --column-statistics=0 "$FORRAS_DB" 2>/dev/null | my "$CEL_DB"

FORRAS_TABLAK=$(my -N -e "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema='$FORRAS_DB';")
CEL_TABLAK=$(my -N -e "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema='$CEL_DB';")
if [[ "$FORRAS_TABLAK" != "$CEL_TABLAK" ]]; then
    echo "HIBA: a klonozas hianyos ($FORRAS_TABLAK vs $CEL_TABLAK tabla)." >&2
    exit 1
fi
echo "$CEL_TABLAK tabla atmasolva."

# --- wp-config --------------------------------------------------------------

lep "wp-config.php irasa"
wpc config create --dbname="$CEL_DB" --dbuser=root --dbpass= --dbhost=127.0.0.1 \
    --dbprefix=wper_ --locale=hu_HU --force --extra-php <<'PHP'
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
// Szandekosan lathato: pontosan ez a beallitas teszi eszrevehetove, ha egy
// riport PHP-figyelmeztetest ir a binaris XLSX valaszba.
define('WP_DEBUG_DISPLAY', true);
define('SCRIPT_DEBUG', true);
define('WP_ENVIRONMENT_TYPE', 'local');
define('DISALLOW_FILE_EDIT', true);
define('AUTOMATIC_UPDATER_DISABLED', true);
define('WP_AUTO_UPDATE_CORE', false);
PHP

# --- Oldal beállításai ------------------------------------------------------

lep "Oldal-beallitasok ($HOSZT)"
wpc option update siteurl "http://$HOSZT"
wpc option update home "http://$HOSZT"
wpc option update blogname "FODISZ VESPA - helyi teszt"
# Az eles oldal pluginjai (Wordfence, wps-hide-login, reCAPTCHA) helyileg nincsenek
# meg, es a wps-hide-login elrejtene a bejelentkezest. Tiszta lappal indulunk.
wpc option update active_plugins '[]' --format=json
wpc rewrite structure '/%postname%/' --hard >/dev/null 2>&1 || true

# --- Biztonsági mu-pluginok -------------------------------------------------

lep "Biztonsagi mu-pluginok masolasa"
mkdir -p "$CEL_DIR/wp-content/mu-plugins"
cp "$SCRIPT_DIR/mu-plugins/"*.php "$CEL_DIR/wp-content/mu-plugins/"

# --- Pluginok ---------------------------------------------------------------

lep "Pluginok bekotese"
ln -sfn "$PLUGIN_DIR" "$CEL_DIR/wp-content/plugins/$(basename "$PLUGIN_DIR")"
wpc plugin activate "$(basename "$PLUGIN_DIR")"

if [[ -d "$SZAMLALO_DIR" ]]; then
    ln -sfn "$SZAMLALO_DIR" "$CEL_DIR/wp-content/plugins/$(basename "$SZAMLALO_DIR")"
    wpc plugin activate "$(basename "$SZAMLALO_DIR")"
else
    echo "A megtekintes-szamlalo nincs meg itt: $SZAMLALO_DIR — kihagyom."
fi

wpc theme activate twentytwentythree >/dev/null 2>&1 || true

# --- Mintaadat --------------------------------------------------------------

lep "Mintaadat a szamlalohoz"
wpc plugin install the-events-calendar --activate >/dev/null 2>&1 \
    || echo "Az Events Calendar telepitese nem sikerult — a tribe_events ag nem tesztelheto."

for i in 1 2 3; do
    wpc post create --post_type=post --post_status=publish \
        --post_title="Teszt bejegyzés $i" --post_content="Számláló-teszt tartalom $i." \
        --porcelain >/dev/null
done
for i in 1 2; do
    wpc post create --post_type=tribe_events --post_status=publish \
        --post_title="Teszt esemény $i" --post_content="Számláló-teszt esemény $i." \
        --porcelain >/dev/null 2>&1 || true
done

lep "Helyi adminisztrator"
wpc user create helyi_admin helyi_admin@localhost.test --role=administrator \
    --user_pass=teszt123 >/dev/null 2>&1 \
    && echo "Letrehozva: helyi_admin / teszt123" \
    || echo "A helyi_admin mar letezik."

# --- Teszt-szkriptek --------------------------------------------------------

lep "Teszt-szkriptek masolasa"
cp "$SCRIPT_DIR/riport-teszt.php" "$SCRIPT_DIR/szuro-hatas.php" "$CEL_DIR/"
cp "$SCRIPT_DIR/riport-ellenoriz.sh" "$SCRIPT_DIR/riport-matrix.sh" "$CEL_DIR/"
chmod +x "$CEL_DIR/riport-ellenoriz.sh" "$CEL_DIR/riport-matrix.sh"

# --- Zárás ------------------------------------------------------------------

cat <<OSSZEGZES

============================================================
Kesz.

  Oldal:        http://$HOSZT
  Bejelentkezes: http://$HOSZT/wp-admin/
  Fiok:         helyi_admin / teszt123
  Adatbazis:    $CEL_DB  (a(z) $FORRAS_DB erintetlen marad)

Riportok ellenorzese:
  cd $CEL_DIR && ./riport-matrix.sh

Egy riport:
  cd $CEL_DIR && ./riport-ellenoriz.sh tanev_diakolimpia_diakok series=3

Szuro hatasanak bizonyitasa:
  cd $CEL_DIR && "$HERD_PHP" szuro-hatas.php \\
      "tanev_diakolimpia_diakok series=3" \\
      "tanev_diakolimpia_diakok series=3 gender=nő"
============================================================
OSSZEGZES
