<?php

function flzpu_probeunterricht_form($atts): string
{
	$out = '';
	$registration_saved = false;
	    $atts = shortcode_atts(
        array(
            'essen' => false, // Standardwert für den Parameter "essen" ist false
            'danke' => ''
        ),
        $atts
    );
    $essen = filter_var($atts['essen'], FILTER_VALIDATE_BOOLEAN); // Konvertiere den Wert des Parameters in einen boolschen Wert


	try {
		// Aktivierungslink geklickt.
		if ( isset( $_GET['id'], $_GET['token'] ) ) {
			$participant = FlzPuParticipant::get_by_id( absint( wp_unslash( $_GET['id'] ) ) );
			if ( ! $participant instanceof FlzPuParticipant ) {
				$out .= '<p class="flz-pu-error">Der Aktivierungslink ist ungültig.</p>';
			} else {
				$out .= $participant->activate( sanitize_text_field( wp_unslash( $_GET['token'] ) ) );
			}
		}

		if ( isset( $_POST['participant'] ) ) {
			if (
				! isset( $_POST['flzpu_nonce'] )
				|| ! wp_verify_nonce(
					sanitize_text_field( wp_unslash( $_POST['flzpu_nonce'] ) ),
					'flzpu_register_participant'
				)
			) {
				throw new RuntimeException( 'Die Nonce-Prüfung der Probeunterrichtsanmeldung ist fehlgeschlagen.' );
			}

			$participant_post = map_deep( wp_unslash( $_POST['participant'] ), 'sanitize_text_field' );
			if ( ! is_array( $participant_post ) ) {
				throw new UnexpectedValueException( 'Die Teilnehmerdaten besitzen kein gültiges Array-Format.' );
			}
			$school_id = isset( $participant_post['school_id'] ) ? absint( $participant_post['school_id'] ) : 0;
			$school = FlzPuSchool::get_by_id( $school_id );
			if ( ! $school instanceof FlzPuSchool ) {
				throw new UnexpectedValueException( 'Die ausgewählte Grundschule wurde nicht gefunden.' );
			}
			if (
				FlzPuParticipant::count_by()
				>= (int) FlzPuSetting::get_value_by_name( 'MaxTeilnehmerGesamt' )
			) {
				$out .= '<p class="flz-pu-error">Die maximale Teilnehmerzahl ist bereits erreicht.</p>';
			} else {
				unset( $participant_post['school_id'] );
				$participant = new FlzPuParticipant( array() );
				$participant->assignPostData(
					$participant_post,
					array( 'name', 'firstName', 'email', 'class', 'lunch' )
				);
				$participant->school = $school;
				flz_wpdb_objects\FlzWpdbTransaction::run(
					static function () use ( $participant, $school ): void {
						$participant->save();
					$school->take_seat();
					},
					'Speichern einer Probeunterrichtsanmeldung und Reservieren des Schulplatzes'
				);

				$registration_saved = true;
				try {
					$participant->send_activation_email();
					$out .= '<p class="flz-pu-success">Danke, die Anmeldung und die Aktivierungs-E-Mail wurden versendet.</p>';
				} catch ( Throwable $mail_error ) {
					flzpu_log_error( $mail_error, 'Versenden der Aktivierungs-E-Mail nach gespeicherter Anmeldung' );
					$out .= '<p class="flz-pu-error">Die Anmeldung wurde gespeichert, aber die Aktivierungs-E-Mail konnte nicht versendet werden. Bitte kontaktieren Sie die Schule.</p>';
				}

				if ( ! empty( $atts['danke'] ) ) {
					$thank_you_page_url = get_permalink( absint( $atts['danke'] ) );
					if ( ! is_string( $thank_you_page_url ) || ! wp_safe_redirect( $thank_you_page_url ) ) {
						throw new RuntimeException( 'Die Weiterleitung zur Danke-Seite ist fehlgeschlagen.' );
					}
					exit;
				}
			}
		}

		$max_reached = (int) FlzPuSetting::get_value_by_name( 'MaxTeilnehmerGesamt' )
			- FlzPuParticipant::count_by() <= 0;
		$schools = FlzPuSchool::get_all_by( order_by: 'name' );
	} catch ( Throwable $error ) {
		flzpu_log_error( $error, 'Verarbeiten der öffentlichen Probeunterrichtsseite' );
		$out .= '<p class="flz-pu-error">Die Anfrage konnte wegen eines technischen Fehlers nicht verarbeitet werden. Bitte später erneut versuchen.</p>';
		$max_reached = true;
		$schools = array();
	}

	ob_start();
	echo wp_kses_post( $out );
	if ( ! $registration_saved ) {
		include plugin_dir_path( __FILE__ ) . 'templates/frontend-form.php';
	}
	return (string) ob_get_clean();
}



// Hinzufügen des Formulars im Frontend
add_shortcode( 'flzpu', 'flzpu_probeunterricht_form' );
