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

	if (isset($_POST['reset_participants'])) {
		$new_available_seats = isset($_POST['available_seats']) ? intval(wp_unslash($_POST['available_seats'])) : 0;
		FlzPuParticipant::reset($new_available_seats);
	}

	if (isset($_POST['participant_delete'])) {
		$participant = FlzPuParticipant::get_by_id(absint(wp_unslash($_POST['participant_delete'])));
		if (!$participant instanceof FlzPuParticipant) {
			throw new UnexpectedValueException('Der zu löschende Teilnehmer wurde nicht gefunden.');
		}
		$participant->delete();
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

	$csv_file = flz_wpdb_objects_create_csv_file(
		array('ID', 'Name', 'Vorname', 'Klasse', 'Email Eltern', 'Schule', 'Mittagessen', 'Status'),
		array_map(
			static fn(FlzPuParticipant $participant): array => array(
				$participant->id,
				$participant->name,
				$participant->firstName,
				$participant->class,
				$participant->email,
				$participant->school?->name,
				$participant->lunch ? 'Ja' : 'Nein',
				$participant->status,
			),
			$participants
		),
		'probeunterricht.csv'
	);

	include dirname(__DIR__) . '/templates/participants.php';
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
	$school = FlzPuSchool::get_by_id($new_school_id);
	if (!$school instanceof FlzPuSchool) {
		throw new UnexpectedValueException('Die ausgewählte Grundschule wurde nicht gefunden.');
	}

	$is_new_participant = empty($participant_save_post['id']);
	unset($participant_save_post['school_id']);
	$participant_save = $is_new_participant
		? new FlzPuParticipant(array())
		: FlzPuParticipant::get_by_id((int) $participant_save_post['id']);
	if (!$participant_save instanceof FlzPuParticipant) {
		throw new UnexpectedValueException('Der zu bearbeitende Teilnehmer wurde nicht gefunden.');
	}

	$participant_save->assignPostData(
		$participant_save_post,
		array('name', 'firstName', 'email', 'class', 'lunch')
	);
	$participant_save->school = $school;

	flz_wpdb_objects\FlzWpdbTransaction::run(
		static function () use ($is_new_participant, $old_school_id, $new_school_id, $participant_save, $school): void {
			if ($is_new_participant) {
				$school->take_seat();
			} elseif ($old_school_id !== $new_school_id) {
				$old_school = FlzPuSchool::get_by_id($old_school_id);
				if (!$old_school instanceof FlzPuSchool) {
					throw new UnexpectedValueException('Die bisherige Grundschule wurde nicht gefunden.');
				}
				$old_school->free_seat();
				$school->take_seat();
			}
			$participant_save->save();
		},
		'Speichern eines Probeunterrichtsteilnehmers und Anpassen der Schulplätze'
	);
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
