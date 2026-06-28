<?php

defined('ABSPATH') || exit;

/**
 * Protokolliert die vollständige Exception-Kette des Probeunterrichts.
 */
function flzpu_log_error(Throwable $error, string $context): void
{
	flz_wpdb_objects\FlzWpdbObjectsException::log_error($error, 'flz_probeunterricht', $context);
}

/**
 * Sichere Fehlergrenze für Backend-Seiten.
 */
function flzpu_render_admin_error(Throwable $error, string $context): void
{
	flzpu_log_error($error, $context);
	echo '<div class="notice notice-error"><p>'
		. esc_html__('Die Daten konnten nicht verarbeitet werden. Details stehen im Serverprotokoll.', 'flz-probeunterricht')
		. '</p></div>';
}

/**
 * Ergänzt eine technische Ursache um den fachlichen Probeunterrichts-Kontext.
 */
function flzpu_operation_error(Throwable $error, string $operation): flz_wpdb_objects\FlzWpdbObjectsException
{
	return flz_wpdb_objects\FlzWpdbObjectsException::operation(
		$operation,
		'Plugin flz_probeunterricht',
		$error
	);
}

/**
 * Prüft Berechtigung und Nonce einer Probeunterrichts-Backend-Seite.
 */
function flzpu_assert_admin_request(): void
{
	if ( ! current_user_can( 'flz_pu' ) ) {
		wp_die( esc_html__( 'Keine Berechtigung.', 'flz-probeunterricht' ) );
	}

	$request_method = isset( $_SERVER['REQUEST_METHOD'] )
		? sanitize_key( wp_unslash( $_SERVER['REQUEST_METHOD'] ) )
		: '';
	if ( 'post' === $request_method ) {
		check_admin_referer( 'flzpu_admin_action' );
	}
}
