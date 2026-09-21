<?php

// Exception-Texte sind interne Logdaten; HTML-Escaping erfolgt erst an der UI-Grenze.
// phpcs:disable WordPress.Security.EscapeOutput.ExceptionNotEscaped


require_once("classes/FlzPuSchool.php");
require_once ("classes/FlzPuParticipant.php");
require_once( "classes/FlzPuSetting.php" );


// Funktion zur Erstellung der Tabellen beim Aktivieren des Plugins
function flzpu_probeunterricht_activate(): void {
	try {
		FlzPuSchemaMigrator::maybe_upgrade();

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
		add_option('flzpu_retention_enabled', 0);
		add_option('flzpu_retention_months', 24);
		if ((bool) get_option('flzpu_retention_enabled', 0)) {
			flzpu_schedule_privacy_cleanup();
		}
	} catch ( Throwable $error ) {
		throw flzpu_operation_error( $error, 'Aktivieren des Probeunterrichts-Plugins' );
	}
}

// Deaktivierung trennt ausschließlich die WordPress-Hooks. Persistente Daten,
// Rollen und Capabilities bleiben für eine spätere Reaktivierung erhalten.
function flzpu_probeunterricht_deactivate(): void
{
	try {
		flzpu_unschedule_privacy_cleanup();
	} catch (Throwable $error) {
		flzpu_log_error($error, 'Entfernen des Probeunterricht-Privacy-Jobs bei Deaktivierung');
	}
}
