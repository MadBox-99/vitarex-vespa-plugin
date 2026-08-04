#!/usr/bin/env bash
#
# riport-ellenoriz.sh — legenerál egy riportot, és megnézi, ép XLSX lett-e.
#
# Használat:
#   ./riport-ellenoriz.sh <riport_tipus> [kulcs=ertek ...]
#
# Amit ellenőriz:
#   1. a fájl ZIP-fejléccel kezdődik-e (PK\x03\x04) — az XLSX ZIP-konténer,
#      tehát bármilyen elé írt figyelmeztetés azonnal látszik itt;
#   2. van-e a tartalomban PHP-figyelmeztetésre utaló szöveg;
#   3. megnyitható-e a munkafüzet, és hány sort tartalmaz.
#
set -uo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd -P)"
PHP="${HERD_PHP:-$HOME/Library/Application Support/Herd/bin/php83}"

if [[ $# -lt 1 ]]; then
    sed -n '2,14p' "$0"
    exit 1
fi

TIPUS="$1"
mkdir -p "$SCRIPT_DIR/riport-kimenet"
KIMENET="$SCRIPT_DIR/riport-kimenet/$TIPUS.xlsx"
HIBA="$SCRIPT_DIR/riport-kimenet/$TIPUS.stderr"

"$PHP" "$SCRIPT_DIR/riport-teszt.php" "$@" > "$KIMENET" 2> "$HIBA"
KILEPES=$?

MERET=$(wc -c < "$KIMENET" | tr -d ' ')
echo "riport:  $*"
echo "kilepes: $KILEPES"
echo "meret:   $MERET bajt"

if [[ "$MERET" -eq 0 ]]; then
    echo "EREDMENY: URES KIMENET"
    [[ -s "$HIBA" ]] && { echo "--- stderr ---"; cat "$HIBA"; }
    exit 1
fi

# 1. ZIP-fejléc a fájl legelején
FEJLEC=$(head -c 4 "$KIMENET" | xxd -p)
if [[ "$FEJLEC" != "504b0304" ]]; then
    echo "EREDMENY: SERULT — a fajl nem ZIP-fejleccel kezdodik (elso 4 bajt: $FEJLEC)"
    echo "--- a kimenet eleje ---"
    head -c 400 "$KIMENET"
    echo
    exit 1
fi

# 2. PHP-figyelmeztetés bárhol a tartalomban
if grep -qaE "Warning:|Notice:|Deprecated:|Fatal error:|_doing_it_wrong|<b>" "$KIMENET"; then
    echo "EREDMENY: SERULT — PHP-figyelmeztetes a fajlban:"
    grep -aoE "(Warning|Notice|Deprecated|Fatal error)[^<]{0,160}" "$KIMENET" | head -5
    exit 1
fi

# 3. Megnyitható-e, és mennyi sor van benne
# A -d kapcsolók csak az ellenőrző olvasót némítják: a PhpSpreadsheet régi
# szintaxist használ, és a saját deprecation-üzenete elfedné az eredményt.
"$PHP" -d error_reporting=0 -d display_errors=0 -r '
    require "'"$SCRIPT_DIR"'/wp-content/plugins/vitarex-vespa-plugin/lib/vendor/autoload.php";
    $f = "'"$KIMENET"'";
    try {
        $olvaso = PhpOffice\PhpSpreadsheet\IOFactory::createReaderForFile($f);
        $olvaso->setReadDataOnly(true);
        $fuzet = $olvaso->load($f);
        $lap = $fuzet->getActiveSheet();
        echo "EREDMENY: EP XLSX — ", $lap->getHighestRow(), " sor, ",
             $lap->getHighestColumn(), " oszlopig\n";
        echo "fejlec:   ", trim((string) $lap->getCell("A1")->getValue()), "\n";
    } catch (Throwable $e) {
        echo "EREDMENY: NEM NYITHATO MEG — ", $e->getMessage(), "\n";
        exit(1);
    }
'
