<?php

// Exception-Texte sind interne Logdaten; HTML-Escaping erfolgt erst an der UI-Grenze.
// phpcs:disable WordPress.Security.EscapeOutput.ExceptionNotEscaped


require_once("classes/FlzPuSchool.php");
require_once ("classes/FlzPuParticipant.php");
require_once( "classes/FlzPuSetting.php" );


// Funktion zur Erstellung der Tabellen beim Aktivieren des Plugins
function flzpu_probeunterricht_activate(): void {
	try {
		FlzPuSchool::create_table();
		FlzPuParticipant::create_table();
		FlzPuSetting::create_table();

		add_role(
			'flz_pu_editor',
			'Probeunterrichtsbeauftragte_r',
			array(
				'flz_pu' => true,
			),
		);
		$role = get_role( 'administrator' );
		if ( ! $role instanceof WP_Role ) {
			throw new RuntimeException( 'Die Administratorrolle wurde nicht gefunden.' );
		}
		$role->add_cap( 'flz_pu' );
	} catch ( Throwable $error ) {
		throw flzpu_operation_error( $error, 'Aktivieren des Probeunterrichts-Plugins' );
	}
}

// Funktion zum Löschen der Tabellen beim Deaktivieren des Plugins
function flzpu_probeunterricht_deactivate(): void
{
	try {
		FlzPuParticipant::delete_table();
		FlzPuSchool::delete_table();
		FlzPuSetting::delete_table();

		remove_role( 'flz_pu_editor' );
		$role = get_role( 'administrator' );
		if ( ! $role instanceof WP_Role ) {
			throw new RuntimeException( 'Die Administratorrolle wurde nicht gefunden.' );
		}
		$role->remove_cap( 'flz_pu' );
	} catch ( Throwable $error ) {
		throw flzpu_operation_error( $error, 'Deaktivieren des Probeunterrichts-Plugins' );
	}
}
