<?php

/**
 * A szintidő (qualifying time) tiszta, WordPress-független logikája.
 *
 * Ez a fájl KIZÁRÓLAG függvényeket definiál — se hook, se ABSPATH-guard, se
 * WP-hívás betöltéskor. Így a tests/test-szintido.php sima PHP-vel betöltheti.
 *
 * A tanárok "14.84", "14,84" (magyar tizedesvessző) vagy "1:02.5" (perc:mp)
 * alakban gépelik be az időt. Ezeket egységesen másodpercben tárolt,
 * arikmetikailag összehasonlítható számmá alakítjuk — a szintidő-feltétel
 * összevetése soha nem stringen, hanem ezen a kanonikus számon történik.
 */

/**
 * Emberi bevitel (idő) átalakítása kanonikus másodperc-értékké.
 *
 * Elfogadott alakok: "14.84", "14,84", "1:02.5", "1:02,5", "01:02.50",
 * sima egész szám, körülöttük whitespace. Visszautasítja az üres/csak
 * whitespace bemenetet, a nem-számot, a kettőnél több kettőspontos alakot,
 * a negatív értéket, és azt a perc:másodperc alakot, aminek másodperc
 * része eléri vagy meghaladja a 60-at (pl. "1:75.0" — ez soha nem érvényes
 * idő, tehát félreütés jele, nem 1 perc 75 másodperc).
 *
 * @return float|null a másodpercek száma, vagy null, ha nem értelmezhető
 */
function vespa_szintido_parse($szoveg)
{
    if ($szoveg === null) {
        return null;
    }

    $szoveg = trim((string) $szoveg);
    if ($szoveg === '') {
        return null;
    }

    if (strpos($szoveg, ':') !== false) {
        $reszek = explode(':', $szoveg);
        if (count($reszek) !== 2) {
            return null;
        }

        list($perc, $mp) = $reszek;
        if (!preg_match('/^\d+$/', $perc)) {
            return null;
        }

        $mp = str_replace(',', '.', $mp);
        if (!preg_match('/^\d+(\.\d+)?$/', $mp)) {
            return null;
        }

        $mp_ertek = (float) $mp;
        // A másodperc rész soha nem érheti el a 60-at — ha mégis, az
        // félreütés (pl. "1:75.0"), nem érvényes 1 perc 75 másodperces idő.
        if ($mp_ertek >= 60) {
            return null;
        }

        $ertek = intval($perc) * 60 + $mp_ertek;
    } else {
        $normalizalt = str_replace(',', '.', $szoveg);
        if (!preg_match('/^\d+(\.\d+)?$/', $normalizalt)) {
            return null;
        }

        $ertek = (float) $normalizalt;
    }

    if ($ertek < 0) {
        return null;
    }

    // Ezredmásodpercre kerekítve — ez a tábla seconds oszlopának (decimal(8,3))
    // pontossága, így a tárolt és a frissen beolvasott érték mindig egyezik.
    return round($ertek, 3);
}

/**
 * Kanonikus másodperc-érték emberi olvasásra formázása.
 *
 * 60 másodperc alatt "14.84" stílusú, két tizedesjegyes alak. 60 másodperc
 * vagy afelett "1:02.50" stílusú, ahol a másodperc rész nullával kitöltött.
 */
function vespa_szintido_format($masodperc)
{
    $masodperc = round((float) $masodperc, 3);

    if ($masodperc < 60) {
        return sprintf('%.2f', $masodperc);
    }

    $perc = intval(floor($masodperc / 60));
    $mp = $masodperc - ($perc * 60);

    return $perc . ':' . sprintf('%05.2f', $mp);
}

/**
 * Megfelel-e a sportoló rögzített ideje a szintidő-feltételnek?
 *
 * Alacsonyabb idő a jobb: a sportoló akkor felel meg, ha az ideje kisebb
 * vagy egyenlő a minimumnál (a határeset is megfelel). Ha a sportolónak
 * nincs rögzített ideje, sosem felel meg — kivéve, ha nincs is minimum
 * beállítva, hiszen akkor nincs mit teljesíteni (ez teszi a feltételt
 * opt-in-né: minimum nélkül mindenki megfelel).
 */
function vespa_szintido_megfelel($sportolo_masodperc, $minimum_masodperc)
{
    if ($minimum_masodperc === null || $minimum_masodperc === '') {
        return true;
    }

    if ($sportolo_masodperc === null || $sportolo_masodperc === '') {
        return false;
    }

    // Kerekítve hasonlítunk: a decimal(8,3) oszlopból stringként visszakapott
    // érték és a lebegőpontos bemenet közötti apró eltérés (pl. 14.840 vs
    // 14.84) így nem okoz hamis "nem felel meg" eredményt.
    return round((float) $sportolo_masodperc, 3) <= round((float) $minimum_masodperc, 3);
}
