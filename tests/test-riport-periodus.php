<?php
/**
 * A riportok időszak-szűrőjének (szezon + naptári év) unit tesztjei.
 * Futtatás: php tests/test-riport-periodus.php
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

// ---- Csak szezon -------------------------------------------------------

$r = vespa_riport_periodus_szuro('3', '0');
allit($r['sql'] === ' AND vc.contest_series=%d', 'csak szezon: sql toredek');
allit($r['params'] === array(3), 'csak szezon: params egesz szamma alakitva');

// ---- Csak naptári év ---------------------------------------------------

$r = vespa_riport_periodus_szuro('0', '2025');
allit($r['sql'] === ' AND YEAR(vc.start_at)=%d', 'csak ev: sql toredek');
allit($r['params'] === array(2025), 'csak ev: params');

// ---- Mindkettő ---------------------------------------------------------
// A sorrend kötött: előbb a szezon helyőrzője, utána az évé. Ha ez elcsúszik,
// a prepare() a szezon-azonosítót helyettesíti be évszámként.

$r = vespa_riport_periodus_szuro('3', '2025');
allit(
    $r['sql'] === ' AND vc.contest_series=%d AND YEAR(vc.start_at)=%d',
    'szezon+ev: mindket toredek, szezon eloszor'
);
allit($r['params'] === array(3, 2025), 'szezon+ev: params sorrendje szezon, majd ev');

// ---- Egyik sem ---------------------------------------------------------

$r = vespa_riport_periodus_szuro('0', '0');
allit($r['sql'] === '', 'egyik sem: ures sql');
allit($r['params'] === array(), 'egyik sem: ures params');

// ---- Szabadidős kivétel (tanev_diakolimpia_versenyszam_sportag) ---------
// Ez a riport a szabadidős versenyeket szezontól függetlenül beszámolja.

$r = vespa_riport_periodus_szuro('3', '0', true);
allit(
    $r['sql'] === ' AND (vc.contest_series=%d OR vc.contest_type=4)',
    'szabadidos kivetel + szezon: vagyos szezon-feltetel'
);
allit($r['params'] === array(3), 'szabadidos kivetel + szezon: params');

$r = vespa_riport_periodus_szuro('0', '2025', true);
allit(
    $r['sql'] === ' AND YEAR(vc.start_at)=%d',
    'szabadidos kivetel szezon nelkul: nincs szezon-toredek'
);
allit($r['params'] === array(2025), 'szabadidos kivetel szezon nelkul: params');

$r = vespa_riport_periodus_szuro('3', '2025', true);
allit(
    $r['sql'] === ' AND (vc.contest_series=%d OR vc.contest_type=4) AND YEAR(vc.start_at)=%d',
    'szabadidos kivetel + szezon + ev: az ev a teljes kifejezesre AND-elodik'
);
allit($r['params'] === array(3, 2025), 'szabadidos kivetel + szezon + ev: params');

// ---- Érvénytelen bemenetek ---------------------------------------------
// A GET-ből bármi jöhet; ezek mind "nincs szűrés"-ként viselkedjenek.

$rosszErtekek = array('', 'abc', '-1', '0', 0, null, false);
foreach ($rosszErtekek as $rossz) {
    $r = vespa_riport_periodus_szuro($rossz, $rossz);
    allit(
        $r['sql'] === '' && $r['params'] === array(),
        'ervenytelen bemenet nem szur: ' . var_export($rossz, true)
    );
}

// Vegyes eset: érvénytelen szezon mellett érvényes év.
$r = vespa_riport_periodus_szuro('abc', '2025');
allit($r['sql'] === ' AND YEAR(vc.start_at)=%d', 'ervenytelen szezon + ervenyes ev: csak ev');
allit($r['params'] === array(2025), 'ervenytelen szezon + ervenyes ev: params');

// ---- Fejléc-felirat ----------------------------------------------------

allit(
    vespa_riport_periodus_felirat('2025/2026', 0) === '2025/2026',
    'felirat: csak szezon'
);
allit(
    vespa_riport_periodus_felirat('2025/2026', '2025') === '2025/2026 — naptári év: 2025',
    'felirat: szezon + ev'
);
allit(
    vespa_riport_periodus_felirat('', '2025') === 'Naptári év: 2025',
    'felirat: csak ev'
);
allit(
    vespa_riport_periodus_felirat(null, 0) === 'Nincs időszakszűrés (összes verseny)',
    'felirat: egyik sem'
);
allit(
    vespa_riport_periodus_felirat(null, 'abc') === 'Nincs időszakszűrés (összes verseny)',
    'felirat: ervenytelen ev ugy szamit, mintha nem lenne'
);

// ---- Összegzés ---------------------------------------------------------

echo "\n";
if ($hibak === 0) {
    echo "Minden teszt sikeres.\n";
    exit(0);
}

echo $hibak . " teszt elbukott.\n";
exit(1);
