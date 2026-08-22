<?php
/*
Plugin Name: FLZ Probeunterricht
Description: Probeunterricht am Tagore-Gymnasium
Version: 1.1.0
Author: Filzmann
License: GPLv2 or later
Requires Plugins: flz_wpdb_objects, flz_ui_components
Text Domain: flz-probeunterricht
Requires at least: 6.5
Requires PHP: 8.1
*/

defined( 'ABSPATH' ) || exit;

const FLZPU_VERSION = '1.1.0';
const FLZPU_DB_VERSION = '2.1.0';
const FLZPU_MIN_WPDB_OBJECTS_VERSION = '2.0.0';
const FLZPU_MIN_UI_COMPONENTS_VERSION = '0.1.11';

function flzpu_dependencies_available(): bool {
	return defined( 'FLZ_WPDB_OBJECTS_VERSION' )
		&& version_compare( FLZ_WPDB_OBJECTS_VERSION, FLZPU_MIN_WPDB_OBJECTS_VERSION, '>=' )
		&& class_exists( 'flz_wpdb_objects\\FlzWpdbObject' )
		&& defined( 'FLZ_UI_COMPONENTS_VERSION' )
		&& version_compare( FLZ_UI_COMPONENTS_VERSION, FLZPU_MIN_UI_COMPONENTS_VERSION, '>=' )
		&& function_exists( 'flz_ui' );
}

function flzpu_dependency_notice(): void {
	echo '<div class="notice notice-error"><p>'
		. esc_html__( 'flz_probeunterricht benötigt aktuelle, aktive Versionen von flz_wpdb_objects und flz_ui_components.', 'flz-probeunterricht' )
		. '</p></div>';
}

function flzpu_bootstrap(): bool {
	static $loaded = false;

	if ( $loaded ) {
		return true;
	}
	if ( ! flzpu_dependencies_available() ) {
		add_action( 'admin_notices', 'flzpu_dependency_notice' );
		return false;
	}

	require_once plugin_dir_path( __FILE__ ) . 'error-handling.php';
	require_once plugin_dir_path( __FILE__ ) . 'classes/FlzPuSchool.php';
	require_once plugin_dir_path( __FILE__ ) . 'classes/FlzPuParticipant.php';
	require_once plugin_dir_path( __FILE__ ) . 'classes/FlzPuSetting.php';
	require_once plugin_dir_path( __FILE__ ) . 'includes/class-flz-pu-registration-service.php';
	require_once plugin_dir_path( __FILE__ ) . 'includes/class-flz-pu-schema-migrator.php';
	require_once plugin_dir_path( __FILE__ ) . 'includes/class-flz-pu-csv-contract.php';
	require_once plugin_dir_path( __FILE__ ) . 'includes/privacy.php';
	require_once plugin_dir_path( __FILE__ ) . 'activate-deactivate.php';
	require_once plugin_dir_path( __FILE__ ) . 'backend/backend.php';
	require_once plugin_dir_path( __FILE__ ) . 'frontend.php';
	FlzPuSchemaMigrator::maybe_upgrade();
	$loaded = true;

	return true;
}

function flzpu_activate(): void {
	if ( ! flzpu_bootstrap() ) {
		wp_die( esc_html__( 'Aktivierung abgebrochen: Erforderliche FLZ-Plugins fehlen oder sind zu alt.', 'flz-probeunterricht' ) );
	}
	flzpu_probeunterricht_activate();
}

function flzpu_deactivate(): void {
	if ( flzpu_bootstrap() ) {
		flzpu_probeunterricht_deactivate();
	}
}

register_activation_hook( __FILE__, 'flzpu_activate' );
register_deactivation_hook( __FILE__, 'flzpu_deactivate' );
add_action( 'plugins_loaded', 'flzpu_bootstrap', 20 );

if ( did_action( 'plugins_loaded' ) ) {
	flzpu_bootstrap();
}
