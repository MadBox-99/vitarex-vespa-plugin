# Bővített szűrés az eredménylistákban (A2) — Implementációs terv

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** A három eredménylista-nézet szűrésének bővítése gyermek-/eredmény- és sportág–versenyszám-szűrőkkel, a meglévő mechanizmusokba építve.

**Architecture:** A fő „Verseny eredmények" nézet szerveroldali szűrő-paramétereket kap a meglévő AJAX-ban (a rendezés-mechanizmus mintájára), a JSON-blob érintetlen marad. A riportban a (jelenleg holt) sportág-szűrő élővé válik, mellé versenyszám-szűrő kerül két backend-függvényben. A rögzítő rács kliensoldali JS-szűrést kap. Nincs séma-migráció.

**Tech Stack:** PHP (WordPress plugin, `$wpdb`), jQuery, Vue 3, PhpSpreadsheet (riport-export).

## Global Constraints

- Minden UI-szöveg és kód-komment **magyar**.
- A táblák neve `$wpdb->prefix` NÉLKÜL használandó (kódbázis-konvenció): `vespa_athletes`, `vespa_sports`, `vespa_sport_events`, `vespa_constest_events`, `vespa_constest_events_results`.
- `gender` kanonikus tárolása kisbetűs: `'férfi'` / `'nő'`.
- Minden változót tartalmazó SQL `$wpdb->prepare`-en át: egészek `%d`, sztringek `%s`; `LIKE` esetén `$wpdb->esc_like` + wildcard.
- Minden **új** kimenet `esc_html` (szöveg) / `esc_attr` (attribútum) escape-pel. (A meglévő, nem escapelt kimenetet nem írjuk át — az A2 hatókörén kívül.)
- Jogosultság: a fő nézet és az AJAX gate-je `VESPA_Roles::riportalas` (a `results_dashboard.php` oldal is ezt használja); a riport gate-je `VESPA_Roles::riportalas`.
- `contest_type` értékek: 1=országos, 2=regionális, 3=megyei, 4=szabadidősport (`VespaContestType` konstansok léteznek).
- **Nincs automata teszt-suite.** A tesztelés a projekt konvenciója szerint: `php -l` szintaxis-ellenőrzés + `grep` állítások + manuális böngésző-forgatókönyv. Minden task „teszt" lépései ezt jelentik.
- Gyakori commitok, taskonként legalább egy.

---

### Task 1: Fő nézet — szerveroldali szűrés a `vespa_get_contest_results` AJAX-ban

**Files:**
- Modify: `includes/Ajax/ajax.contest_results.php:57-176` (a `vespa_get_contest_results` függvény)

**Interfaces:**
- Consumes: `$_POST` — a meglévő `contest_id`, `tab_id`, `rendezes_mezo`, `rendezes_irany`, és ÚJ: `sport_event_id` (int, 0/üres=mind), `athlete_name` (string), `van_eredmeny` (''/'1'/'0'), `helyezes_tol` (int/üres), `helyezes_ig` (int/üres), `eredmeny_kereses` (string).
- Produces: a `wp_send_json(array("success"=>true, "html"=>...))` válasz HTML-je mostantól egy **szűrő-sorral** kezdődik (a Task 2 JS ezeket az input-osztályokat olvassa), majd a szűrt eredmény-tábla következik. Input-osztályok a `#eredmenytablazat_<tab_id>` konténerben: `.vespa-szuro-versenyszam`, `.vespa-szuro-nev`, `.vespa-szuro-eredmeny-letezik`, `.vespa-szuro-helyezes-tol`, `.vespa-szuro-helyezes-ig`, `.vespa-szuro-eredmeny`, rejtett `.vespa-szuro-rendezes-mezo`, `.vespa-szuro-rendezes-irany`. A szűrő-vezérlők `vespaSzures(<tab_id>,<contest_id>)` / `vespaSzuroTorles(<tab_id>,<contest_id>)` függvényeket hívnak (Task 2 definiálja).

- [ ] **Step 1: Jogosultság-ellenőrzés hozzáadása a függvény elejére**

A `vespa_get_contest_results` első sora (a `global $wpdb;` UTÁN, jelenleg 59. sor) elé:

```php
    if (!current_user_can(VESPA_Roles::riportalas)) {
        wp_send_json_error(array("message" => "Jogosulatlan hozzáférés"), 403);
    }
```

- [ ] **Step 2: Új szűrő-paraméterek beolvasása**

A meglévő `$rendezes_irany`-beolvasás blokk (jelenleg 60-67. sor) UTÁN illeszd be:

```php
    // A2 szűrők. Üres/0 = nincs szűrés az adott mezőre.
    $szuro_sport_event_id = isset($_POST["sport_event_id"]) && is_numeric($_POST["sport_event_id"]) ? intval($_POST["sport_event_id"]) : 0;
    $szuro_nev            = isset($_POST["athlete_name"]) ? trim(wp_unslash($_POST["athlete_name"])) : '';
    $szuro_van_eredmeny   = isset($_POST["van_eredmeny"]) ? $_POST["van_eredmeny"] : '';
    $szuro_helyezes_tol   = isset($_POST["helyezes_tol"]) && $_POST["helyezes_tol"] !== '' && is_numeric($_POST["helyezes_tol"]) ? intval($_POST["helyezes_tol"]) : null;
    $szuro_helyezes_ig    = isset($_POST["helyezes_ig"]) && $_POST["helyezes_ig"] !== '' && is_numeric($_POST["helyezes_ig"]) ? intval($_POST["helyezes_ig"]) : null;
    $szuro_eredmeny       = isset($_POST["eredmeny_kereses"]) ? trim(wp_unslash($_POST["eredmeny_kereses"])) : '';
```

- [ ] **Step 3: A SELECT bővítése a versenyszám-azonosítóval**

A `$event_sql` első SELECT-sorába (jelenleg 68-69. sor) vedd fel a `vce.event_id`-t `sport_event_id` néven. A `$event_sql` új eleje:

```php
    $event_sql = "SELECT vcer.*, vce.event_id AS sport_event_id, vse.sport_event_name AS sport_event_name, vse.result_type as sport_event_result_type,
    vs.result_type as sport_result_type
    FROM vespa_constest_events_results AS vcer
    INNER JOIN vespa_constest_events AS vce ON vce.id=vcer.contest_event_id
    LEFT JOIN vespa_sport_events AS vse ON vse.sport_event_id=vce.event_id
    LEFT JOIN vespa_sports AS vs ON vs.sport_id=vce.sport_id
    WHERE vcer.contest_id=%d";
```

- [ ] **Step 4: Versenyszám-legördülő opciók + szűrő-sor HTML felépítése**

A `$sportolok = $wpdb->get_results(...)` sor (jelenleg 89.) UTÁN, a `$html = "<table width='100%'>";` sor (jelenleg 92.) ELÉ illeszd be a szűrő-sor felépítését. A versenyszám-opciók a teljes (szűretlen) `$event_results`-ból épülnek, hogy a legördülő mindig teljes maradjon:

```php
    // Versenyszám-legördülő opciói a verseny összes eseményéből (szűrés előtti halmaz).
    $versenyszam_opciok = array();
    foreach ($event_results as $er) {
        if (!empty($er->sport_event_id) && !isset($versenyszam_opciok[$er->sport_event_id])) {
            $versenyszam_opciok[$er->sport_event_id] = $er->sport_event_name;
        }
    }

    // Szűrő-sor. A vezérlők a Task 2 JS-függvényeit hívják; az értékek a válaszban
    // megőrződnek (esc_attr), így szűrés+rendezés után is láthatóak maradnak.
    $szuro_html  = "<div class='vespa-eredmeny-szuro' style='margin-bottom:8px;'>";
    $szuro_html .= "<select class='vespa-szuro-versenyszam' onchange='vespaSzures($tab_id,$contest_id)'>";
    $szuro_html .= "<option value=''>Összes versenyszám</option>";
    foreach ($versenyszam_opciok as $ev_id => $ev_nev) {
        $kivalasztva = ($szuro_sport_event_id === intval($ev_id)) ? " selected" : "";
        $szuro_html .= "<option value='" . esc_attr($ev_id) . "'$kivalasztva>" . esc_html($ev_nev) . "</option>";
    }
    $szuro_html .= "</select> ";
    $szuro_html .= "<input type='text' class='vespa-szuro-nev' placeholder='Gyermek neve' value='" . esc_attr($szuro_nev) . "'> ";
    $szuro_html .= "<select class='vespa-szuro-eredmeny-letezik'>";
    $szuro_html .= "<option value=''" . ($szuro_van_eredmeny === '' ? ' selected' : '') . ">Van-e eredmény: mind</option>";
    $szuro_html .= "<option value='1'" . ($szuro_van_eredmeny === '1' ? ' selected' : '') . ">Van eredmény</option>";
    $szuro_html .= "<option value='0'" . ($szuro_van_eredmeny === '0' ? ' selected' : '') . ">Nincs eredmény</option>";
    $szuro_html .= "</select> ";
    $szuro_html .= "<input type='number' class='vespa-szuro-helyezes-tol' placeholder='Helyezés-tól' style='width:110px' value='" . esc_attr($szuro_helyezes_tol === null ? '' : $szuro_helyezes_tol) . "'> ";
    $szuro_html .= "<input type='number' class='vespa-szuro-helyezes-ig' placeholder='Helyezés-ig' style='width:110px' value='" . esc_attr($szuro_helyezes_ig === null ? '' : $szuro_helyezes_ig) . "'> ";
    $szuro_html .= "<input type='text' class='vespa-szuro-eredmeny' placeholder='Eredmény' value='" . esc_attr($szuro_eredmeny) . "'> ";
    $szuro_html .= "<button type='button' onclick='vespaSzures($tab_id,$contest_id)'>Szűrés</button> ";
    $szuro_html .= "<button type='button' onclick='vespaSzuroTorles($tab_id,$contest_id)'>Szűrők törlése</button>";
    $szuro_html .= "<input type='hidden' class='vespa-szuro-rendezes-mezo' value='" . esc_attr($rendezes_mezo) . "'>";
    $szuro_html .= "<input type='hidden' class='vespa-szuro-rendezes-irany' value='" . esc_attr($rendezes_irany) . "'>";
    $szuro_html .= "</div>";
```

- [ ] **Step 5: A render-ciklus átalakítása szűréssel + üres állapottal**

Cseréld le a teljes eredmény-építő blokkot — a jelenlegi `$html = "<table width='100%'>";` sortól (92.) a `$html .= "</table>";` (173.) és a `wp_send_json(...)` (174.) sorig — az alábbira:

```php
    $tabla_html = "<table width='100%'>";
    $volt_talalat = false;
    foreach ($event_results as $event_result) {
        // Versenyszám-szűrő: csak a kiválasztott esemény-blokk marad.
        if ($szuro_sport_event_id > 0 && intval($event_result->sport_event_id) !== $szuro_sport_event_id) {
            continue;
        }
        $eredmenyek = json_decode($event_result->result);
        if (!is_array($eredmenyek)) {
            continue;
        }

        // Soronkénti szűrés (név / van-e eredmény / helyezés-tartomány / eredmény-szöveg).
        $szurt = array();
        foreach ($eredmenyek as $eredmeny) {
            $sportolo = $sportolok[array_search($eredmeny->athlete_id, array_column($sportolok, 'athlete_id'))];

            if ($szuro_nev !== '') {
                $nev = isset($sportolo->athlete_name) ? $sportolo->athlete_name : '';
                if (mb_stripos($nev, $szuro_nev) === false) {
                    continue;
                }
            }
            $van_helyezes = isset($eredmeny->helyezes) && $eredmeny->helyezes !== '' && $eredmeny->helyezes !== null;
            $van_ertek    = isset($eredmeny->eredmeny) && $eredmeny->eredmeny !== '' && $eredmeny->eredmeny !== null;
            if ($szuro_van_eredmeny === '1' && !($van_helyezes || $van_ertek)) {
                continue;
            }
            if ($szuro_van_eredmeny === '0' && ($van_helyezes || $van_ertek)) {
                continue;
            }
            if ($szuro_helyezes_tol !== null || $szuro_helyezes_ig !== null) {
                if (!$van_helyezes || !is_numeric($eredmeny->helyezes)) {
                    continue;
                }
                $h = intval($eredmeny->helyezes);
                if ($szuro_helyezes_tol !== null && $h < $szuro_helyezes_tol) {
                    continue;
                }
                if ($szuro_helyezes_ig !== null && $h > $szuro_helyezes_ig) {
                    continue;
                }
            }
            if ($szuro_eredmeny !== '') {
                $ertek = isset($eredmeny->eredmeny) ? (string) $eredmeny->eredmeny : '';
                if (mb_stripos($ertek, $szuro_eredmeny) === false) {
                    continue;
                }
            }
            $szurt[] = $eredmeny;
        }

        if (empty($szurt)) {
            continue; // üres blokk fejlécét nem írjuk ki
        }
        $volt_talalat = true;
        $eredmenyek = $szurt;

        usort($eredmenyek, function ($eredmeny1, $eredmeny2) use ($rendezes_mezo, $rendezes_irany, $sportolok) {
            //ha egyedi rendezés kell
            $sportolo1 = $sportolok[array_search($eredmeny1->athlete_id, array_column($sportolok, 'athlete_id'))];
            $sportolo2 = $sportolok[array_search($eredmeny2->athlete_id, array_column($sportolok, 'athlete_id'))];
            if ($rendezes_irany == "desc") {
                $tmp = $sportolo1;
                $sportolo1 = $sportolo2;
                $sportolo2 = $tmp;
            }
            switch ($rendezes_mezo) {
                case 'athlete_name':
                    return strcmp($sportolo1->athlete_name, $sportolo2->athlete_name);
                case 'birth_date':
                    return strcmp($sportolo1->birth_date, $sportolo2->birth_date);
                case 'ins_name':
                    return strcmp($sportolo1->ins_name, $sportolo2->ins_name);
                case 'ins_state':
                    return strcmp($sportolo1->ins_state, $sportolo2->ins_state);
                case 'helyezes':
                    $helyezes1 = $eredmeny1->helyezes;
                    $helyezes2 = $eredmeny2->helyezes;
                    if ($rendezes_irany == "desc") {
                        $tmp = $helyezes1;
                        $helyezes1 = $helyezes2;
                        $helyezes2 = $tmp;
                    }
                    if (
                        !empty($helyezes1) && !empty($helyezes2)
                        && is_numeric($helyezes1 && is_numeric($helyezes2))
                    )
                        return intval($helyezes1) - intval($helyezes2);
                    return strcmp($helyezes1, $helyezes2);
                case 'eredmeny':
                    $eredmeny1 = $eredmeny1->eredmeny;
                    $eredmeny2 = $eredmeny2->eredmeny;
                    if ($rendezes_irany == "desc") {
                        $tmp = $eredmeny1;
                        $eredmeny1 = $eredmeny2;
                        $eredmeny2 = $tmp;
                    }
                    if (
                        !empty($eredmeny1) && !empty($eredmeny2)
                        && is_numeric($eredmeny1 && is_numeric($eredmeny2))
                    )
                        return intval($eredmeny1) - intval($eredmeny2);
                    return strcmp($eredmeny1, $eredmeny2);
            }
        });
        //rendezett output
        $tabla_html .= "<tr><td colspan=6><h4 class='pl-1'>$event_result->sport_event_name</h4></td></tr>";
        $mertekegyseg = !empty($event_result->sport_event_result_type) ? $event_result->sport_event_result_type : $event_result->sport_result_type;
        $eredmeny_tipus = mertekegyseg_ertek($mertekegyseg);
        $tabla_html .= "<tr>
                <th width='7%' style='cursor: pointer;' onclick=rendezes($tab_id,$contest_id," . rendezes_irany_kalkulacio($rendezes_mezo, 'helyezes', $rendezes_irany, 'Helyezés') . "</th>
                <th width='28%' style='cursor: pointer;' class='pl-1' onclick=rendezes($tab_id,$contest_id," . rendezes_irany_kalkulacio($rendezes_mezo, 'athlete_name', $rendezes_irany, 'Név') . "</th>
                <th width='10%' style='cursor: pointer;' onclick=rendezes($tab_id,$contest_id," . rendezes_irany_kalkulacio($rendezes_mezo, 'birth_date', $rendezes_irany, 'Születési dátum') . "</th>
                <th width='20%' style='cursor: pointer;' onclick=rendezes($tab_id,$contest_id," . rendezes_irany_kalkulacio($rendezes_mezo, 'ins_name', $rendezes_irany, 'Intézmény') . "</th>
                <th width='15%' style='cursor: pointer;' onclick=rendezes($tab_id,$contest_id," . rendezes_irany_kalkulacio($rendezes_mezo, 'ins_state', $rendezes_irany, 'Megye') . "</th>";

        if ($mertekegyseg != 'helyezes') {
            $felirat = "Eredmény ($eredmeny_tipus)";
            $tabla_html .= "<th width='20%' onclick=rendezes($tab_id,$contest_id," . rendezes_irany_kalkulacio($rendezes_mezo, 'eredmeny', $rendezes_irany, $felirat) . "</th>";
        }
        $tabla_html .= "</tr>";
        foreach ($eredmenyek as $kulcs => $eredmeny) :
            $sportolo = $sportolok[array_search($eredmeny->athlete_id, array_column($sportolok, 'athlete_id'))];
            $tabla_html .= "<tr style='border-top: thin solid; border-bottom: thin solid;'><td class='pl-2'>$eredmeny->helyezes</td>
            <td class='pl-1'>$sportolo->athlete_name</td>
            <td>$sportolo->birth_date</td>
            <td>$sportolo->ins_name</td>
            <td>$sportolo->state_name";

            if ($mertekegyseg != 'helyezes')
                $tabla_html .= "<td>$eredmeny->eredmeny</td>";
            $tabla_html .= "</tr>";
        endforeach;
        $tabla_html .= "<tr/>";
    }
    $tabla_html .= "</table>";

    if (!$volt_talalat) {
        $tabla_html = "<div class='pl-1'>Nincs a szűrőknek megfelelő eredmény.</div>";
    }

    wp_send_json(array("success" => true, "html" => $szuro_html . $tabla_html));
```

- [ ] **Step 6: Szintaxis-ellenőrzés**

Run: `php -l includes/Ajax/ajax.contest_results.php`
Expected: `No syntax errors detected`

- [ ] **Step 7: Grep-ellenőrzés — a szűrők és a biztonsági elemek jelen vannak**

Run: `grep -n "szuro_sport_event_id\|szuro_nev\|van_eredmeny\|helyezes_tol\|eredmeny_kereses\|current_user_can(VESPA_Roles::riportalas)\|Nincs a szűrőknek megfelelő" includes/Ajax/ajax.contest_results.php`
Expected: mindegyik minta legalább egyszer megjelenik; az `esc_attr`/`esc_html` a szűrő-sorban használva van (`grep -n "esc_attr\|esc_html" includes/Ajax/ajax.contest_results.php`).

- [ ] **Step 8: Commit**

```bash
git add includes/Ajax/ajax.contest_results.php
git commit -m "feat(A2): szerveroldali szűrők a verseny eredmények AJAX-ban"
```

---

### Task 2: Fő nézet — JS-bekötés a `results_dashboard.php`-ban

**Files:**
- Modify: `templates/results_dashboard.php:90-128` (a `<script>` blokk)

**Interfaces:**
- Consumes: a Task 1 által renderelt szűrő-sor input-osztályai a `#eredmenytablazat_<tab_id>` konténerben.
- Produces: globális JS-függvények: `vespaEredmenyBetolt(tab_id, contest_id, rendezes_mezo, rendezes_irany)`, `rendezes(...)` (ennek aliasa), `vespaSzures(tab_id, contest_id)`, `vespaSzuroTorles(tab_id, contest_id)`. A `selectCell` továbbra is `rendezes(...)`-t hív.

- [ ] **Step 1: A `<script>` blokk cseréje**

Cseréld le a teljes `<script> ... </script>` blokkot (jelenleg 90-128. sor) az alábbira. A `selectCell` változatlan; a `rendezes` mostantól egy közös betöltő köré szervezve begyűjti a szűrőket, és két új függvény kezeli a szűrés-gombokat:

```html
<script>
  function selectCell(tab_id, contest_id) {
    let cell = "cell_" + tab_id + "-" + contest_id;
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

  // Közös eredmény-betöltő: begyűjti az aktuális szűrő-értékeket a válaszban
  // renderelt szűrő-sorból (első betöltéskor a sor még nincs kint -> üres értékek).
  function vespaEredmenyBetolt(tab_id, contest_id, rendezes_mezo, rendezes_irany) {
    let kont = "#eredmenytablazat_" + tab_id + " ";
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
        rendezes_irany: rendezes_irany,
        sport_event_id: jQuery(kont + ".vespa-szuro-versenyszam").val() || "",
        athlete_name: jQuery(kont + ".vespa-szuro-nev").val() || "",
        van_eredmeny: jQuery(kont + ".vespa-szuro-eredmeny-letezik").val() || "",
        helyezes_tol: jQuery(kont + ".vespa-szuro-helyezes-tol").val() || "",
        helyezes_ig: jQuery(kont + ".vespa-szuro-helyezes-ig").val() || "",
        eredmeny_kereses: jQuery(kont + ".vespa-szuro-eredmeny").val() || ""
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

  // Oszlopfejléc-rendezés: megőrzi az aktuális szűrőket.
  function rendezes(tab_id, contest_id, rendezes_mezo, rendezes_irany) {
    vespaEredmenyBetolt(tab_id, contest_id, rendezes_mezo, rendezes_irany);
  }

  // "Szűrés" gomb / versenyszám-váltás: megőrzi az aktuális rendezést.
  function vespaSzures(tab_id, contest_id) {
    let kont = "#eredmenytablazat_" + tab_id + " ";
    let mezo = jQuery(kont + ".vespa-szuro-rendezes-mezo").val() || "helyezes";
    let irany = jQuery(kont + ".vespa-szuro-rendezes-irany").val() || "asc";
    vespaEredmenyBetolt(tab_id, contest_id, mezo, irany);
  }

  // "Szűrők törlése": kiüríti a mezőket, majd alap rendezéssel újratölt.
  function vespaSzuroTorles(tab_id, contest_id) {
    let kont = "#eredmenytablazat_" + tab_id + " ";
    jQuery(kont + ".vespa-szuro-versenyszam").val("");
    jQuery(kont + ".vespa-szuro-nev").val("");
    jQuery(kont + ".vespa-szuro-eredmeny-letezik").val("");
    jQuery(kont + ".vespa-szuro-helyezes-tol").val("");
    jQuery(kont + ".vespa-szuro-helyezes-ig").val("");
    jQuery(kont + ".vespa-szuro-eredmeny").val("");
    vespaEredmenyBetolt(tab_id, contest_id, "helyezes", "asc");
  }
</script>
```

- [ ] **Step 2: Grep-ellenőrzés**

Run: `grep -n "vespaEredmenyBetolt\|vespaSzures\|vespaSzuroTorles\|sport_event_id\|van_eredmeny" templates/results_dashboard.php`
Expected: mindegyik függvény és paraméter megjelenik.

- [ ] **Step 3: Manuális böngésző-teszt**

1. Nyisd meg a **Verseny eredmények** oldalt, válassz sportág-fület és versenyt → betölt a szűrő-sor + eredmények.
2. **Versenyszám** legördülő → egy blokkra szűkül; „Összes versenyszám" → vissza.
3. **Gyermek neve** részszöveg + „Szűrés" → csak az egyező sorok.
4. **Van-e eredmény = Nincs eredmény** → csak eredmény nélküli sorok.
5. **Helyezés-tól 1, -ig 3** → dobogósok.
6. **Eredmény** részszöveg (pl. `14`) → egyező eredmények.
7. Oszlopfejléc-kattintás (rendezés) a szűrő megtartásával rendez.
8. **Szűrők törlése** → minden visszaáll, teljes lista.
9. Olyan szűrő, aminek nincs találata → „Nincs a szűrőknek megfelelő eredmény.".

- [ ] **Step 4: Commit**

```bash
git add templates/results_dashboard.php
git commit -m "feat(A2): szűrő-sor bekötése a verseny eredmények nézetben"
```

---

### Task 3: Riport — Vue-frontend: versenyszám-legördülő + sportág-adat

**Files:**
- Modify: `templates/riports_dashboard.php` (PHP adatbetöltés 33. sor környéke; select-blokk 130-139. sor után; Vue `data`/`computed`/`watch`/`getSubmitUri`)

**Interfaces:**
- Consumes: `vespa_sport_events` tábla (`sport_event_id`, `sport_id`, `sport_event_name`).
- Produces: a GET-lekérdezésbe `&sport=<sport_id>&sportEventId=<sport_event_id>` kerül a három sportág-mutató riportnál (`legnepszerubb_sportag`, `iskola_sportoltatott_diakok`, `tanev_diakolimpia_diakok`). A backend (Task 4) ezeket olvassa.

- [ ] **Step 1: Versenyszám-adat betöltése PHP-ban**

A `$sports = $wpdb->get_results("SELECT * FROM vespa_sports WHERE is_deleted=0");` sor (jelenleg 33.) UTÁN illeszd be:

```php
$sport_events = $wpdb->get_results("SELECT * FROM vespa_sport_events WHERE is_deleted=0");
```

- [ ] **Step 2: Versenyszám select-blokk hozzáadása a Sport-blokk után**

A Sport select-blokk (`<div class="col-md-4" v-if="showedInputs.sport">` ... `</div>`, jelenleg 130-139. sor) UTÁN illeszd be:

```html
        <div class="col-md-4" v-if="showedInputs.sportEvent">
            <div class="form-group">
                <label>Versenyszám</label>
                <select name="sportEvent" id="sportEvent" class="form-control input-sm" v-model="selectedRiportData.sportEvent" :disabled="!selectedRiportData.sport">
                        <option v-for="item in getSportEventList" :value="item.sport_event_id">
                            {{item.sport_event_name}}
                        </option>
                </select>
            </div>
        </div>
```

- [ ] **Step 3: `sportEvent` felvétele a Vue állapotba**

A `data()` return-jében a `defaultShowState`-hez add hozzá a `sportEvent: 0`-t, a `defaultRiportState`-hez a `sportEvent: 0`-t, és vedd fel a `sportEvents` adatot. A módosított kulcsok (a többi mező változatlan):

```js
                defaultShowState: {filter: 0, state: 0, schoolDistrict: 0, institution: 0, disabilityGroup: 0, gender: 0, interval: 0, series: 0, sport: 0, sportEvent: 0, year: 0},
                defaultRiportState: {
                    filter: 'all',
                    schoolDistrict: 0, 
                    institutionId: 0, 
                    disabilityGroupId: 0,
                    series: 0,
                    dateFrom: new Date( new Date().getFullYear(), 0, 2).toISOString().substr(0, 10),
                    dateTo: new Date( new Date().getFullYear(), 11, 32).toISOString().substr(0, 10),
                    gender: 'összes',
                    sport: 0,
                    sportEvent: 0,
                    year: 0
                },
                series: <?php echo json_encode($series) ?>,
                sports: <?php echo json_encode($sports) ?>,
                sportEvents: <?php echo json_encode($sport_events) ?>,
                baseUrl: "<?php echo home_url('/'); ?>",
                gender: <?php echo json_encode($gender); ?>
```

- [ ] **Step 4: `getSportEventList` computed hozzáadása**

A `getSportList()` computed (jelenleg 264-266. sor) UTÁN illeszd be:

```js
            getSportEventList() {
                const all = [{sport_event_id: 0, sport_event_name: 'Összes versenyszám'}]
                if (!this.selectedRiportData.sport) return all
                return [...all, ...this.sportEvents.filter(e => e.sport_id == this.selectedRiportData.sport)]
            },
```

- [ ] **Step 5: Sportág-váltáskor a versenyszám nullázása**

A `watch` objektumban a `selectedRiportType(newType)` metódus UTÁN (vessző után) illeszd be:

```js
            'selectedRiportData.sport'() {
                this.selectedRiportData.sportEvent = 0
            }
```

- [ ] **Step 6: `sportEvent: 1` a három sportág-mutató riport show-állapotába**

A `watch.selectedRiportType` switch-ében a következő három ághoz add hozzá a `sportEvent: 1`-et (a `sport: 1` mellé):

```js
                    case 'legnepszerubb_sportag':
                        this.showedInputs = {...this.defaultShowState, filter: 1, interval: 1, gender: 1, disabilityGroup: 1, schoolDistrict: 1, institution: 1, sport: 1, sportEvent: 1}
                        break;
                    case 'iskola_sportoltatott_diakok':
                        this.showedInputs = {...this.defaultShowState, filter: 1, interval: 1, gender: 1, disabilityGroup: 1, schoolDistrict: 1, institution: 1, sport: 1, sportEvent: 1}
                        break;
                    case 'tanev_diakolimpia_diakok':
                        this.showedInputs = {...this.defaultShowState, filter: 1, series: 1, gender: 1, disabilityGroup: 1, schoolDistrict: 1, institution: 1, sport: 1, sportEvent: 1}
                        break;
```

- [ ] **Step 7: `getSubmitUri` — sport + versenyszám param a három riporthoz**

A `getSubmitUri` switch-ében válaszd külön a `legnepszerubb_sportag` és `iskola_sportoltatott_diakok` ágakat a jelenlegi csoportosított ágaikból, és tedd be a `sport`+`sportEventId` paramétert. A `tanev_diakolimpia_diakok` ágat egészítsd ki. A módosított ágak:

```js
                    case 'verseny_versenyszam':
                        return `${baseUrl}&dateFrom=${this.selectedRiportData.dateFrom}&dateTo=${this.selectedRiportData.dateTo}`          
                    case 'legnepszerubb_sportag':
                        return `${baseUrl}&dateFrom=${this.selectedRiportData.dateFrom}&dateTo=${this.selectedRiportData.dateTo}&sport=${this.selectedRiportData.sport}&sportEventId=${this.selectedRiportData.sportEvent}`
                    case 'verseny_diak':
                        return `${baseUrl}&dateFrom=${this.selectedRiportData.dateFrom}&dateTo=${this.selectedRiportData.dateTo}&schoolDistrict=${this.selectedRiportData.schoolDistrict}&institutionId=${this.selectedRiportData.institutionId}&disabilityGroupId=${this.selectedRiportData.disabilityGroupId}&gender=${this.selectedRiportData.gender}`
                    case 'iskola_sportoltatott_diakok':
                        return `${baseUrl}&dateFrom=${this.selectedRiportData.dateFrom}&dateTo=${this.selectedRiportData.dateTo}&schoolDistrict=${this.selectedRiportData.schoolDistrict}&institutionId=${this.selectedRiportData.institutionId}&disabilityGroupId=${this.selectedRiportData.disabilityGroupId}&gender=${this.selectedRiportData.gender}&sport=${this.selectedRiportData.sport}&sportEventId=${this.selectedRiportData.sportEvent}`
                    case 'tanev_diakolimpia_diakok':
                        return `${baseUrl}&series=${this.selectedRiportData.series}&schoolDistrict=${this.selectedRiportData.schoolDistrict}&institutionId=${this.selectedRiportData.institutionId}&disabilityGroupId=${this.selectedRiportData.disabilityGroupId}&gender=${this.selectedRiportData.gender}&sport=${this.selectedRiportData.sport}&sportEventId=${this.selectedRiportData.sportEvent}`        
```

**Fontos:** a jelenlegi kód a `verseny_versenyszam` és `legnepszerubb_sportag` ágakat, illetve a `verseny_diak` és `iskola_sportoltatott_diakok` ágakat egy közös `return`-nel kezeli (fall-through). A cél az, hogy mindegyik típus a fenti, saját `return`-jét kapja meg — a `verseny_versenyszam` és `verseny_diak` viselkedése változatlan marad.

- [ ] **Step 8: Grep-ellenőrzés**

Run: `grep -n "sportEvent\|getSportEventList\|sportEventId" templates/riports_dashboard.php`
Expected: a select-blokk, a computed, a watch, és a három `getSubmitUri`-ág is tartalmazza.

- [ ] **Step 9: Manuális böngésző-teszt (frontend)**

1. Riport oldal → válaszd a **„sportágak népszerűsége"** típust → megjelenik a Sport ÉS a Versenyszám legördülő.
2. Sport kiválasztása → a Versenyszám legördülő feltöltődik az adott sportág versenyszámaival; sport nélkül a legördülő letiltott, „Összes versenyszám".
3. Másik sportág választása → a versenyszám visszaáll „Összes versenyszám"-ra.
4. A böngésző fejlesztői eszközében (vagy „Riport generálás" előtt) ellenőrizd, hogy a megnyíló URL tartalmazza a `sport=` és `sportEventId=` paramétereket.

- [ ] **Step 10: Commit**

```bash
git add templates/riports_dashboard.php
git commit -m "feat(A2): versenyszám-szűrő a riport felületen (Vue)"
```

---

### Task 4: Riport — backend: sportág + versenyszám szűrés két függvényben

**Files:**
- Modify: `includes/Export/download_riports.php` — `vespa_download_riport_legnepszerubb_sportagak()` (911-1036. sor) és `vespa_download_riport_tanev($type)` (771-909. sor)

**Interfaces:**
- Consumes: `$_GET['sport']` (sportág id, 0=mind), `$_GET['sportEventId']` (versenyszám id, 0=mind) — a Task 3 küldi.
- Produces: a két riport-export a sportág/versenyszám szerint szűrt adaton készül.

- [ ] **Step 1: `legnepszerubb_sportagak` — paraméterek beolvasása**

A `vespa_download_riport_legnepszerubb_sportagak()`-ban a `$disabilityGroupId = $_GET['disabilityGroupId'];` sor (jelenleg 922.) UTÁN:

```php
    $sportId = isset($_GET['sport']) ? $_GET['sport'] : 0;
    $sportEventId = isset($_GET['sportEventId']) ? $_GET['sportEventId'] : 0;
```

- [ ] **Step 2: `legnepszerubb_sportagak` — WHERE-feltételek**

Ugyanebben a függvényben a gender-blokk (`if ($gender == 'nő' || $gender == 'férfi') { ... }`, jelenleg 987-990.) UTÁN, a `$sql .= " GROUP BY contest_event_id";` sor (992.) ELÉ:

```php
    if (is_numeric($sportId) && $sportId > 0) {
        $sql .= " AND vce.sport_id=%d";
        $params[] = intval($sportId);
    }
    if (is_numeric($sportEventId) && $sportEventId > 0) {
        $sql .= " AND vce.event_id=%d";
        $params[] = intval($sportEventId);
    }
```

- [ ] **Step 3: `vespa_download_riport_tanev` — paraméterek beolvasása**

A `vespa_download_riport_tanev($type)`-ban a `$seriesId = $_GET['series'];` sor (jelenleg 781.) UTÁN:

```php
    $sportId = isset($_GET['sport']) ? $_GET['sport'] : 0;
    $sportEventId = isset($_GET['sportEventId']) ? $_GET['sportEventId'] : 0;
```

- [ ] **Step 4: `vespa_download_riport_tanev` — JOIN a versenyszám-táblához**

Ugyanebben a függvényben az első `$data` lekérdezés SQL-jét (jelenleg 824-828.) egészítsd ki egy JOIN-nal a `vespa_constest_events`-hez, hogy a sportág/versenyszám elérhető legyen:

```php
    $sql = "SELECT COUNT(DISTINCT va.athlete_id) as diakok, vi.institution_id, vi.ins_name FROM `vespa_institutions` as vi
            LEFT JOIN vespa_athletes as va ON va.school_id=vi.institution_id
            LEFT JOIN vespa_athlete_entries as vae ON va.athlete_id=vae.athlete_id
            LEFT JOIN vespa_contests as vc ON vae.contest_id=vc.contest_id
            LEFT JOIN vespa_constest_events as vce ON vae.contest_event_id=vce.id
            WHERE 1";
```

- [ ] **Step 5: `vespa_download_riport_tanev` — WHERE-feltételek**

Ugyanebben a függvényben a schoolDistrict-blokk (`if($schoolDistrictId > 0) { ... }`, jelenleg 850-853.) UTÁN, a `$sql .= " GROUP BY vi.institution_id;";` sor (854.) ELÉ:

```php
    if (is_numeric($sportId) && $sportId > 0) {
        $sql .= " AND vce.sport_id=%d";
        $params[] = intval($sportId);
    }
    if (is_numeric($sportEventId) && $sportEventId > 0) {
        $sql .= " AND vce.event_id=%d";
        $params[] = intval($sportEventId);
    }
```

- [ ] **Step 6: Szintaxis-ellenőrzés**

Run: `php -l includes/Export/download_riports.php`
Expected: `No syntax errors detected`

- [ ] **Step 7: Grep-ellenőrzés**

Run: `grep -n "sportId\|sportEventId\|vce.sport_id=%d\|vce.event_id=%d\|LEFT JOIN vespa_constest_events as vce ON vae.contest_event_id=vce.id" includes/Export/download_riports.php`
Expected: a két paraméter mindkét függvényben, a két WHERE-feltétel mindkét függvényben, és a tanev-JOIN egyszer.

- [ ] **Step 8: Manuális böngésző-teszt (végpontok)**

1. **„sportágak népszerűsége"** riport sportág+versenyszám szűrővel → az XLSX csak az adott sportág/versenyszám nevezéseit tartalmazza; „Összes" → a régi, teljes riport.
2. **„iskolánkénti indult diákok"** és **„tanév diákolimpia diákok"** riport sportág szűrővel → a diák-létszámok a sportág szerint csökkennek; „Összes sport" → változatlan a korábbihoz képest.

- [ ] **Step 9: Commit**

```bash
git add includes/Export/download_riports.php
git commit -m "feat(A2): sportág + versenyszám szűrés a riport-exportokban"
```

---

### Task 5: Rács — kliensoldali keresés a `contest_results.php`-ban

**Files:**
- Modify: `templates/contest_results.php` (a rács-sorok `<tr>`-je 334. sor; a `#versenyszamok` blokk fejléce 299-305. sor környéke; ÚJ plain-JS `<script>`)

**Interfaces:**
- Consumes: a szerveroldalon renderelt rács-sorok. Minden sportoló-sor kap egy `data-athlete-row` és `data-athlete-name` attribútumot; a „megjelent" állapot a soron belüli `input[type=checkbox].result-field`-ből olvasható.
- Produces: `vespaRacsSzuro()` JS-függvény, amely név + megjelent szerint mutat/rejt sorokat.

- [ ] **Step 1: Adat-attribútumok a sportoló-sorra**

A sportoló-sor nyitó `<tr>`-jét (jelenleg 334. sor: `<tr id="row-<?php echo $athlete->athlete_id; ?>">`) cseréld erre:

```php
                                                        <tr id="row-<?php echo $athlete->athlete_id; ?>" data-athlete-row data-athlete-name="<?php echo esc_attr($athlete->athlete_name); ?>">
```

- [ ] **Step 2: Szűrő-sor + üres-állapot beszúrása a rács fölé**

A `#versenyszamok` blokkban a `<div class="col-md-12">` UTÁN, amely a `<form ...>`-ot tartalmazza (jelenleg 305-306. sor között), a `<form ...>` ELÉ illeszd be a szűrő-sort:

```html
                        <div class="vespa-racs-szuro" style="margin-bottom:8px;">
                            <input type="text" id="racs_szuro_nev" class="form-control input-sm" style="display:inline-block;width:auto;" placeholder="Sportoló neve" autocomplete="off" oninput="vespaRacsSzuro()">
                            <select id="racs_szuro_megjelent" class="form-control input-sm" style="display:inline-block;width:auto;" onchange="vespaRacsSzuro()">
                                <option value="">Van-e eredmény: mind</option>
                                <option value="1">Megjelent</option>
                                <option value="0">Nem jelent meg</option>
                            </select>
                        </div>
                        <div id="racs_nincs_talalat" style="display:none;margin:8px 0;">Nincs a keresésnek megfelelő sportoló.</div>
```

- [ ] **Step 3: A szűrő JS hozzáadása**

A záró `<script src="https://unpkg.com/write-excel-file@1.x/bundle/write-excel-file.min.js"></script>` sor (jelenleg 706.) UTÁN illeszd be:

```html
<script>
    // A2 rács-szűrő: név + megjelent szerint mutat/rejt sportoló-sorokat.
    // A csoport-fejléc sorok érintetlenek maradnak. A "megjelent" állapotot a
    // soron belüli, Vue által vezérelt checkboxból olvassuk (aktuális állapot).
    function vespaRacsSzuro() {
        var nevKereses = (document.getElementById('racs_szuro_nev').value || '').toLowerCase();
        var megjelentSzuro = document.getElementById('racs_szuro_megjelent').value;
        var sorok = document.querySelectorAll('#versenyszamok tr[data-athlete-row]');
        var talalat = 0;
        sorok.forEach(function (sor) {
            var nev = (sor.getAttribute('data-athlete-name') || '').toLowerCase();
            var nevOk = nevKereses === '' || nev.indexOf(nevKereses) !== -1;
            var megjelentOk = true;
            if (megjelentSzuro !== '') {
                var cb = sor.querySelector('input[type=checkbox].result-field');
                var megjelent = cb ? cb.checked : false;
                megjelentOk = (megjelentSzuro === '1') ? megjelent : !megjelent;
            }
            var mutat = nevOk && megjelentOk;
            sor.style.display = mutat ? '' : 'none';
            if (mutat) talalat++;
        });
        var uzenet = document.getElementById('racs_nincs_talalat');
        if (uzenet) uzenet.style.display = talalat === 0 ? '' : 'none';
    }
</script>
```

- [ ] **Step 4: Grep-ellenőrzés**

Run: `grep -n "data-athlete-row\|data-athlete-name\|vespaRacsSzuro\|racs_szuro_nev\|racs_szuro_megjelent\|racs_nincs_talalat" templates/contest_results.php`
Expected: mindegyik minta megjelenik.

- [ ] **Step 5: Manuális böngésző-teszt**

1. Nyiss meg egy véglegesített verseny egy versenyszámának **eredmény-rögzítő** rácsát.
2. **Sportoló neve** részszöveg → csak az egyező sportoló-sorok látszanak, a csoport-fejlécek megmaradnak.
3. **Megjelent** legördülő = „Megjelent" → csak a bepipált „Megjelent" sorok; = „Nem jelent meg" → a többi.
4. Olyan keresés, aminek nincs találata → „Nincs a keresésnek megfelelő sportoló." megjelenik.
5. A helyezés-rendezés (fejléc-kattintás) továbbra is működik.

- [ ] **Step 6: Commit**

```bash
git add templates/contest_results.php
git commit -m "feat(A2): sportoló-keresés az eredmény-rögzítő rácson"
```

---

## Önellenőrzés (a terv írója tölti ki)

- **Spec-lefedettség:** A2(b) sportág–versenyszám → Task 1/2 (fő nézet, versenyen belül), Task 3/4 (riport). A2(a) gyermek-név → Task 1/2 (fő), Task 5 (rács). Van-e eredmény → Task 1/2, Task 5. Helyezés → Task 1/2. Eredmény-érték (szöveg) → Task 1/2. Üres állapotok → Task 1, Task 5. Minden spec-pont leképezve.
- **Placeholder- scan:** nincs TBD/TODO; minden kód-lépés teljes kódot ad.
- **Típus-konzisztencia:** a Task 1 által renderelt input-osztályok (`.vespa-szuro-*`) pontosan egyeznek a Task 2 JS-szelektoraival; a Task 3 GET-paraméternevei (`sport`, `sportEventId`) pontosan egyeznek a Task 4 `$_GET`-olvasásával; a rács `data-athlete-*` attribútumai egyeznek a Task 5 JS-ével.
- **Eltérés a spectől (dokumentálva):** a spec „a meglévő Sport-szűrő mellé" megfogalmazása azt feltételezte, hogy a riport Sport-szűrője működik; a tervezés feltárta, hogy holt volt, ezért a Task 3/4 a sportág-szűrőt is bekötötte (a felhasználó „minden sportág-mutató riport" döntése alapján).
