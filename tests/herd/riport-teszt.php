<?php
/**
 * Riport-generálás parancssorból, valódi WordPress betöltésével.
 *
 * Használat:
 *   php riport-teszt.php <riport_tipus> [kulcs=ertek ...]
 *
 * Példa:
 *   php riport-teszt.php tanev_diakolimpia_diakok series=0 year=2025
 *
 * A riport a php://output-ra ír és exit-tel zárul, ezért nem lehet a hívó
 * folyamaton belül elkapni: külön folyamatban futtatjuk, és a szabványos
 * kimenetet a hívó irányítja fájlba. A CLI SAPI-ban a header() csendben
 * elszáll, tehát csak a fájl tartalma marad — pont amit vizsgálni akarunk.
 */

if (PHP_SAPI !== 'cli') {
    exit("Csak parancssorbol futtathato.\n");
}

$argumentumok = $argv;
array_shift($argumentumok);

if (!$argumentumok) {
    fwrite(STDERR, "Hasznalat: php riport-teszt.php <riport_tipus> [kulcs=ertek ...]\n");
    exit(1);
}

$_GET['download_riports'] = array_shift($argumentumok);

foreach ($argumentumok as $par) {
    if (strpos($par, '=') === false) {
        continue;
    }
    list($kulcs, $ertek) = explode('=', $par, 2);
    $_GET[$kulcs] = $ertek;
}

// A riport-végpont az init hookon fut, ezért a jogosultságot még a WordPress
// betöltése előtt kell beállítani — a beállított felhasználót az alábbi szűrő
// adja vissza, így nem kell munkamenet-sütit hamisítani.
$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['HTTP_HOST']      = 'fodisz-teszt.test';
$_SERVER['REQUEST_URI']    = '/';

define('WP_USE_THEMES', false);

// A jogosultságot a teszt-cli-hitelesites.php mu-plugin adja, mert az init
// hook már a wp-load.php betöltése közben lefut — vagyis a riport itt, a
// require sorában generálódik és exitel.
putenv('VESPA_TESZT_ADMIN=' . (getenv('VESPA_TESZT_ADMIN') ?: 'elso'));

// A riport binárisan ír; a képernyőre küldött figyelmeztetés pont ezt rontaná
// el. A tesztben ezért NEM némítjuk el őket — hagyjuk, hogy beleírjanak a
// kimenetbe, és a hívó ellenőrizze, hogy tiszta maradt-e a fájl.
require_once __DIR__ . '/wp-load.php';

fwrite(STDERR, "A riport nem generalt kimenetet (a vegpont nem futott le).\n");
exit(2);
