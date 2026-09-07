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

		// A megyei szintű szerep azt sem törölheti, amit szerkeszteni sem tud:
		// más megye versenyét, illetve regionális vagy szabadidős versenyt.
		$jogok = vespa_contest_szerkesztes_jogok();
		if (
			!empty($jogok['megyei_szerep'])
			&& !vespa_contest_szerkesztes_engedelyezett(
				isset($this->record->contest_type) ? $this->record->contest_type : 0,
				$jogok,
				isset($this->record->state_id) ? $this->record->state_id : 0
			)
		) {
			return false;
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
 * Ki szerkeszthet egy versenyt a típusa és a megyéje alapján.
 *
 * Korábban egyetlen capability (versenyek_kezelese_kiiras_modositas_torles)
 * döntött minden versenynél, ezért a megyei szintű szerepek az országos
 * kiírásokat és bármelyik megye versenyét is módosíthatták. Két szűkítés van:
 *
 *  - az országos szint a WordPress adminisztrátoré;
 *  - a versenyigazgató és a megyei vezető csak a saját megyéje megyei
 *    versenyeit szerkesztheti.
 *
 * A döntés tiszta függvényben van (tesztek: tests/test-contest-edit-access.php),
 * a WordPress-től csak a jogosultság-jelzőket kapja.
 *
 * @param mixed $contest_type     versenytípus azonosítója (szövegként is jöhet)
 * @param array $jogok            logikai jelzők: admin, versenykezeles,
 *                                megyei_szerep, sajat_megye
 * @param mixed $contest_state_id a verseny megyéje; null, ha nem ismert (ilyenkor
 *                                csak a típus dönt — pl. a típusválasztónál)
 * @return bool
 */
function vespa_contest_szerkesztes_engedelyezett($contest_type, $jogok, $contest_state_id = null)
{
	// Az adminisztrátor minden versenyt szerkeszthet, akkor is, ha a
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

	if ($tipus === VespaContestType::ORSZAGOS) {
		return false;
	}

	// A megyei szintű szerepek hatóköre a saját megyéjük megyei versenye. A
	// regionális és a szabadidős verseny több megyét érint, ezért nem az övék.
	if (!empty($jogok['megyei_szerep'])) {
		if ($tipus !== VespaContestType::MEGYEI) {
			return false;
		}

		$sajat_megye = isset($jogok['sajat_megye']) && is_numeric($jogok['sajat_megye'])
			? intval($jogok['sajat_megye'])
			: 0;

		// Akinek nincs beállítva megyéje, egyetlen megyei versenyhez sem tartozik.
		if ($sajat_megye <= 0) {
			return false;
		}

		// A megye ismerete nélkül (típusválasztó) csak a típus dönt.
		if ($contest_state_id === null) {
			return true;
		}

		$verseny_megye = is_numeric($contest_state_id) ? intval($contest_state_id) : 0;

		return $verseny_megye === $sajat_megye;
	}

	return true;
}

/**
 * Az aktuális felhasználó jogosultság-jelzői a fenti szabályhoz.
 */
function vespa_contest_szerkesztes_jogok()
{
	$roles = VESPA_Roles::getInstance();

	// Aki a megyei szerep mellett országos hatókörű szerepet is kapott, nem
	// szigorodik be a saját megyéjére: az erősebb jogkör érvényesül.
	$orszagos_hatokor = $roles->current_user_has_role(VESPA_Roles::FOVESZ_FODISZ_SPORTIGAZGATO)
		|| $roles->current_user_has_role(VESPA_Roles::DIAK_SPORTIGAZGATO);

	$megyei_szerep = !$orszagos_hatokor && (
		$roles->current_user_has_role(VESPA_Roles::MEGYEI_VERSENYIGAZGATO)
		|| $roles->current_user_has_role(VESPA_Roles::MEGYEI_VEZETO)
	);

	return array(
		'admin'          => is_super_admin() || current_user_can('manage_options'),
		'versenykezeles' => current_user_can(VESPA_Roles::versenyek_kezelese_kiiras_modositas_torles),
		'megyei_szerep'  => $megyei_szerep,
		'sajat_megye'    => get_user_meta(get_current_user_id(), 'state_id', true),
	);
}

/**
 * A szabály kiértékelése az aktuális felhasználóra, versenytípus (és ha ismert,
 * megye) alapján. Új verseny felvitelénél ez a hívható változat, mert ott még
 * nincs rekord; megye nélkül csak a típus dönt.
 */
function vespa_user_can_edit_contest_type($contest_type, $contest_state_id = null)
{
	return vespa_contest_szerkesztes_engedelyezett(
		$contest_type,
		vespa_contest_szerkesztes_jogok(),
		$contest_state_id
	);
}

/**
 * Egy létező verseny típusa és megyéje. Nem létező versenynél 0-s típus, így a
 * hívó a szigorúbb ágra fut (csak admin).
 */
function vespa_contest_access_row($contest_id)
{
	global $wpdb;

	$contest_id = intval($contest_id);
	if ($contest_id <= 0) {
		return array('contest_type' => 0, 'state_id' => 0);
	}

	static $cache = array();
	if (!array_key_exists($contest_id, $cache)) {
		$sor = $wpdb->get_row($wpdb->prepare(
			"SELECT contest_type, state_id FROM vespa_contests WHERE contest_id=%d",
			$contest_id
		));

		$cache[$contest_id] = array(
			'contest_type' => $sor ? intval($sor->contest_type) : 0,
			'state_id'     => $sor ? intval($sor->state_id) : 0,
		);
	}

	return $cache[$contest_id];
}

/**
 * Egy létező verseny típusa.
 */
function vespa_contest_type_by_id($contest_id)
{
	$sor = vespa_contest_access_row($contest_id);

	return $sor['contest_type'];
}

/**
 * Szerkesztheti-e az aktuális felhasználó a megadott versenyt.
 */
function vespa_user_can_edit_contest($contest_id)
{
	$sor = vespa_contest_access_row($contest_id);

	return vespa_user_can_edit_contest_type($sor['contest_type'], $sor['state_id']);
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
