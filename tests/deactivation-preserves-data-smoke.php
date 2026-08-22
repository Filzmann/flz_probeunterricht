<?php

if ( PHP_SAPI !== 'cli' ) {
	http_response_code( 404 );
	exit;
}

// Test-Exceptions werden ausschließlich von der CLI ausgewertet.
// phpcs:disable WordPress.Security.EscapeOutput.ExceptionNotEscaped

$source = file_get_contents( dirname( __DIR__ ) . '/activate-deactivate.php' );
if ( false === $source ) {
	throw new RuntimeException( 'Der Aktivierungsvertrag konnte nicht gelesen werden.' );
}

$start = strpos( $source, 'function flzpu_probeunterricht_deactivate' );
if ( false === $start ) {
	throw new RuntimeException( 'Der Deaktivierungs-Handler fehlt.' );
}

$deactivation = substr( $source, $start );
foreach ( array( 'delete_table', 'remove_role', 'remove_cap' ) as $forbidden_call ) {
	if ( str_contains( $deactivation, $forbidden_call ) ) {
		throw new RuntimeException( 'Die Deaktivierung enthält weiterhin den destruktiven Aufruf ' . $forbidden_call . '().' );
	}
}

echo "OK: flz_probeunterricht deactivation preservation smoke test\n";

