<?php

if ( PHP_SAPI !== 'cli' ) {
	http_response_code( 404 );
	exit;
}

// Source contract: the settings are declarative UI and the deletion guard is
// additionally enforced on the server.
// phpcs:disable WordPress.Security.EscapeOutput.ExceptionNotEscaped

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', '/tmp/' );
}

$root     = dirname( __DIR__ );
$template = file_get_contents( $root . '/templates/settings.php' );
$privacy  = file_get_contents( $root . '/includes/privacy.php' );
require_once $root . '/backend/settings.php';

if ( false === $template || false === $privacy ) {
	throw new RuntimeException( 'Settings source could not be read.' );
}

$requirements = array(
	"'type' => 'checkbox'"                                => $template,
	"'name' => 'confirm_delete_expired_participants'"     => $template,
	"'required' => true"                                  => $template,
	"'1' !== sanitize_text_field"                          => $privacy,
);

foreach ( $requirements as $needle => $source ) {
	if ( ! str_contains( $source, $needle ) ) {
		throw new RuntimeException( 'Settings input contract failed.' );
	}
}

$per_school = flzpu_setting_input_args( 'MaxTeilnehmerProSchule', '8' );
$total      = flzpu_setting_input_args( 'MaxTeilnehmerGesamt', '180' );
if (
	'number' !== $per_school['type']
	|| 1 !== $per_school['min']
	|| 10000 !== $per_school['max']
	|| 'number' !== $total['type']
	|| 0 !== $total['min']
) {
	throw new RuntimeException( 'Capacity settings must use their numeric bounds.' );
}

if ( str_contains( $template, "'confirm_delete_expired_participants' => '1'" ) ) {
	throw new RuntimeException( 'Deletion confirmation must not be preselected.' );
}

echo "OK: flz_probeunterricht settings input contract\n";
