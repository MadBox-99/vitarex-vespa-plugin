<?php

/**
 * A beszámoló-válaszok question_id oszlopának egyszeri felvétele és feltöltése.
 *
 * A vespa_questions_answered táblát NEM ez a plugin hozza létre — a séma a
 * dumpban él —, ezért itt nem dbDelta-t használunk: az teljes CREATE TABLE-t
 * várna, és egy jövőbeli kézi séma-változást visszaírhatna. Helyette
 * megnézzük, létezik-e már az oszlop, és csak akkor futtatunk ALTER TABLE-t.
 *
 * A séma a dumpban él, aktivációs hook nincs — ezért, a szerepekhez és a
 * szabadidős telepítőhöz hasonlóan, init-en, verzió-kapuval fut.
 */
add_action('init', 'vespa_kerdoiv_install', 5);

function vespa_kerdoiv_install()
{
    if (get_option('vespa_kerdoiv_db_version') === '1') {
        return;
    }

    global $wpdb;

    if (!vespa_kerdoiv_oszlop_letezik()) {
        $wpdb->query(
            "ALTER TABLE vespa_questions_answered
               ADD COLUMN question_id int(11) NOT NULL DEFAULT 0,
               ADD KEY contest_question (contest_id, question_id)"
        );
    }

    // A kaput csak akkor zárjuk le, ha az oszlop tényleg létrejött. Enélkül
    // egy elbukott migráció után soha többé nem futna újra.
    if (!vespa_kerdoiv_oszlop_letezik()) {
        return;
    }

    // A meglévő sorok párosítása a közös kérdéskészlethez a kérdés SZÖVEGE
    // alapján — ez az egyetlen kapocs, ami a régi adatban létezik. Ami nem
    // talál (azóta törölt vagy átírt kérdés), az 0 marad, és a beszámoló
    // történelmi részeként megőrződik.
    $wpdb->query(
        "UPDATE vespa_questions_answered AS qa
         INNER JOIN vespa_contests_questions AS q ON q.question = qa.question
         SET qa.question_id = q.question_id
         WHERE qa.question_id = 0"
    );

    update_option('vespa_kerdoiv_db_version', '1');
}

/**
 * Létezik-e már a question_id oszlop? A migráció ez alapján idempotens akkor
 * is, ha az option valamiért elveszett.
 */
function vespa_kerdoiv_oszlop_letezik()
{
    global $wpdb;

    $db = $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = %s AND COLUMN_NAME = %s",
        'vespa_questions_answered',
        'question_id'
    ));

    return intval($db) > 0;
}
