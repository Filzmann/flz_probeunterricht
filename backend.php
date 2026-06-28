<?php

// Exception-Texte sind interne Logdaten; HTML-Escaping erfolgt erst an der UI-Grenze.
// phpcs:disable WordPress.Security.EscapeOutput.ExceptionNotEscaped
// Alle POST-Pfade laufen durch flzpu_assert_admin_request(); der Sniff erkennt die zentrale Nonce-Prüfung nicht.
// phpcs:disable WordPress.Security.NonceVerification.Missing

// Funktion zur Erstellung des Backend-Menüs
function flzpu_probeunterricht_menu(): void {
	add_menu_page(
		'FLZ Probeunterricht',
		'FLZ Probeunterricht',
		'flz_pu',
		'flzpu_participants',
		'flzpu_participants_page',
		'dashicons-welcome-learn-more',
		28
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

/**
 * Liest einen erlaubten Sortierschlüssel aus der Admin-URL.
 *
 * @param array<int,string> $allowed Erlaubte Spalten.
 */
function flzpu_admin_orderby(array $allowed, string $default): string
{
	$orderby = isset( $_GET['orderby'] ) ? sanitize_key( wp_unslash( $_GET['orderby'] ) ) : $default;
	return in_array( $orderby, $allowed, true ) ? $orderby : $default;
}

/**
 * Liest die Sortierrichtung aus der Admin-URL.
 */
function flzpu_admin_order(): string
{
	$order = isset( $_GET['order'] ) ? strtolower( sanitize_key( wp_unslash( $_GET['order'] ) ) ) : 'asc';
	return 'desc' === $order ? 'desc' : 'asc';
}

/**
 * Liest einen kurzen Textfilter aus der Admin-URL.
 */
function flzpu_admin_filter_text(string $key): string
{
	return isset( $_GET[ $key ] ) ? sanitize_text_field( wp_unslash( $_GET[ $key ] ) ) : '';
}

/**
 * Sortiert zwei skalare Werte stabil für Admin-Tabellen.
 *
 * @param int|string $left  Erster Wert.
 * @param int|string $right Zweiter Wert.
 */
function flzpu_admin_compare($left, $right, string $order): int
{
	if ( is_numeric( $left ) && is_numeric( $right ) ) {
		$result = (int) $left <=> (int) $right;
	} else {
		$result = strnatcasecmp( (string) $left, (string) $right );
	}

	return 'desc' === $order ? -$result : $result;
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
	$settings_notice = '';

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
			$settings_notice = 'Einstellungen gespeichert.';
		}
	if ( isset( $_POST['flzpu_install_demo'] ) ) {
		$created = flzpu_install_demo_content();
		$settings_notice = sprintf(
			'Demo-Daten angelegt/aktualisiert: %d Grundschulen, %d Beispielanmeldungen.',
			$created['schools'],
			$created['participants']
		);
		}
	$settings = FlzPuSetting::get_all_by();
	include( plugin_dir_path( __FILE__ ) . 'templates/settings.php' );
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
		static function () use ( &$result ): void {
			$schools = flzpu_ensure_demo_schools( 3, $result );
			$demo_participants = array(
				array( 'name' => 'Demo-Kind', 'firstName' => 'Mia', 'email' => 'demo.probeunterricht.mia@example.test', 'class' => '6a', 'lunch' => true, 'status' => 'active' ),
				array( 'name' => 'Demo-Schüler', 'firstName' => 'Noah', 'email' => 'demo.probeunterricht.noah@example.test', 'class' => '6b', 'lunch' => false, 'status' => 'pending' ),
				array( 'name' => 'Demo-Test', 'firstName' => 'Lea', 'email' => 'demo.probeunterricht.lea@example.test', 'class' => '6c', 'lunch' => true, 'status' => 'active' ),
			);

			foreach ( $demo_participants as $index => $participant_data ) {
				$participant = FlzPuParticipant::get_by_email( $participant_data['email'] );
				if ( $participant instanceof FlzPuParticipant ) {
					continue;
				}

				$school = $schools[ $index % count( $schools ) ];
				if ( $school->available_seats === null || $school->available_seats <= 0 ) {
					$school->available_seats = max( 1, (int) FlzPuSetting::get_value_by_name( 'MaxTeilnehmerProSchule' ) );
					$school->save();
				}
				$school->take_seat();
				$participant_data['school'] = $school;
				$participant_data['activationExpiration'] = null;
				$participant_data['activationToken'] = null;
				( new FlzPuParticipant( $participant_data ) )->save();
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
function flzpu_ensure_demo_schools( int $needed, array &$result ): array
{
	$schools = FlzPuSchool::get_all_by( order_by: 'name' );
	$schools = array_values( array_filter( $schools, static fn( $school ): bool => $school instanceof FlzPuSchool ) );

	foreach ( FlzPuSchool::example_schools as $school_name ) {
		if ( count( $schools ) >= $needed ) {
			break;
		}
		if ( FlzPuSchool::get_by_name( $school_name ) instanceof FlzPuSchool ) {
			continue;
		}

		$school = new FlzPuSchool(
			array(
				'name'            => $school_name,
				'available_seats' => max( 1, (int) FlzPuSetting::get_value_by_name( 'MaxTeilnehmerProSchule' ) ),
			)
		);
		$school->save();
		$schools[] = $school;
		$result['schools']++;
	}

	if ( empty( $schools ) ) {
		throw new UnexpectedValueException( 'Für Demo-Daten konnte keine Grundschule gefunden oder angelegt werden.' );
	}

	return array_slice( $schools, 0, $needed );
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
	$school_search = flzpu_admin_filter_text( 'school_search' );
	$school_orderby = flzpu_admin_orderby( array( 'name', 'available_seats' ), 'name' );
	$school_order = flzpu_admin_order();
	$school_base_args = array( 'page' => 'flzpu_schools' );
	if ( '' !== $school_search ) {
		$school_base_args['school_search'] = $school_search;
	}

	if ( '' !== $school_search ) {
		$schools = array_values( array_filter(
			$schools,
			static function ( FlzPuSchool $school ) use ( $school_search ): bool {
				return false !== stripos( (string) $school->name, $school_search );
			}
		) );
	}

	usort(
		$schools,
		static function ( FlzPuSchool $left, FlzPuSchool $right ) use ( $school_orderby, $school_order ): int {
			$values = array(
				'name'            => array( (string) $left->name, (string) $right->name ),
				'available_seats' => array( (int) $left->available_seats, (int) $right->available_seats ),
			);

			$result = flzpu_admin_compare( $values[ $school_orderby ][0], $values[ $school_orderby ][1], $school_order );
			if ( 0 === $result ) {
				return flzpu_admin_compare( (string) $left->name, (string) $right->name, 'asc' );
			}

			return $result;
		}
	);
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
	$participant_search = flzpu_admin_filter_text( 'participant_search' );
	$participant_class_filter = flzpu_admin_filter_text( 'class_filter' );
	$participant_school_filter = isset( $_GET['school_filter'] ) ? absint( wp_unslash( $_GET['school_filter'] ) ) : 0;
	$participant_status_filter = isset( $_GET['status_filter'] ) ? sanitize_key( wp_unslash( $_GET['status_filter'] ) ) : '';
	$participant_orderby = flzpu_admin_orderby( array( 'id', 'name', 'firstName', 'class', 'email', 'school', 'lunch', 'status' ), 'name' );
	$participant_order = flzpu_admin_order();
	$participant_base_args = array( 'page' => 'flzpu_participants' );
	if ( '' !== $participant_search ) {
		$participant_base_args['participant_search'] = $participant_search;
	}
	if ( '' !== $participant_class_filter ) {
		$participant_base_args['class_filter'] = $participant_class_filter;
	}
	if ( $participant_school_filter > 0 ) {
		$participant_base_args['school_filter'] = $participant_school_filter;
	}
	if ( '' !== $participant_status_filter ) {
		$participant_base_args['status_filter'] = $participant_status_filter;
	}
	$participant_status_options = array();
	$participant_class_options = array();
	$participant_school_filter_options = array( '0' => 'alle Schulen' );
	foreach ( $schools as $school ) {
		if ( ! empty( $school->id ) ) {
			$participant_school_filter_options[ (string) $school->id ] = (string) $school->name;
		}
	}
	foreach ( $participants as $participant ) {
		if ( '' !== trim( (string) $participant->status ) ) {
			$participant_status_options[ (string) $participant->status ] = (string) $participant->status;
		}
		if ( '' !== trim( (string) $participant->class ) ) {
			$participant_class_options[ (string) $participant->class ] = (string) $participant->class;
		}
	}
	ksort( $participant_status_options, SORT_NATURAL | SORT_FLAG_CASE );
	ksort( $participant_class_options, SORT_NATURAL | SORT_FLAG_CASE );
	$participant_status_options = array( '' => 'alle Status' ) + $participant_status_options;
	$participant_class_options = array( '' => 'alle Klassen' ) + $participant_class_options;

	$participants = array_values( array_filter(
		$participants,
		static function ( FlzPuParticipant $participant ) use ( $participant_search, $participant_class_filter, $participant_school_filter, $participant_status_filter ): bool {
			if ( '' !== $participant_search && false === stripos( (string) $participant->name, $participant_search ) ) {
				return false;
			}

			if ( '' !== $participant_class_filter && (string) $participant->class !== $participant_class_filter ) {
				return false;
			}

			if ( $participant_school_filter > 0 && (int) ( $participant->school?->id ?? 0 ) !== $participant_school_filter ) {
				return false;
			}

			if ( '' !== $participant_status_filter && (string) $participant->status !== $participant_status_filter ) {
				return false;
			}

			return true;
		}
	) );

	usort(
		$participants,
		static function ( FlzPuParticipant $left, FlzPuParticipant $right ) use ( $participant_orderby, $participant_order ): int {
			$values = array(
				'id'        => array( (int) $left->id, (int) $right->id ),
				'name'      => array( (string) $left->name, (string) $right->name ),
				'firstName' => array( (string) $left->firstName, (string) $right->firstName ),
				'class'     => array( (string) $left->class, (string) $right->class ),
				'email'     => array( (string) $left->email, (string) $right->email ),
				'school'    => array( (string) ( $left->school?->name ?? '' ), (string) ( $right->school?->name ?? '' ) ),
				'lunch'     => array( ! empty( $left->lunch ) ? 1 : 0, ! empty( $right->lunch ) ? 1 : 0 ),
				'status'    => array( (string) $left->status, (string) $right->status ),
			);

			$result = flzpu_admin_compare( $values[ $participant_orderby ][0], $values[ $participant_orderby ][1], $participant_order );
			if ( 0 === $result ) {
				return flzpu_admin_compare( (string) $left->name, (string) $right->name, 'asc' );
			}

			return $result;
		}
	);
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
