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

// ---- Megyei szintű szerepek --------------------------------------------
// A versenyigazgató és a megyei vezető csak a saját megyéje megyei
// versenyeit szerkesztheti. Eddig minden nem országos versenyt módosíthatott,
// bármelyik megyéé volt.

$megyei = array('admin' => false, 'versenykezeles' => true, 'megyei_szerep' => true, 'sajat_megye' => 5);

allit(vespa_contest_szerkesztes_engedelyezett(VespaContestType::MEGYEI, $megyei, 5) === true, 'megyei szerep / saját megyéje megyei versenye -> igen');
allit(vespa_contest_szerkesztes_engedelyezett(VespaContestType::MEGYEI, $megyei, 7) === false, 'megyei szerep / másik megye megyei versenye -> nem');
allit(vespa_contest_szerkesztes_engedelyezett(VespaContestType::MEGYEI, $megyei, 0) === false, 'megyei szerep / megye nélküli megyei verseny -> nem');
allit(vespa_contest_szerkesztes_engedelyezett(VespaContestType::ORSZAGOS, $megyei, 5) === false, 'megyei szerep / országos verseny -> nem');
allit(vespa_contest_szerkesztes_engedelyezett(VespaContestType::REGIONALIS, $megyei, 5) === false, 'megyei szerep / regionális verseny a saját megyéjében -> nem');
allit(vespa_contest_szerkesztes_engedelyezett(VespaContestType::SZABADIDOS, $megyei, 5) === false, 'megyei szerep / szabadidős verseny a saját megyéjében -> nem');

// A megye szövegesen is érkezhet a $_POST-ból, illetve az adatbázisból.
allit(vespa_contest_szerkesztes_engedelyezett('3', $megyei, '5') === true, 'megyei szerep / szöveges típus és megye -> igen');

// Megye nélkül (null) csak a típust nézzük: ez kell a szerkesztő
// típusválasztójához, ahol még nincs kiválasztott megye.
allit(vespa_contest_szerkesztes_engedelyezett(VespaContestType::MEGYEI, $megyei) === true, 'megyei szerep / típusválasztó: megyei típus -> igen');
allit(vespa_contest_szerkesztes_engedelyezett(VespaContestType::REGIONALIS, $megyei) === false, 'megyei szerep / típusválasztó: regionális típus -> nem');

// Akinek nincs beállítva megyéje, megyei versenyt sem szerkeszthet — eddig a
// hiányzó megye csendben 0 lett, és a szűrés így senkire nem illett.
$megye_nelkuli = array('admin' => false, 'versenykezeles' => true, 'megyei_szerep' => true, 'sajat_megye' => 0);

allit(vespa_contest_szerkesztes_engedelyezett(VespaContestType::MEGYEI, $megye_nelkuli, 5) === false, 'megye nélküli versenyigazgató / megyei verseny -> nem');
allit(vespa_contest_szerkesztes_engedelyezett(VespaContestType::MEGYEI, $megye_nelkuli, 0) === false, 'megye nélküli versenyigazgató / megye nélküli verseny -> nem');
allit(vespa_contest_szerkesztes_engedelyezett(VespaContestType::MEGYEI, $megye_nelkuli) === false, 'megye nélküli versenyigazgató / típusválasztó -> nem');

// Aki megyei szerep mellett országos hatókörű szerepet is kapott, nem
// szigorodik be: a FOVESZ/FODISZ sportigazgatói jogköre marad az erősebb.
$vegyes = array('admin' => false, 'versenykezeles' => true, 'megyei_szerep' => false, 'sajat_megye' => 5);

allit(vespa_contest_szerkesztes_engedelyezett(VespaContestType::MEGYEI, $vegyes, 7) === true, 'megyei + országos hatókörű szerep / másik megye versenye -> igen');
allit(vespa_contest_szerkesztes_engedelyezett(VespaContestType::REGIONALIS, $vegyes, 7) === true, 'megyei + országos hatókörű szerep / regionális verseny -> igen');

// Az adminisztrátort a megyei szerep sem korlátozza.
$admin_megyei = array('admin' => true, 'versenykezeles' => true, 'megyei_szerep' => true, 'sajat_megye' => 5);

allit(vespa_contest_szerkesztes_engedelyezett(VespaContestType::MEGYEI, $admin_megyei, 7) === true, 'admin megyei szereppel / másik megye versenye -> igen');
allit(vespa_contest_szerkesztes_engedelyezett(VespaContestType::ORSZAGOS, $admin_megyei, 0) === true, 'admin megyei szereppel / országos verseny -> igen');

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
