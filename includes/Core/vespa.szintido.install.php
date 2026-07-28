<?php

/**
 * A szintidő séma felvétele: az új vespa_athlete_qualifying_times tábla
 * (dbDelta-val, ez a mi táblánk), és a vespa_constest_events tábla
 * min_qualifying_seconds oszlopa (plain ALTER TABLE — azt a táblát NEM ez a
 * plugin hozza létre, dbDelta egy teljes CREATE TABLE-t várna, ami egy
 * jövőbeli kézi séma-változást visszaírhatna).
 *
 * A plugin nem használ aktivációs hookot; a séma a dumpban él, ezért — a
 * kérdőív- és a szabadidős telepítőhöz hasonlóan — init-en, verzió-kapuval fut.
 */
add_action('init', 'vespa_szintido_install', 5);

function vespa_szintido_install()
{
    if (get_option('vespa_szintido_db_version') === '1') {
        return;
    }

    global $wpdb;
    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    $charset_collate = $wpdb->get_charset_collate();

    // A tábla szándékosan $wpdb->prefix NÉLKÜL (a plugin minden vespa_* táblája így hivatkozott).
    $sql_idok = "CREATE TABLE vespa_athlete_qualifying_times (
  qt_id          bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  athlete_id     int(11) NOT NULL,
  sport_event_id int(11) NOT NULL,
  seconds        decimal(8,3) NOT NULL,
  raw_input      varchar(30) NOT NULL,
  recorded_at    datetime NOT NULL,
  recorded_by    bigint(20) unsigned DEFAULT NULL,
  PRIMARY KEY  (qt_id),
  UNIQUE KEY uniq_athlete_event (athlete_id, sport_event_id),
  KEY sport_event_id (sport_event_id)
) $charset_collate;";

    dbDelta($sql_idok);

    if (!vespa_szintido_oszlop_letezik()) {
        $wpdb->query(
            "ALTER TABLE vespa_constest_events
               ADD COLUMN min_qualifying_seconds decimal(8,3) DEFAULT NULL"
        );
    }

    // A kaput csak akkor zárjuk le, ha az oszlop tényleg létrejött. Enélkül
    // egy elbukott migráció után soha többé nem futna újra.
    if (!vespa_szintido_oszlop_letezik()) {
        return;
    }

    update_option('vespa_szintido_db_version', '1');
}

/**
 * Létezik-e már a min_qualifying_seconds oszlop? A migráció ez alapján
 * idempotens akkor is, ha az option valamiért elveszett.
 */
function vespa_szintido_oszlop_letezik()
{
    global $wpdb;

    $db = $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = %s AND COLUMN_NAME = %s",
        'vespa_constest_events',
        'min_qualifying_seconds'
    ));

    return intval($db) > 0;
}
