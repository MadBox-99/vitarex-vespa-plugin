<?php
/**
 * A riportok intézmény- és sportoló-szűrőjének unit tesztjei.
 * Futtatás: php tests/test-riport-szurok.php
 * WordPress nem kell hozzá: a tesztelt függvények tiszták.
 */

require_once __DIR__ . '/../includes/Core/vespa.riport.periodus.php';

$hibak = 0;

function allit($feltetel, $leiras)
{
    global $hibak;
    if ($feltetel) {
        echo "OK    " . $leiras . "\n";
    } else {
        echo "HIBA  " . $leiras . "\n";
        $hibak++;
    }
}

// ---- Intézmény-szűrő: csak tankerület ----------------------------------

$r = vespa_riport_intezmeny_szuro('7', '0');
allit($r['sql'] === ' AND vi.school_district_id=%d', 'csak tankerulet: sql');
allit($r['params'] === array(7), 'csak tankerulet: params');

// ---- Intézmény-szűrő: csak intézmény -----------------------------------

$r = vespa_riport_intezmeny_szuro('0', '42');
allit($r['sql'] === ' AND vi.institution_id=%d', 'csak intezmeny: sql');
allit($r['params'] === array(42), 'csak intezmeny: params');

// ---- Intézmény-szűrő: mindkettő ----------------------------------------
// A sorrend kötött: előbb a tankerület helyőrzője, utána az intézményé.

$r = vespa_riport_intezmeny_szuro('7', '42');
allit(
    $r['sql'] === ' AND vi.school_district_id=%d AND vi.institution_id=%d',
    'tankerulet+intezmeny: sql, tankerulet eloszor'
);
allit($r['params'] === array(7, 42), 'tankerulet+intezmeny: params sorrend');

// ---- Intézmény-szűrő: egyik sem ----------------------------------------

$r = vespa_riport_intezmeny_szuro('0', '0');
allit($r['sql'] === '' && $r['params'] === array(), 'intezmeny-szuro: egyik sem');

// ---- Intézmény-szűrő: érvénytelen bemenetek ----------------------------

foreach (array('', 'abc', '-1', 0, null, false) as $rossz) {
    $r = vespa_riport_intezmeny_szuro($rossz, $rossz);
    allit(
        $r['sql'] === '' && $r['params'] === array(),
        'intezmeny-szuro ervenytelen bemenet: ' . var_export($rossz, true)
    );
}

// ---- Sportoló-szűrő: csak fogyatékossági csoport -----------------------

$r = vespa_riport_sportolo_szuro('3', 'összes');
allit($r['sql'] === ' AND va.disability_type=%d', 'csak fogyatekossag: sql');
allit($r['params'] === array(3), 'csak fogyatekossag: params');

// ---- Sportoló-szűrő: csak nem ------------------------------------------

$r = vespa_riport_sportolo_szuro('0', 'nő');
allit($r['sql'] === ' AND va.gender=%s', 'csak nem: sql');
allit($r['params'] === array('nő'), 'csak nem: params');

$r = vespa_riport_sportolo_szuro('0', 'férfi');
allit($r['params'] === array('férfi'), 'csak nem: ferfi is szur');

// ---- Sportoló-szűrő: mindkettő -----------------------------------------
// A sorrend kötött: előbb a fogyatékossági csoport, utána a nem.

$r = vespa_riport_sportolo_szuro('3', 'nő');
allit(
    $r['sql'] === ' AND va.disability_type=%d AND va.gender=%s',
    'fogyatekossag+nem: sql, fogyatekossag eloszor'
);
allit($r['params'] === array(3, 'nő'), 'fogyatekossag+nem: params sorrend');

// ---- Sportoló-szűrő: a nem fehérlistája --------------------------------
// A felület 'összes'-t is küld, és a GET-ből bármi jöhet. Csak a pontosan
// egyező 'nő' és 'férfi' szűrhet — a fehérlista véd attól, hogy szemét érték
// kerüljön a lekérdezésbe.

foreach (array('összes', '', 'FÉRFI', 'No', 'nő ', 'egyeb', null, 0) as $rossz) {
    $r = vespa_riport_sportolo_szuro('0', $rossz);
    allit(
        $r['sql'] === '' && $r['params'] === array(),
        'nem fehErlista elutasit: ' . var_export($rossz, true)
    );
}

// ---- Sportoló-szűrő: egyik sem -----------------------------------------

$r = vespa_riport_sportolo_szuro('0', 'összes');
allit($r['sql'] === '' && $r['params'] === array(), 'sportolo-szuro: egyik sem');

// ---- Összegzés ---------------------------------------------------------

echo "\n";
if ($hibak === 0) {
    echo "Minden teszt sikeres.\n";
    exit(0);
}

echo $hibak . " teszt elbukott.\n";
exit(1);
