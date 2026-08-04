#!/usr/bin/env bash
#
# riport-matrix.sh — végigfuttatja az összes riportot a fontos szűrő-
# kombinációkkal, és soronként jelzi, ép XLSX lett-e.
#
# A kombinációk azt célozzák, ami eddig eltört: a szezon nélküli („nincs
# szűrés") állapotot, az önálló naptári évet, és az „összes megye" ágat.
#
set -uo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd -P)"

# A dumpban meglévő szezon azonosítója — ha üres, csak a szezon nélküli
# ágakat lehet értelmesen vizsgálni.
SZEZON="${SZEZON:-1}"
EV="${EV:-2025}"

FUTAS=(
    # Szezon riport: mind a négy időszak-kombináció
    "szezon_riport"
    "szezon_riport series=$SZEZON"
    "szezon_riport year=$EV"
    "szezon_riport series=$SZEZON year=$EV"
    "szezon_riport series=0"

    # Tanév-riportok: szezon nélkül, csak évvel, mindkettővel
    "iskola_sportoltatott_diakok series=$SZEZON"
    "iskola_sportoltatott_diakok series=0"
    "iskola_sportoltatott_diakok series=0 year=$EV"
    "iskola_sportoltatott_diakok series=$SZEZON year=$EV"

    "tanev_diakolimpia_diakok series=$SZEZON"
    "tanev_diakolimpia_diakok series=0"
    "tanev_diakolimpia_diakok series=0 year=$EV"
    "tanev_diakolimpia_diakok series=$SZEZON gender=nő"
    "tanev_diakolimpia_diakok series=$SZEZON disabilityGroupId=1"

    "tanev_versenyen_indult_iskolak series=$SZEZON"
    "tanev_versenyen_indult_iskolak series=0"
    "tanev_versenyen_indult_iskolak series=0 year=$EV"

    # Versenyszám-riportok
    "verseny_versenyszam"
    "tanev_diakolimpia_versenyszam series=$SZEZON"
    "tanev_diakolimpia_versenyszam_sportag series=$SZEZON"

    # Verseny-diák riport (ismert, javítatlan prepare-hiba gyanúja)
    "verseny_diak"
    "verseny_diak filter=0"
    "verseny_diak filter=1"

    # Legnépszerűbb sportágak: a filter=0 („összes megye") ág volt üres
    "legnepszerubb_sportag"
    "legnepszerubb_sportag filter=0"
    "legnepszerubb_sportag filter=1"
    "legnepszerubb_sportag filter=0 series=0 year=$EV"
)

EP=0
ROSSZ=0
mkdir -p "$SCRIPT_DIR/riport-kimenet"
OSSZEGZES="$SCRIPT_DIR/riport-kimenet/osszegzes.txt"
: > "$OSSZEGZES"

for FUT in "${FUTAS[@]}"; do
    # shellcheck disable=SC2086
    KIMENET=$("$SCRIPT_DIR/riport-ellenoriz.sh" $FUT 2>&1)
    SOR=$(echo "$KIMENET" | grep "^EREDMENY:" | head -1)
    [[ -z "$SOR" ]] && SOR="EREDMENY: ISMERETLEN"

    if [[ "$SOR" == *"EP XLSX"* ]]; then
        EP=$((EP + 1))
        JEL="OK  "
    else
        ROSSZ=$((ROSSZ + 1))
        JEL="HIBA"
    fi

    printf '%s  %-58s %s\n' "$JEL" "$FUT" "${SOR#EREDMENY: }" | tee -a "$OSSZEGZES"

    if [[ "$JEL" == "HIBA" ]]; then
        echo "$KIMENET" | sed 's/^/        /' >> "$OSSZEGZES"
    fi
done

echo
echo "Osszesen: $((EP + ROSSZ)) futas — $EP ep, $ROSSZ hibas"
echo "Reszletek: $OSSZEGZES"
[[ "$ROSSZ" -eq 0 ]]
