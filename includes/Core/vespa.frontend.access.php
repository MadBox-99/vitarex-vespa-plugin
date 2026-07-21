<?php

/**
 * Eldönti, mi történjen egy front-end kéréssel.
 *
 * Szándékosan tiszta függvény: nem hív WordPress API-t, így önmagában
 * tesztelhető (lásd tests/test-frontend-access.php).
 *
 * @param bool  $is_admin_area   igaz, ha a kérés a wp-admin felületre megy
 * @param bool  $is_logged_in    igaz, ha van bejelentkezett felhasználó
 * @param bool  $is_participant  igaz, ha a felhasználó szabadidős külső résztvevő
 * @param int   $post_id         az aktuális egyedi bejegyzés ID-ja, vagy 0
 * @param array $public_page_ids publikusan elérhetőnek jelölt oldal-ID-k
 *
 * @return string 'pass' (engedjük), 'admin' (wp-adminba) vagy 'login' (login oldalra)
 */
function vespa_frontend_access_decide($is_admin_area, $is_logged_in, $is_participant, $post_id, $public_page_ids)
{
    if ($is_admin_area) {
        return 'pass';
    }

    $post_id = intval($post_id);
    if ($post_id > 0) {
        foreach ((array) $public_page_ids as $publikus_id) {
            if (intval($publikus_id) === $post_id) {
                return 'pass';
            }
        }
    }

    // A szabadidős résztvevőnek nincs dolga a wp-adminban, ezért őt sosem
    // küldjük oda: a szabadidos.isolation.php onnan visszairányítaná, és a
    // két szabály végtelen hurkot zárna. Ez a lépés vágja el a hurkot.
    if ($is_logged_in && $is_participant) {
        return 'pass';
    }

    return $is_logged_in ? 'admin' : 'login';
}

/** A beállítás option neve. */
function vespa_frontend_access_option_name()
{
    return 'vespa_frontend_access';
}

/**
 * A mentett beállítások, mindig teljes és normalizált szerkezetben.
 */
function vespa_frontend_access_get_settings()
{
    $mentett = get_option(vespa_frontend_access_option_name(), array());
    if (!is_array($mentett)) {
        $mentett = array();
    }

    $oldalak = array();
    if (isset($mentett['public_page_ids']) && is_array($mentett['public_page_ids'])) {
        foreach ($mentett['public_page_ids'] as $id) {
            $oldalak[] = intval($id);
        }
    }

    return array(
        'public_page_ids'            => $oldalak,
        'szabadidos_landing_page_id' => isset($mentett['szabadidos_landing_page_id'])
            ? intval($mentett['szabadidos_landing_page_id'])
            : 0,
    );
}

/**
 * A publikusan elérhetőnek jelölt oldal-ID-k.
 */
function vespa_frontend_access_public_page_ids()
{
    $beallitasok = vespa_frontend_access_get_settings();
    return $beallitasok['public_page_ids'];
}

/**
 * A szabadidős résztvevő kezdőoldalának URL-je.
 *
 * Ha nincs beállítva, vagy az oldalt időközben törölték/piszkozatba tették,
 * csendben a kezdőlapra esik vissza.
 */
function vespa_frontend_access_landing_url()
{
    $beallitasok = vespa_frontend_access_get_settings();
    $id = $beallitasok['szabadidos_landing_page_id'];

    if ($id > 0 && get_post_status($id) === 'publish') {
        $link = get_permalink($id);
        // A wp_safe_redirect() idegen hoszt esetén csendben admin_url()-re esik
        // vissza. Szabadidős résztvevőnél ez újraindítaná az izolációt, és
        // visszahozná a hurkot, ezért csak azonos hosztú linket adunk vissza.
        if ($link && vespa_frontend_access_same_host($link)) {
            return $link;
        }
    }

    return home_url('/');
}

/**
 * Igaz, ha az URL a saját oldalunk hosztjára mutat.
 * Relatív URL-t sajátnak tekintünk.
 */
function vespa_frontend_access_same_host($url)
{
    $url_host  = wp_parse_url($url, PHP_URL_HOST);
    $home_host = wp_parse_url(home_url('/'), PHP_URL_HOST);

    if (empty($url_host)) {
        return true;
    }

    return strtolower($url_host) === strtolower($home_host);
}

/**
 * Csak létező, publikált oldalak ID-jeit engedi át, duplikátum nélkül.
 */
function vespa_frontend_access_sanitize_page_ids($ids)
{
    $eredmeny = array();

    foreach ((array) $ids as $id) {
        $id = intval($id);
        if ($id <= 0) {
            continue;
        }
        if (get_post_type($id) !== 'page' || get_post_status($id) !== 'publish') {
            continue;
        }
        if (!in_array($id, $eredmeny, true)) {
            $eredmeny[] = $id;
        }
    }

    return $eredmeny;
}
