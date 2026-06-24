<?php

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
		FlzPuParticipant::truncate_table();
		$schools = FlzPuSchool::get_all_by();
		foreach ( $schools as $school ) {
			$school->available_seats = max( 0, $new_available_seats );
			$school->save();
		}
	}

	public function generate_activation_link(): string {
		// Generiere einen eindeutigen Token
		$this->activationToken = md5( uniqid() );

		// Speichere den Token und das Ablaufdatum in der Datenbank
		$this->activationExpiration = date( 'Y-m-d H:i:s', strtotime( '+48 hours' ) );
		$this->save();

		// Baue den Aktivierungslink
		return get_permalink() . '?id=' . $this->id . '&token=' . $this->activationToken;
	}

	function send_activation_email(): void {
		$activation_link = $this->generate_activation_link();
		// E-Mail-Inhalt erstellen
		$subject = 'Probeunterricht am Tagore Gymnasium';
		$message = 'Bitte klicken Sie auf den folgenden Link, um die Teilnahme am Probeunterricht zu bestätigen: <br /> ' . $activation_link;

		// E-Mail versenden
		wp_mail( $this->email, $subject, $message );
	}

	public function activate( $token ): string {
		if ( $this->activationToken === $token ) {
			$this->status = 'active';
			$this->save();

			return "<div style='font-size: 2em; background-color:lawngreen;'>Der Teilnehmer wurde aktiviert. Sie können das Fenster jetzt schließen!</div>";
		} else {
			return "<div style='font-size: 2em; background-color:red;'>Fehler bei der Aktivierung. Bitte Link nochmal testen oder Teilnehmer erneut registrieren. </div>";
		}
	}
	protected static function afterInsert(): void {
		//FlzPuSchool::take_seat();
	}
	protected static function afterDelete(): void {
		//FlzPuSchool::free_seat();
	}


}
