#!/usr/bin/env bash
#
# build.sh — telepíthető ZIP artifact készítése a VESPA pluginból éles deployhoz.
#
# Használat:
#   ./build.sh                 # patch verziót emel, majd builderol: build/vitarex-vespa-plugin-<verzio>.zip
#   ./build.sh --upload        # ugyanaz + scp-vel felteszi a szerverre (deploy.env kell)
#   ./build.sh --help
#
# Minden build AUTOMATIKUSAN +1 patch verziót lép a fő plugin fájlban (Version: + define).
# A major/minor emelés szándékos és kézi (szerkeszd a vitarex-vespa-plugin.php-t).
# A megemelt verziót utána commitold!
#
# A szerver-adatokat egy gitignore-olt deploy.env fájlból olvassa (lásd deploy.env.example).
#
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd -P)"
SLUG="vitarex-vespa-plugin"
MAIN_FILE="$SCRIPT_DIR/$SLUG.php"
BUILD_DIR="$SCRIPT_DIR/build"
STAGE_DIR="$BUILD_DIR/$SLUG"

UPLOAD=0

# --- argumentumok ---
for arg in "$@"; do
    case "$arg" in
        --upload) UPLOAD=1 ;;
        -h|--help)
            grep '^#' "$0" | sed 's/^# \{0,1\}//'
            exit 0
            ;;
        *)
            echo "Ismeretlen argumentum: $arg" >&2
            echo "Lásd: ./build.sh --help" >&2
            exit 1
            ;;
    esac
done

# --- verzió kiolvasása a plugin fejlécéből ---
if [[ ! -f "$MAIN_FILE" ]]; then
    echo "HIBA: nem találom a fő plugin fájlt: $MAIN_FILE" >&2
    exit 1
fi

VERSION="$(grep -iE '^[[:space:]]*Version:' "$MAIN_FILE" | head -1 | sed -E 's/.*Version:[[:space:]]*//I' | tr -d '\r' | xargs)"
if [[ -z "${VERSION:-}" ]]; then
    echo "HIBA: nem sikerült kiolvasni a verziót a fejlécből (Version: ...)." >&2
    exit 1
fi

# --- automatikus PATCH verzióemelés (minden buildnél) ---
# A major/minor emelés szándékos, kézi marad; a patch automatikusan nő minden buildnél.
if [[ ! "$VERSION" =~ ^([0-9]+)\.([0-9]+)\.([0-9]+)$ ]]; then
    echo "HIBA: a verzió nem 'X.Y.Z' formátumú: '$VERSION'." >&2
    exit 1
fi
OLD_VERSION="$VERSION"
NEW_VERSION="${BASH_REMATCH[1]}.${BASH_REMATCH[2]}.$(( BASH_REMATCH[3] + 1 ))"
OLD_ESC="${OLD_VERSION//./\\.}"
sed -i.bak -E \
    -e "s/(Version:[[:space:]]*)$OLD_ESC/\1$NEW_VERSION/" \
    -e "s/(VITAREX_VESPA_VERSION'[[:space:]]*,[[:space:]]*')$OLD_ESC/\1$NEW_VERSION/" \
    "$MAIN_FILE"
rm -f "$MAIN_FILE.bak"
VERSION="$NEW_VERSION"
echo "==> Verzió emelve: $OLD_VERSION -> $NEW_VERSION (fő fájl frissítve)"

ZIP_NAME="$SLUG-$VERSION.zip"
ZIP_PATH="$BUILD_DIR/$ZIP_NAME"

echo "==> VESPA plugin build — verzió: $VERSION"

# --- függőségek ellenőrzése ---
for bin in rsync zip; do
    if ! command -v "$bin" >/dev/null 2>&1; then
        echo "HIBA: '$bin' nincs telepítve, pedig szükséges." >&2
        exit 1
    fi
done

# --- staging mappa előkészítése ---
rm -rf "$STAGE_DIR" "$ZIP_PATH"
mkdir -p "$STAGE_DIR"

# A pluginból kihagyandó fejlesztői / meta fájlok. A futáshoz NEM szükségesek.
# Megjegyzés: lib/vendor SZÁNDÉKOSAN bent marad (élesben kell).
rsync -a \
    --exclude='.git/' \
    --exclude='.github/' \
    --exclude='.gitignore' \
    --exclude='.DS_Store' \
    --exclude='docs/' \
    --exclude='database/' \
    --exclude='tmp/' \
    --exclude='fodisz_vespa.sql' \
    --exclude='build/' \
    --exclude='build.sh' \
    --exclude='deploy.env' \
    --exclude='deploy.env.example' \
    --exclude='*.zip' \
    "$SCRIPT_DIR/" "$STAGE_DIR/"

# --- ZIP készítése (a zip gyökerében a vitarex-vespa-plugin/ mappa lesz) ---
( cd "$BUILD_DIR" && zip -rq "$ZIP_NAME" "$SLUG" )

# takarítás: a kicsomagolt staging mappára már nincs szükség
rm -rf "$STAGE_DIR"

ZIP_SIZE="$(du -h "$ZIP_PATH" | cut -f1)"
echo "==> Kész: $ZIP_PATH ($ZIP_SIZE)"

# --- opcionális feltöltés ---
if [[ "$UPLOAD" -eq 1 ]]; then
    ENV_FILE="$SCRIPT_DIR/deploy.env"
    if [[ ! -f "$ENV_FILE" ]]; then
        echo "HIBA: --upload kérve, de nincs deploy.env. Másold le a deploy.env.example-t és töltsd ki." >&2
        exit 1
    fi

    # shellcheck disable=SC1090
    set -a; source "$ENV_FILE"; set +a

    : "${SSH_HOST:?deploy.env: SSH_HOST kötelező}"
    : "${SSH_USER:?deploy.env: SSH_USER kötelező}"
    : "${REMOTE_UPLOAD_DIR:?deploy.env: REMOTE_UPLOAD_DIR kötelező}"
    SSH_PORT="${SSH_PORT:-22}"

    SSH_OPTS=(-P "$SSH_PORT")
    if [[ -n "${SSH_KEY:-}" ]]; then
        SSH_OPTS+=(-i "$SSH_KEY")
    fi

    echo "==> Feltöltés: $ZIP_NAME -> $SSH_USER@$SSH_HOST:$REMOTE_UPLOAD_DIR/"
    scp "${SSH_OPTS[@]}" "$ZIP_PATH" "$SSH_USER@$SSH_HOST:$REMOTE_UPLOAD_DIR/"
    echo "==> Feltöltve. A szerveren bontsd ki a wp-content/plugins/ mappába:"
    echo "    unzip -o $REMOTE_UPLOAD_DIR/$ZIP_NAME -d /útvonal/wp-content/plugins/"
fi

echo "==> Emlékeztető: a megemelt verziót ($VERSION) commitold a vitarex-vespa-plugin.php-ben!"
echo "==> Emlékeztető: új telepítésnél futtasd a database/changes.sql új migrációit az éles DB-n."
