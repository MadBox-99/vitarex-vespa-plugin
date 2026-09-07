<?php

/**
 * A vespa_contest_types tábla azonosítói.
 *
 * A pluginban ezek az értékek ~40 helyen magic numberként szerepelnek; ez az
 * osztály egyelőre CSAK a szezon riportban (includes/Export/download_riports.php)
 * van használatban. A többi előfordulás átírása szándékosan nem része ennek a
 * csomagnak.
 */
class VespaContestType
{
	const ORSZAGOS   = 1;
	const REGIONALIS = 2;
	const MEGYEI     = 3;
	const SZABADIDOS = 4;
}

class VespaContest
{
	public $record;

	public function __construct($record)
	{
		$this->record = $record;
	}

	public function load($id)
	{
		$this->record = $GLOBALS['VESPA_Contests']->load($id);
	}

	public function can_delete()
	{
		// A testnevelő semmilyen esetben (még saját kiírásként sem) nem törölhet versenykiírást.
		if (VESPA_Roles::getInstance()->current_user_has_role(VESPA_Roles::TESTNEVELO)) {
			return false;
		}

		// Az országos versenyt csak a WordPress adminisztrátor törölheti — itt a
		// „saját kiírás" kivétel sem érvényesül.
		if (isset($this->record->contest_type) && intval($this->record->contest_type) === VespaContestType::ORSZAGOS) {
			return is_super_admin() || current_user_can('manage_options');
		}

		if (
			$this->record->creator_user_id == get_current_user_id() ||
			is_super_admin() ||
			//current_user_can(VESPA_Roles::versenyek_kezelese_kiiras_modositas_torles) ||
			VESPA_Roles::getInstance()->current_user_has_role(VESPA_Roles::ADMINISZTRATOR) ||
			VESPA_Roles::getInstance()->current_user_has_role(VESPA_Roles::FOVESZ_FODISZ_SPORTIGAZGATO)
		) {
			return true;
		}

		return false;
	}

	public function is_school_entered($school_id)
	{
	}

	public function is_open()
	{
		// school_entry_start_at, school_entry_end_at
		if (strtotime($this->record->school_entry_start_at) <= time() &&  strtotime($this->record->school_entry_end_at) >= time()) {
			return true;
		}

		return false;
	}
}

/**
 * Ki szerkeszthet egy versenyt a típusa alapján.
 *
 * Korábban egyetlen capability (versenyek_kezelese_kiiras_modositas_torles)
 * döntött minden versenytípusnál, így a megyei szintű szerepek az országos
 * kiírásokat is módosíthatták. Az országos szint mostantól a WordPress
 * adminisztrátoré; a többi típusnál a szabály változatlan.
 *
 * A döntés tiszta függvényben van (tesztek: tests/test-contest-edit-access.php),
 * a WordPress-től csak a jogosultság-jelzőket kapja.
 *
 * @param mixed $contest_type versenytípus azonosítója (szövegként is jöhet)
 * @param array $jogok        logikai jelzők: admin, versenykezeles
 * @return bool
 */
function vespa_contest_szerkesztes_engedelyezett($contest_type, $jogok)
{
	// Az adminisztrátor minden típust szerkeszthet, akkor is, ha a
	// versenykezelés capability hiányzik a szerepéről.
	if (!empty($jogok['admin'])) {
		return true;
	}

	if (empty($jogok['versenykezeles'])) {
		return false;
	}

	$tipus = is_numeric($contest_type) ? intval($contest_type) : 0;

	// Ismeretlen vagy hiányzó típusnál nem tudjuk kizárni, hogy országosról van
	// szó, ezért ide csak az admin jut túl.
	$ismert_tipusok = array(
		VespaContestType::ORSZAGOS,
		VespaContestType::REGIONALIS,
		VespaContestType::MEGYEI,
		VespaContestType::SZABADIDOS,
	);
	if (!in_array($tipus, $ismert_tipusok, true)) {
		return false;
	}

	return $tipus !== VespaContestType::ORSZAGOS;
}

/**
 * A fenti szabály kiértékelése az aktuális felhasználóra, versenytípus alapján.
 * Új verseny felvitelénél ez a hívható változat, mert ott még nincs rekord.
 */
function vespa_user_can_edit_contest_type($contest_type)
{
	return vespa_contest_szerkesztes_engedelyezett($contest_type, array(
		'admin'          => is_super_admin() || current_user_can('manage_options'),
		'versenykezeles' => current_user_can(VESPA_Roles::versenyek_kezelese_kiiras_modositas_torles),
	));
}

/**
 * Egy létező verseny típusa. Nem létező versenynél 0, így a hívó a szigorúbb
 * ágra fut (csak admin).
 */
function vespa_contest_type_by_id($contest_id)
{
	global $wpdb;

	$contest_id = intval($contest_id);
	if ($contest_id <= 0) {
		return 0;
	}

	static $cache = array();
	if (!array_key_exists($contest_id, $cache)) {
		$cache[$contest_id] = intval($wpdb->get_var($wpdb->prepare(
			"SELECT contest_type FROM vespa_contests WHERE contest_id=%d",
			$contest_id
		)));
	}

	return $cache[$contest_id];
}

/**
 * Szerkesztheti-e az aktuális felhasználó a megadott versenyt.
 */
function vespa_user_can_edit_contest($contest_id)
{
	return vespa_user_can_edit_contest_type(vespa_contest_type_by_id($contest_id));
}

/**
 * Melyik versenyhez tartozik a versenyszám. A versenyszámokat módosító
 * végpontok csak a sor azonosítóját kapják meg, a jogosultsághoz viszont a
 * verseny típusa kell.
 */
function vespa_contest_id_by_event($event_row_id)
{
	global $wpdb;

	return intval($wpdb->get_var($wpdb->prepare(
		"SELECT contest_id FROM vespa_constest_events WHERE id=%d",
		intval($event_row_id)
	)));
}

/**
 * Melyik versenyhez tartozik a kísérő.
 */
function vespa_contest_id_by_escort($contest_escort_id)
{
	global $wpdb;

	return intval($wpdb->get_var($wpdb->prepare(
		"SELECT contest_id FROM vespa_contests_escorts WHERE contest_escort_id=%d",
		intval($contest_escort_id)
	)));
}

/**
 * Ajax végpontok őre: jogosultság hiányában 403-mal megszakítja a kérést.
 */
function vespa_require_contest_edit($contest_id)
{
	if (!vespa_user_can_edit_contest($contest_id)) {
		wp_send_json_error(array("message" => "Jogosulatlan hozzáférés"), 403);
	}
}
