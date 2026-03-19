<?php

function vespa_menu_masterdatas()
{
    add_menu_page(
        'Versenyek',
        'Versenyek',
        'versenyek_megtekintese',
        'contests',
        'vespa_menu_contests',
        'dashicons-awards',
        2
    );

    add_menu_page(
        'Törzsadatok',
        'Törzsadatok',
        'manage_options',
        'torzsadatok',
        'vespa_menu_school_district',
        'dashicons-book',
        3
    );

    add_menu_page(
        'Intézmények',
        'Intézmények',
        'intezmenyek_listazasa',
        'institution',
        'vespa_menu_institution',
        'dashicons-building',
        3
    );

    add_menu_page(
        'Sportolók',
        'Sportolók',
        'sportolok_listazasa',
        'athletes',
        'vespa_menu_athletes',
        'dashicons-universal-access',
        3
    );

    add_menu_page(
        'Riportok',
        'Riportok',
        VESPA_Roles::riportalas,
        'riports',
        'vespa_menu_riports',
        'dashicons-media-spreadsheet',
        3
    );

    add_menu_page(
        'Verseny eredmények',
        'Verseny eredmények',
        VESPA_Roles::eredmenyek_megtekintese,
        'results',
        'vespa_menu_results',
        'dashicons-chart-bar',
        4
    );



    add_submenu_page(
        'torzsadatok',
        'Tankerületek',
        'Tankerületek',
        'manage_options',
        'torzsadatok',
        'vespa_menu_school_district'
    );

    add_submenu_page(
        'torzsadatok',
        'Korcsoportok',
        'Korcsoportok',
        'manage_options',
        'agegroups',
        'vespa_menu_agegroups'
    );

    add_submenu_page(
        'torzsadatok',
        'Versenysorozatok',
        'Versenysorozatok',
        'manage_options',
        'series',
        'vespa_menu_series'
    );

    add_submenu_page(
        'torzsadatok',
        'Sportok',
        'Sportok',
        'manage_options',
        'sports',
        'vespa_menu_sports'
    );

    add_submenu_page(
        'torzsadatok',
        'Versenyszámok',
        'Versenyszámok',
        'manage_options',
        'sportevents',
        'vespa_menu_sportevents'
    );
    /*
    add_submenu_page(
        'torzsadatok',
        'Verseny típusa (DEMO)',
        'Verseny típusa (DEMO)',
        'manage_options',
        'demo',
        'vespa_menu_demo'
    );
*/
    add_submenu_page(
        'torzsadatok',
        'Lebonyolítás rendje',
        'Lebonyolítás rendje',
        'manage_options',
        'contest_subtypes',
        'vespa_menu_contest_subtypes'
    );

    add_submenu_page(
        'torzsadatok',
        'Fogyatékossági csoportok',
        'Fogyatékossági csoportok',
        'manage_options',
        'disability_groups',
        'disability_groups'
    );

    add_submenu_page(
        'torzsadatok',
        'Beszámoló kérdések',
        'Beszámoló kérdések',
        'manage_options',
        'questions',
        'vespa_menu_questions'
    );

    /*
    add_submenu_page(
        'torzsadatok',
        'Tesztadat (DEMO)',
        'Tesztadat (DEMO)',
        'manage_options',
        'test_data',
        'vespa_menu_test_data'
    );
*/

    add_submenu_page(
        'athletes',
        'Sportoló import',
        'Sportoló import',
        'sportolok_letrehozasa_modositasa',
        'import_athletes',
        'vespa_menu_import_athletes'
    );
}
add_action('admin_menu', 'vespa_menu_masterdatas');

function vespa_menu_school_district()
{
    if (!isset($_GET['id'])) {
        vespa_load_template('school_district_list.php');
    } else {
        vespa_load_template('school_district_editor.php');
    }
}


function vespa_menu_agegroups()
{
    if (!isset($_GET['id'])) {
        vespa_load_template('agegroup_list.php');
    } else {
        vespa_load_template('agegroup_editor.php');
    }
}

function vespa_menu_series()
{
    if (!isset($_GET['id'])) {
        vespa_load_template('series_list.php');
    } else {
        vespa_load_template('series_editor.php');
    }
}

function vespa_menu_questions()
{
    vespa_load_template('contests_questions_editor.php');
}

function vespa_menu_sports()
{
    if (!isset($_GET['id'])) {
        vespa_load_template('sport_list.php');
    } else {
        vespa_load_template('sport_editor.php');
    }
}

function vespa_menu_sportevents()
{
    if (!isset($_GET['id'])) {
        vespa_load_template('sportevent_list.php');
    } else {
        vespa_load_template('sportevent_editor.php');
    }
}

function vespa_menu_demo()
{
    if (!isset($_GET['id'])) {
        vespa_load_template('demo_list.php');
    } else {
        vespa_load_template('demo_editor.php');
    }
}

function vespa_menu_contests()
{
    if (!isset($_GET['id'])) {
        vespa_load_template('contest_list.php');
    } else {
        if (!isset($_GET['action'])) {
            vespa_load_template('contest_editor.php');
        } else if ('view' == $_GET['action']) {
            vespa_load_template('contest_view.php');
        } else if ('results' == $_GET['action']) {
            vespa_load_template('contest_results.php');
        } else if ('question' == $_GET['action']) {
            vespa_load_template('questions_answered_editor.php');
        }
    }
}

function vespa_menu_institution()
{
    if (
        VESPA_Roles::getInstance()->current_user_has_role(VESPA_Roles::TESTNEVELO) ||
        VESPA_Roles::getInstance()->current_user_has_role(VESPA_Roles::ISKOLAIGAZGATO)
    ) {
        vespa_load_template('institution_editor.php');
    } else {
        if (!isset($_GET['id'])) {
            vespa_load_template('institution_list.php');
        } else {
            vespa_load_template('institution_editor.php');
        }
    }
}

function vespa_menu_riports()
{
    if (current_user_can(VESPA_Roles::riportalas)) {
        vespa_load_template('riports_dashboard.php');
    }
}

function vespa_menu_athletes()
{
    if (!isset($_GET['id'])) {
        vespa_load_template('athletes_list.php');
    } else {
        vespa_load_template('athletes_editor.php');
    }
}
function vespa_menu_contest_subtypes()
{
    if (!isset($_GET['id'])) {
        vespa_load_template('contest_subtype_list.php');
    } else {
        vespa_load_template('contest_subtype_editor.php');
    }
}

function disability_groups()
{
    if (!isset($_GET['id'])) {
        vespa_load_template('disability_group_list.php');
    } else {
        vespa_load_template('disability_group_editor.php');
    }
}


function vespa_menu_roles()
{
    if (!isset($_GET['id'])) {
        $roles = wp_roles();
        $keys = array_keys($roles->roles);
        foreach ($keys as $roleKey) {
            echo "-----------------------<br/>";
            echo $roles->roles[$roleKey]["name"] . "<br/>";
            echo "caps ($roleKey): ";
            $caps = get_role($roleKey)->capabilities;
            echo "<pre>";
            print_r($caps);
            echo "</pre>";
        }
        //debug infó
        //VESPA_Roles::getInstance() ->debug();
    }
}
function vespa_menu_import_athletes()
{
    vespa_load_template('import_athletes.php');
}

function vespa_menu_results()
{
    if (current_user_can(VESPA_Roles::eredmenyek_megtekintese)) {
        vespa_load_template('results_dashboard.php');
    }
}
