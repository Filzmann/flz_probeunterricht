<?php

defined('ABSPATH') || PHP_SAPI === 'cli' || exit;

// Exception-Texte sind interne Diagnosedaten; Escaping erfolgt erst an der UI-Grenze.
// phpcs:disable WordPress.Security.EscapeOutput.ExceptionNotEscaped

/**
 * Atomare Anwendungsgrenze für Teilnehmer- und Platzänderungen.
 */
final class FlzPuRegistrationService
{
	/** @param array<string,mixed> $data Bereits am Request-Rand bereinigte Daten. */
	public static function register(array $data): FlzPuParticipant
	{
		self::assert_participant_data($data);

		return flz_wpdb_objects\FlzWpdbTransaction::run(
			static function () use ($data): FlzPuParticipant {
				$now = function_exists('current_time') ? current_time('mysql') : gmdate('Y-m-d H:i:s');
				$maximum = FlzPuSetting::lock_capacity_limit();
				if ($maximum <= FlzPuParticipant::count_by()) {
					throw new UnexpectedValueException('Die maximale Teilnehmerzahl ist bereits erreicht.');
				}

				$school = FlzPuSchool::get_by_id_for_update((int) $data['school_id']);
				if (!$school instanceof FlzPuSchool) {
					throw new UnexpectedValueException('Die ausgewählte Grundschule wurde nicht gefunden.');
				}

				$participant = new FlzPuParticipant(array(
					'name'      => (string) $data['name'],
					'firstName' => (string) $data['firstName'],
					'email'     => (string) $data['email'],
					'class'     => (string) $data['class'],
					'lunch'     => !empty($data['lunch']),
					'school'    => $school,
					'status'    => 'pending',
					'created_at'=> $now,
					'updated_at'=> $now,
				));
				$school->claim_seat();
				$participant->save();

				return $participant;
			},
			'Reservieren eines Probeunterrichtsplatzes'
		);
	}

	/** @param array<string,mixed> $data Bereits am Request-Rand bereinigte Daten. */
	public static function update(FlzPuParticipant $participant, array $data): FlzPuParticipant
	{
		self::assert_participant_data($data);
		if (empty($participant->id)) {
			throw new UnexpectedValueException('Der zu aktualisierende Teilnehmer wurde nicht gefunden.');
		}

		return flz_wpdb_objects\FlzWpdbTransaction::run(
			static function () use ($participant, $data): FlzPuParticipant {
				FlzPuSetting::lock_capacity_limit();
				$current = FlzPuParticipant::get_by_id_for_update((int) $participant->id);
				if (!$current instanceof FlzPuParticipant || !$current->school instanceof FlzPuSchool) {
					throw new UnexpectedValueException('Der zu aktualisierende Teilnehmer wurde nicht gefunden.');
				}

				$new_school_id = (int) $data['school_id'];
				$old_school_id = (int) $current->school->id;
				if ($new_school_id !== $old_school_id) {
					$school_ids = array($new_school_id, $old_school_id);
					sort($school_ids, SORT_NUMERIC);
					$locked_schools = array();
					foreach ($school_ids as $school_id) {
						$locked_schools[$school_id] = FlzPuSchool::get_by_id_for_update($school_id);
					}
					$new_school = $locked_schools[$new_school_id] ?? null;
					$old_school = $locked_schools[$old_school_id] ?? null;
					if (!$new_school instanceof FlzPuSchool || !$old_school instanceof FlzPuSchool) {
						throw new UnexpectedValueException('Eine Grundschule der Platzumbuchung wurde nicht gefunden.');
					}
					$new_school->claim_seat();
					$old_school->release_seat();
					$current->school = $new_school;
				}

				$current->name = (string) $data['name'];
				$current->firstName = (string) $data['firstName'];
				$current->email = (string) $data['email'];
				$current->class = (string) $data['class'];
				$current->lunch = !empty($data['lunch']);
				$current->updated_at = function_exists('current_time') ? current_time('mysql') : gmdate('Y-m-d H:i:s');
				$current->save();

				return $current;
			},
			'Aktualisieren eines Probeunterrichtsteilnehmenden und seiner Platzbilanz'
		);
	}

	public static function delete(FlzPuParticipant $participant): void
	{
		if (empty($participant->id)) {
			throw new UnexpectedValueException('Der zu löschende Teilnehmer wurde nicht gefunden.');
		}

		flz_wpdb_objects\FlzWpdbTransaction::run(
			static function () use ($participant): void {
				FlzPuSetting::lock_capacity_limit();
				$current = FlzPuParticipant::get_by_id_for_update((int) $participant->id);
				if (!$current instanceof FlzPuParticipant || !$current->school instanceof FlzPuSchool) {
					throw new UnexpectedValueException('Der zu löschende Teilnehmer wurde nicht gefunden.');
				}
				$school = FlzPuSchool::get_by_id_for_update((int) $current->school->id);
				if (!$school instanceof FlzPuSchool) {
					throw new UnexpectedValueException('Die Grundschule des Teilnehmenden wurde nicht gefunden.');
				}
				$current->delete();
				$school->release_seat();
			},
			'Löschen eines Probeunterrichtsteilnehmenden und Freigeben seines Platzes'
		);
	}

	public static function reset(int $new_available_seats): void
	{
		$new_available_seats = max(0, $new_available_seats);
		flz_wpdb_objects\FlzWpdbTransaction::run(
			static function () use ($new_available_seats): void {
				FlzPuSetting::lock_capacity_limit();
				foreach (FlzPuParticipant::get_all_by() as $participant) {
					if ($participant instanceof FlzPuParticipant) {
						$participant->delete();
					}
				}
				foreach (FlzPuSchool::get_all_by() as $school) {
					if ($school instanceof FlzPuSchool) {
						$school->capacity = $new_available_seats;
						$school->available_seats = $new_available_seats;
						$school->save();
					}
				}
			},
			'Leeren der Probeunterrichtsteilnehmenden und Zurücksetzen der Platzbilanz'
		);
	}

	/** @param array<string,mixed> $data */
	private static function assert_participant_data(array $data): void
	{
		foreach (array('name', 'firstName', 'email', 'class', 'school_id') as $required) {
			if (!isset($data[$required]) || '' === trim((string) $data[$required])) {
				throw new UnexpectedValueException('Ein erforderliches Teilnehmerfeld fehlt: ' . $required . '.');
			}
		}
		foreach (array('name' => 255, 'firstName' => 255, 'email' => 255, 'class' => 32) as $field => $maximum) {
			if (strlen(trim((string) $data[$field])) > $maximum) {
				throw new UnexpectedValueException('Ein Teilnehmerfeld überschreitet die erlaubte Länge: ' . $field . '.');
			}
		}
		if (!is_email((string) $data['email'])) {
			throw new UnexpectedValueException('Die Eltern-E-Mail-Adresse ist ungültig.');
		}
		if ((int) $data['school_id'] <= 0) {
			throw new UnexpectedValueException('Die ausgewählte Grundschule ist ungültig.');
		}
		if (!preg_match('/^[\p{L}\p{N} ._-]+$/u', (string) $data['class'])) {
			throw new UnexpectedValueException('Die Klassenangabe enthält ungültige Zeichen.');
		}
	}
}
