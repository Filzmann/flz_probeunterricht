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
	public string|null $created_at;
	public string|null $updated_at;

	public function __construct(array $data	) {
		parent::__construct($data);
		$this->class     = $data['class']??null;
		$this->lunch     = $data['lunch']??null;
		$this->school    = $data['school']??null;
		$this->status    = $data['status']??'pending';
		$this->activationExpiration = $data['activationExpiration']??null;
		$this->activationToken      = $data['activationToken']??null;
		$this->created_at           = $data['created_at'] ?? null;
		$this->updated_at           = $data['updated_at'] ?? null;
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
			'activationToken' => $this->activationToken,
			'created_at' => $this->created_at,
			'updated_at' => $this->updated_at,
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
	        created_at DATETIME NULL,
	        updated_at DATETIME NULL,
	        PRIMARY KEY (id)
        )
        ";
	}

	public static function get_by_id_for_update(int $id): ?self {
		$sql = 'SELECT * FROM ' . static::table_name() . ' WHERE id = %d FOR UPDATE';
		$models = static::query_models($sql, array($id), 'Sperren eines Probeunterrichtsteilnehmenden');

		return $models[0] ?? null;
	}

	/** @return array<int,self> */
	public static function find_expired(string $cutoff): array {
		$sql = 'SELECT * FROM ' . static::table_name()
			. ' WHERE created_at IS NOT NULL AND created_at < %s ORDER BY id ASC';

		return static::query_models($sql, array($cutoff), 'Laden abgelaufener Probeunterrichtsanmeldungen');
	}

	public static function reset( int $new_available_seats = 8 ): void {
		FlzPuRegistrationService::reset($new_available_seats);
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

}
