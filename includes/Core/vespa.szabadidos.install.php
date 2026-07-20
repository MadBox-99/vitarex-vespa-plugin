<?php

/**
 * A szabadidős külső regisztráció három táblájának idempotens létrehozása.
 * A plugin nem használ aktivációs hookot; a séma a dumpban él, ezért — a
 * szerepekhez hasonlóan (init_custom_roles) — init-en, verzió-kapuval fut.
 */
add_action('init', 'vespa_szabadidos_install', 5);

function vespa_szabadidos_install()
{
    $telepitett = get_option('vespa_szabadidos_db_version');
    if ($telepitett === '1') {
        return;
    }

    global $wpdb;
    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    $charset_collate = $wpdb->get_charset_collate();

    // A táblák szándékosan $wpdb->prefix NÉLKÜL (a plugin minden vespa_* táblája így hivatkozott).
    $sql_participants = "CREATE TABLE vespa_external_participants (
  participant_id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  user_id bigint(20) unsigned NOT NULL,
  full_name varchar(190) NOT NULL,
  birth_date date DEFAULT NULL,
  gender varchar(10) DEFAULT NULL,
  email varchar(190) NOT NULL,
  phone varchar(50) DEFAULT NULL,
  consent_at datetime DEFAULT NULL,
  created_at datetime NOT NULL,
  PRIMARY KEY  (participant_id),
  KEY user_id (user_id)
) $charset_collate;";

    $sql_entries = "CREATE TABLE vespa_external_entries (
  entry_id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  participant_id bigint(20) unsigned NOT NULL,
  contest_id bigint(20) unsigned NOT NULL,
  contest_event_id bigint(20) unsigned NOT NULL,
  entry_date datetime NOT NULL,
  PRIMARY KEY  (entry_id),
  KEY participant_id (participant_id),
  KEY contest_event_id (contest_event_id)
) $charset_collate;";

    $sql_open = "CREATE TABLE vespa_szabadidos_open_contests (
  contest_id bigint(20) unsigned NOT NULL,
  opened_at datetime NOT NULL,
  opened_by bigint(20) unsigned DEFAULT NULL,
  PRIMARY KEY  (contest_id)
) $charset_collate;";

    dbDelta($sql_participants);
    dbDelta($sql_entries);
    dbDelta($sql_open);

    update_option('vespa_szabadidos_db_version', '1');
}
