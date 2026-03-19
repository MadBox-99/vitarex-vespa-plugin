<?php

function vespa_user_has_role($role)
{
    $user = wp_get_current_user();
    if (is_array($role)) {
        foreach ($role as $ro) {
            if (in_array($ro, (array) $user->roles)) {
                return true;
            }
        }
    } else {
        if (in_array($role, (array) $user->roles)) {
            return true;
        }
    }

    return false;
}

class VESPA_Roles extends Singleton
{
    //roles

    const TANULO                      = "tanulo";
    const SPORTOLO                    = "sportolo";
    const TESTNEVELO                  = "testnevelo";
    const ISKOLAIGAZGATO              = "iskolaigazgato";
    const FELETTES_SZERV              = "felettes_szerv";
    const DIAK_SPORTIGAZGATO          = "diak_sportigazgato";
    const FOVESZ_FODISZ_SPORTIGAZGATO = "fovesz_fodisz_sportigazgato";
    const MEGYEI_VERSENYIGAZGATO      = "megyei_versenyigazgato";
    const MEGYEI_VEZETO               = "megyei_vezeto";
    const TANKERULETI_IGAZGATO        = "tankeruleti_igazgato";
    const ADMINISZTRATOR              = "adminisztrator";
    
    private $custom_roles_array = [
        [VESPA_Roles::TANULO, "Tanuló"], [VESPA_Roles::SPORTOLO, "Sportoló"], [VESPA_Roles::TESTNEVELO, "Testnevelő"], [VESPA_Roles::ISKOLAIGAZGATO, "Iskolaigazgató"],
        [VESPA_Roles::FELETTES_SZERV, "Felettes szerv"], [VESPA_Roles::DIAK_SPORTIGAZGATO, "Diák sportigazgató"], [VESPA_Roles::FOVESZ_FODISZ_SPORTIGAZGATO, "FOVESZ/FODISZ sportigazgató"],
        [VESPA_Roles::MEGYEI_VERSENYIGAZGATO, "Versenyigazgató"], [VESPA_Roles::MEGYEI_VEZETO, "Megyei vezető"],
        [VESPA_Roles::TANKERULETI_IGAZGATO, "Tankerületi igazgató"]
    ];

    //caps
    const eredmenyek_megtekintese = "eredmenyek_megtekintese";
    const nevezesek_megtekintese = "nevezesek_megtekintese";
    const tanulok_listazasa = "tanulok_listazasa";
    const tanulok_letrehozasa_modositasa = "tanulok_letrehozasa_modositasa";
    const sportolok_listazasa = "sportolok_listazasa";
    const sportolok_letrehozasa_modositasa = "sportolok_letrehozasa_modositasa";
    const versenyek_megtekintese = "versenyek_megtekintese";
    const sportolot_nevezhet = "sportolot_nevezhet";
    const riportalas = "riportalas";
    const iskolat_nevezhet_versenyre = "iskolat_nevezhet_versenyre";
    const testnevelok_listazasa = "testnevelok_listazasa";
    const testnevelok_letrehozas_modositasa = "testnevelok_letrehozas_modositasa";
    const intezmenyek_listazasa = "intezmenyek_listazasa";
    const versenyigazgatok_kezelese = "versenyigazgatok_kezelese";
    const versenyek_kezelese_kiiras_modositas_torles = "versenyek_kezelese_kiiras_modositas_torles";
    const verseny_eredmenyek_kezelese_rogzites_modositas = "verseny_eredmenyek_kezelese_rogzites_modositas";
    const versenylogisztika_lekerdezes = "versenylogisztika_lekerdezes";

    public function debug()
    {
        print_r($this->custom_roles_array);
    }

    public function init_custom_roles()
    {
        global $wp_roles;

        //if (get_option('custom_roles_version') < 1) {

        foreach ($wp_roles->roles as $key => $role) {
            if ($key != 'administrator') {
                remove_role($key);
            }
            else
            {
                $role = get_role($key);
                $capabilites = $this->get_role_capabilites();

                foreach ($capabilites['wpadmin'] as $cap => $grant) {
                    $role->add_cap($cap, $grant);
                }
            }
        }

        //ROLE hozzáadása (alap caps)
        foreach ($this->custom_roles_array as $role)
            add_role($role[0], $role[1], ["read" => true]);
        $this->set_custom_capabilites($wp_roles);
        // update_option('custom_roles_version', 1);
        //}
    }

    public function set_custom_capabilites($wp_roles)
    {

        $capabilites = $this->get_role_capabilites();

        foreach (array_keys($wp_roles->roles) as $key) {
            $role = get_role($key);
            if (array_key_exists($key, $capabilites))
                foreach ($capabilites[$key] as $cap => $grant) {
                    $role->add_cap($cap, $grant);
                }
        }
    }

    public function get_role_capabilites()
    {
        try {
            $result =
                array(
                    VESPA_Roles::TANULO    => array(),
                    VESPA_Roles::SPORTOLO    => array(

                        VESPA_Roles::eredmenyek_megtekintese  => true,
                        VESPA_Roles::nevezesek_megtekintese        => true
                    ),
                    VESPA_Roles::TESTNEVELO    => array(
                        VESPA_Roles::tanulok_listazasa => true,
                        VESPA_Roles::tanulok_letrehozasa_modositasa => true,
                        VESPA_Roles::sportolok_listazasa => true,
                        VESPA_Roles::sportolok_letrehozasa_modositasa => true,
                        VESPA_Roles::versenyek_megtekintese => true,
                        VESPA_Roles::eredmenyek_megtekintese => true,
                        VESPA_Roles::sportolot_nevezhet => true,
                        VESPA_Roles::intezmenyek_listazasa => true,
                    ),
                    VESPA_Roles::ISKOLAIGAZGATO => array(
                        VESPA_Roles::tanulok_listazasa => true,
                        VESPA_Roles::tanulok_letrehozasa_modositasa => true,
                        VESPA_Roles::versenyek_megtekintese => true,
                        VESPA_Roles::riportalas => true,
                        VESPA_Roles::iskolat_nevezhet_versenyre => true,
                        VESPA_Roles::testnevelok_listazasa => true,
                        VESPA_Roles::testnevelok_letrehozas_modositasa => true,
                        VESPA_Roles::intezmenyek_listazasa => true,
                    ),
                    VESPA_Roles::FELETTES_SZERV    => array(
                        VESPA_Roles::versenyek_megtekintese  => true,
                        VESPA_Roles::riportalas        => true
                    ),
                    VESPA_Roles::DIAK_SPORTIGAZGATO    => array(
                        VESPA_Roles::sportolok_listazasa => true,
                        VESPA_Roles::intezmenyek_listazasa => true,
                        VESPA_Roles::versenyigazgatok_kezelese => true,
                        VESPA_Roles::versenyek_megtekintese => true,
                        VESPA_Roles::versenyek_kezelese_kiiras_modositas_torles => true,
                        VESPA_Roles::verseny_eredmenyek_kezelese_rogzites_modositas => true
                    ),
                    VESPA_Roles::FOVESZ_FODISZ_SPORTIGAZGATO => array(
                        VESPA_Roles::sportolok_listazasa => true,
                        VESPA_Roles::intezmenyek_listazasa => true,
                        VESPA_Roles::versenyigazgatok_kezelese => true,
                        VESPA_Roles::versenyek_megtekintese => true,
                        VESPA_Roles::versenyek_kezelese_kiiras_modositas_torles => true,
                        VESPA_Roles::verseny_eredmenyek_kezelese_rogzites_modositas => true,
                        VESPA_Roles::nevezesek_megtekintese => true,
                        VESPA_Roles::tanulok_listazasa => true,
                        VESPA_Roles::tanulok_letrehozasa_modositasa => true,
                        VESPA_Roles::sportolok_letrehozasa_modositasa => true,
                        VESPA_Roles::sportolot_nevezhet => true,
                        VESPA_Roles::riportalas => true,
                        VESPA_Roles::iskolat_nevezhet_versenyre => true,
                        VESPA_Roles::testnevelok_listazasa => true,
                        VESPA_Roles::testnevelok_letrehozas_modositasa => true,
                        VESPA_Roles::versenylogisztika_lekerdezes => true,
                        VESPA_Roles::eredmenyek_megtekintese => true,
                        'list_users' => true,
                        'edit_users' => true,
                        'create_user' => true,
                        'create_users' => true,
                        'add_users' => true,
                        'promote_users' => true,
                        'remove_users' => true,
                    ),
                    VESPA_Roles::MEGYEI_VERSENYIGAZGATO => array(
                        VESPA_Roles::sportolok_listazasa => true,
                        VESPA_Roles::intezmenyek_listazasa => true,
                        VESPA_Roles::versenyigazgatok_kezelese => true,
                        VESPA_Roles::versenyek_megtekintese => true,
                        VESPA_Roles::versenyek_kezelese_kiiras_modositas_torles => true,
                        VESPA_Roles::verseny_eredmenyek_kezelese_rogzites_modositas => true
                    ),
                    VESPA_Roles::MEGYEI_VEZETO => array(
                        VESPA_Roles::versenyigazgatok_kezelese => true,
                        VESPA_Roles::versenyek_megtekintese => true,
                        VESPA_Roles::versenyek_kezelese_kiiras_modositas_torles => true,
                        VESPA_Roles::versenylogisztika_lekerdezes => true
                    ),
                    VESPA_Roles::TANKERULETI_IGAZGATO    => array(
                        VESPA_Roles::riportalas => true,
                        VESPA_Roles::versenyek_megtekintese        => true
                    ),
                    VESPA_Roles::ADMINISZTRATOR     => array(                       
                    ),
                    'wpadmin' => array(
                        VESPA_Roles::sportolok_listazasa => true,
                        VESPA_Roles::intezmenyek_listazasa => true,
                        VESPA_Roles::versenyigazgatok_kezelese => true,
                        VESPA_Roles::versenyek_megtekintese => true,
                        VESPA_Roles::versenyek_kezelese_kiiras_modositas_torles => true,
                        VESPA_Roles::verseny_eredmenyek_kezelese_rogzites_modositas => true,
                        VESPA_Roles::nevezesek_megtekintese => true,
                        VESPA_Roles::tanulok_listazasa => true,
                        VESPA_Roles::tanulok_letrehozasa_modositasa => true,
                        VESPA_Roles::sportolok_letrehozasa_modositasa => true,
                        VESPA_Roles::sportolot_nevezhet => true,
                        VESPA_Roles::riportalas => true,
                        VESPA_Roles::iskolat_nevezhet_versenyre => true,
                        VESPA_Roles::testnevelok_listazasa => true,
                        VESPA_Roles::testnevelok_letrehozas_modositasa => true,
                        VESPA_Roles::versenylogisztika_lekerdezes => true,
                        VESPA_Roles::eredmenyek_megtekintese => true                        
                    )
                );
            return $result;
        } catch (Exception $ex) {
            clog($ex->getMessage());
            throw $ex;
        }
    }

    public function current_user_has_role( $role ) {
        $user = wp_get_current_user();
        if ( in_array( $role, (array) $user->roles ) ) {
            return true;
        }

        return false;
    }
}

function clog(string $s)
{
    echo "<script>console.log('$s')</script>";
}
