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
	array('550e8400-e29b-41d4-a716-446655440000', 'Beispiel-Grundschule', '12'),
));
if (12 !== $schools[0]['capacity']) {
	throw new RuntimeException('Die Schulkapazität geht im CSV-Roundtrip verloren.');
}
if ('550e8400-e29b-41d4-a716-446655440000' !== $schools[0]['record_uuid']) { throw new RuntimeException('Die Schul-ID geht verloren.'); }
$old_schools = FlzPuCsvContract::parse_schools(array(array('Schule', 'Kapazität'), array('Beispiel-Grundschule', '12')));
if ('' !== $old_schools[0]['record_uuid']) { throw new RuntimeException('Die alte Schul-CSV wird nicht kompatibel gelesen.'); }
try {
	FlzPuCsvContract::parse_schools(array(FlzPuCsvContract::school_header(), array('ungueltig', 'Schule', '12')));
	throw new RuntimeException('Eine ungültige ID wurde akzeptiert.');
} catch (UnexpectedValueException $error) {
	if (!str_contains($error->getMessage(), 'Datensatz-ID')) { throw $error; }
}
try {
	FlzPuCsvContract::parse_schools(array(FlzPuCsvContract::school_header(), array('550e8400-e29b-41d4-a716-446655440000', 'Schule A', '12'), array('550e8400-e29b-41d4-a716-446655440000', 'Schule B', '12')));
	throw new RuntimeException('Eine doppelte ID wurde akzeptiert.');
} catch (UnexpectedValueException $error) {
	if (!str_contains($error->getMessage(), 'doppelt')) { throw $error; }
}

$participants = FlzPuCsvContract::parse_participants(array(
	FlzPuCsvContract::participant_header(),
	array(
		'550e8400-e29b-41d4-a716-446655440001',
		'Beispiel',
		'Mia',
		'6a',
		'mia@example.test',
		'Beispiel-Grundschule',
		'1',
		'active',
		'2026-08-01 10:00:00',
		'0',
	),
));
if ('mia@example.test' !== $participants[0]['email'] || true !== $participants[0]['lunch']) {
	throw new RuntimeException('Der Teilnehmenden-CSV-Roundtrip verliert fachliche Werte.');
}
if (false !== ($participants[0]['isConfirmed'] ?? null)) {
	throw new RuntimeException('Eine unbestätigte Anmeldung wird beim CSV-Import nicht als unbestätigt erhalten.');
}
$confirmed_row = array('550e8400-e29b-41d4-a716-446655440001', 'Beispiel', 'Mia', '6a', 'mia@example.test', 'Beispiel-Grundschule', '1', 'active', '2026-08-01 10:00:00', '1');
if (true !== FlzPuCsvContract::parse_participants(array(FlzPuCsvContract::participant_header(), $confirmed_row))[0]['isConfirmed']) {
	throw new RuntimeException('Eine bestätigte Anmeldung verliert ihren Bestätigungszustand.');
}
$invalid_confirmation = $confirmed_row;
$invalid_confirmation[9] = 'vielleicht';
try {
	FlzPuCsvContract::parse_participants(array(FlzPuCsvContract::participant_header(), $invalid_confirmation));
	throw new RuntimeException('Ein ungültiger Bestätigungswert wurde akzeptiert.');
} catch (UnexpectedValueException $error) {
	if (!str_contains($error->getMessage(), 'Bestätigt')) { throw $error; }
}
$old_participant = FlzPuCsvContract::parse_participants(array(
	array('Nachname', 'Vorname', 'Klasse', 'E-Mail Eltern', 'Grundschule', 'Mittagessen', 'Status', 'Anmeldedatum'),
	array('Beispiel', 'Mia', '6a', 'mia@example.test', 'Beispiel-Grundschule', '1', 'active', '2026-08-01 10:00:00'),
));
if (!array_key_exists('isConfirmed', $old_participant[0]) || null !== $old_participant[0]['isConfirmed']) {
	throw new RuntimeException('Ein altes CSV ohne Bestätigungsinformation muss den Zustand als unbekannt kennzeichnen.');
}
$previous_uuid_row = array_slice($confirmed_row, 0, -1);
$previous_uuid = FlzPuCsvContract::parse_participants(array(array_slice(FlzPuCsvContract::participant_header(), 0, -1), $previous_uuid_row));
if ($previous_uuid[0]['record_uuid'] !== $confirmed_row[0] || null !== $previous_uuid[0]['isConfirmed']) {
	throw new RuntimeException('Eine UUID-CSV ohne Bestätigungsfeld wird nicht kompatibel gelesen.');
}
$versioned_header = array('format_version', 'record_type', 'last_name', 'first_name', 'class', 'email', 'school_name', 'lunch', 'status', 'created_at');
$versioned_row = array('flz_probeunterricht_v1', 'participant', 'Beispiel', 'Mia', '6a', 'mia@example.test', 'Beispiel-Grundschule', '1', 'pending', '2026-08-01 10:00:00');
if (null !== FlzPuCsvContract::parse_participants(array($versioned_header, $versioned_row))[0]['isConfirmed']) {
	throw new RuntimeException('Ein versionierter Altimport erfindet einen Bestätigungszustand.');
}
$old_confirm_header = array('Nachname', 'Vorname', 'Klasse', 'E-Mail Eltern', 'Grundschule', 'Mittagessen', 'isConfirmed', 'Status', 'Anmeldedatum');
foreach (array('ja' => true, 'Nein' => false, 'TRUE' => true, 'false' => false, '1' => true, '0' => false) as $value => $expected) {
	$row = array('Beispiel', 'Mia', '6a', 'mia@example.test', 'Beispiel-Grundschule', '1', (string) $value, 'active', '2026-08-01 10:00:00');
	if ($expected !== FlzPuCsvContract::parse_participants(array($old_confirm_header, $row))[0]['isConfirmed']) {
		throw new RuntimeException('Ein historischer Bestätigungswert wurde nicht korrekt erkannt: ' . $value);
	}
}
$versioned_with_confirmation = $versioned_header;
$versioned_with_confirmation[] = 'is_confirmed';
$versioned_confirm_row = $versioned_row;
$versioned_confirm_row[] = '0';
if (false !== FlzPuCsvContract::parse_participants(array($versioned_with_confirmation, $versioned_confirm_row))[0]['isConfirmed']) {
	throw new RuntimeException('Die Bestätigungsspalte des versionierten Altformats ging verloren.');
}
$duplicate_header = $old_confirm_header;
$duplicate_header[] = 'Bestätigt';
try {
	FlzPuCsvContract::parse_participants(array($duplicate_header, array_merge($row, array('1'))));
	throw new RuntimeException('Mehrdeutige Bestätigungsspalten wurden akzeptiert.');
} catch (UnexpectedValueException $error) {
	if (!str_contains($error->getMessage(), 'Bestätigungs')) { throw $error; }
}

$invalid = array(
	FlzPuCsvContract::participant_header(),
	array('550e8400-e29b-41d4-a716-446655440001', 'Beispiel', 'Mia', '6a', 'keine-mail', 'Schule', '0', 'pending', '2026-08-01 10:00:00', '0'),
);
try {
	FlzPuCsvContract::parse_participants($invalid);
	throw new RuntimeException('Eine ungültige Eltern-E-Mail wurde akzeptiert.');
} catch (UnexpectedValueException $error) {
	// Erwarteter Deny-Fall.
}

$cancelled = array(
	FlzPuCsvContract::participant_header(),
	array('550e8400-e29b-41d4-a716-446655440001', 'Beispiel', 'Mia', '6a', 'mia@example.test', 'Schule', '0', 'cancelled', '2026-08-01 10:00:00', '0'),
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
		"isset(\$_POST['confirm_participant_csv_import'])",
		"isset(\$_POST['confirm_school_csv_import'])",
		'FlzPuCsvContract::parse_schools',
		'FlzPuCsvContract::parse_participants',
		'FlzWpdbTransaction::run',
		'FlzCsvImportStage::store',
		'FlzCsvImportStage::consume',
		'flzpu_participant_csv_identity_key',
		'flzpu_requested_participant_csv_deletions',
		'flzpu_validate_participant_csv_capacity',
		'get_by_id_for_update',
	) as $required
) {
	if (!str_contains($combined, $required)) {
		throw new RuntimeException('Der sichere CSV-Importvertrag fehlt: ' . $required);
	}
}
foreach (array('Geprüfte Teilnehmenden-CSV jetzt importieren', 'Geprüfte Schulen-CSV jetzt importieren') as $required_confirmation) {
	if (!str_contains($templates, $required_confirmation)) {
		throw new RuntimeException('Die Adminoberfläche bietet keine eindeutige CSV-Importbestätigung an: ' . $required_confirmation);
	}
}
foreach (array("'confirm_participant_csv_import' => '1'", "'confirm_school_csv_import' => '1'", 'button_save( array(') as $required_submit_contract) {
	if (!str_contains($templates, $required_submit_contract)) {
		throw new RuntimeException('Die CSV-Importbestätigung ist kein verlässlicher Submit-Request: ' . $required_submit_contract);
	}
}
foreach (array("'name' => 'delete_missing_participants[]'", 'Nicht in der CSV enthaltene Teilnehmende endgültig löschen', 'flzpu-participant-csv-removable') as $required_participant_deletion_contract) {
	if (!str_contains($templates, $required_participant_deletion_contract)) {
		throw new RuntimeException('Die Teilnehmenden-CSV bietet keine sichere Einzelauswahl für Löschkandidaten an: ' . $required_participant_deletion_contract);
	}
}

foreach (array('schools.php', 'participants.php') as $template_name) {
	$template = file_get_contents(dirname(__DIR__) . '/templates/' . $template_name);
	$csv_grid_position = strpos($template, 'flz-ui-card-grid flz-ui-card-grid--two-columns flzpu-csv-grid');
	$table_position = strpos($template, '<table class="widefat striped flz-ui-admin-table">');
	if (false === $csv_grid_position || false === $table_position || $table_position >= $csv_grid_position) {
		throw new RuntimeException('Der CSV-Bereich muss als responsives Raster unter der Haupttabelle stehen: ' . $template_name);
	}
}

echo "OK: flz_probeunterricht CSV roundtrip contract smoke test\n";
