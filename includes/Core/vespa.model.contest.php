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
