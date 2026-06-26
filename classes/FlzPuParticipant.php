<?php

// Exception-Texte sind interne Logdaten; HTML-Escaping erfolgt erst an der UI-Grenze.
// phpcs:disable WordPress.Security.EscapeOutput.ExceptionNotEscaped

class FlzPuParticipant extends FlzPerson{
	
	public int|null $id;
	public string|null $class;
	public bool|null $lunch;
	public FlzPuSchool|null $school;
	public string|null $status;
	public string|null $activationExpiration;
	public string|null $activationToken;

	public function __construct(array $data	) {
		parent::__construct($data);
		$this->class     = $data['class']??null;
		$this->lunch     = $data['lunch']??null;
		$this->school    = $data['school']??null;
		$this->status    = $data['status']??'pending';
		$this->activationExpiration = $data['activationExpiration']??null;
		$this->activationToken      = $data['activationToken']??null;
	}
	protected function prepareDataForSaving(): array {
		return [
			'name' => $this->name,
			'firstName' => $this->firstName,
			'email' => $this->email,
			'class' => $this->class,
			'lunch' => $this->lunch,
			'school_id' => $this->school->id,
			'status'=> $this->status,
			'activationExpiration' => $this->activationExpiration,
			'activationToken' => $this->activationToken
		];
	}

	protected static function get_table_schema(): string {
		return "(
			id INT(11) NOT NULL AUTO_INCREMENT,
	        name VARCHAR(255) NULL,
	        firstName VARCHAR(255) NULL,
	        class VARCHAR(255) NULL,
	        lunch BOOLEAN NULL,
	        email VARCHAR(255) NULL,
	        school_id INT(11) NULL,
	        status VARCHAR(25) DEFAULT 'pending',
	        activationExpiration TIMESTAMP,
	        activationToken VARCHAR(255) NULL,
	        PRIMARY KEY (id),
	        FOREIGN KEY (school_id) REFERENCES " . FlzPuSchool::table_name() . "(id)
        )
        ";
	}

	public static function reset( int $new_available_seats = 8 ): void {
		flz_wpdb_objects\FlzWpdbTransaction::run(
			static function () use ( $new_available_seats ): void {
				foreach ( FlzPuParticipant::get_all_by() as $participant ) {
					$participant->delete();
				}
				$schools = FlzPuSchool::get_all_by();
				foreach ( $schools as $school ) {
					$school->available_seats = max( 0, $new_available_seats );
					$school->save();
				}
			},
			'Leeren der Probeunterrichtsteilnehmer und Zurücksetzen der Schulplätze'
		);
	}

	public function generate_activation_link(): string {
		$this->activationToken = wp_generate_password( 48, false, false );

		// Speichere den Token und das Ablaufdatum in der Datenbank
		$this->activationExpiration = gmdate( 'Y-m-d H:i:s', time() + 2 * DAY_IN_SECONDS );
		$this->save();

		// Baue den Aktivierungslink
		return add_query_arg(
			array(
				'id' => $this->id,
				'token' => $this->activationToken,
			),
			get_permalink()
		);
	}

	function send_activation_email(): void {
		$activation_link = $this->generate_activation_link();
		// E-Mail-Inhalt erstellen
		$subject = 'Probeunterricht am Tagore Gymnasium';
		$message = 'Bitte klicken Sie auf den folgenden Link, um die Teilnahme am Probeunterricht zu bestätigen: <br /> ' . $activation_link;

		// E-Mail versenden
		if ( ! wp_mail( $this->email, $subject, $message ) ) {
			throw flzpu_operation_error(
				new RuntimeException( 'wp_mail() hat die Aktivierungs-E-Mail nicht angenommen.' ),
				'Senden der Aktivierungs-E-Mail für Teilnehmer-ID ' . (string) $this->id
			);
		}
	}

	public function activate( $token ): string {
		$expires_at = strtotime( (string) $this->activationExpiration );
		$token_valid = is_string( $token )
			&& $this->activationToken !== null
			&& hash_equals( $this->activationToken, $token );
		if ( $token_valid && $expires_at !== false && $expires_at >= time() ) {
			$this->status = 'active';
			$this->activationToken = null;
			$this->activationExpiration = null;
			$this->save();

			return flz_ui()->notice( 'Der Teilnehmer wurde aktiviert. Sie können das Fenster jetzt schließen!', 'success' );
		} else {
			return flz_ui()->notice( 'Fehler bei der Aktivierung. Bitte Link nochmal testen oder Teilnehmer erneut registrieren.', 'error' );
		}
	}

	public function getCsvLine(): string {
		$values = array(
			$this->id,
			$this->name,
			$this->firstName,
			$this->class,
			$this->email,
			$this->school?->name,
			$this->lunch ? 'Ja' : 'Nein',
			$this->status,
		);
		$values = array_map(
			static fn( $value ): string => '"' . str_replace( '"', '""', (string) $value ) . '"',
			$values
		);

		return implode( ';', $values );
	}
	protected static function afterInsert(): void {
		//FlzPuSchool::take_seat();
	}
	protected static function afterDelete(): void {
		//FlzPuSchool::free_seat();
	}


}
