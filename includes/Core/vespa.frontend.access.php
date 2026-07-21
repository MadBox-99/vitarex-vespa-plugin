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
