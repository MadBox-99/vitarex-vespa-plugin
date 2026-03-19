<?php
global $wpdb;

if (!current_user_can(VESPA_Roles::riportalas)) {
  echo 'Nincs megfelelő jogosultságod az oldal megtekintéséhez.';
  exit;
}

$sportok = $wpdb->get_results("SELECT * FROM vespa_sports ORDER BY sport_name");
$verseny_sql = "SELECT DISTINCT vc.*, vce.sport_id AS sport_id FROM vespa_contests AS vc
INNER JOIN vespa_constest_events AS vce ON vce.contest_id=vc.contest_id
WHERE vce.id IN (SELECT DISTINCT contest_event_id FROM vespa_constest_events_results)";
$versenyek = $wpdb->get_results($verseny_sql);
$sportolo_sql = "SELECT DISTINCT va.* FROM vespa_athletes AS va 
INNER JOIN vespa_athlete_entries AS vae ON vae.athlete_id=va.athlete_id
WHERE vae.contest_event_id IN (SELECT DISTINCT contest_event_id FROM vespa_constest_events_results)";
$sportolok = $wpdb->get_results($sportolo_sql);
//A felület a https://eredmenyek.com alapján épül fel
?>
<h1>Verseny eredmények</h1>

<div class="w3-bar w3-black">

  <?php
  foreach ($sportok as $kulcs => $sport) {
    $btn_class = "w3-bar-item w3-button tablink";
    if ($kulcs == 0)
      $btn_class .= " w3-red";
    $tab_id = $sport->sport_id;
    echo "<button type='button' onclick=tabKivalasztas(event,$tab_id) class='$btn_class' >$sport->sport_name</button>";
  }
  ?>
</div>

<?php
// ha nincs a szűrésnek eredménye, akkor nem lesz adathalmaz, az aktuális verseny nevét jelenítjük meg és alatta a nem található eredmény feliratot
foreach ($sportok as $kulcs => $sport) : {
    $div_style = "display:none";
    $tab_id = $sport->sport_id;
    $sport_nev = $sport->sport_name;
    if ($kulcs == 0)
      $div_style = "display:block";
    echo "<div id='$tab_id' class='w3-container w3-border w3-tabarea p-1' style='$div_style'>";
  }
  echo  "<h3>$sport_nev eredmények</h3>";
  $szurt_versenyek = array_filter($versenyek, fn ($aktualis) => $aktualis->sport_id == $tab_id);
  if (!empty($szurt_versenyek)) :
?>
    <table width="100%" border="1px solid black">
      <tr>
        <th class="p-1" width="20%">Versenyek </th>
        <th class="p-1" width="80%">Eredmények</th>
      </tr>
      <tr>
        <td valign='top'>
          <!-- versenyek lista -->
          <table width='100%' id='<?php echo "versenytablazat_$tab_id" ?>'>
            <?php
            foreach ($szurt_versenyek as $kulcs => $aktualis_verseny) {
              $verseny_megnevezes = stripslashes($aktualis_verseny->contest_name);
              echo "<tr><td class='p-2 versenylabel' id='cell_$tab_id-$aktualis_verseny->contest_id' onclick='selectCell($tab_id, $aktualis_verseny->contest_id)'>$verseny_megnevezes</td><tr/>";
              // if ($kulcs == 0)
              //   echo "<script>setTimeout(()=>{selectCell($aktualis_verseny->contest_id,$tab_id);},1500)</script>";
            }
            ?>
          </table>
        </td>
        <!-- Eredmények -->
        <td valign='top' class="py-1">
          <div id='<?php echo "eredmenytablazat_$tab_id" ?>' class='pl-1'>
            Válasszon versenyt a bal oldali listából
          </div>
          <div class="loader" id='<?php echo "loader_$tab_id" ?>' hidden />

        </td>
      </tr>
    </table>
  <?php endif;
  if (empty($szurt_versenyek))
    echo "<div>Nem található $sport_nev verseny eredmény</div>" ?>

  </div>
<?php endforeach ?>

<style>
  .versenylabel {
    cursor: pointer;
  }
</style>
<script>
  function selectCell(tab_id, contest_id) {
    let cell = "cell_" + tab_id + "-" + contest_id;
    // console.log("Cella kiválasztás: ", cell);
    jQuery("#eredmenytablazat_" + tab_id).html("");
    var labelLista = document.getElementById("versenytablazat_" + tab_id).getElementsByClassName("versenylabel");
    for (let label of labelLista) {
      if (label.classList.contains("w3-red"))
        label.classList.remove("w3-red");
    };
    document.getElementById(cell).classList.add("w3-red");
    //default rendezés szerint megy a cella kiválasztása után
    rendezes(tab_id, contest_id, "helyezes", "asc");
  }

  function rendezes(tab_id, contest_id, rendezes_mezo, rendezes_irany) {
    jQuery("#loader_" + tab_id).toggle();
    jQuery.ajax({
      type: "POST",
      dataType: "json",
      url: vitarex_vespa_ajaxurl,
      data: {
        action: "vespa_get_contest_results",
        contest_id: contest_id,
        tab_id: tab_id,
        rendezes_mezo: rendezes_mezo,
        rendezes_irany: rendezes_irany
      },
      success: function(resp) {
        if (resp.success) {
          jQuery("#eredmenytablazat_" + tab_id).html(resp.html);
        }
      },
      complete: function() {
        jQuery("#loader_" + tab_id).toggle();
      }
    });
  }
</script>