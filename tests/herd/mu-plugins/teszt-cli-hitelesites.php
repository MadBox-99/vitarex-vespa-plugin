<?php
/*
    Plugin Name: Teszt CLI hitelesítés
    Description: Parancssori riport-teszthez adminisztrátorként azonosít, ha a VESPA_TESZT_ADMIN környezeti változó be van állítva.
*/

/**
 * A riport-végpont az `init` hookon fut, az `init` viszont már a wp-load.php
 * betöltése közben eldördül — vagyis mire a hívó szkript kódja következne, a
 * jogosultság-ellenőrzés rég megtörtént. Ezért a felhasználót nem a szkriptből,
 * hanem innen, a mu-plugin szintjéről állítjuk be: ez a WordPress betöltésének
 * korai szakasza, jóval az init előtt.
 *
 * Csak parancssorból és csak kifejezett környezeti változóra aktív, hogy a
 * böngészőből érkező kéréseken semmilyen hatása ne legyen.
 */
add_filter('determine_current_user', function ($felhasznalo) {
    if (PHP_SAPI !== 'cli' || !getenv('VESPA_TESZT_ADMIN')) {
        return $felhasznalo;
    }

    $keresett = getenv('VESPA_TESZT_ADMIN');

    if (ctype_digit((string) $keresett)) {
        return (int) $keresett;
    }

    // Bármi más érték esetén az első adminisztrátort adjuk.
    $adminok = get_users(array('role' => 'administrator', 'number' => 1, 'fields' => 'ID'));

    return $adminok ? (int) $adminok[0] : $felhasznalo;
}, 20);
