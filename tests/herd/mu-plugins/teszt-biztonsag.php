<?php
/*
    Plugin Name: Teszt környezet biztonsági fék
    Description: Kizárja, hogy a produkcióból klónozott adatokkal a helyi példány kifelé kommunikáljon.
*/

/**
 * Minden kimenő levél eldobása.
 *
 * Az adatbázis éles másolat: valódi pedagógus- és igazgató-címeket tartalmaz.
 * Egy véletlen jelszó-emlékeztető vagy értesítő valódi embereknek menne ki, ezért
 * a levélküldést nem konfiguráljuk, hanem elvágjuk. A pre_wp_mail szűrő a
 * wp_mail() legelső kapuja: true-t visszaadva a levél sikeresnek látszik, de
 * semmilyen továbbítás nem történik.
 */
add_filter('pre_wp_mail', '__return_true', PHP_INT_MAX);

// A keresőmotorok elől mindenképp rejtve.
add_filter('pre_option_blog_public', '__return_zero');

// Látható jelzés az adminban, nehogy valaki összekeverje az élessel.
add_action('admin_notices', function () {
    echo '<div class="notice notice-warning"><p><strong>HELYI TESZT KÖRNYEZET</strong> — '
        . 'az adatbázis éles másolat, a levélküldés le van tiltva.</p></div>';
});
