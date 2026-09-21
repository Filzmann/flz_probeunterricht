<?php

declare(strict_types=1);

if ( PHP_SAPI !== 'cli' ) {
	exit;
}

// Test-Exceptions werden ausschließlich von der CLI ausgewertet.
// phpcs:disable WordPress.Security.EscapeOutput.ExceptionNotEscaped

const DAY_IN_SECONDS = 86400;

class FlzPerson {
	public string|null $name = null;
	public string|null $firstName = null;
	public string|null $email = null;

	public function __construct( array $data ) {
		$this->name = $data['name'] ?? null;
		$this->firstName = $data['firstName'] ?? null;
		$this->email = $data['email'] ?? null;
	}

	public function save(): int {
		return 1;
	}
}

final class FlzPuActivationTestUi {
	public function notice( string $message, string $type ): string {
		return $type . ':' . $message;
	}
}

final class FlzPuActivationTestDie extends RuntimeException {
	public string $title;
	public array $arguments;

	public function __construct( string $message, string $title, array $arguments ) {
		parent::__construct( $message );
		$this->title = $title;
		$this->arguments = $arguments;
	}
}

function wp_generate_password(): string {
	return 'activation-token';
}

function home_url( string $path ): string {
	return 'https://example.test' . $path;
}

function add_query_arg( array $arguments, string $url ): string {
	return $url . '?' . http_build_query( $arguments );
}

function add_action(): void {}

function add_shortcode(): void {}

function sanitize_text_field( mixed $value ): string {
	return is_scalar( $value ) ? trim( (string) $value ) : '';
}

function wp_unslash( mixed $value ): mixed {
	return $value;
}

function flz_ui(): FlzPuActivationTestUi {
	return new FlzPuActivationTestUi();
}

function wp_kses_post( string $value ): string {
	return $value;
}

function esc_html__( string $value ): string {
	return $value;
}

function wp_die( string $message, string $title, array $arguments ): never {
	throw new FlzPuActivationTestDie( $message, $title, $arguments );
}

require dirname( __DIR__ ) . '/classes/FlzPuParticipant.php';
require dirname( __DIR__ ) . '/frontend.php';

$participant = new FlzPuParticipant( array() );
$participant->id = 42;
$link = $participant->generate_activation_link();
if ( 'https://example.test/?flzpu_activate=1&id=42&token=activation-token' !== $link ) {
	throw new RuntimeException( 'Der Aktivierungslink nutzt nicht den festen Website-Endpunkt.' );
}
if ( 'activation-token' !== $participant->activationToken || null === $participant->activationExpiration ) {
	throw new RuntimeException( 'Der Aktivierungslink speichert keinen vollständigen Aktivierungszustand.' );
}

$_GET = array( 'flzpu_activate' => '1' );
try {
	flzpu_handle_activation_request();
	throw new RuntimeException( 'Ein unvollständiger Aktivierungslink wurde nicht beendet.' );
} catch ( FlzPuActivationTestDie $response ) {
	if ( 'Aktivierung' !== $response->title || 200 !== $response->arguments['response'] ) {
		throw new RuntimeException( 'Der ungültige Aktivierungslink erzeugt keine sichere Fehlerantwort.' );
	}
	if ( ! str_contains( $response->getMessage(), 'ungültig' ) ) {
		throw new RuntimeException( 'Der ungültige Aktivierungslink wird nicht abgewiesen.' );
	}
}

echo "OK: flz_probeunterricht activation link execution smoke test\n";
