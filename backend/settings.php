<?php

defined('ABSPATH') || exit;

// Exception-Texte sind interne Logdaten; HTML-Escaping erfolgt erst an der UI-Grenze.
// phpcs:disable WordPress.Security.EscapeOutput.ExceptionNotEscaped
// Alle POST-Pfade laufen durch flzpu_assert_admin_request(); der Sniff erkennt die zentrale Nonce-Prüfung nicht.
// phpcs:disable WordPress.Security.NonceVerification.Missing

function flzpu_settings_page(): void
{
	try {
		flzpu_settings_page_content();
	} catch (Throwable $error) {
		flzpu_render_admin_error($error, 'Anzeigen der Probeunterrichts-Einstellungen');
	}
}

function flzpu_settings_page_content(): void
{
	flzpu_assert_admin_request();
	$settings_notice = '';

	if (isset($_POST['settings'])) {
		$posted_settings = map_deep(wp_unslash($_POST['settings']), 'sanitize_text_field');
		flz_wpdb_objects\FlzWpdbTransaction::run(
			static function () use ($posted_settings): void {
				foreach ((array) $posted_settings as $id => $value) {
					$setting = FlzPuSetting::get_by_id(absint($id));
					if (!$setting instanceof FlzPuSetting) {
						throw new UnexpectedValueException('Eine zu speichernde Einstellung wurde nicht gefunden.');
					}
					$setting->value = $value;
					$setting->save();
				}
			},
			'Speichern der Probeunterrichts-Einstellungen'
		);
		$settings_notice = 'Einstellungen gespeichert.';
	}

	if (isset($_POST['flzpu_install_demo'])) {
		$created = flzpu_install_demo_content();
		$settings_notice = sprintf(
			'Demo-Daten angelegt/aktualisiert: %d Grundschulen, %d Beispielanmeldungen.',
			$created['schools'],
			$created['participants']
		);
	}

	$settings = FlzPuSetting::get_all_by();
	include dirname(__DIR__) . '/templates/settings.php';
}

/**
 * Legt kleine, klar erkennbare Demo-Daten für lokale Tests an.
 *
 * Vorhandene Schulen werden bevorzugt genutzt. Demo-Teilnehmer*innen werden
 * anhand ihrer example.test-Adressen wiederverwendet statt dupliziert.
 *
 * @return array{schools:int,participants:int}
 */
function flzpu_install_demo_content(): array
{
	$result = array(
		'schools'      => 0,
		'participants' => 0,
	);

	flz_wpdb_objects\FlzWpdbTransaction::run(
		static function () use (&$result): void {
			$schools = flzpu_ensure_demo_schools(3, $result);
			$demo_participants = array(
				array('name' => 'Demo-Kind', 'firstName' => 'Mia', 'email' => 'demo.probeunterricht.mia@example.test', 'class' => '6a', 'lunch' => true, 'status' => 'active'),
				array('name' => 'Demo-Schüler', 'firstName' => 'Noah', 'email' => 'demo.probeunterricht.noah@example.test', 'class' => '6b', 'lunch' => false, 'status' => 'pending'),
				array('name' => 'Demo-Test', 'firstName' => 'Lea', 'email' => 'demo.probeunterricht.lea@example.test', 'class' => '6c', 'lunch' => true, 'status' => 'active'),
			);

			foreach ($demo_participants as $index => $participant_data) {
				$participant = FlzPuParticipant::get_by_email($participant_data['email']);
				if ($participant instanceof FlzPuParticipant) {
					continue;
				}

				$school = $schools[$index % count($schools)];
				if ($school->available_seats === null || $school->available_seats <= 0) {
					$school->available_seats = max(1, (int) FlzPuSetting::get_value_by_name('MaxTeilnehmerProSchule'));
					$school->save();
				}
				$school->take_seat();
				$participant_data['school'] = $school;
				$participant_data['activationExpiration'] = null;
				$participant_data['activationToken'] = null;
				(new FlzPuParticipant($participant_data))->save();
				$result['participants']++;
			}
		},
		'Anlegen von Probeunterricht-Demo-Daten'
	);

	return $result;
}

/**
 * @param array{schools:int,participants:int} $result
 * @return array<int,FlzPuSchool>
 */
function flzpu_ensure_demo_schools(int $needed, array &$result): array
{
	$schools = FlzPuSchool::get_all_by(order_by: 'name');
	$schools = array_values(array_filter($schools, static fn($school): bool => $school instanceof FlzPuSchool));

	foreach (FlzPuSchool::example_schools as $school_name) {
		if (count($schools) >= $needed) {
			break;
		}
		if (FlzPuSchool::get_by_name($school_name) instanceof FlzPuSchool) {
			continue;
		}

		$school = new FlzPuSchool(
			array(
				'name'            => $school_name,
				'available_seats' => max(1, (int) FlzPuSetting::get_value_by_name('MaxTeilnehmerProSchule')),
			)
		);
		$school->save();
		$schools[] = $school;
		$result['schools']++;
	}

	if (empty($schools)) {
		throw new UnexpectedValueException('Für Demo-Daten konnte keine Grundschule gefunden oder angelegt werden.');
	}

	return array_slice($schools, 0, $needed);
}
