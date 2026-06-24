<?php

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

	if ( isset( $_POST['settings'] ) ) {
		foreach ( $_POST['settings'] as $id => $value ) {
			$value          = sanitize_text_field( $value );
			$setting        = FlzPuSetting::get_by_id( $id );
			$setting->value = $value;
			$setting->save();
		}
	}
	$settings = FlzPuSetting::get_all_by();
	include( plugin_dir_path( __FILE__ ) . 'templates/settings.php' );
}


// Funktion zur Anzeige der Schulen-Seite im Backend
function flzpu_schools_page(): void {

    $unprocessed=processSchoolsCsvFile();
    if($unprocessed)
    {
        echo "Folgende Datensätze konnten nicht verarbeitet werden:<br>";
        echo"<textarea cols='100' rows='5'>";
        foreach ($unprocessed as $line)
            echo implode(";",$line)."\n";
        echo"</textarea>";
    }
	if ( isset( $_POST['school_edit'] ) ) {
		$school_id       = intval( $_POST['school_id'] );
		$selected_school = FlzPuSchool::get_by_id( $school_id );
	} else {
		$selected_school = new FlzPuSchool( [] );
	}
	if ( isset( $_POST['school_submit'] ) ) {
		$name            = sanitize_text_field( $_POST['name'] );
		$available_seats = intval( $_POST['available_seats'] );

		if ( ! empty( $name ) ) {
			$school_id = $_POST['school_id'] ? intval( $_POST['school_id'] ) : null;
			$school    = new FlzPuSchool( array(
				'name'            => $name,
				'available_seats' => $available_seats,
				'id'              => $school_id
			) );
			$school->save();
		}

	}
	if ( isset( $_POST['school_delete'] ) ) {
		$school_id = intval( $_POST['school_delete'] );
		$school    = FlzPuSchool::get_by_id( $school_id );
		$school->delete();
	}





	$schools = FlzPuSchool::get_all_by( order_by: 'name' );
	include( plugin_dir_path( __FILE__ ) . 'templates/schools.php' );
}

// Funktion zur Anzeige der Teilnehmer-Seite im Backend
function flzpu_participants_page(): void {

	if ( isset( $_POST['reset_participants'] ) ) {
		$new_available_seats = intval( $_POST['available_seats'] );
		FlzPuParticipant::reset( $new_available_seats );
	}

	if ( isset( $_POST['participant_delete'] ) ) {
		$participant = FlzPuParticipant::get_by_id( intval( $_POST['participant_delete'] ) );
		$participant->delete();
	}

	if ( isset( $_POST['participant_edit'] ) ) {
		$participant_edit = FlzPuParticipant::get_by_id( intval( $_POST['participant_edit'] ) );
	} else {
		$participant_edit             = new FlzPuParticipant( [] );
		$participant_edit->school     = new FlzPuSchool( [] );
		$participant_edit->school->id = 9999;
	}

	/** Speichern des bearbeiteten participant  */


	if ( isset( $_POST['participant_save'] ) ) {
		$participant_save_post=$_POST['participant_save'];

		$school=FlzPuSchool::get_by_id($participant_save_post['school_id']);
		if($participant_save_post['old_school_id']!=$participant_save_post['school_id'])
		{
			$old_school=FlzPuSchool::get_by_id($participant_save_post['old_school_id']);
			$old_school->free_seat();
			$school->take_seat();
		}
		unset($participant_save_post['school_id']);
		$participant_save=$participant_save_post['id']?FlzPuParticipant::get_by_id(intval($participant_save_post['id'])):new FlzPuParticipant([]);
		$participant_save->assignPostData(
			$participant_save_post,
			[ 'name', 'firstName', 'email', 'class', 'lunch' ]
		);
		$participant_save->school=$school;
		$participant_save->save();

	}


	// Anzeige der Teilnehmer-Tabelle
	$participants = FlzPuParticipant::get_all_by( order_by: 'name' );
	$schools      = FlzPuSchool::get_all_by( order_by: 'name' );
	array_unshift( $schools, new FlzPuSchool( [
		'id'              => 9999,
		'name'            => 'Bitte Grundschule auswählen',
		'available_seats' => 0
	] ) );
	// CSV-Download-Link
	$csv_data = array();
	foreach ( $participants as $participant ) {
		$csv_data[] = array(
			'ID'           => $participant->id,
			'Name'         => $participant->name,
			'Vorname'      => $participant->firstName,
			'Klasse'       => $participant->class,
			'Email Eltern' => $participant->email,
			'Schule'       => $participant->school->name,
			'Mittagessen'  => ( $participant->lunch ? 'Ja' : 'Nein' ),
			'Status'       => $participant->status
		);
	}

	$csv_file = fopen( 'probeunterricht.csv', 'w' );
	if ( ! empty( $csv_data ) ) {
		fputcsv( $csv_file, array_keys( $csv_data[0] ) ); // CSV-Header schreiben
		foreach ( $csv_data as $data ) {
			fputcsv( $csv_file, $data, ";" ); // CSV-Daten schreiben
		}
	}
	fclose( $csv_file );


	include( plugin_dir_path( __FILE__ ) . 'templates/participants.php' );
}

function processSchoolsCsvFile(): array|null {
    $unprocessedLines=array();

    try {
        $isFormSubmitted = isset($_POST['submit_csv']);
        if (!$isFormSubmitted) {
            return null;
        }
        if (isset($_FILES['schools-csv']['tmp_name'])) {
            $fileOriginalName = $_FILES['schools-csv']['name'];
            // sanitize the file input
            $filePath = $_FILES['schools-csv']['tmp_name'];
            //verify if the file is a CSV file
            if (pathinfo($fileOriginalName, PATHINFO_EXTENSION) != 'csv') {
                throw new Exception('Das ist keine csv-Datei');
            }
            //open the file
            $fileHandle = fopen($filePath, 'r');
            if (!$fileHandle) {
                throw new Exception("Konnte die Datei nicht öffnen");
            }
            fgetcsv($fileHandle);
            //read each line and make a new teacher object
            while(($line = fgetcsv($fileHandle, separator: ';')) !== false) {
                // Assuming FlzEstTeachers accepts an array to create a new teacher

                $school= FlzPuSchool::get_by_name($line[0])?:new FlzPuSchool([
                    'name'=>$line[0],
                ]);
                $school->available_seats=$line[1];
                $school->save();
            }

            fclose($fileHandle);

        } else {
            throw new Exception("Keine Datei hochgeladen");
        }
    } catch (Exception $e) {
        // Echoing the script tag with alert to show the error message
        echo '<script>alert("'.$e->getMessage().'")</script>';
    }
    return $unprocessedLines;
}

// Hinzufügen der Backend-Menüs
add_action( 'admin_menu', 'flzpu_probeunterricht_menu' );
