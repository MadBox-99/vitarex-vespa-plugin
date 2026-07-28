<?php

/**
 * A beszámoló kérdőív kitöltöttségének tiszta, WordPress-független logikája.
 *
 * Ez a fájl KIZÁRÓLAG függvényeket definiál — se hook, se ABSPATH-guard, se
 * WP-hívás betöltéskor. Így a tests/test-kerdoiv.php sima PHP-vel betöltheti.
 */

/**
 * Megválaszoltnak számít-e a kérdés?
 *
 * A 23 közös kérdésből 17-nek egyetlen válaszlehetősége van, jellemzően
 * "válasz a megjegyzésben" — ezeknél a megjegyzés MAGA a válasz, ezért a
 * megjegyzés önmagában is megválaszoltnak számít.
 */
function vespa_kerdoiv_kerdes_megvalaszolt($answer, $qnote)
{
    return trim((string) $answer) !== '' || trim((string) $qnote) !== '';
}

/**
 * A beszámoló állapota a megválaszolt és az összes kérdés arányából.
 * Kérdés nélküli rendszerben nincs mit mérni.
 */
function vespa_kerdoiv_allapot($megvalaszolt, $osszes)
{
    $megvalaszolt = intval($megvalaszolt);
    $osszes = intval($osszes);

    if ($osszes <= 0 || $megvalaszolt <= 0) {
        return 'nincs';
    }
    if ($megvalaszolt >= $osszes) {
        return 'kesz';
    }
    return 'reszleges';
}

/**
 * A listaoszlop cellájának megjelenítési adatai.
 *
 * A részleges állapot szándékosan semleges: a közös kérdéskészlet bővült,
 * ezért a régi beszámolók szinte mind részlegesek — figyelmet csak a
 * teljesen hiányzó beszámoló kér.
 */
function vespa_kerdoiv_cella($megvalaszolt, $osszes)
{
    $megvalaszolt = intval($megvalaszolt);
    $osszes = intval($osszes);

    if ($osszes <= 0) {
        return array('allapot' => 'nincs', 'cimke' => '—', 'szin' => '#646970');
    }

    $allapot = vespa_kerdoiv_allapot($megvalaszolt, $osszes);

    if ($allapot === 'nincs') {
        return array('allapot' => 'nincs', 'cimke' => 'Nincs kitöltve', 'szin' => '#b32d2e');
    }
    if ($allapot === 'kesz') {
        return array('allapot' => 'kesz', 'cimke' => 'Kitöltve', 'szin' => '#1a7f37');
    }

    return array(
        'allapot' => 'reszleges',
        'cimke'   => $megvalaszolt . '/' . $osszes,
        'szin'    => '#646970',
    );
}
