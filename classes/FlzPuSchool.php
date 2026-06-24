<?php


use flz_wpdb_objects\FlzWpdbObject;

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

	public function __construct( $data ) {
		parent::__construct($data['id']??null);
		$this->name     = $data['name']??null;
		$this->available_seats = $data['available_seats']??null;
	}

	public static function get_by_name( string $name ): object|null {
		return static::get_by_fields( [ 'name' => sanitize_text_field( $name ) ] );
	}

	protected static function get_table_schema(): string {
		return "(
			id INT(11) NOT NULL AUTO_INCREMENT,
            name VARCHAR(255) NOT NULL,
            available_seats INT(11) NOT NULL,
            PRIMARY KEY (id)
            )";
	}

	protected function prepareDataForSaving(): array {
		return [
			'name' => $this->name,
			'available_seats' => $this->available_seats,
		];
	}

	protected static function afterCreate(): void {
		//insert defaults
		if ( FlzPuSchool::count_by() == 0 ) {
			foreach ( FlzPuSchool::example_schools as $example_school ) {
				$school=new FlzPuSchool(array(
					'name'     => $example_school,
					'available_seats' => 8
				));
				$school->save();
			}
		}
	}


	public function take_seat(): void {
		$this->available_seats -= 1;
		$this->save();
	}

	public function free_seat(): void {
		$this->available_seats += 1;
		$this->save();
	}


    public static function get_csv_link():string{
        // CSV-Download-Link
		$schools=static::get_all_by( order_by: 'name' );


        $csv_data = array();
        foreach ( $schools as $school ) {
            $csv_data[] = array(
                'name'         => $school->name,
                'available_seats' => $school->available_seats,
            );
        }

        $csv_file = fopen( 'schools.csv', 'w' );
        if ( ! empty( $csv_data ) ) {
            fputcsv( $csv_file, array_keys( $csv_data[0] ) ); // CSV-Header schreiben
            foreach ( $csv_data as $data ) {
                fputcsv( $csv_file, $data, ";" ); // CSV-Daten schreiben
            }
        }
        fclose( $csv_file );
        return admin_url()."schools.csv";
    }

}
