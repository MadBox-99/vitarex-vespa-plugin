<?php

/**
 * Ki tölthet le melyik verseny-dokumentumot.
 *
 * A `?download_contest_docs=` végpont korábban semmilyen jogosultságot nem
 * ellenőrzött, és `init`-en fut, ezért a linket ismerve bejelentkezés nélkül is
 * letölthető volt a beszámoló, a sportolói logisztika, az email lista és a
 * nevezési lista. A szabályok az admin felület legördülőjét tükrözik (sőt,
 * annál valamivel bővebbek), így aki eddig a felületen elérte a dokumentumot,
 * az továbbra is eléri — a változás csak az, hogy a közvetlen URL-t már nem
 * lehet jogosultság nélkül meghívni.
 *
 * A döntés tiszta függvényben van (tesztek: tests/test-docs-access.php), a
 * WordPress-től csak a jogosultság-jelzőket kapja.
 */

/**
 * @param string $tipus a download_contest_docs paraméter értéke
 * @param array  $jogok logikai jelzők: admin, versenyek_megtekintese,
 *                      versenykezeles, logisztika, testnevelo
 * @return bool
 */
function vespa_contest_doc_engedelyezett($tipus, $jogok)
{
    $jog = function ($kulcs) use ($jogok) {
        return !empty($jogok[$kulcs]);
    };

    // Ismeretlen dokumentumtípusra senkinek nincs joga — az adminnak sem.
    $ismert_tipusok = array('listing', 'answers', 'logistic', 'emails', 'athletes', 'medical_approval');
    if (!in_array($tipus, $ismert_tipusok, true)) {
        return false;
    }

    // Az adminisztrátor mindent lát. Külön ág kell rá, mert az „adminisztrator"
    // szerephez a VESPA_Roles nem rendel egyetlen capability-t sem — a
    // versenyek_megtekintese feltételen elvérezne.
    if ($jog('admin')) {
        return true;
    }

    // Alapfeltétel minden dokumentumra: a versenyeket egyáltalán látnia kell.
    // Ez zárja ki a nem bejelentkezett látogatót, a sportolót és a tanulót.
    if (!$jog('versenyek_megtekintese')) {
        return false;
    }

    switch ($tipus) {
        case 'listing': // Kiírás — bárki, aki a versenyt is látja
            return true;

        case 'answers': // Beszámoló — csak aki rögzíteni is tudja
            return $jog('versenykezeles');

        case 'logistic': // Sportolói logisztika
        case 'emails':   // Nevezettek email listája
            return $jog('logisztika');

        case 'athletes': // Nevezési lista — a testnevelő más iskoláét nem látja
            return !$jog('testnevelo');

        case 'medical_approval': // Orvosi engedély
            return $jog('testnevelo') || $jog('versenykezeles');
    }

    // Ismeretlen dokumentumtípus: nincs letöltés.
    return false;
}

/**
 * A fenti szabály kiértékelése az aktuális felhasználóra. A felület és a
 * végpont is ezt hívja, így a kettő nem tud elcsúszni egymástól.
 */
function vespa_user_can_download_contest_doc($tipus)
{
    $roles = VESPA_Roles::getInstance();

    return vespa_contest_doc_engedelyezett($tipus, array(
        'admin' => is_super_admin()
            || current_user_can('manage_options')
            || $roles->current_user_has_role(VESPA_Roles::ADMINISZTRATOR),

        'versenyek_megtekintese' => current_user_can(VESPA_Roles::versenyek_megtekintese),

        'versenykezeles' => current_user_can(VESPA_Roles::versenyek_kezelese_kiiras_modositas_torles),

        // A felületen eddig szerep szerint jelent meg; a capability-vel bővítve
        // a megyei vezető sem esik ki, aki eddig is jogosult volt rá.
        'logisztika' => current_user_can(VESPA_Roles::versenylogisztika_lekerdezes)
            || $roles->current_user_has_role(VESPA_Roles::MEGYEI_VERSENYIGAZGATO)
            || $roles->current_user_has_role(VESPA_Roles::FOVESZ_FODISZ_SPORTIGAZGATO),

        'testnevelo' => $roles->current_user_has_role(VESPA_Roles::TESTNEVELO),
    ));
}
