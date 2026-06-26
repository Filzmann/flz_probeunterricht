<?php

// Exception-Texte sind interne Logdaten; HTML-Escaping erfolgt erst an der UI-Grenze.
// phpcs:disable WordPress.Security.EscapeOutput.ExceptionNotEscaped
// Alle POST-Pfade laufen durch flzpu_assert_admin_request(); der Sniff erkennt die zentrale Nonce-Prüfung nicht.
// phpcs:disable WordPress.Security.NonceVerification.Missing

// Funktion zur Erstellung des Backend-Menüs
function flzpu_probeunterricht_menu(): void {
	add_menu_page(
		'Probeunterricht',
		'Probeunterricht',
		'flz_pu',
		'flzpu_participants',
		'flzpu_participants_page'
	);
	add_submenu_page(
		'flzpu_participants',
		'Schulen',
		'Schulen',
		'flz_pu',
		'flzpu_schools',
		'flzpu_schools_page'
	);
	add_submenu_page(
		'flzpu_participants',
		'Einstellungen',
		'Einstellungen',
		'flz_pu',
		'flzpu_settings',
		'flzpu_settings_page'
	);
}


// Funktion zur Anzeige der Schulen-Seite im Backend
function flzpu_settings_page(): void {
	try {
		flzpu_settings_page_content();
	} catch ( Throwable $error ) {
		flzpu_render_admin_error( $error, 'Anzeigen der Probeunterrichts-Einstellungen' );
	}
}

function flzpu_settings_page_content(): void {
	flzpu_assert_admin_request();

	if ( isset( $_POST['settings'] ) ) {
			$posted_settings = map_deep( wp_unslash( $_POST['settings'] ), 'sanitize_text_field' );
			flz_wpdb_objects\FlzWpdbTransaction::run(
				static function () use ( $posted_settings ): void {
					foreach ( (array) $posted_settings as $id => $value ) {
						$setting = FlzPuSetting::get_by_id( absint( $id ) );
						if ( ! $setting instanceof FlzPuSetting ) {
							throw new UnexpectedValueException( 'Eine zu speichernde Einstellung wurde nicht gefunden.' );
						}
						$setting->value = $value;
						$setting->save();
					}
				},
				'Speichern der Probeunterrichts-Einstellungen'
			);
		}
	$settings = FlzPuSetting::get_all_by();
	include( plugin_dir_path( __FILE__ ) . 'templates/settings.php' );
}


// Funktion zur Anzeige der Schulen-Seite im Backend
function flzpu_schools_page(): void {
	try {
		flzpu_schools_page_content();
	} catch ( Throwable $error ) {
		flzpu_render_admin_error( $error, 'Anzeigen und Verarbeiten der Grundschulen' );
	}
}

function flzpu_schools_page_content(): void {
	flzpu_assert_admin_request();

    $unprocessed=processSchoolsCsvFile();
	    if($unprocessed)
	    {
	        echo flz_ui()->csv_unprocessed_notice( $unprocessed, array( 'name' => 'flzpu_unprocessed_schools' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Renderer escaped die Komponente.
    }
	if ( isset( $_POST['school_submit'] ) ) {
			$name = isset( $_POST['name'] ) ? sanitize_text_field( wp_unslash( $_POST['name'] ) ) : '';
			$available_seats = isset( $_POST['available_seats'] ) ? intval( wp_unslash( $_POST['available_seats'] ) ) : 0;

		if ( ! empty( $name ) ) {
				$school_id = isset( $_POST['school_id'] ) && $_POST['school_id'] !== ''
					? absint( wp_unslash( $_POST['school_id'] ) )
					: null;
			$school    = new FlzPuSchool( array(
				'name'            => $name,
				'available_seats' => $available_seats,
				'id'              => $school_id
			) );
			$school->save();
		}

	}
	if ( isset( $_POST['school_delete'] ) ) {
			$school_id = absint( wp_unslash( $_POST['school_delete'] ) );
			$school    = FlzPuSchool::get_by_id( $school_id );
			if ( ! $school instanceof FlzPuSchool ) {
				throw new UnexpectedValueException( 'Die zu löschende Grundschule wurde nicht gefunden.' );
			}
		$school->delete();
	}





	$schools = FlzPuSchool::get_all_by( order_by: 'name' );
	include( plugin_dir_path( __FILE__ ) . 'templates/schools.php' );
}

// Funktion zur Anzeige der Teilnehmer-Seite im Backend
function flzpu_participants_page(): void {
	try {
		flzpu_participants_page_content();
	} catch ( Throwable $error ) {
		flzpu_render_admin_error( $error, 'Anzeigen und Verarbeiten der Probeunterrichtsteilnehmer' );
	}
}

function flzpu_participants_page_content(): void {
	flzpu_assert_admin_request();

	if ( isset( $_POST['reset_participants'] ) ) {
			$new_available_seats = isset( $_POST['available_seats'] ) ? intval( wp_unslash( $_POST['available_seats'] ) ) : 0;
		FlzPuParticipant::reset( $new_available_seats );
	}

	if ( isset( $_POST['participant_delete'] ) ) {
			$participant = FlzPuParticipant::get_by_id( absint( wp_unslash( $_POST['participant_delete'] ) ) );
			if ( ! $participant instanceof FlzPuParticipant ) {
				throw new UnexpectedValueException( 'Der zu löschende Teilnehmer wurde nicht gefunden.' );
			}
		$participant->delete();
	}

	if ( isset( $_POST['participant_save'] ) ) {
			$participant_save_post = map_deep( wp_unslash( $_POST['participant_save'] ), 'sanitize_text_field' );
			if ( ! is_array( $participant_save_post ) ) {
				throw new UnexpectedValueException( 'Die Teilnehmerdaten besitzen kein gültiges Array-Format.' );
			}

		$new_school_id = isset( $participant_save_post['school_id'] ) ? absint( $participant_save_post['school_id'] ) : 0;
		$old_school_id = isset( $participant_save_post['old_school_id'] ) ? absint( $participant_save_post['old_school_id'] ) : 0;
		$school=FlzPuSchool::get_by_id($new_school_id);
		if ( ! $school instanceof FlzPuSchool ) {
			throw new UnexpectedValueException( 'Die ausgewählte Grundschule wurde nicht gefunden.' );
		}
		$is_new_participant = empty( $participant_save_post['id'] );
		unset($participant_save_post['school_id']);
		$participant_save=$participant_save_post['id']?FlzPuParticipant::get_by_id(intval($participant_save_post['id'])):new FlzPuParticipant([]);
		if ( ! $participant_save instanceof FlzPuParticipant ) {
			throw new UnexpectedValueException( 'Der zu bearbeitende Teilnehmer wurde nicht gefunden.' );
		}
		$participant_save->assignPostData(
			$participant_save_post,
			[ 'name', 'firstName', 'email', 'class', 'lunch' ]
		);
		$participant_save->school=$school;
		flz_wpdb_objects\FlzWpdbTransaction::run(
			static function () use ( $is_new_participant, $old_school_id, $new_school_id, $participant_save, $school ): void {
				if ( $is_new_participant ) {
					$school->take_seat();
				} elseif ( $old_school_id !== $new_school_id ) {
					$old_school = FlzPuSchool::get_by_id( $old_school_id );
					if ( ! $old_school instanceof FlzPuSchool ) {
						throw new UnexpectedValueException( 'Die bisherige Grundschule wurde nicht gefunden.' );
					}
					$old_school->free_seat();
					$school->take_seat();
				}
				$participant_save->save();
			},
			'Speichern eines Probeunterrichtsteilnehmers und Anpassen der Schulplätze'
		);

	}


	// Anzeige der Teilnehmer-Tabelle
	$participants = FlzPuParticipant::get_all_by( order_by: 'name' );
	$schools      = FlzPuSchool::get_all_by( order_by: 'name' );
	array_unshift( $schools, new FlzPuSchool( [
		'id'              => 9999,
		'name'            => 'Bitte Grundschule auswählen',
		'available_seats' => 0
	] ) );
	$csv_file = flz_wpdb_objects_create_csv(
		$participants,
		'probeunterricht.csv',
		"ID;Name;Vorname;Klasse;Email Eltern;Schule;Mittagessen;Status\n"
	);


	include( plugin_dir_path( __FILE__ ) . 'templates/participants.php' );
}

function processSchoolsCsvFile(): array|null {
	$unprocessedLines = array();

	try {
		if ( ! isset( $_POST['submit_csv'] ) ) {
			return null;
		}

		$school_rows = array();
		foreach ( flz_wpdb_objects_read_uploaded_csv( 'schools-csv', 'Importieren der Grundschul-CSV-Datei' ) as $line ) {
			if ( count( $line ) < 2 || trim( (string) $line[0] ) === '' || ! is_numeric( $line[1] ) ) {
				$line['error'] = 'Die CSV-Zeile benötigt einen Schulnamen und eine numerische Platzzahl.';
				$unprocessedLines[] = $line;
				continue;
			}
			$school_rows[] = array(
				'name' => sanitize_text_field( $line[0] ),
				'available_seats' => max( 0, intval( $line[1] ) ),
			);
		}

		flz_wpdb_objects\FlzWpdbTransaction::run(
			static function () use ( $school_rows ): void {
				foreach ( $school_rows as $school_data ) {
					$school = FlzPuSchool::get_by_name( $school_data['name'] )
						?: new FlzPuSchool( array( 'name' => $school_data['name'] ) );
					$school->available_seats = $school_data['available_seats'];
					$school->save();
				}
			},
			'Importieren der Grundschul-CSV-Datei'
		);
	} catch (Throwable $error) {
		throw flzpu_operation_error( $error, 'Importieren der Grundschul-CSV-Datei' );
	}
	return $unprocessedLines;
}

// Hinzufügen der Backend-Menüs
add_action( 'admin_menu', 'flzpu_probeunterricht_menu' );
