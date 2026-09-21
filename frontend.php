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
			$participant = FlzPuRegistrationService::register(
				$participant_post + array( 'school_id' => $school_id )
			);

			$registration_saved = true;
			try {
				$participant->send_activation_email();
				$out .= flz_ui()->notice( 'Danke, die Anmeldung und die Aktivierungs-E-Mail wurden versendet.', 'success' );
			} catch ( Throwable $mail_error ) {
				flzpu_log_error( $mail_error, 'Versenden der Aktivierungs-E-Mail nach gespeicherter Anmeldung' );
				$out .= flz_ui()->notice( 'Die Anmeldung wurde gespeichert, aber die Aktivierungs-E-Mail konnte nicht versendet werden. Bitte kontaktieren Sie die Schule.', 'error' );
			}

			if ( ! empty( $atts['danke'] ) ) {
				$thank_you_page_url = get_permalink( absint( $atts['danke'] ) );
				if ( ! is_string( $thank_you_page_url ) || ! wp_safe_redirect( $thank_you_page_url ) ) {
					throw new RuntimeException( 'Die Weiterleitung zur Danke-Seite ist fehlgeschlagen.' );
				}
				exit;
			}
		}

		$max_reached = (int) FlzPuSetting::get_value_by_name( 'MaxTeilnehmerGesamt' )
			- FlzPuParticipant::count_by() <= 0;
		$schools = FlzPuSchool::get_all_by( order_by: 'name' );
	} catch ( Throwable $error ) {
		flzpu_log_error( $error, 'Verarbeiten der öffentlichen Probeunterrichtsseite' );
		$out .= flz_ui()->notice( 'Die Anfrage konnte wegen eines technischen Fehlers nicht verarbeitet werden. Bitte später erneut versuchen.', 'error' );
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

/**
 * Verarbeitet Aktivierungslinks unabhängig von der Seite mit dem Anmeldeformular.
 */
function flzpu_handle_activation_request(): void
{
	if ( ! isset( $_GET['flzpu_activate'] ) ) {
		return;
	}

	if ( '1' !== sanitize_text_field( wp_unslash( $_GET['flzpu_activate'] ) ) ) {
		return;
	}

	try {
		$participant = isset( $_GET['id'] )
			? FlzPuParticipant::get_by_id( absint( wp_unslash( $_GET['id'] ) ) )
			: null;
		if ( ! $participant instanceof FlzPuParticipant || ! isset( $_GET['token'] ) ) {
			$message = flz_ui()->notice( 'Der Aktivierungslink ist ungültig.', 'error' );
		} else {
			$message = $participant->activate( sanitize_text_field( wp_unslash( $_GET['token'] ) ) );
		}
	} catch ( Throwable $error ) {
		flzpu_log_error( $error, 'Verarbeiten eines Aktivierungslinks' );
		$message = flz_ui()->notice( 'Die Aktivierung konnte wegen eines technischen Fehlers nicht verarbeitet werden. Bitte später erneut versuchen.', 'error' );
	}

	wp_die(
		wp_kses_post( $message ),
		esc_html__( 'Aktivierung', 'flz-probeunterricht' ),
		array( 'response' => 200 )
	);
}

add_action( 'template_redirect', 'flzpu_handle_activation_request' );



// Hinzufügen des Formulars im Frontend
add_shortcode( 'flzpu', 'flzpu_probeunterricht_form' );

/**
 * Registriert den Gutenberg-Block für das Probeunterrichtsformular.
 */
function flzpu_register_blocks(): void {
	if ( ! function_exists( 'flz_ui_register_shortcode_block' ) ) {
		return;
	}

	flz_ui_register_shortcode_block( array(
		'name'        => 'flz/probeunterricht',
		'shortcode'   => 'flzpu',
		'title'       => 'FLZ Probeunterricht',
		'description' => 'Anmeldeformular für den Probeunterricht.',
		'icon'        => 'welcome-learn-more',
		'keywords'    => array( 'probeunterricht', 'anmeldung', 'flz' ),
		'attributes'  => array(
			'essen' => array(
				'type'    => 'boolean',
				'default' => false,
			),
			'danke' => array(
				'type'    => 'string',
				'default' => '',
			),
		),
		'fields'      => array(
			'essen' => array(
				'label'       => 'Mittagessen abfragen',
				'description' => 'Blendet die Mittagessen-Auswahl im Formular ein.',
			),
			'danke' => array(
				'label'       => 'Danke-Seite-ID',
				'description' => 'Optional: WordPress-Seiten-ID für die Weiterleitung nach erfolgreicher Anmeldung.',
			),
		),
	) );
}

add_action( 'init', 'flzpu_register_blocks' );
add_action('wp_enqueue_scripts', 'flzpu_maybe_enqueue_frontend_ui_assets');

function flzpu_maybe_enqueue_frontend_ui_assets(): void
{
	global $post;

	$content = is_object($post) && isset($post->post_content) ? (string) $post->post_content : '';
	if (
		(has_shortcode($content, 'flzpu') || has_block('flz/probeunterricht', $content))
		&& function_exists('flz_ui_components_enqueue_assets')
	) {
		flz_ui_components_enqueue_assets();
	}
}
