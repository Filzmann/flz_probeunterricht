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
	$participant_csv_stage_ready = FlzCsvImportStage::exists('flzpu:participants', get_current_user_id());

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

	if (isset($_POST['participant_confirm'])) {
		$participant = FlzPuParticipant::get_by_id(absint(wp_unslash($_POST['participant_confirm'])));
		if (!$participant instanceof FlzPuParticipant || $participant->isConfirmed) {
			throw new UnexpectedValueException('Die zu bestätigende Vormerkung wurde nicht gefunden.');
		}
		$participant->isConfirmed = true;
		$participant->activationToken = null;
		$participant->activationExpiration = null;
		$participant->activationWarningSentAt = null;
		$participant->updated_at = current_time('mysql');
		$participant->save();
	}

	if (isset($_POST['participant_bulk_action'])) {
		$action = sanitize_key(wp_unslash($_POST['participant_bulk_action']));
		$ids = isset($_POST['participant_bulk_ids']) && is_array($_POST['participant_bulk_ids']) ? array_unique(array_map('absint', wp_unslash($_POST['participant_bulk_ids']))) : array();
		foreach (array_filter($ids) as $participant_id) {
			$participant = FlzPuParticipant::get_by_id($participant_id);
			if (!$participant instanceof FlzPuParticipant || $participant->isConfirmed) {
				continue;
			}
			if ('confirm' === $action) {
				$participant->isConfirmed = true;
				$participant->activationToken = null;
				$participant->activationExpiration = null;
				$participant->activationWarningSentAt = null;
				$participant->updated_at = current_time('mysql');
				$participant->save();
			} elseif ('delete' === $action) {
				FlzPuRegistrationService::delete($participant);
			}
		}
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
		$is_import = isset($_POST['submit_csv']);
		$is_confirmation = isset($_POST['confirm_participant_csv_import']);
		if (!$is_import && !$is_confirmation) {
			return null;
		}
		if ($is_import) {
			$rows = FlzPuCsvContract::parse_participants(flz_wpdb_objects_read_uploaded_csv('participants-csv', 'Importieren der Teilnehmenden-CSV-Datei', false));
			$plan = flzpu_plan_participant_csv_import($rows);
			$proof = flzpu_participant_csv_proof_hash($rows);
			FlzCsvImportStage::store('flzpu:participants', get_current_user_id(), $rows, $proof, 15 * MINUTE_IN_SECONDS);

			return array(
				'type' => 'success',
				'message' => sprintf('Dry-Run erfolgreich: %d Teilnehmende werden aktualisiert, %d neu angelegt. %d nicht in der CSV enthaltene Teilnehmende sind zur Einzelauswahl markiert.', $plan['updated'], $plan['created'], count($plan['missing_participants'])),
				'missing_participants' => array_map(
					static fn(FlzPuParticipant $participant): array => array(
						'id' => (int) $participant->id,
						'label' => trim((string) $participant->firstName . ' ' . (string) $participant->name) . ' (' . (string) $participant->email . ')',
					),
					$plan['missing_participants']
				),
			);
		}

		$stage = FlzCsvImportStage::consume('flzpu:participants', get_current_user_id());
		if (!is_array($stage)) {
			throw new UnexpectedValueException('Der geprüfte CSV-Stand ist abgelaufen. Bitte führen Sie den Dry-Run erneut aus.');
		}
		$rows = $stage['rows'];
		$proof = $stage['proof'];
		$plan = flzpu_plan_participant_csv_import($rows);
		$delete_missing_participant_ids = flzpu_requested_participant_csv_deletions($plan);

		flz_wpdb_objects\FlzWpdbTransaction::run(
			static function () use ($plan, $proof, $rows, $delete_missing_participant_ids): void {
				$maximum = FlzPuSetting::lock_capacity_limit();
				if (!hash_equals($proof, flzpu_participant_csv_proof_hash($rows))) {
					throw new UnexpectedValueException('Der Teilnehmerbestand hat sich seit dem Dry-Run verändert.');
				}
				flzpu_validate_participant_csv_capacity($plan, $delete_missing_participant_ids, $maximum);
				flzpu_apply_participant_csv_import_plan($plan, $delete_missing_participant_ids);
			},
			'Wiederherstellen des vollständigen Probeunterricht-Teilnehmersnapshots'
		);
		FlzCsvImportStage::discard('flzpu:participants', get_current_user_id());

		return array('type' => 'success', 'message' => sprintf('Die Teilnehmenden-CSV wurde importiert: %d aktualisiert, %d neu angelegt%s.', $plan['updated'], $plan['created'], !empty($delete_missing_participant_ids) ? sprintf(', %d ausgewählte nicht enthaltene Teilnehmende endgültig gelöscht', count($delete_missing_participant_ids)) : ''));
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
	$schools = array();
	foreach (FlzPuSchool::get_all_by(order_by: 'id') as $school) {
		$schools[strtolower((string) $school->name)] = $school;
	}
	$counts = array();
	$participants = array();
	$existing_by_identity = array();
	foreach (FlzPuParticipant::get_all_by(order_by: 'id') as $participant) {
		$key = flzpu_participant_csv_identity_key($participant);
		if (isset($existing_by_identity[$key])) {
			throw new UnexpectedValueException('Die vorhandenen Teilnehmendendaten enthalten einen doppelten CSV-Abgleichsschlüssel.');
		}
		$existing_by_identity[$key] = $participant;
	}
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
		$key = flzpu_participant_csv_identity_key($row);
		$existing = $existing_by_identity[$key] ?? null;
		unset($existing_by_identity[$key]);
		$participants[] = array('data' => $row, 'school' => $school, 'existing' => $existing);
	}

	$updated = count(array_filter($participants, static fn(array $item): bool => $item['existing'] instanceof FlzPuParticipant));
	return array(
		'participants' => $participants,
		'school_counts' => $counts,
		'schools' => array_values($schools),
		'updated' => $updated,
		'created' => count($participants) - $updated,
		'missing_participants' => array_values($existing_by_identity),
	);
}

/** @param array<string,mixed>|FlzPuParticipant $participant */
function flzpu_participant_csv_identity_key(array|FlzPuParticipant $participant): string
{
	$value = static function (array|FlzPuParticipant $source, string $property): string {
		if (is_array($source)) {
			return (string) ($source[$property] ?? '');
		}
		return (string) ($source->{$property} ?? '');
	};
	return strtolower(implode('|', array(
		$value($participant, 'name'),
		$value($participant, 'firstName'),
		$value($participant, 'email'),
		$value($participant, 'created_at'),
	)));
}

/**
 * @param array{missing_participants:array<int,FlzPuParticipant>} $plan
 * @return array<int,int>
 */
function flzpu_requested_participant_csv_deletions(array $plan): array
{
	// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Jede ID wird als positive Zahl und gegen den serverseitigen Dry-Run-Plan validiert.
	$requested = isset($_POST['delete_missing_participants']) ? wp_unslash($_POST['delete_missing_participants']) : array();
	if (!is_array($requested)) {
		throw new UnexpectedValueException('Die Auswahl der zu löschenden Teilnehmenden ist ungültig.');
	}
	$requested_ids = array();
	foreach ($requested as $participant_id) {
		if (!is_scalar($participant_id) || !ctype_digit((string) $participant_id) || 0 === (int) $participant_id) {
			throw new UnexpectedValueException('Die Auswahl der zu löschenden Teilnehmenden ist ungültig.');
		}
		$requested_ids[] = (int) $participant_id;
	}
	$requested_ids = array_values(array_unique($requested_ids));
	$allowed_ids = array_map(static fn(FlzPuParticipant $participant): int => (int) $participant->id, $plan['missing_participants']);
	if (array_diff($requested_ids, $allowed_ids)) {
		throw new UnexpectedValueException('Die Auswahl enthält keinen nicht im geprüften CSV-Stand enthaltenen Teilnehmenden.');
	}
	return $requested_ids;
}

/** @param array<string,mixed> $plan @param array<int,int> $delete_missing_participant_ids */
function flzpu_validate_participant_csv_capacity(array $plan, array $delete_missing_participant_ids, int $maximum): void
{
	$final_count = count($plan['participants']) + count(array_filter(
		$plan['missing_participants'],
		static fn(FlzPuParticipant $participant): bool => !in_array((int) $participant->id, $delete_missing_participant_ids, true)
	));
	if ($final_count > $maximum) {
		throw new UnexpectedValueException('Der Import überschreitet die globale Probeunterrichtskapazität. Wählen Sie weitere markierte Teilnehmende zur Löschung aus oder korrigieren Sie die CSV.');
	}
	$school_counts = $plan['school_counts'];
	foreach ($plan['missing_participants'] as $participant) {
		if (!in_array((int) $participant->id, $delete_missing_participant_ids, true)) {
			$school_id = (int) ($participant->school?->id ?? 0);
			$school_counts[$school_id] = ($school_counts[$school_id] ?? 0) + 1;
		}
	}
	foreach ($plan['schools'] as $school) {
		if (($school_counts[(int) $school->id] ?? 0) > (int) $school->capacity) {
			throw new UnexpectedValueException('Der Import überschreitet die Kapazität der Grundschule „' . $school->name . '“. Wählen Sie weitere markierte Teilnehmende zur Löschung aus oder korrigieren Sie die CSV.');
		}
	}
}

/** @param array<string,mixed> $plan @param array<int,int> $delete_missing_participant_ids */
function flzpu_apply_participant_csv_import_plan(array $plan, array $delete_missing_participant_ids = array()): void
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

	foreach ($plan['missing_participants'] as $participant) {
		if (in_array((int) $participant->id, $delete_missing_participant_ids, true)) {
			$locked_participant = FlzPuParticipant::get_by_id_for_update((int) $participant->id);
			if (!$locked_participant instanceof FlzPuParticipant) {
				throw new UnexpectedValueException('Ein zum Löschen ausgewählter Teilnehmer wurde nicht gefunden.');
			}
			$locked_participant->delete();
		}
	}
	$now = current_time('mysql');
	foreach ($plan['participants'] as $item) {
		$data = $item['data'];
		$school = $locked_schools[(int) $item['school']->id];
		$participant = $item['existing'] instanceof FlzPuParticipant
			? FlzPuParticipant::get_by_id_for_update((int) $item['existing']->id)
			: new FlzPuParticipant(array());
		if (!$participant instanceof FlzPuParticipant) {
			throw new UnexpectedValueException('Ein zu aktualisierender Teilnehmer wurde nicht gefunden.');
		}
		$participant->name = $data['name'];
		$participant->firstName = $data['firstName'];
		$participant->class = $data['class'];
		$participant->email = $data['email'];
		$participant->school = $school;
		$participant->lunch = $data['lunch'];
		$participant->status = $data['status'];
		$participant->activationExpiration = null;
		$participant->activationToken = null;
		$participant->activationWarningSentAt = null;
		$participant->isConfirmed = true;
		$participant->created_at = $data['created_at'];
		$participant->updated_at = $now;
		$participant->save();
	}
	$school_counts = $plan['school_counts'];
	foreach ($plan['missing_participants'] as $participant) {
		if (!in_array((int) $participant->id, $delete_missing_participant_ids, true)) {
			$school_id = (int) ($participant->school?->id ?? 0);
			$school_counts[$school_id] = ($school_counts[$school_id] ?? 0) + 1;
		}
	}
	foreach ($locked_schools as $school_id => $school) {
		$school->available_seats = (int) $school->capacity - ($school_counts[$school_id] ?? 0);
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
