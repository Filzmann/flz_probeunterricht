<?php
/*
Plugin Name: flz_probeunterricht
Plugin URI: Deine Plugin-URI
Description: Probeunterricht am Tagore-Gymnasium
Version: 1.0
Author: Filzmann
Author URI: Deine Autor-URI
License: GPLv2 or later
Requires Plugins: flz_wpdb_objects, flz_ui_components
*/


// Aktivierungshook, um die Tabellen zu erstellen
register_activation_hook( __FILE__, 'flzpu_probeunterricht_activate' );
// Deaktivierungshook, um die Daten zu löschen
register_deactivation_hook( __FILE__, 'flzpu_probeunterricht_deactivate' );



require_once (WP_PLUGIN_DIR ."/flz_wpdb_objects/FlzWpdbObject.php");
require_once (WP_PLUGIN_DIR ."/flz_wpdb_objects/FlzPerson.php");

$flzpu_ui_components_file = WP_PLUGIN_DIR . '/flz_ui_components/flz_ui_components.php';
if ( ! function_exists( 'flz_ui' ) && is_readable( $flzpu_ui_components_file ) ) {
	require_once $flzpu_ui_components_file;
}

if ( ! function_exists( 'flz_ui' ) ) {
	add_action(
		'admin_notices',
		static function (): void {
			echo '<div class="notice notice-error"><p>' . esc_html__( 'flz_probeunterricht benötigt das aktive Plugin flz_ui_components.', 'flz-probeunterricht' ) . '</p></div>';
		}
	);
	return;
}

require_once plugin_dir_path(__FILE__) . 'error-handling.php';
require_once("classes/FlzPuSchool.php");
require_once ("classes/FlzPuParticipant.php");
require_once( "classes/FlzPuSetting.php" );

// Aktivierung/Deaktivierung-Modul einbinden
require_once(plugin_dir_path(__FILE__) . 'activate-deactivate.php');
//
// Backend-Modul einbinden
require_once(plugin_dir_path(__FILE__) . 'backend.php');

// Frontend-Modul einbinden
require_once(plugin_dir_path(__FILE__) . 'frontend.php');


?>
