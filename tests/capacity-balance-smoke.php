<?php

declare(strict_types=1);

namespace flz_wpdb_objects {
	final class FlzWpdbTransaction {
		public static array $events = array();

		public static function run( callable $callback, string $operation ) {
			self::$events[] = 'transaction:' . $operation;
			return $callback();
		}
	}
}

namespace {
	// Test-Exceptions werden ausschließlich von der CLI ausgewertet.
	// phpcs:disable WordPress.Security.EscapeOutput.ExceptionNotEscaped

	final class FlzPuSetting {
		public static int $maximum = 2;
		public static array $events = array();

		public static function lock_capacity_limit(): int {
			self::$events[] = 'lock-total';
			return self::$maximum;
		}
	}

	final class FlzPuSchool {
		public static array $schools = array();
		public int $id;
		public int $available_seats;

		public function __construct( int $id, int $available_seats ) {
			$this->id = $id;
			$this->available_seats = $available_seats;
			self::$schools[ $id ] = $this;
		}

		public static function get_by_id_for_update( int $id ): ?self {
			return self::$schools[ $id ] ?? null;
		}

		public static function get_all_by(): array {
			return array_values( self::$schools );
		}

		public function claim_seat(): void {
			if ( $this->available_seats <= 0 ) {
				throw new UnexpectedValueException( 'Kein Schulplatz verfügbar.' );
			}
			--$this->available_seats;
		}

		public function release_seat(): void {
			++$this->available_seats;
		}

		public function save(): int {
			return 1;
		}
	}

	final class FlzPuParticipant {
		public static array $records = array();
		public ?int $id = null;
		public ?FlzPuSchool $school = null;
		public string $name = '';
		public string $firstName = '';
		public string $email = '';
		public string $class = '';
		public bool $lunch = false;

		public function __construct( array $data = array() ) {
			foreach ( $data as $key => $value ) {
				$this->{$key} = $value;
			}
		}

		public static function count_by(): int {
			return count( self::$records );
		}

		public static function get_by_id_for_update( int $id ): ?self {
			return self::$records[ $id ] ?? null;
		}

		public static function get_all_by(): array {
			return array_values( self::$records );
		}

		public function save(): int {
			$this->id ??= count( self::$records ) + 1;
			self::$records[ $this->id ] = $this;
			return 1;
		}

		public function delete(): int {
			unset( self::$records[ $this->id ] );
			return 1;
		}
	}

	function is_email( string $email ): bool {
		return false !== filter_var( $email, FILTER_VALIDATE_EMAIL );
	}

	require dirname( __DIR__ ) . '/includes/class-flz-pu-registration-service.php';

	$school = new FlzPuSchool( 7, 1 );
	$participant = FlzPuRegistrationService::register(
		array(
			'name'      => 'Beispiel',
			'firstName' => 'Mia',
			'email'     => 'mia@example.test',
			'class'     => '6a',
			'lunch'     => true,
			'school_id' => 7,
		)
	);
	if ( 1 !== count( FlzPuParticipant::$records ) || 0 !== $school->available_seats ) {
		throw new RuntimeException( 'Anmeldung und Sitzabbuchung sind nicht gemeinsam erfolgt.' );
	}

	try {
		FlzPuRegistrationService::register(
			array(
				'name'      => 'Zweite',
				'firstName' => 'Person',
				'email'     => 'zweite@example.test',
				'class'     => '6b',
				'lunch'     => false,
				'school_id' => 7,
			)
		);
		throw new RuntimeException( 'Eine Anmeldung ohne Schulplatz wurde akzeptiert.' );
	} catch ( UnexpectedValueException $error ) {
		// Erwarteter Deny-Fall.
	}
	if ( 1 !== count( FlzPuParticipant::$records ) || 0 !== $school->available_seats ) {
		throw new RuntimeException( 'Der abgewiesene Vorgang hatte eine verbotene Nebenwirkung.' );
	}

	FlzPuRegistrationService::delete( $participant );
	if ( 0 !== count( FlzPuParticipant::$records ) || 1 !== $school->available_seats ) {
		throw new RuntimeException( 'Löschen und Sitzfreigabe sind nicht gemeinsam erfolgt.' );
	}

	try {
		FlzPuRegistrationService::register(
			array(
				'name'      => str_repeat( 'x', 256 ),
				'firstName' => 'Manipuliert',
				'email'     => 'lang@example.test',
				'class'     => '6a',
				'school_id' => 7,
			)
		);
		throw new RuntimeException( 'Ein überlanges Teilnehmerfeld wurde akzeptiert.' );
	} catch ( UnexpectedValueException $error ) {
		// Erwarteter Deny-Fall.
	}
	if ( 0 !== count( FlzPuParticipant::$records ) || 1 !== $school->available_seats ) {
		throw new RuntimeException( 'Die ungültige Anmeldung hatte eine verbotene Nebenwirkung.' );
	}

	FlzPuRegistrationService::register(
		array(
			'name'      => 'Reset',
			'firstName' => 'Test',
			'email'     => 'reset@example.test',
			'class'     => '6c',
			'lunch'     => false,
			'school_id' => 7,
		)
	);
	FlzPuRegistrationService::reset( 5 );
	if ( 0 !== count( FlzPuParticipant::$records ) || 5 !== $school->available_seats ) {
		throw new RuntimeException( 'Reset und neue Platzbilanz sind nicht gemeinsam erfolgt.' );
	}

	if ( array( 'lock-total', 'lock-total', 'lock-total', 'lock-total', 'lock-total' ) !== FlzPuSetting::$events ) {
		throw new RuntimeException( 'Die globale Kapazitätsgrenze wird nicht für jeden Schreibvorgang gesperrt.' );
	}

	echo "OK: flz_probeunterricht capacity balance smoke test\n";
}
