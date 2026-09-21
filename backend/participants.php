<?php

defined('ABSPATH') || exit;

// Exception-Texte sind interne Logdaten; HTML-Escaping erfolgt erst an der UI-Grenze.
// phpcs:disable WordPress.Security.EscapeOutput.ExceptionNotEscaped
// Alle POST-Pfade laufen durch flzpu_assert_admin_request(); der Sniff erkennt die zentrale Nonce-Prüfung nicht.
// phpcs:disable WordPress.Security.NonceVerification.Missing

function flzpu_participants_page(): void
{
	try {
		flzpu_participants_page_content();
	} catch (Throwable $error) {
		flzpu_render_admin_error($error, 'Anzeigen und Verarbeiten der Probeunterrichtsteilnehmer');
	}
}

function flzpu_participants_page_content(): void
{
	flzpu_assert_admin_request();
	$participant_csv_notice = flzpu_process_participants_csv_file();

	if (isset($_POST['reset_participants'])) {
		$new_available_seats = isset($_POST['available_seats']) ? intval(wp_unslash($_POST['available_seats'])) : 0;
		FlzPuRegistrationService::reset($new_available_seats);
	}

	if (isset($_POST['participant_delete'])) {
		$participant = FlzPuParticipant::get_by_id(absint(wp_unslash($_POST['participant_delete'])));
		if (!$participant instanceof FlzPuParticipant) {
			throw new UnexpectedValueException('Der zu löschende Teilnehmer wurde nicht gefunden.');
		}
		FlzPuRegistrationService::delete($participant);
	}

	if (isset($_POST['participant_save'])) {
		$participant_save_post = map_deep(wp_unslash($_POST['participant_save']), 'sanitize_text_field');
		if (!is_array($participant_save_post)) {
			throw new UnexpectedValueException('Die Teilnehmerdaten besitzen kein gültiges Array-Format.');
		}
		flzpu_save_participant_from_post($participant_save_post);
	}

	$participants = FlzPuParticipant::get_all_by(order_by: 'name');
	$schools = FlzPuSchool::get_all_by(order_by: 'name');

	$participant_search = flz_ui_admin_filter_text('participant_search');
	$participant_class_filter = flz_ui_admin_filter_text('class_filter');
	$participant_school_filter = isset($_GET['school_filter']) ? absint(wp_unslash($_GET['school_filter'])) : 0;
	$participant_status_filter = isset($_GET['status_filter']) ? sanitize_key(wp_unslash($_GET['status_filter'])) : '';
	$participant_orderby = flz_ui_admin_orderby(array('id', 'name', 'firstName', 'class', 'email', 'school', 'lunch', 'status'), 'name');
	$participant_order = flz_ui_admin_order();

	$participant_base_args = array('page' => 'flzpu_participants');
	if ($participant_search !== '') {
		$participant_base_args['participant_search'] = $participant_search;
	}
	if ($participant_class_filter !== '') {
		$participant_base_args['class_filter'] = $participant_class_filter;
	}
	if ($participant_school_filter > 0) {
		$participant_base_args['school_filter'] = $participant_school_filter;
	}
	if ($participant_status_filter !== '') {
		$participant_base_args['status_filter'] = $participant_status_filter;
	}

	$participant_status_options = array();
	$participant_class_options = array();
	$participant_school_filter_options = array('0' => 'alle Schulen');
	foreach ($schools as $school) {
		if (!empty($school->id)) {
			$participant_school_filter_options[(string) $school->id] = (string) $school->name;
		}
	}
	foreach ($participants as $participant) {
		if (trim((string) $participant->status) !== '') {
			$participant_status_options[(string) $participant->status] = (string) $participant->status;
		}
		if (trim((string) $participant->class) !== '') {
			$participant_class_options[(string) $participant->class] = (string) $participant->class;
		}
	}
	ksort($participant_status_options, SORT_NATURAL | SORT_FLAG_CASE);
	ksort($participant_class_options, SORT_NATURAL | SORT_FLAG_CASE);
	$participant_status_options = array('' => 'alle Status') + $participant_status_options;
	$participant_class_options = array('' => 'alle Klassen') + $participant_class_options;

	$participants = flzpu_filter_participants(
		$participants,
		$participant_search,
		$participant_class_filter,
		$participant_school_filter,
		$participant_status_filter
	);
	flzpu_sort_participants($participants, $participant_orderby, $participant_order);

	$participant_csv_export_url = wp_nonce_url(
		admin_url('admin-post.php?action=flzpu_export_participants_csv'),
		'flzpu_export_participants_csv'
	);

	include dirname(__DIR__) . '/templates/participants.php';
}

/**
 * Sendet die Teilnehmenden nach expliziter Zugriffskontrolle direkt als CSV.
 */
function flzpu_export_participants_csv(): void
{
	flzpu_assert_csv_export_request('flzpu_export_participants_csv');

	try {
		$participants = FlzPuParticipant::get_all_by(order_by: 'name');
		flz_wpdb_objects_send_csv_download(
			FlzPuCsvContract::participant_header(),
			FlzPuCsvContract::export_participants($participants),
			'probeunterricht.csv'
		);
	} catch (Throwable $error) {
		flzpu_log_error($error, 'Exportieren der Probeunterrichtsteilnehmenden');
		wp_die(esc_html__('Die Teilnehmenden-CSV konnte nicht erstellt werden.', 'flz-probeunterricht'));
	}
}

/** @return array{type:string,message:string}|null */
function flzpu_process_participants_csv_file(): ?array
{
	try {
		$is_dry_run = isset($_POST['submit_csv_dry_run']);
		$is_import = isset($_POST['submit_csv']);
		if (!$is_dry_run && !$is_import) {
			return null;
		}

		$rows = FlzPuCsvContract::parse_participants(
			flz_wpdb_objects_read_uploaded_csv('participants-csv', 'Importieren der Teilnehmenden-CSV-Datei', false)
		);
		$plan = flzpu_plan_participant_csv_import($rows);
		$proof = flzpu_participant_csv_proof_hash($rows);
		if ($is_dry_run) {
			set_transient(flzpu_participant_csv_proof_key(), $proof, 15 * MINUTE_IN_SECONDS);

			return array(
				'type' => 'success',
				'message' => sprintf('Dry-Run erfolgreich: vollständiger Snapshot mit %d Teilnehmenden.', count($plan['participants'])),
			);
		}

		$stored_proof = get_transient(flzpu_participant_csv_proof_key());
		if (!is_string($stored_proof) || !hash_equals($stored_proof, $proof)) {
			throw new UnexpectedValueException('Diese Teilnehmenden-CSV muss zuerst erneut erfolgreich als Dry-Run geprüft werden.');
		}

		flz_wpdb_objects\FlzWpdbTransaction::run(
			static function () use ($plan, $proof, $rows): void {
				$maximum = FlzPuSetting::lock_capacity_limit();
				if (count($plan['participants']) > $maximum) {
					throw new UnexpectedValueException('Der Snapshot überschreitet die globale Probeunterrichtskapazität.');
				}
				if (!hash_equals($proof, flzpu_participant_csv_proof_hash($rows))) {
					throw new UnexpectedValueException('Der Teilnehmerbestand hat sich seit dem Dry-Run verändert.');
				}
				flzpu_apply_participant_csv_import_plan($plan);
			},
			'Wiederherstellen des vollständigen Probeunterricht-Teilnehmersnapshots'
		);
		delete_transient(flzpu_participant_csv_proof_key());

		return array('type' => 'success', 'message' => 'Die Teilnehmenden-CSV wurde vollständig importiert.');
	} catch (Throwable $error) {
		flzpu_log_error($error, 'Prüfen oder Importieren der Teilnehmenden-CSV-Datei');

		return array(
			'type' => 'error',
			'message' => $error instanceof UnexpectedValueException
				? $error->getMessage()
				: 'Die Teilnehmenden-CSV konnte nicht sicher verarbeitet werden.',
		);
	}
}

/** @param array<int,array<string,mixed>> $rows */
function flzpu_plan_participant_csv_import(array $rows): array
{
	$maximum = max(0, (int) FlzPuSetting::get_value_by_name('MaxTeilnehmerGesamt'));
	if (count($rows) > $maximum) {
		throw new UnexpectedValueException('Der Snapshot überschreitet die globale Probeunterrichtskapazität.');
	}

	$schools = array();
	foreach (FlzPuSchool::get_all_by(order_by: 'id') as $school) {
		$schools[strtolower((string) $school->name)] = $school;
	}
	$counts = array();
	$participants = array();
	foreach ($rows as $row) {
		$key = strtolower($row['school_name']);
		$school = $schools[$key] ?? null;
		if (!$school instanceof FlzPuSchool) {
			throw new UnexpectedValueException('Die CSV referenziert eine unbekannte Grundschule: ' . $row['school_name'] . '.');
		}
		$counts[(int) $school->id] = ($counts[(int) $school->id] ?? 0) + 1;
		if ($counts[(int) $school->id] > (int) $school->capacity) {
			throw new UnexpectedValueException('Die CSV überschreitet die Kapazität der Grundschule „' . $school->name . '“.');
		}
		$participants[] = array('data' => $row, 'school' => $school);
	}

	return array('participants' => $participants, 'school_counts' => $counts, 'schools' => array_values($schools));
}

function flzpu_apply_participant_csv_import_plan(array $plan): void
{
	$locked_schools = array();
	$school_ids = array_map(static fn(FlzPuSchool $school): int => (int) $school->id, $plan['schools']);
	sort($school_ids, SORT_NUMERIC);
	foreach ($school_ids as $school_id) {
		$school = FlzPuSchool::get_by_id_for_update($school_id);
		if (!$school instanceof FlzPuSchool) {
			throw new UnexpectedValueException('Eine Grundschule des Snapshots wurde nicht gefunden.');
		}
		$locked_schools[$school_id] = $school;
	}

	foreach (FlzPuParticipant::get_all_by() as $participant) {
		if ($participant instanceof FlzPuParticipant) {
			$participant->delete();
		}
	}
	$now = current_time('mysql');
	foreach ($plan['participants'] as $item) {
		$data = $item['data'];
		$school = $locked_schools[(int) $item['school']->id];
		(new FlzPuParticipant(array(
			'name' => $data['name'],
			'firstName' => $data['firstName'],
			'class' => $data['class'],
			'email' => $data['email'],
			'school' => $school,
			'lunch' => $data['lunch'],
			'status' => $data['status'],
			'activationExpiration' => null,
			'activationToken' => null,
			'created_at' => $data['created_at'],
			'updated_at' => $now,
		)))->save();
	}
	foreach ($locked_schools as $school_id => $school) {
		$school->available_seats = (int) $school->capacity - ($plan['school_counts'][$school_id] ?? 0);
		$school->save();
	}
}

/** @param array<int,array<string,mixed>> $rows */
function flzpu_participant_csv_proof_hash(array $rows): string
{
	$current = array_map(
		static fn(FlzPuParticipant $participant): array => array(
			'id' => (int) $participant->id,
			'school_id' => (int) ($participant->school?->id ?? 0),
			'name' => (string) $participant->name,
			'firstName' => (string) $participant->firstName,
			'class' => (string) $participant->class,
			'email' => (string) $participant->email,
			'lunch' => (bool) $participant->lunch,
			'status' => (string) $participant->status,
			'created_at' => (string) $participant->created_at,
		),
		FlzPuParticipant::get_all_by(order_by: 'id')
	);

	return hash('sha256', serialize(array($rows, $current)));
}

function flzpu_participant_csv_proof_key(): string
{
	return 'flzpu_participant_csv_proof_' . get_current_user_id();
}

/**
 * Speichert einen Teilnehmenden inklusive Platzumbuchung der Grundschule.
 *
 * @param array<string,mixed> $participant_save_post Sanitized Daten aus $_POST.
 */
function flzpu_save_participant_from_post(array $participant_save_post): void
{

	$new_school_id = isset($participant_save_post['school_id']) ? absint($participant_save_post['school_id']) : 0;
	$old_school_id = isset($participant_save_post['old_school_id']) ? absint($participant_save_post['old_school_id']) : 0;
	$is_new_participant = empty($participant_save_post['id']);
	$participant_save = $is_new_participant ? null : FlzPuParticipant::get_by_id((int) $participant_save_post['id']);
	if ($is_new_participant) {
		FlzPuRegistrationService::register($participant_save_post + array('school_id' => $new_school_id));
		return;
	}
	if (!$participant_save instanceof FlzPuParticipant) {
		throw new UnexpectedValueException('Der zu bearbeitende Teilnehmer wurde nicht gefunden.');
	}

	FlzPuRegistrationService::update($participant_save, $participant_save_post + array(
		'school_id' => $new_school_id,
		'old_school_id' => $old_school_id,
	));
}

/**
 * @param array<int,FlzPuParticipant> $participants
 * @return array<int,FlzPuParticipant>
 */
function flzpu_filter_participants(
	array $participants,
	string $participant_search,
	string $participant_class_filter,
	int $participant_school_filter,
	string $participant_status_filter
): array {
	return array_values(
		array_filter(
			$participants,
			static function (FlzPuParticipant $participant) use ($participant_search, $participant_class_filter, $participant_school_filter, $participant_status_filter): bool {
				if ($participant_search !== '' && false === stripos((string) $participant->name, $participant_search)) {
					return false;
				}

				if ($participant_class_filter !== '' && (string) $participant->class !== $participant_class_filter) {
					return false;
				}

				if ($participant_school_filter > 0 && (int) ($participant->school?->id ?? 0) !== $participant_school_filter) {
					return false;
				}

				if ($participant_status_filter !== '' && (string) $participant->status !== $participant_status_filter) {
					return false;
				}

				return true;
			}
		)
	);
}

/**
 * @param array<int,FlzPuParticipant> $participants
 */
function flzpu_sort_participants(array &$participants, string $participant_orderby, string $participant_order): void
{
	usort(
		$participants,
		static function (FlzPuParticipant $left, FlzPuParticipant $right) use ($participant_orderby, $participant_order): int {
			$values = array(
				'id'        => array((int) $left->id, (int) $right->id),
				'name'      => array((string) $left->name, (string) $right->name),
				'firstName' => array((string) $left->firstName, (string) $right->firstName),
				'class'     => array((string) $left->class, (string) $right->class),
				'email'     => array((string) $left->email, (string) $right->email),
				'school'    => array((string) ($left->school?->name ?? ''), (string) ($right->school?->name ?? '')),
				'lunch'     => array(!empty($left->lunch) ? 1 : 0, !empty($right->lunch) ? 1 : 0),
				'status'    => array((string) $left->status, (string) $right->status),
			);

			$result = flz_ui_admin_compare($values[$participant_orderby][0], $values[$participant_orderby][1], $participant_order);
			if (0 === $result) {
				return flz_ui_admin_compare((string) $left->name, (string) $right->name, 'asc');
			}

			return $result;
		}
	);
}
