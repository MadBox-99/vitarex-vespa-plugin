<?php
/**
 * A szintidő tiszta logikájának unit tesztjei.
 * Futtatás: php tests/test-szintido.php
 * WordPress nem kell hozzá: a tesztelt függvények tiszták.
 */

require_once __DIR__ . '/../includes/Core/vespa.szintido.php';

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

// ---- Parse: elfogadott formák -----------------------------------------

allit(vespa_szintido_parse('14.84') === 14.84, 'pont tizedesvesszo elfogadva');
allit(vespa_szintido_parse('14,84') === 14.84, 'magyar vessző tizedesvesszokent elfogadva');
allit(vespa_szintido_parse('1:02.5') === 62.5, 'perc:masodperc pont tizedesvesszovel');
allit(vespa_szintido_parse('1:02,5') === 62.5, 'perc:masodperc vesszo tizedesvesszovel');
allit(vespa_szintido_parse('01:02.50') === 62.5, 'nullaval kezdodo perc es ket tizedesjegy');
allit(vespa_szintido_parse('15') === 15.0, 'egesz szam masodperckent');
allit(vespa_szintido_parse('  14.84  ') === 14.84, 'korulotte whitespace levagva');
allit(vespa_szintido_parse("\t9,5\n") === 9.5, 'tab es ujsor is levagva');

// ---- Parse: elutasitott formák -----------------------------------------

allit(vespa_szintido_parse('') === null, 'ures string elutasitva');
allit(vespa_szintido_parse('   ') === null, 'csak whitespace elutasitva');
allit(vespa_szintido_parse('abc') === null, 'nem szamjegy elutasitva');
allit(vespa_szintido_parse('1:2:3') === null, 'ket kettospont elutasitva');
allit(vespa_szintido_parse('-5') === null, 'negativ ertek elutasitva');
allit(vespa_szintido_parse('1:75.0') === null, 'perc:masodperc forma, ahol a masodperc resz >= 60, elutasitva');
allit(vespa_szintido_parse(null) === null, 'null bemenet elutasitva');

// ---- Format -------------------------------------------------------------

allit(vespa_szintido_format(14.84) === '14.84', '60 masodperc alatt ket tizedesjegy, kettospont nelkul');
allit(vespa_szintido_format(9.5) === '9.50', 'kerekitett ket tizedesjegy 60 alatt');
allit(vespa_szintido_format(62.5) === '1:02.50', '60 masodperc felett perc:masodperc, nullaval kitoltve');
allit(vespa_szintido_format(605.07) === '10:05.07', 'tobb perces ido is helyesen formazva');
allit(vespa_szintido_format(60) === '1:00.00', 'pontosan 60 masodperc mar perc:masodperc formaban');

// ---- Megfelel -------------------------------------------------------------

allit(vespa_szintido_megfelel(14.84, 14.84) === true, 'pontosan egyezo ido megfelel (hatarertek)');
allit(vespa_szintido_megfelel(14.83, 14.84) === true, 'gyorsabb ido megfelel');
allit(vespa_szintido_megfelel(14.85, 14.84) === false, 'lassabb ido nem felel meg');
allit(vespa_szintido_megfelel(null, 14.84) === false, 'nincs rogzitett ido -> nem felel meg');
allit(vespa_szintido_megfelel(14.84, null) === true, 'nincs minimum -> nincs feltetel, mindenki megfelel');
allit(vespa_szintido_megfelel(null, null) === true, 'sem ido sem minimum -> nincs feltetel');
allit(vespa_szintido_megfelel(14.840, 14.84) === true, 'lebegopontos pontatlansag (14.840 vs 14.84) nem szamit');

echo "\n" . ($hibak === 0 ? "Minden teszt sikeres.\n" : $hibak . " teszt elbukott.\n");
exit($hibak === 0 ? 0 : 1);
