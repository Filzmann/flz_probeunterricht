<?php

if ( PHP_SAPI !== 'cli' ) {
	http_response_code( 404 );
	exit;
}

define('ABSPATH', __DIR__ . '/');

// Test-Exceptions werden ausschließlich von der CLI ausgewertet.
// phpcs:disable WordPress.Security.EscapeOutput.ExceptionNotEscaped

function sanitize_text_field($value): string {
	return trim(strip_tags((string) $value));
}
function sanitize_email($value): string {
	return (string) filter_var(trim((string) $value), FILTER_SANITIZE_EMAIL);
}
function is_email($value): bool {
	return false !== filter_var((string) $value, FILTER_VALIDATE_EMAIL);
}

$contract_file = dirname(__DIR__) . '/includes/class-flz-pu-csv-contract.php';
if (!is_file($contract_file)) {
	throw new RuntimeException('Der versionierte Probeunterricht-CSV-Vertrag fehlt.');
}
require_once $contract_file;

$schools = FlzPuCsvContract::parse_schools(array(
	FlzPuCsvContract::school_header(),
	array(FlzPuCsvContract::VERSION, 'school', 'Beispiel-Grundschule', '12'),
));
if (12 !== $schools[0]['capacity']) {
	throw new RuntimeException('Die Schulkapazität geht im CSV-Roundtrip verloren.');
}

$participants = FlzPuCsvContract::parse_participants(array(
	FlzPuCsvContract::participant_header(),
	array(
		FlzPuCsvContract::VERSION,
		'participant',
		'Beispiel',
		'Mia',
		'6a',
		'mia@example.test',
		'Beispiel-Grundschule',
		'1',
		'active',
		'2026-08-01 10:00:00',
	),
));
if ('mia@example.test' !== $participants[0]['email'] || true !== $participants[0]['lunch']) {
	throw new RuntimeException('Der Teilnehmenden-CSV-Roundtrip verliert fachliche Werte.');
}

$invalid = array(
	FlzPuCsvContract::participant_header(),
	array(FlzPuCsvContract::VERSION, 'participant', 'Beispiel', 'Mia', '6a', 'keine-mail', 'Schule', '0', 'pending', '2026-08-01 10:00:00'),
);
try {
	FlzPuCsvContract::parse_participants($invalid);
	throw new RuntimeException('Eine ungültige Eltern-E-Mail wurde akzeptiert.');
} catch (UnexpectedValueException $error) {
	// Erwarteter Deny-Fall.
}

$cancelled = array(
	FlzPuCsvContract::participant_header(),
	array(FlzPuCsvContract::VERSION, 'participant', 'Beispiel', 'Mia', '6a', 'mia@example.test', 'Schule', '0', 'cancelled', '2026-08-01 10:00:00'),
);
try {
	FlzPuCsvContract::parse_participants($cancelled);
	throw new RuntimeException('Ein stornierter Datensatz mit weiterhin belegtem Platz wurde akzeptiert.');
} catch (UnexpectedValueException $error) {
	// Erwarteter Deny-Fall; Stornierung erfolgt durch Löschung und Platzfreigabe.
}

if (in_array('activation_token', FlzPuCsvContract::participant_header(), true)) {
	throw new RuntimeException('Der CSV-Vertrag exportiert Aktivierungstoken.');
}

$participants_source = file_get_contents(dirname(__DIR__) . '/backend/participants.php');
$schools_source = file_get_contents(dirname(__DIR__) . '/backend/schools.php');
$templates = file_get_contents(dirname(__DIR__) . '/templates/participants.php')
	. file_get_contents(dirname(__DIR__) . '/templates/schools.php');
$combined = $participants_source . $schools_source;
foreach (
	array(
		"isset(\$_POST['submit_csv_dry_run'])",
		'FlzPuCsvContract::parse_schools',
		'FlzPuCsvContract::parse_participants',
		'FlzWpdbTransaction::run',
		'set_transient',
		'get_transient',
	) as $required
) {
	if (!str_contains($combined, $required)) {
		throw new RuntimeException('Der sichere CSV-Importvertrag fehlt: ' . $required);
	}
}
if (!str_contains($templates, 'Dry-Run')) {
	throw new RuntimeException('Die Adminoberfläche bietet keinen obligatorischen CSV-Dry-Run an.');
}

echo "OK: flz_probeunterricht CSV roundtrip contract smoke test\n";
