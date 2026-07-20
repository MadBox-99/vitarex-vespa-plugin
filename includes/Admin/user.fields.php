<?php


function new_modify_user_table( $column ) {
    $column['school'] = 'Intézmény';
    return $column;
}

add_filter( 'manage_users_columns', 'new_modify_user_table' );
function new_modify_user_table_row( $val, $column_name, $user_id ) {
    if( $column_name == 'school' ){

        $school_id = get_user_meta( $user_id, 'school_id', true );
        $school = $GLOBALS['VESPA_Institution']->load( $school_id );

        if( ! empty($school) ){
            return $school->ins_name;
        }
        else {
            return $school_id;
        }
    }

    return $val;
}
add_filter('manage_users_custom_column','new_modify_user_table_row', 10, 3 );


//------------------------------
    add_action( 'show_user_profile', 'vespa_extra_user_profile_fields' );
    add_action( 'edit_user_profile', 'vespa_extra_user_profile_fields' );
    add_action( 'user_new_form', 'vespa_extra_user_profile_fields' );

    function vespa_extra_user_profile_fields( $user ) { 
            global $wpdb;    

            // Input hiba esetén ne vesszen el a már kiválasztott intézmény!
            $school_id = isset($_POST['school_id']) ? $_POST['school_id'] : '';
            $school_name = isset($_POST['school_name']) ? $_POST['school_name'] : '';
            $params = [];
            // Új felhasználónál a $user onjektumm még nem létezik!
            if ( isset( $user ) && is_object( $user ) && isset( $user->ID ) ) {
                $school_id = get_user_meta( $user->ID, 'school_id', true );
            }
            $sql = "SELECT * FROM vespa_institutions";
            if( !(VESPA_Roles::getInstance()->current_user_has_role(VESPA_Roles::FOVESZ_FODISZ_SPORTIGAZGATO) || 
                VESPA_Roles::getInstance()->current_user_has_role(VESPA_Roles::ADMINISZTRATOR) ||    
                is_super_admin())
            ){
                $schoolId = get_user_meta( get_current_user_id(), 'school_id', true );
                $sql  = $sql . " WHERE institution_id=%d";
                $params[] = $schoolId;
            }


            $institutions = $wpdb->get_results($wpdb->prepare($sql, ...$params));
            

            $school_list = array();
            foreach ($institutions as $institution){
                if( ! empty($school_id) && $school_id == $institution->institution_id ){
                    $school_name = $institution->ins_name;
                }

                $school_list[] = array(
                    'value' => $institution->institution_id,
                    'label' => $institution->institution_id . ' - ' . $institution->ins_name,
                );
            }            
        ?>
            <h3><?php _e("VESPA információk", "blank"); ?></h3>

            <table class="form-table" id="school" style="display: none;">
            <tr>
                <th><label for="address">Iskola / intézmény: (kötelező)</label></th>
                <td>
                    <input type="hidden" name="school_id" id="school_id" value="<?php echo $school_id; ?>">
                    <input type="text" class="regular-text" name="school_name" id="school_name" value="<?php echo $school_id . ' - ' . $school_name; ?>">
                </td>
            </tr>
            </table>

            <script>
                var institutions =  <?php echo json_encode($school_list) ?>;

                jQuery( document ).ready( function() {
                    jQuery( "#school_name" ).autocomplete({
                        source: institutions,
                        select: function(event, ui){
                            event.preventDefault();
                            
                            jQuery('#school_id').val(ui.item.value);
                            jQuery('#school_name').val(ui.item.label);
                        }
                    });
                });
            </script>            
        <?php 
    }

    add_action( 'user_register', 'vespa_save_extra_user_profile_fields');
    add_action( 'personal_options_update', 'vespa_save_extra_user_profile_fields' );
    add_action( 'edit_user_profile_update', 'vespa_save_extra_user_profile_fields' );
    
    function vespa_save_extra_user_profile_fields( $user_id ) {
        if ( !current_user_can( 'edit_user', $user_id ) ) { 
            return false; 
        }

        if(  isset($_POST['school_id']) && is_numeric($_POST['school_id'] ) && current_user_can(VESPA_Roles::testnevelok_letrehozas_modositasa) ){
            $schoolId = $_POST['school_id'];
            if( !(VESPA_Roles::getInstance()->current_user_has_role(VESPA_Roles::FOVESZ_FODISZ_SPORTIGAZGATO) || 
                VESPA_Roles::getInstance()->current_user_has_role(VESPA_Roles::ADMINISZTRATOR) || 
                is_super_admin())
            ){
                $schoolId = get_user_meta( get_current_user_id(), 'school_id', true );
            }
            update_user_meta( $user_id, 'school_id', $schoolId );
            return;
        }
        return false;
    }



###########
## MEGYE ##
###########


//------------------------------
    add_action( 'show_user_profile', 'vespa_extra_user_profile_fields2' );
    add_action( 'edit_user_profile', 'vespa_extra_user_profile_fields2' );
    add_action( 'user_new_form', 'vespa_extra_user_profile_fields2' );

    function vespa_extra_user_profile_fields2( $user ) { 
            global $wpdb;    

            // Input hiba esetén ne vesszen el a már kiválasztott intézmény!
            $state_id = isset($_POST['state_id']) ? $_POST['state_id'] : '';
            $state_name = isset($_POST['state_name']) ? $_POST['state_name'] : '';

            // Új felhasználónál a $user onjektumm még nem létezik!
            if ( isset( $user ) && is_object( $user ) && isset( $user->ID ) ) {
                $state_id = get_user_meta( $user->ID, 'state_id', true );
            }

            $states = $wpdb->get_results('SELECT * FROM vespa_states');
            
            $state_list = array();
            foreach ($states as $state){
                if( ! empty($state_id) && $state_id == $state->state_id ){
                    $state_name = $state->state_name;
                }

                $state_list[] = array(
                    'value' => $state->state_id,
                    'label' => $state->state_name,
                );
            }            
        ?>
            <table class="form-table" id="state" style="display: none;">
            <tr>
                <th><label for="address">Megye: (kötelező)</label></th>
                <td>
                    <input type="hidden" name="state_id" id="state_id" value="<?php echo $state_id; ?>">
                    <input type="text" class="regular-text" name="state_name" id="state_name" value="<?php echo $state_name; ?>">
                </td>
            </tr>
            </table>

            <script>
                var states = <?php echo json_encode($state_list) ?>;

                jQuery( document ).ready( function() {
                    jQuery( "#state_name" ).autocomplete({
                        source: states,
                        select: function(event, ui){
                            event.preventDefault();
                            
                            jQuery('#state_id').val(ui.item.value);
                            jQuery('#state_name').val(ui.item.label);
                        }
                    });
                });
            </script>
        <?php 
    }

    add_action( 'user_register', 'vespa_save_extra_user_profile_fields2');
    add_action( 'personal_options_update', 'vespa_save_extra_user_profile_fields2' );
    add_action( 'edit_user_profile_update', 'vespa_save_extra_user_profile_fields2' );
    
    function vespa_save_extra_user_profile_fields2( $user_id ) {
        if ( !current_user_can( 'edit_user', $user_id ) ) { 
            return false; 
        }

        if( isset($_POST['state_id']) && is_numeric($_POST['state_id'] )){
            $stateId = $_POST['state_id'];
            if( current_user_can(VESPA_Roles::versenyigazgatok_kezelese) ){
                
                if( !(VESPA_Roles::getInstance()->current_user_has_role(VESPA_Roles::FOVESZ_FODISZ_SPORTIGAZGATO) || 
                    VESPA_Roles::getInstance()->current_user_has_role(VESPA_Roles::ADMINISZTRATOR) || 
                    is_super_admin())
                ){
                    $stateId = get_user_meta( get_current_user_id(), 'state_id', true );
                }
                update_user_meta( $user_id, 'state_id', $stateId );
                return true;
            }
        }
 
        return false;
    }

################
## Tankerület ##
################


//------------------------------
add_action( 'show_user_profile', 'vespa_extra_user_profile_fields3' );
add_action( 'edit_user_profile', 'vespa_extra_user_profile_fields3' );
add_action( 'user_new_form', 'vespa_extra_user_profile_fields3' );

function vespa_extra_user_profile_fields3( $user ) { 
        global $wpdb;    

        // Input hiba esetén ne vesszen el a már kiválasztott intézmény!
        $school_district_id = isset($_POST['school_district_id']) ? $_POST['school_district_id'] : '';
        $school_district_name = isset($_POST['school_district_name']) ? $_POST['school_district_name'] : '';

        // Új felhasználónál a $user onjektumm még nem létezik!
        if ( isset( $user ) && is_object( $user ) && isset( $user->ID ) ) {
            $school_district_id = get_user_meta( $user->ID, 'school_district_id', true );
        }

        $districts = $wpdb->get_results('SELECT * FROM vespa_school_districts');
        
        $district_list = array();
        foreach ($districts as $district){
            if( ! empty($school_district_id) && $school_district_id == $district->school_district_id ){
                $school_district_name = $district->school_district_name;
            }

            $district_list[] = array(
                'value' => $district->school_district_id,
                'label' => $district->school_district_name,
            );
        }         
    ?>
        <table class="form-table" id="district" style="display: none;">
        <tr>
            <th><label for="address">Tankerület: (kötelező)</label></th>
            <td>
                <input type="hidden" name="school_district_id" id="school_district_id" value="<?php echo $school_district_id; ?>">
                <input type="text" class="regular-text" name="school_district_name" id="school_district_name" value="<?php echo $school_district_name; ?>">
            </td>
        </tr>
        </table>

        <script>
            var districts = <?php echo json_encode($district_list) ?>;

            jQuery( document ).ready( function() {
                jQuery( "#school_district_name" ).autocomplete({
                    source: districts,
                    select: function(event, ui){
                        event.preventDefault();
                        
                        jQuery('#school_district_id').val(ui.item.value);
                        jQuery('#school_district_name').val(ui.item.label);
                    }
                });
            });
        </script>
    <?php 
}

add_action( 'user_register', 'vespa_save_extra_user_profile_fields3');
add_action( 'personal_options_update', 'vespa_save_extra_user_profile_fields3' );
add_action( 'edit_user_profile_update', 'vespa_save_extra_user_profile_fields3' );

function vespa_save_extra_user_profile_fields3( $user_id ) {
    if ( !current_user_can( 'edit_user', $user_id ) ) { 
        return false; 
    }
    
    if( isset($_POST['school_district_id']) && is_numeric($_POST['school_district_id'] )){
        $schoolDistrictId = $_POST['school_district_id'];
        update_user_meta( $user_id, 'school_district_id', $schoolDistrictId );
        return true;
    }

    return false;
}

##############
## TELEFON  ##
##############


//------------------------------
add_action( 'show_user_profile', 'vespa_extra_user_profile_fields4' );
add_action( 'edit_user_profile', 'vespa_extra_user_profile_fields4' );
add_action( 'user_new_form', 'vespa_extra_user_profile_fields4' );

function vespa_extra_user_profile_fields4( $user ) {

        // Input hiba esetén ne vesszen el a már beírt szám!
        $phone = isset($_POST['phone']) ? $_POST['phone'] : '';

        // Új felhasználónál a $user objektum még nem létezik!
        if ( isset( $user ) && is_object( $user ) && isset( $user->ID ) ) {
            $phone = get_user_meta( $user->ID, 'phone', true );
        }
    ?>
        <table class="form-table" id="phone_row" style="display: none;">
        <tr>
            <th><label for="phone">Telefonszám: (kötelező)</label></th>
            <td>
                <input type="tel" class="regular-text" name="phone" id="phone" value="<?php echo esc_attr($phone); ?>">
            </td>
        </tr>
        </table>
    <?php
}

add_action( 'user_register', 'vespa_save_extra_user_profile_fields4');
add_action( 'personal_options_update', 'vespa_save_extra_user_profile_fields4' );
add_action( 'edit_user_profile_update', 'vespa_save_extra_user_profile_fields4' );

function vespa_save_extra_user_profile_fields4( $user_id ) {
    if ( !current_user_can( 'edit_user', $user_id ) ) {
        return false;
    }

    // FIGYELEM: itt SZÁNDÉKOSAN nincs extra capability-feltétel (szemben az
    // iskola mentőjével). A testnevelőnek a saját telefonszámát el kell tudnia
    // menteni, különben a validate_extra() örökre kizárná a profiljából.
    if( isset($_POST['phone']) ){
        update_user_meta( $user_id, 'phone', sanitize_text_field( $_POST['phone'] ) );
        return true;
    }

    return false;
}

    ### közös
    add_action( 'user_profile_update_errors', 'validate_extra' );
    function validate_extra(&$errors, $update = null, &$user  = null)
    {
        // A szerepkör forrása NEM lehet pusztán a $_POST['role']: a role legördülő
        // csak a user-new.php-n és a user-edit.php-n létezik. Saját profil
        // mentésekor (profile.php) nincs a POST-ban, ilyenkor a ténylegesen
        // szerkesztett userből olvassuk ki.
        // FIGYELEM: a $user itt stdClass (az edit_user() építi a POST-ból),
        // NEM WP_User -- nincs rajta ->roles tömb, ezért kell a get_userdata().
        $role = '';
        if (isset($_POST['role']) && $_POST['role'] !== '') {
            $role = $_POST['role'];                      // user-new.php / user-edit.php
        } elseif (!empty($user->ID)) {
            $u = get_userdata($user->ID);                // profile.php (saját profil)
            if ($u && !empty($u->roles)) {
                $role = $u->roles[0];
            }
        }

        if(($role == VESPA_Roles::MEGYEI_VEZETO ||
            $role == VESPA_Roles::MEGYEI_VERSENYIGAZGATO) &&
            !(isset($_POST['state_id']) && is_numeric($_POST['state_id'])))
        {
            $errors->add('state', "<strong>Hiba</strong>: Megye megadása kötelező.");
        }

        if(($role == VESPA_Roles::TESTNEVELO ||
            $role == VESPA_Roles::TANULO ||
            $role == VESPA_Roles::ISKOLAIGAZGATO ||
            $role == VESPA_Roles::SPORTOLO) &&
            !(isset($_POST['school_id']) && is_numeric($_POST['school_id'])))
        {
            $errors->add('school', "<strong>Hiba</strong>: Iskola megadása kötelező.");
        }

        if(($role == VESPA_Roles::TANKERULETI_IGAZGATO) &&
            !(isset($_POST['school_district_id']) && is_numeric($_POST['school_district_id'])))
        {
            $errors->add('district', "<strong>Hiba</strong>: Tankerület megadása kötelező.");
        }

        // A telefonszám csak a testnevelőnek kötelező. A meglévő, telefon nélküli
        // testnevelők érintetlenek maradnak: a kényszer csak mentéskor lép életbe.
        if($role == VESPA_Roles::TESTNEVELO &&
            !(isset($_POST['phone']) && vespa_validate_phone($_POST['phone'])))
        {
            $errors->add('phone', "<strong>Hiba</strong>: Telefonszám megadása kötelező, érvényes formátumban.");
        }
    }

    add_action( 'show_user_profile', 'vespa_extra_user_profile_fields_scripts' );
    add_action( 'edit_user_profile', 'vespa_extra_user_profile_fields_scripts' );
    add_action( 'user_new_form', 'vespa_extra_user_profile_fields_scripts' );

    function vespa_extra_user_profile_fields_scripts( $user ) {

        // A saját profil oldalon (profile.php) nincs #role legördülő, ezért a
        // szerepkört a szerkesztett userből olvassuk ki. Enélkül a handleFields()
        // undefined szerepkörrel az else-ágra futna, és kiürítené a rejtett
        // #school_id / #phone mezőket -> a user nem tudná menteni a saját profilját.
        $server_role = '';
        if ( is_object( $user ) && ! empty( $user->roles ) ) {
            $server_role = $user->roles[0];
        }
    ?>
        <script>
            const schoolRoles = ['testnevelo', 'sportolo', 'iskolaigazgato', 'tanulo']
            const stateRoles = ['megyei_vezeto', 'megyei_versenyigazgato']
            const district = ['tankeruleti_igazgato']
            const phoneRoles = ['testnevelo']
            const serverRole = <?php echo json_encode( $server_role ); ?>;
            document.addEventListener('DOMContentLoaded', function() {
                jQuery('#role').on('change', function() {
                    handleFields()
                })
                handleFields()
            }, false);

            function handleFields(){
                // #role a user-new/user-edit oldalon van; a saját profilon (profile.php)
                // nincs, ilyenkor a szerver által átadott szerepkört használjuk.
                const roleField = jQuery('#role')
                const role = roleField.length ? roleField.val() : serverRole

                // A telefon blokk a szerepkör-ágaktól függetlenül kezelhető:
                // csak a testnevelőnél jelenik meg.
                if(phoneRoles.includes(role)){
                    jQuery('#phone_row').show()
                } else {
                    jQuery('#phone_row').hide()
                    jQuery('#phone').val('')
                }

                if(schoolRoles.includes(role)){
                    jQuery('#school').show()
                    jQuery('#state').hide()
                    jQuery('#state_id').val('')
                    jQuery('#state_name').val('')
                    jQuery('#district').hide()
                    jQuery('#school_district_id').val('')
                    jQuery('#school_district_name').val('')
                } else if(stateRoles.includes(role)){
                    jQuery('#state').show()
                    jQuery('#school').hide()
                    jQuery('#school_id').val('')
                    jQuery('#school_name').val('')
                    jQuery('#district').hide()
                    jQuery('#school_district_id').val('')
                    jQuery('#school_district_name').val('')
                } else if(district.includes(role)){
                    jQuery('#district').show()
                    jQuery('#school').hide()
                    jQuery('#school_id').val('')
                    jQuery('#school_name').val('')
                    jQuery('#state').hide()
                    jQuery('#state_id').val('')
                    jQuery('#state_name').val('')
                } 
                else{
                    jQuery('#school').hide()
                    jQuery('#school_id').val('')
                    jQuery('#school_name').val('')
                    jQuery('#state').hide()
                    jQuery('#state_id').val('')
                    jQuery('#state_name').val('')
                    jQuery('#district').hide()
                    jQuery('#school_district_id').val('')
                    jQuery('#school_district_name').val('')
                }
            }
        </script>
    <?php 
}