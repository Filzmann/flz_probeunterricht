<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$participant = file_get_contents($root . '/classes/FlzPuParticipant.php');
$release = file_get_contents($root . '/includes/unconfirmed-release.php');
$settings = file_get_contents($root . '/templates/settings.php');
$service = file_get_contents($root . '/includes/class-flz-pu-registration-service.php');

if (!is_string($participant) || !is_string($release) || !is_string($settings) || !is_string($service)) {
	throw new RuntimeException('Die Quellen für die Probeunterrichts-Bestätigungsfrist konnten nicht gelesen werden.');
}

function flzpu_release_assert(bool $condition, string $message): void
{
	if (!$condition) {
		// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- CLI-Testfehler wird nicht gerendert.
		throw new RuntimeException($message);
	}
}

flzpu_release_assert(str_contains($participant, 'isConfirmed tinyint(1) NOT NULL DEFAULT 1'), 'Das boolesche Bestätigungsmerkmal fehlt.');
flzpu_release_assert(str_contains($service, "'isConfirmed' => false"), 'Neue Anmeldungen starten nicht unbestätigt.');
flzpu_release_assert(str_contains($participant, '$this->isConfirmed = true'), 'Die Aktivierung setzt das Bestätigungsmerkmal nicht.');
flzpu_release_assert(str_contains($release, "add_action('flzpu_hourly_unconfirmed_release'") && str_contains($release, 'FlzPuRegistrationService::delete($participant)'), 'Die automatische Freigabe nutzt keinen sicheren Platzfreigabepfad.');
flzpu_release_assert(str_contains($settings, 'Vorgemerkte Anmeldungen automatisch freigeben') && str_contains($settings, 'Warnung vor der Freigabe'), 'Die Freigabe- und Warnoptionen fehlen im Backend.');

echo "OK: flz_probeunterricht unconfirmed release smoke test\n";
