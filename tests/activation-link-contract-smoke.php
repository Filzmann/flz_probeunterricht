<?php

if ( PHP_SAPI !== 'cli' ) {
	exit;
}

// Test-Exceptions werden ausschließlich von der CLI ausgewertet.
// phpcs:disable WordPress.Security.EscapeOutput.ExceptionNotEscaped

$root = dirname( __DIR__ );
$participant = file_get_contents( $root . '/classes/FlzPuParticipant.php' );
$frontend = file_get_contents( $root . '/frontend.php' );

if ( false === $participant || false === $frontend ) {
	throw new RuntimeException( 'Die Quellen des Aktivierungsvertrags konnten nicht gelesen werden.' );
}

foreach (
	array(
		'\'flzpu_activate\' => \'1\'' => $participant,
		'home_url( \'/\' )'          => $participant,
		'add_action( \'template_redirect\', \'flzpu_handle_activation_request\' )' => $frontend,
		'isset( $_GET[\'flzpu_activate\'] )' => $frontend,
		'! $participant instanceof FlzPuParticipant || ! isset( $_GET[\'token\'] )' => $frontend,
		'wp_die(' => $frontend,
		'wp_kses_post( $message )' => $frontend,
		'esc_html__( \'Aktivierung\', \'flz-probeunterricht\' )' => $frontend,
	) as $needle => $source
) {
	if ( ! str_contains( $source, $needle ) ) {
		throw new RuntimeException( 'Der feste Aktivierungsendpunkt fehlt: ' . $needle );
	}
}

if ( str_contains( $participant, 'get_permalink()' ) ) {
	throw new RuntimeException( 'Der Aktivierungslink hängt weiterhin von der aktuellen Formularseite ab.' );
}

if ( str_contains( $frontend, 'if ( isset( $_GET[\'id\'], $_GET[\'token\'] ) )' ) ) {
	throw new RuntimeException( 'Die Aktivierung wird weiterhin nur während des Shortcode-Renderings verarbeitet.' );
}

$activation_start = strpos( $participant, 'public function activate( $token ): string' );
$activation_source = false === $activation_start ? '' : substr( $participant, $activation_start );
$success_start = strpos( $activation_source, 'if ( $token_valid && $expires_at !== false && $expires_at >= time() ) {' );
$save_position = strpos( $activation_source, '$this->save();' );
$deny_start = strpos( $activation_source, '} else {' );
if ( false === $success_start || false === $save_position || false === $deny_start || $save_position < $success_start || $save_position > $deny_start ) {
	throw new RuntimeException( 'Ein ungültiger oder abgelaufener Token könnte den Teilnehmerstatus verändern.' );
}

echo "OK: flz_probeunterricht activation link contract smoke test\n";
