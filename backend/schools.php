<?php

defined('ABSPATH') || exit;

// Exception-Texte sind interne Logdaten; HTML-Escaping erfolgt erst an der UI-Grenze.
// phpcs:disable WordPress.Security.EscapeOutput.ExceptionNotEscaped
// Alle POST-Pfade laufen durch flzpu_assert_admin_request(); der Sniff erkennt die zentrale Nonce-Prüfung nicht.
// phpcs:disable WordPress.Security.NonceVerification.Missing

function flzpu_schools_page(): void
{
	try {
		flzpu_schools_page_content();
	} catch (Throwable $error) {
		flzpu_render_admin_error($error, 'Anzeigen und Verarbeiten der Grundschulen');
	}
}

function flzpu_schools_page_content(): void
{
	flzpu_assert_admin_request();

	$unprocessed = flzpu_process_schools_csv_file();
	if ($unprocessed) {
		echo flz_ui()->csv_unprocessed_notice($unprocessed, array('name' => 'flzpu_unprocessed_schools')); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Renderer escaped die Komponente.
	}

	if (isset($_POST['school_submit'])) {
		$name = isset($_POST['name']) ? sanitize_text_field(wp_unslash($_POST['name'])) : '';
		$available_seats = isset($_POST['available_seats']) ? intval(wp_unslash($_POST['available_seats'])) : 0;

		if ($name !== '') {
			$school_id = isset($_POST['school_id']) && $_POST['school_id'] !== ''
				? absint(wp_unslash($_POST['school_id']))
				: null;
			$school = new FlzPuSchool(
				array(
					'name'            => $name,
					'available_seats' => $available_seats,
					'id'              => $school_id,
				)
			);
			$school->save();
		}
	}

	if (isset($_POST['school_delete'])) {
		$school_id = absint(wp_unslash($_POST['school_delete']));
		$school = FlzPuSchool::get_by_id($school_id);
		if (!$school instanceof FlzPuSchool) {
			throw new UnexpectedValueException('Die zu löschende Grundschule wurde nicht gefunden.');
		}
		$school->delete();
	}

	$schools = FlzPuSchool::get_all_by(order_by: 'name');
	$school_search = flz_ui_admin_filter_text('school_search');
	$school_orderby = flz_ui_admin_orderby(array('name', 'available_seats'), 'name');
	$school_order = flz_ui_admin_order();
	$school_base_args = array('page' => 'flzpu_schools');
	if ($school_search !== '') {
		$school_base_args['school_search'] = $school_search;
		$schools = array_values(
			array_filter(
				$schools,
				static function (FlzPuSchool $school) use ($school_search): bool {
					return false !== stripos((string) $school->name, $school_search);
				}
			)
		);
	}

	usort(
		$schools,
		static function (FlzPuSchool $left, FlzPuSchool $right) use ($school_orderby, $school_order): int {
			$values = array(
				'name'            => array((string) $left->name, (string) $right->name),
				'available_seats' => array((int) $left->available_seats, (int) $right->available_seats),
			);

			$result = flz_ui_admin_compare($values[$school_orderby][0], $values[$school_orderby][1], $school_order);
			if (0 === $result) {
				return flz_ui_admin_compare((string) $left->name, (string) $right->name, 'asc');
			}

			return $result;
		}
	);

	include dirname(__DIR__) . '/templates/schools.php';
}

function flzpu_process_schools_csv_file(): ?array
{
	$unprocessed_lines = array();

	try {
		if (!isset($_POST['submit_csv'])) {
			return null;
		}

		$school_rows = array();
		foreach (flz_wpdb_objects_read_uploaded_csv('schools-csv', 'Importieren der Grundschul-CSV-Datei') as $line) {
			if (count($line) < 2 || trim((string) $line[0]) === '' || !is_numeric($line[1])) {
				$line['error'] = 'Die CSV-Zeile benötigt einen Schulnamen und eine numerische Platzzahl.';
				$unprocessed_lines[] = $line;
				continue;
			}
			$school_rows[] = array(
				'name'            => sanitize_text_field($line[0]),
				'available_seats' => max(0, intval($line[1])),
			);
		}

		flz_wpdb_objects\FlzWpdbTransaction::run(
			static function () use ($school_rows): void {
				foreach ($school_rows as $school_data) {
					$school = FlzPuSchool::get_by_name($school_data['name'])
						?: new FlzPuSchool(array('name' => $school_data['name']));
					$school->available_seats = $school_data['available_seats'];
					$school->save();
				}
			},
			'Importieren der Grundschul-CSV-Datei'
		);
	} catch (Throwable $error) {
		throw flzpu_operation_error($error, 'Importieren der Grundschul-CSV-Datei');
	}

	return $unprocessed_lines;
}
