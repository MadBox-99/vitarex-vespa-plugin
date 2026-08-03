<?php
/**
 * A riportok időszak-szűrője: versenysorozat (tanév/szezon) és naptári év.
 *
 * A két szűrő független egymástól: bármelyik elhagyható, és mind a négy
 * kombináció érvényes (csak szezon / csak év / mindkettő / egyik sem).
 * A tiszta függvények WordPress nélkül tesztelhetők — lásd
 * tests/test-riport-periodus.php.
 */

/**
 * Igaz, ha a GET-ből érkező nyers érték pozitív azonosítóként használható.
 * A riport-felület a "nincs szűrés" állapotot 0-val jelzi, de a GET-ből
 * elvileg bármilyen szöveg érkezhet.
 */
function vespa_riport_pozitiv_egesz($ertek)
{
    return is_numeric($ertek) && (int) $ertek > 0;
}

/**
 * Az időszak-szűrés SQL-töredéke és prepared paraméterei.
 *
 * A visszaadott 'sql' a hívó lekérdezésének WHERE ágához fűzhető, a 'params'
 * pedig a hívó paramétertömbjéhez. A SORREND KÖTÖTT: előbb a szezon
 * helyőrzője, utána az évé — a hívónak ugyanebben a sorrendben kell fűznie.
 *
 * @param mixed $seriesId  versenysorozat azonosítója; csak a pozitív egész szűr
 * @param mixed $year      naptári év; csak a pozitív egész szűr
 * @param bool  $szabadidosKivetel  ha true, a szezon-feltétel a szabadidős
 *        (contest_type=4) versenyeket szezontól függetlenül beengedi. Ezt
 *        egyedül a tanev_diakolimpia_versenyszam_sportag riport használja.
 * @return array {sql: string, params: array}
 */
function vespa_riport_periodus_szuro($seriesId, $year, $szabadidosKivetel = false)
{
    $sql    = '';
    $params = array();

    if (vespa_riport_pozitiv_egesz($seriesId)) {
        $sql .= $szabadidosKivetel
            ? ' AND (vc.contest_series=%d OR vc.contest_type=4)'
            : ' AND vc.contest_series=%d';
        $params[] = (int) $seriesId;
    }

    // A naptári év a verseny KEZDŐNAPJÁRA vonatkozik. Szándékosan a teljes
    // szezon-kifejezésre AND-elődik: a szabadidős kivétellel együtt is
    // érvényes marad az évszűrés.
    if (vespa_riport_pozitiv_egesz($year)) {
        $sql .= ' AND YEAR(vc.start_at)=%d';
        $params[] = (int) $year;
    }

    return array('sql' => $sql, 'params' => $params);
}

/**
 * A riport XLSX fejlécébe írandó időszak-felirat.
 *
 * A szezon nevét a hívó nézi ki az adatbázisból, hogy ez a függvény tiszta
 * maradhasson. Ez a felirat teszi egyértelművé a letöltött fájlból, hogy
 * melyik időszak-értelmezés volt érvényben.
 *
 * @param string|null $seriesName a szezon neve, vagy üres/null ha nincs szezonszűrés
 * @param mixed       $year
 * @return string
 */
function vespa_riport_periodus_felirat($seriesName, $year)
{
    $vanSzezon = is_string($seriesName) && $seriesName !== '';
    $vanEv     = vespa_riport_pozitiv_egesz($year);

    if ($vanSzezon && $vanEv) {
        return $seriesName . ' — naptári év: ' . (int) $year;
    }
    if ($vanSzezon) {
        return $seriesName;
    }
    if ($vanEv) {
        return 'Naptári év: ' . (int) $year;
    }

    return 'Nincs időszakszűrés (összes verseny)';
}

/**
 * A versenysorozat neve azonosító alapján; üres szöveg, ha nincs szezonszűrés
 * vagy a sorozat nem található.
 *
 * Mind a négy érintett riportfüggvénynek ugyanerre van szüksége a fejléc
 * feliratához, ezért egy helyen áll. A $wpdb-t használja, ezért nem tiszta és
 * nincs unit tesztje.
 */
function vespa_riport_szezon_neve($seriesId)
{
    global $wpdb;

    if (!vespa_riport_pozitiv_egesz($seriesId)) {
        return '';
    }

    $sor = $wpdb->get_row($wpdb->prepare(
        "SELECT series_name FROM vespa_series WHERE series_id=%d",
        (int) $seriesId
    ));

    return $sor ? $sor->series_name : '';
}

/**
 * A riport-lekérdezések futtatása üres paramétertömb esetén is biztonságosan.
 *
 * A $wpdb->prepare() helyőrző nélküli lekérdezésre _doing_it_wrong()
 * figyelmeztetést vált ki, ami WP_DEBUG_DISPLAY mellett beleíródik a válasz
 * törzsébe — a riport viszont bináris XLSX-et ír a php://output-ra, így a
 * letöltött fájl megsérülne. Szűrő nélküli riport most már előfordulhat,
 * ezért a prepare() csak akkor fut, ha ténylegesen van behelyettesítendő érték.
 *
 * Ez a függvény a $wpdb-t használja, ezért nem tiszta és nincs unit tesztje.
 */
function vespa_riport_get_results($sql, $params)
{
    global $wpdb;

    return $wpdb->get_results($params ? $wpdb->prepare($sql, ...$params) : $sql);
}

/**
 * Az intézményre vonatkozó szűrés SQL-töredéke és prepared paraméterei.
 *
 * A `vi` (vespa_institutions) táblára szűr, ezért csak olyan lekérdezésben
 * használható, amely ezt az aliast ismeri. A sorrend kötött: előbb a
 * tankerület helyőrzője, utána az intézményé.
 *
 * @param mixed $schoolDistrictId  GET-ből jövő nyers érték; csak a pozitív egész szűr
 * @param mixed $institutionId     GET-ből jövő nyers érték; csak a pozitív egész szűr
 * @return array {sql: string, params: array}
 */
function vespa_riport_intezmeny_szuro($schoolDistrictId, $institutionId)
{
    $sql    = '';
    $params = array();

    if (vespa_riport_pozitiv_egesz($schoolDistrictId)) {
        $sql .= ' AND vi.school_district_id=%d';
        $params[] = (int) $schoolDistrictId;
    }

    if (vespa_riport_pozitiv_egesz($institutionId)) {
        $sql .= ' AND vi.institution_id=%d';
        $params[] = (int) $institutionId;
    }

    return array('sql' => $sql, 'params' => $params);
}

/**
 * A sportolóra vonatkozó szűrés SQL-töredéke és prepared paraméterei.
 *
 * A `va` (vespa_athletes) táblára szűr. Szándékosan külön áll az
 * intézmény-szűrőtől: a tanév-riport intézmény-listázó lekérdezése csak a `vi`
 * táblát ismeri, ott ez a töredék hibás SQL-t adna. A vágás a query-aliasok
 * mentén fut, nem tetszőlegesen.
 *
 * @param mixed $disabilityGroupId csak a pozitív egész szűr
 * @param mixed $gender            csak a pontosan 'nő' vagy 'férfi' szűr
 * @return array {sql: string, params: array}
 */
function vespa_riport_sportolo_szuro($disabilityGroupId, $gender)
{
    $sql    = '';
    $params = array();

    if (vespa_riport_pozitiv_egesz($disabilityGroupId)) {
        $sql .= ' AND va.disability_type=%d';
        $params[] = (int) $disabilityGroupId;
    }

    // Fehérlista, nem feketelista: a felület 'összes'-t is küld, a GET-ből
    // pedig bármi jöhet. Csak a két ismert érték kerülhet a lekérdezésbe.
    if ($gender === 'nő' || $gender === 'férfi') {
        $sql .= ' AND va.gender=%s';
        $params[] = $gender;
    }

    return array('sql' => $sql, 'params' => $params);
}
