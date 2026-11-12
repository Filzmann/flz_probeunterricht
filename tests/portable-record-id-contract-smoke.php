<?php

require_once dirname( __DIR__ ) . '/includes/class-flz-pu-csv-contract.php';

if ( ! in_array( 'Datensatz-ID', FlzPuCsvContract::school_header(), true ) || ! in_array( 'Datensatz-ID', FlzPuCsvContract::participant_header(), true ) ) {
	throw new RuntimeException( 'Alle Probeunterricht-CSV-Exporte benötigen eine portable Datensatz-ID.' );
}

echo "OK: portable record IDs in Probeunterricht CSV contracts\n";
