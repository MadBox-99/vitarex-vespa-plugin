<h1>Sportoló import</h1>
<?php
global $wpdb;
$is_upload     = false;
$upl_errors    = array();
$import_errors = array();
$imported_num  = 0;
$imported_snum = 0;

//iskola adatok
$sql = 'SELECT * FROM vespa_institutions';
$institutions = $wpdb->get_results($sql);

if (isset($_POST['sbmt']) && isset($_POST['school_id'])) {
    $is_upload = true;

    if (isset($_FILES['file']) && $_FILES['file']['error'] == UPLOAD_ERR_OK) {
        $allowed = array('text/csv');
        if (in_array($_FILES['file']['type'], $allowed)) {

            // process csv file
            $row = 1;
            if (($handle = fopen($_FILES['file']['tmp_name'], "r")) !== FALSE) {
                while (($data = fgetcsv($handle, 1000, ";")) !== FALSE) {
                    $success = false;
                    if ($row == 1) {
                        $row++;
                        continue;
                    }
                    $disid = getDisabilityId($data[12]);
                    if ( $disid->disability_group_id === 1 ) {
                        $import_errors[] = 
                            'A(z) ' . $row . 
                            '. sorban lévő sportoló fogyatékossági típusa nem szerepel az adatbázisban (' 
                            . $data[12] . ')';
                    }
                    $gender = mb_strtolower( $data[11], 'UTF-8' );

                    if ( $gender !== 'nő' && $gender !== 'férfi' ) {
                        $import_errors[] = 'A(z) ' . $row .
                            '. sorban lévő sportoló neme helytelen értékű (' . $data[11] .
                            '). A helyes értékek: Nő, Férfi';
                    }


                    if($data[15] == 0)
                    {
                        $data[15] = null;
                    }else
                    {
                        $data[15] = 0;
                    }



                    // process record
                    $tmp = getDuplicates($data[0], $data[1], $data[2],$data[3]);
                    if (count($tmp) > 0) {
                        $import_errors[] = 'A(z) ' . $row . " . sorban lévő sportoló már rögzítésre került ($data[0]).";
                    } elseif(count($import_errors) == 0) {

                        $success = $wpdb->insert('vespa_athletes', array(
                            'school_id' => $_POST['school_id'], //$data[1],
                            'athlete_name'          => $data[0],
                            'birth_place'           => $data[1],
                            'birth_date'            => $data[2],
                            'mothers_name'          => $data[3],
                            'phone'                 => $data[4],
                            'email'                 => $data[5],
                            'home_zipcode'          => $data[6],
                            'home_city'             => $data[7],
                            'home_address'          => $data[8],
                            'nationality'           => $data[9],
                            'personal_id'           => $data[10],
                            'gender'                => $data[11],
                            'disability_type'       => $disid->disability_group_id, 
                            'registered_at'         => $data[13],
                            'note'                  => $data[14],
                            'active'                => $data[15],
                            'modified_at'           => date("Y-m-d H:i:s"),
                            'modified_by'           => get_current_user_id()
                        ));

                        //echo $wpdb->last_query . '<br>';

                    }


                    if ($success) {
                        //MVK_Logger()->log('Önkéntest rögzített (import)', 'voluntarydata', 'voluntary', $wbdb->insert_id, $wpdb->insert_id );
                        $imported_snum++;
                    } else {
                        if ($tmp == 0) {
                            $import_errors[] = 'A(z) ' . $row . ' . sor importálása közben valami hiba történt.';
                        }
                    }

                    $imported_num++;
                    $row++;
                }
                fclose($handle);
            }
        } else {
            $upl_errors[] = 'Nem engedélyezett fájltípus.';
        }
    } else {
        $errcodes = array(
            UPLOAD_ERR_INI_SIZE => 'Túl nagy méretű a fájl',
            UPLOAD_ERR_FORM_SIZE => 'Túl nagy méretű a fájl.',
            UPLOAD_ERR_PARTIAL => 'Sikertelen feltöltés',
            UPLOAD_ERR_NO_FILE => 'Nem lett fájl kiválasztva',
            UPLOAD_ERR_NO_TMP_DIR => 'Hiányzó tmp mappa miatt meghiúsult a feltöltés',
            UPLOAD_ERR_CANT_WRITE => 'Nincs írási jogod a feltöltéshez.',
            UPLOAD_ERR_EXTENSION => 'PHP hiba'
        );

        $upl_errors[] = (isset($errcodes[$_FILES['file']['error']]) ? $errcodes[$_FILES['file']['error']] : 'Ismertlen hiba');
    }
}

function getDisabilityId($disability_name) {
    global $wpdb;
    $row = $wpdb->get_row(
      $wpdb->prepare(
        "SELECT disability_group_id FROM vespa_disability_groups WHERE disability_group_name=%s",
        $disability_name
      )
    );
    if (! $row) {
        return (object)[ 'disability_group_id' => 1 ];
    }
    return $row;
}




function getDuplicates($athlete_name, $birth_place, $birth_date,$mothers_name)
{
    global $wpdb;
    $list = $wpdb->get_results(
        $wpdb->prepare(
            "SELECT * FROM vespa_athletes WHERE athlete_name=%s AND birth_place=%s AND birth_date=%s AND mothers_name=%s",
            $athlete_name, $birth_place, $birth_date, $mothers_name
        )
    );
    return $list;
}
?>


<div id="import">
    <div class="row">
        <?php
        if ($is_upload && count($upl_errors) > 0) :
        ?>
            <div class="col-md-12">
                <p style="color: red; font-size: 16px;">
                    A feltöltés során az alábbi hibák léptek fel: <br>
                    <strong><?php echo implode('<br>', $upl_errors); ?></strong>
                </p>
            </div>
        <?php
        endif;
        ?>

        <?php
        if ($is_upload && count($upl_errors) == 0) :
        ?>

            <div class="col-md-12">
                <p style=" font-size: 16px;">
                    Az import lefutott. <?php echo $imported_num . ' / ' . $imported_snum; ?> rekord importálása sikeres volt.
                </p>

                <?php if (count($import_errors) > 0) : ?>
                    <p style="color: red; font-size: 16px; ">
                        Az alábbi rekordokkal problémák merültek fel az import során: <br>
                        <strong><?php echo implode('<br>', $import_errors); ?></strong>
                    </p>
                <?php endif; ?>
            </div>
        <?php
        endif;
        ?>

        <script>
            function valami(ez) {
                console.log(ez);
                //
            }
        </script>

        <div class="col-md-4">
            <form action="" method="POST" enctype="multipart/form-data">
                <div class="form-group">
                    <label>Iskola / egyesület</label>
                    <select name="school_id" id="school_id" class="form-control input-sm" required>
                        <?php foreach ($institutions as $institution) : ?>
                            <option value="<?php echo $institution->institution_id ?>">
                                <?php echo $institution->ins_name; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group" style="margin-bottom: 20px;">
                    <input type="file" name="file" id="file" required="">
                </div>

                <button type="submit" class="btn btn-sm btn-primary" name="sbmt">
                    <i class="fa fa-upload" aria-hidden="true"></i>
                    SPORTOLÓK IMPORTÁLÁSA
                </button>
                <a href="<?php echo esc_url( $_SERVER['REQUEST_URI'] ); ?>&vespa_athletes_csv_sample=1" class="btn btn-default btn-sm" style="margin-left:10px;">
                    <i class="fa fa-download" aria-hidden="true"></i>
                    Mintafájl letöltése
                </a>
            </form>
        </div>


    </div>
</div>