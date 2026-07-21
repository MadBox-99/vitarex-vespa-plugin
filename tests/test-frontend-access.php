<?php
/**
 * A front-end hozzáférési döntés unit tesztjei.
 * Futtatás: php tests/test-frontend-access.php
 * WordPress nem kell hozzá: a tesztelt függvény tiszta.
 */

require_once __DIR__ . '/../includes/Core/vespa.frontend.access.php';

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

$publikus = array(204, 310);

// A wp-admin felülettel nincs dolgunk.
allit(
    vespa_frontend_access_decide(true, true, false, 0, $publikus) === 'pass',
    'wp-admin felület mindig átmegy'
);

// Publikusra jelölt oldal mindenkinek elérhető.
allit(
    vespa_frontend_access_decide(false, false, false, 204, $publikus) === 'pass',
    'publikus oldal kijelentkezve átmegy'
);
allit(
    vespa_frontend_access_decide(false, true, false, 204, $publikus) === 'pass',
    'publikus oldal bejelentkezve is átmegy'
);

// Nem publikus oldalon marad a mai viselkedés.
allit(
    vespa_frontend_access_decide(false, true, false, 999, $publikus) === 'admin',
    'nem publikus oldal bejelentkezve wp-adminba megy'
);
allit(
    vespa_frontend_access_decide(false, false, false, 999, $publikus) === 'login',
    'nem publikus oldal kijelentkezve loginra megy'
);

// A szabadidős résztvevőt SOHA nem küldjük wp-adminba — ez töri meg a hurkot.
allit(
    vespa_frontend_access_decide(false, true, true, 999, $publikus) === 'pass',
    'szabadidos resztvevo nem publikus oldalon is atmegy (hurokvedelem)'
);
allit(
    vespa_frontend_access_decide(false, true, true, 0, array()) === 'pass',
    'szabadidos resztvevo ures publikus listaval is atmegy'
);

// Nem egyedi bejegyzés (archívum, 404): post_id = 0, nem publikus.
allit(
    vespa_frontend_access_decide(false, false, false, 0, $publikus) === 'login',
    'nem singular oldal kijelentkezve loginra megy'
);

// Típusbiztonság: a listában szöveges ID is elfogadott, a 0 viszont soha nem talál.
allit(
    vespa_frontend_access_decide(false, false, false, 204, array('204')) === 'pass',
    'szoveges ID a listaban is talal'
);
allit(
    vespa_frontend_access_decide(false, false, false, 0, array(0)) === 'login',
    'a 0 post_id soha nem szamit publikusnak'
);

echo "\n" . ($hibak === 0 ? "Minden teszt sikeres.\n" : $hibak . " teszt elbukott.\n");
exit($hibak === 0 ? 0 : 1);
