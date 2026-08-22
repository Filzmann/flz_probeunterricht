<?php

if ( PHP_SAPI !== 'cli' ) {
	http_response_code( 404 );
	exit;
}

// Test-Exceptions werden ausschließlich von der CLI ausgewertet.
// phpcs:disable WordPress.Security.EscapeOutput.ExceptionNotEscaped

$root = dirname( __DIR__ );
$privacy_file = $root . '/includes/privacy.php';
$bootstrap = file_get_contents( $root . '/flz_probeunterricht.php' );
$activation = file_get_contents( $root . '/activate-deactivate.php' );
$settings = file_get_contents( $root . '/backend/settings.php' )
	. file_get_contents( $root . '/templates/settings.php' );

if ( ! is_file( $privacy_file ) || false === $bootstrap || false === $activation || false === $settings ) {
	throw new RuntimeException( 'Die Quellen des Privacy-Vertrags fehlen.' );
}
$privacy = file_get_contents( $privacy_file );
if ( false === $privacy ) {
	throw new RuntimeException( 'Der Privacy-Vertrag konnte nicht gelesen werden.' );
}

foreach (
	array(
		"add_option('flzpu_retention_enabled', 0)"              => $activation,
		"add_option('flzpu_retention_months', 24)"              => $activation,
		"wp_privacy_personal_data_exporters"                    => $privacy,
		"wp_privacy_personal_data_erasers"                      => $privacy,
		"FlzPuRegistrationService::delete"                      => $privacy,
		"add_action('flzpu_daily_privacy_cleanup'"               => $privacy,
		"if (!(bool) get_option('flzpu_retention_enabled', 0))"  => $privacy,
		"admin_post_flzpu_delete_expired_participants"           => $privacy,
		"check_admin_referer('flzpu_delete_expired_participants')" => $privacy,
		"confirm_delete_expired_participants"                    => $privacy,
		"require_once plugin_dir_path( __FILE__ ) . 'includes/privacy.php'" => $bootstrap,
	) as $needle => $source
) {
	if ( ! str_contains( $source, $needle ) ) {
		throw new RuntimeException( 'Der Privacy-Vertrag fehlt: ' . $needle );
	}
}

if ( ! str_contains( $settings, 'Automatische Löschung aktivieren' ) || ! str_contains( $settings, '24 Monate' ) ) {
	throw new RuntimeException( 'Die Aufbewahrungsentscheidung ist im Admin nicht verständlich sichtbar.' );
}

echo "OK: flz_probeunterricht privacy retention smoke test\n";
