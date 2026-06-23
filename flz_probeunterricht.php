<?php
/*
Plugin Name: flz_probeunterricht
Plugin URI: Deine Plugin-URI
Description: Probeunterricht am Tagore-Gymnasium
Version: 1.0
Author: Filzmann
Author URI: Deine Autor-URI
License: GPLv2 or later
*/


// Aktivierungshook, um die Tabellen zu erstellen
register_activation_hook( __FILE__, 'flzpu_probeunterricht_activate' );
// Deaktivierungshook, um die Daten zu löschen
register_deactivation_hook( __FILE__, 'flzpu_probeunterricht_deactivate' );



require_once (WP_PLUGIN_DIR ."/flz_wpdb_objects/FlzWpdbObject.php");
require_once (WP_PLUGIN_DIR ."/flz_wpdb_objects/FlzPerson.php");
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