<?php
/**
 * A verseny-dokumentumok letöltési jogosultságának unit tesztjei.
 * Futtatás: php tests/test-docs-access.php
 * WordPress nem kell hozzá: a tesztelt függvény tiszta.
 */

require_once __DIR__ . '/../includes/Core/vespa.docs_access.php';

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
 * Egy szerep összes dokumentumtípusra adott eredménye egy lépésben.
 * A $vart kulcsai: listing, answers, logistic, emails, athletes, medical_approval
 */
function allit_szerep($szerepnev, $jogok, $vart)
{
    foreach ($vart as $tipus => $vart_ertek) {
        allit(
            vespa_contest_doc_engedelyezett($tipus, $jogok) === $vart_ertek,
            $szerepnev . ' / ' . $tipus . ' -> ' . ($vart_ertek ? 'igen' : 'nem')
        );
    }
}

// ---- Nem bejelentkezett látogató --------------------------------------
// Ez volt a lyuk: a linket ismerve mindent le tudott tölteni.

allit_szerep('kivulallo', array(), array(
    'listing'          => false,
    'answers'          => false,
    'logistic'         => false,
    'emails'           => false,
    'athletes'         => false,
    'medical_approval' => false,
));

// ---- Sportoló / tanuló: nincs versenyek_megtekintese -------------------

allit_szerep('sportolo', array('testnevelo' => false), array(
    'listing' => false,
    'answers' => false,
));

// ---- Testnevelő --------------------------------------------------------

allit_szerep('testnevelo', array(
    'versenyek_megtekintese' => true,
    'testnevelo'             => true,
), array(
    'listing'          => true,
    'answers'          => false, // beszámoló: csak a versenyt kezelő szerepeknek
    'logistic'         => false,
    'emails'           => false,
    'athletes'         => false, // más iskola nevezési listája nem az ő ügye
    'medical_approval' => true,
));

// ---- Iskolaigazgató (versenyeket lát, de nem kezel) --------------------

allit_szerep('iskolaigazgato', array(
    'versenyek_megtekintese' => true,
), array(
    'listing'          => true,
    'answers'          => false,
    'logistic'         => false,
    'emails'           => false,
    'athletes'         => true,
    'medical_approval' => false,
));

// ---- Megyei versenyigazgató -------------------------------------------

allit_szerep('megyei_versenyigazgato', array(
    'versenyek_megtekintese' => true,
    'versenykezeles'         => true,
    'logisztika'             => true,
), array(
    'listing'          => true,
    'answers'          => true,
    'logistic'         => true,
    'emails'           => true,
    'athletes'         => true,
    'medical_approval' => true,
));

// ---- Diák sportigazgató: kezel, de logisztikát nem kérdez le -----------

allit_szerep('diak_sportigazgato', array(
    'versenyek_megtekintese' => true,
    'versenykezeles'         => true,
), array(
    'answers'  => true,
    'logistic' => false,
    'emails'   => false,
));

// ---- Adminisztrátor: a szerephez nincs capability rendelve -------------
// Enélkül az alapfeltételen elvérezne, pedig a felületen eddig is látta a
// logisztikát.

allit_szerep('adminisztrator', array('admin' => true), array(
    'listing'          => true,
    'answers'          => true,
    'logistic'         => true,
    'emails'           => true,
    'athletes'         => true,
    'medical_approval' => true,
));

// ---- Ismeretlen típus --------------------------------------------------

allit(
    vespa_contest_doc_engedelyezett('valami_mas', array('admin' => true)) === false,
    'ismeretlen dokumentumtipus meg az adminnak sem megy'
);
allit(
    vespa_contest_doc_engedelyezett('', array('admin' => true)) === false,
    'ures dokumentumtipus sem megy'
);

// ---- Összegzés ---------------------------------------------------------

echo "\n";
if ($hibak === 0) {
    echo "Minden teszt sikeres.\n";
    exit(0);
}

echo $hibak . " teszt elbukott.\n";
exit(1);
