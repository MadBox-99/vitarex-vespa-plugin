<?php
/**
 * A versenykiírás szerkesztési jogosultságának unit tesztjei.
 * Futtatás: php tests/test-contest-edit-access.php
 * WordPress nem kell hozzá: a tesztelt függvény tiszta.
 */

require_once __DIR__ . '/../includes/Core/vespa.model.contest.php';

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

/**
 * Egy szerep összes versenytípusra adott eredménye egy lépésben.
 * A $vart kulcsai versenytípus-azonosítók.
 */
function allit_szerep($szerepnev, $jogok, $vart)
{
    foreach ($vart as $tipus => $vart_ertek) {
        allit(
            vespa_contest_szerkesztes_engedelyezett($tipus, $jogok) === $vart_ertek,
            $szerepnev . ' / ' . $tipus . '. típus -> ' . ($vart_ertek ? 'szerkesztheti' : 'nem szerkesztheti')
        );
    }
}

$mind_szerkesztheto = array(
    VespaContestType::ORSZAGOS   => true,
    VespaContestType::REGIONALIS => true,
    VespaContestType::MEGYEI     => true,
    VespaContestType::SZABADIDOS => true,
);

$orszagos_kivetelevel = array(
    VespaContestType::ORSZAGOS   => false,
    VespaContestType::REGIONALIS => true,
    VespaContestType::MEGYEI     => true,
    VespaContestType::SZABADIDOS => true,
);

$semmi = array(
    VespaContestType::ORSZAGOS   => false,
    VespaContestType::REGIONALIS => false,
    VespaContestType::MEGYEI     => false,
    VespaContestType::SZABADIDOS => false,
);

// ---- WordPress adminisztrátor -----------------------------------------
// Az országos szintet mostantól kizárólag ő szerkesztheti.

allit_szerep('wp_admin', array('admin' => true, 'versenykezeles' => true), $mind_szerkesztheto);

// A manage_options önmagában is elég: az admin akkor sem esik ki, ha a
// versenykezelés capability valamiért hiányzik a szerepéről.
allit_szerep('wp_admin_cap_nelkul', array('admin' => true, 'versenykezeles' => false), $mind_szerkesztheto);

// ---- Versenykezelő szerepek -------------------------------------------
// Ez volt a lyuk: a megyei szintű szerepek az országos versenyeket is
// szerkeszthették, mert a jogosultság nem nézte a verseny típusát.

allit_szerep('fovesz_fodisz_sportigazgato', array('admin' => false, 'versenykezeles' => true), $orszagos_kivetelevel);
allit_szerep('diak_sportigazgato', array('admin' => false, 'versenykezeles' => true), $orszagos_kivetelevel);
allit_szerep('megyei_versenyigazgato', array('admin' => false, 'versenykezeles' => true), $orszagos_kivetelevel);
allit_szerep('megyei_vezeto', array('admin' => false, 'versenykezeles' => true), $orszagos_kivetelevel);

// ---- Versenykezelési jog nélkül ---------------------------------------

allit_szerep('testnevelo', array('admin' => false, 'versenykezeles' => false), $semmi);
allit_szerep('kivulallo', array(), $semmi);

// ---- Ismeretlen vagy hiányzó versenytípus ------------------------------
// Nem tudjuk eldönteni, országos-e, ezért csak az admin mehet tovább.

allit(vespa_contest_szerkesztes_engedelyezett(0, array('admin' => false, 'versenykezeles' => true)) === false, 'ismeretlen típus (0) / versenykezelő -> nem');
allit(vespa_contest_szerkesztes_engedelyezett(99, array('admin' => false, 'versenykezeles' => true)) === false, 'ismeretlen típus (99) / versenykezelő -> nem');
allit(vespa_contest_szerkesztes_engedelyezett(null, array('admin' => false, 'versenykezeles' => true)) === false, 'hiányzó típus / versenykezelő -> nem');
allit(vespa_contest_szerkesztes_engedelyezett('', array('admin' => false, 'versenykezeles' => true)) === false, 'üres típus / versenykezelő -> nem');
allit(vespa_contest_szerkesztes_engedelyezett(0, array('admin' => true, 'versenykezeles' => true)) === true, 'ismeretlen típus (0) / admin -> igen');

// ---- A típus szöveges formában is érkezhet ($_POST-ból) ----------------

allit(vespa_contest_szerkesztes_engedelyezett('1', array('admin' => false, 'versenykezeles' => true)) === false, 'szöveges "1" (országos) / versenykezelő -> nem');
allit(vespa_contest_szerkesztes_engedelyezett('3', array('admin' => false, 'versenykezeles' => true)) === true, 'szöveges "3" (megyei) / versenykezelő -> igen');

// ---- Összegzés ---------------------------------------------------------

echo "\n";
if ($hibak === 0) {
    echo "Minden teszt sikeres.\n";
    exit(0);
}

echo $hibak . " teszt elbukott.\n";
exit(1);
