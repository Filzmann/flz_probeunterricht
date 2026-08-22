<?php

if ( PHP_SAPI !== 'cli' ) {
	http_response_code( 404 );
	exit;
}

// Test-Exceptions werden ausschließlich von der CLI ausgewertet.
// phpcs:disable WordPress.Security.EscapeOutput.ExceptionNotEscaped

$root = dirname( __DIR__ );
$bootstrap = file_get_contents( $root . '/flz_probeunterricht.php' );
$activation = file_get_contents( $root . '/activate-deactivate.php' );
$participant = file_get_contents( $root . '/classes/FlzPuParticipant.php' );
$migration_file = $root . '/includes/class-flz-pu-schema-migrator.php';

if ( false === $bootstrap || false === $activation || false === $participant ) {
	throw new RuntimeException( 'Die Quellen des Schemavertrags konnten nicht gelesen werden.' );
}
if ( ! is_file( $migration_file ) ) {
	throw new RuntimeException( 'Der versionierte Schema-Migrator fehlt.' );
}
$migration = file_get_contents( $migration_file );
if ( false === $migration ) {
	throw new RuntimeException( 'Der Schema-Migrator konnte nicht gelesen werden.' );
}

foreach (
	array(
		"const FLZPU_VERSION = '1.1.0'"            => $bootstrap,
		"const FLZPU_DB_VERSION = '2.1.0'"         => $bootstrap,
		'FlzPuSchemaMigrator::maybe_upgrade()'      => $activation,
		'created_at DATETIME NULL'                  => $participant,
		'updated_at DATETIME NULL'                  => $participant,
		"get_option('flzpu_db_version'"            => $migration,
		"update_option('flzpu_db_version'"         => $migration,
		"'flzpuparticipants'"                      => $migration,
		"'flzpuschools'"                           => $migration,
	) as $needle => $source
) {
	if ( ! str_contains( $source, $needle ) ) {
		throw new RuntimeException( 'Der additive Migrationsvertrag fehlt: ' . $needle );
	}
}

if ( str_contains( $migration, 'DROP TABLE' ) || str_contains( $migration, 'delete_table' ) ) {
	throw new RuntimeException( 'Der Migrator entfernt Legacy-Tabellen und damit den Rückbaupfad.' );
}

echo "OK: flz_probeunterricht schema migration smoke test\n";
