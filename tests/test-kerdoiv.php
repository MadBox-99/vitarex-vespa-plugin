<?php
/**
 * A beszámoló kérdőív kitöltöttség-logikájának unit tesztjei.
 * Futtatás: php tests/test-kerdoiv.php
 * WordPress nem kell hozzá: a tesztelt függvények tiszták.
 */

require_once __DIR__ . '/../includes/Core/vespa.kerdoiv.php';

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

// ---- Megválaszoltság --------------------------------------------------

allit(
    vespa_kerdoiv_kerdes_megvalaszolt('nyitott sportpálya', '') === true,
    'valasz onmagaban megvalaszolt'
);
allit(
    vespa_kerdoiv_kerdes_megvalaszolt('', '12 fo') === true,
    'megjegyzes onmagaban megvalaszolt'
);
allit(
    vespa_kerdoiv_kerdes_megvalaszolt('nyitott sportpálya', '12 fo') === true,
    'mindketto megvalaszolt'
);
allit(
    vespa_kerdoiv_kerdes_megvalaszolt('', '') === false,
    'ures mindketto nem megvalaszolt'
);
allit(
    vespa_kerdoiv_kerdes_megvalaszolt('   ', "\n\t ") === false,
    'csak whitespace nem megvalaszolt'
);
allit(
    vespa_kerdoiv_kerdes_megvalaszolt(null, null) === false,
    'null nem megvalaszolt'
);
allit(
    vespa_kerdoiv_kerdes_megvalaszolt('0', '') === true,
    'a nulla szoveg ervenyes valasz'
);

// ---- Állapot ----------------------------------------------------------

allit(vespa_kerdoiv_allapot(0, 23) === 'nincs', 'nulla valasz -> nincs');
allit(vespa_kerdoiv_allapot(1, 23) === 'reszleges', 'egy valasz -> reszleges');
allit(vespa_kerdoiv_allapot(22, 23) === 'reszleges', '22/23 -> reszleges');
allit(vespa_kerdoiv_allapot(23, 23) === 'kesz', '23/23 -> kesz');
allit(vespa_kerdoiv_allapot(0, 0) === 'nincs', 'nulla kerdes -> nincs');
allit(vespa_kerdoiv_allapot(5, 0) === 'nincs', 'nulla kerdes akkor is nincs, ha van sor');
allit(
    vespa_kerdoiv_allapot(30, 23) === 'kesz',
    'a szuksegesnel tobb valasz is kesz (regi, azota szukult kerdessor)'
);
allit(vespa_kerdoiv_allapot(-2, 23) === 'nincs', 'negativ bemenet nincs-nek szamit');

// ---- Cella ------------------------------------------------------------

$c = vespa_kerdoiv_cella(0, 23);
allit($c['allapot'] === 'nincs', 'cella allapota nulla valasznal');
allit($c['cimke'] === 'Nincs kitöltve', 'cella cimkeje nulla valasznal');
allit($c['szin'] === '#b32d2e', 'a hianyzo beszamolo piros');

$c = vespa_kerdoiv_cella(17, 23);
allit($c['allapot'] === 'reszleges', 'cella allapota reszlegesnel');
allit($c['cimke'] === '17/23', 'a reszleges cimke szamlalo');
allit($c['szin'] === '#646970', 'a reszleges semleges szurke');

$c = vespa_kerdoiv_cella(23, 23);
allit($c['allapot'] === 'kesz', 'cella allapota keszen');
allit($c['cimke'] === 'Kitöltve', 'cella cimkeje keszen');
allit($c['szin'] === '#1a7f37', 'a kesz zold');

$c = vespa_kerdoiv_cella(0, 0);
allit($c['cimke'] === '—', 'kerdes nelkul gondolatjel a cimke');
allit($c['szin'] === '#646970', 'kerdes nelkul semleges szin, nem piros');

// ---- Egyopciós kérdés felismerése -------------------------------------
// (17/23 kérdésnek egyetlen válaszlehetősége van, jellemzően "válasz a
// megjegyzésben" -- ezeknél nem rajzolunk rádiógombot, és a mentés nem
// írhatja felül a régi választ egy soha el nem küldött mezővel.)

allit(
    vespa_kerdoiv_egyopcios('válasz a megjegyzésben') === true,
    'egyetlen opcio -> egyopcios'
);
allit(
    vespa_kerdoiv_egyopcios('') === true,
    'ures string -> egyopcios (nincs opcio)'
);
allit(
    vespa_kerdoiv_egyopcios(null) === true,
    'null -> egyopcios (nincs opcio)'
);
allit(
    vespa_kerdoiv_egyopcios(';;') === true,
    'csupa ures elem -> egyopcios'
);
allit(
    vespa_kerdoiv_egyopcios('igen;nem') === false,
    'ket opcio -> nem egyopcios'
);
allit(
    vespa_kerdoiv_egyopcios('igen; ;nem') === false,
    'ket ervenyes opcio ures elem mellett -> nem egyopcios'
);
allit(
    vespa_kerdoiv_egyopcios('  igen  ') === true,
    'egyetlen opcio korulotte whitespace-szel -> egyopcios'
);

echo "\n" . ($hibak === 0 ? "Minden teszt sikeres.\n" : $hibak . " teszt elbukott.\n");
exit($hibak === 0 ? 0 : 1);
