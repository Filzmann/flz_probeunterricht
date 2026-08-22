<?php

// Exception-Texte sind interne Logdaten; HTML-Escaping erfolgt erst an der UI-Grenze.
// phpcs:disable WordPress.Security.EscapeOutput.ExceptionNotEscaped

use flz_wpdb_objects\FlzWpdbObject;
use flz_wpdb_objects\FlzWpdbObjectsException;

class FlzPuSchool extends FlzWpdbObject {
	const example_schools = [
		'Adam Ries GS',
		'Beatrix-Potter-GS',
		'Bernhard-Grzimek-GS',
		'Best-Sabel GS Kaulsdorf',
		'Best-Sabel GS Mahlsdorf',
		'bip GS',
		'Bötzow GS',
		'Brodowin GS',
		'Bücherwurm GS',
		'Bürgermeister Ziethen GS',
		'Ebereschen-GS',
		'Evangelische Schule Lichtenberg',
		'Falken GS',
		'Feldmark-GS',
		'Franz-Carl-Achard-GS',
		'Friedrich Schiller GS',
		'Friedrichsfelder GS',
		'Grüner Campus Malchow',
		'Grzimek GS',
		'GS am Bürgerpark',
		'GS am Fuchsberg',
		'GS am Gutspark',
		'GS am Hollerbusch',
		'GS am Schleipfuhl',
		'GS am Traveplatz',
		'GS am Wäldchen',
		'GS am Wilhelmsberg',
		'GS an der Geißenweide',
		'GS an der Mühle',
		'GS an der Wuhle',
		'GS im Gutspark',
		'GS unter dem Regenbogen',
		'GS unter dem Hollerbusch',
		'Hans Rosenthal GS',
		'Hermann Gmeiner Schule',
		'Jane Goodall GS',
		'Johann-Strauß-GS',
		'Karl-Friedrich-Friesen-GS'
	];

	public string|null $name;
	public int|null $available_seats;
	public int|null $capacity;

	public function __construct( $data ) {
		parent::__construct($data['id']??null);
		$this->name     = $data['name']??null;
		$this->available_seats = $data['available_seats']??null;
		$this->capacity = isset($data['capacity'])
			? max(0, (int) $data['capacity'])
			: max(0, (int) ($data['available_seats'] ?? 0));
	}

	public static function get_by_name( string $name ): object|null {
		return static::get_by_fields( [ 'name' => sanitize_text_field( $name ) ] );
	}

	protected static function get_table_schema(): string {
		return "(
			id INT(11) NOT NULL AUTO_INCREMENT,
            name VARCHAR(255) NOT NULL,
            available_seats INT(11) NOT NULL,
			capacity INT(11) NOT NULL DEFAULT 0,
            PRIMARY KEY (id)
            )";
	}

	protected function prepareDataForSaving(): array {
		return [
			'name' => $this->name,
			'available_seats' => $this->available_seats,
			'capacity' => $this->capacity,
		];
	}

	protected static function afterCreate(): void {
		//insert defaults
		if ( FlzPuSchool::count_by() == 0 ) {
			flz_wpdb_objects\FlzWpdbTransaction::run(
				static function (): void {
					foreach ( FlzPuSchool::example_schools as $example_school ) {
						$school=new FlzPuSchool(array(
							'name'     => $example_school,
							'available_seats' => 8,
							'capacity' => 8,
						));
						$school->save();
					}
				},
				'Anlegen der Standard-Grundschulen'
			);
		}
	}


	public static function get_by_id_for_update(int $id): ?self {
		$sql = 'SELECT * FROM ' . static::table_name() . ' WHERE id = %d FOR UPDATE';
		$models = static::query_models($sql, array($id), 'Sperren einer Grundschule für die Platzbuchung');

		return $models[0] ?? null;
	}

	public function claim_seat(): void {
		if ($this->available_seats === null || $this->available_seats <= 0) {
			throw new UnexpectedValueException('Für die ausgewählte Grundschule ist kein freier Platz verfügbar.');
		}
		--$this->available_seats;
		$this->save();
	}

	public function release_seat(): void {
		++$this->available_seats;
		$this->save();
	}

}
