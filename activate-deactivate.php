<?php



require_once("classes/FlzPuSchool.php");
require_once ("classes/FlzPuParticipant.php");
require_once( "classes/FlzPuSetting.php" );


// Funktion zur Erstellung der Tabellen beim Aktivieren des Plugins
function flzpu_probeunterricht_activate(): void {
	FlzPuSchool::create_table();
	FlzPuParticipant::create_table();
	FlzPuSetting::create_table();

	add_role(
		'flz_pu_editor',
		'Probeunterrichtsbeauftragte_r',
		array(
			'flz_pu'         => true,
		),
	);
	$role = get_role( 'administrator' );
	$role->add_cap( 'flz_pu' );
}

// Funktion zum Löschen der Tabellen beim Deaktivieren des Plugins
function flzpu_probeunterricht_deactivate(): void
{

    FlzPuParticipant::delete_table();
	FlzPuSchool::delete_table();
    flzPuSetting::delete_table();

	remove_role( 'flz_pu_editor' );
	$role = get_role( 'administrator' );
	$role->remove_cap( 'flz_pu' );
}
