<?php

if ( PHP_SAPI !== 'cli' ) {
	http_response_code( 404 );
	exit;
}

// Test-Exceptions werden ausschließlich von der CLI ausgewertet.
// phpcs:disable WordPress.Security.EscapeOutput.ExceptionNotEscaped

$plugin_root = dirname( __DIR__ );
$backend = file_get_contents( $plugin_root . '/backend/backend.php' );
$participants = file_get_contents( $plugin_root . '/backend/participants.php' );
$schools = file_get_contents( $plugin_root . '/backend/schools.php' );
$templates = file_get_contents( $plugin_root . '/templates/participants.php' )
	. file_get_contents( $plugin_root . '/templates/schools.php' );

if ( false === $backend || false === $participants || false === $schools ) {
	throw new RuntimeException( 'Die CSV-Exportquellen konnten nicht gelesen werden.' );
}

foreach (
	array(
		'flzpu_export_participants_csv' => $participants,
		'flzpu_export_schools_csv'      => $schools,
	) as $action => $source
) {
	if ( ! str_contains( $backend, "admin_post_{$action}" ) ) {
		throw new RuntimeException( 'Der geschützte Handler ' . $action . ' ist nicht registriert.' );
	}
	$permission_position = strpos( $source, "flzpu_assert_csv_export_request('{$action}')" );
	$download_position = strpos( $source, 'flz_wpdb_objects_send_csv_download', $permission_position ?: 0 );
	if ( false === $permission_position || false === $download_position || $permission_position > $download_position ) {
		throw new RuntimeException( 'Der Handler ' . $action . ' streamt nicht hinter Capability und Nonce.' );
	}
}

$combined = $participants . $schools . $templates;
if ( str_contains( $combined, 'flz_wpdb_objects_create_csv_file' ) ) {
	throw new RuntimeException( 'Der Probeunterricht erzeugt weiterhin persistente öffentliche CSV-Dateien.' );
}

echo "OK: flz_probeunterricht CSV export security smoke test\n";
