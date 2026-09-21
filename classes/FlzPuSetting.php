<?php

use flz_wpdb_objects\FlzWpdbObject;

class FlzPuSetting extends FlzWpdbObject {

	public string $name;
	public string $value;

	public function __construct(array $data ) {
		parent::__construct($data['id']??null);
		$this->name  = $data['name'];
		$this->value = $data['value'];
	}

	protected static function get_table_schema(): string {
		return "(id INT(11) NOT NULL AUTO_INCREMENT,
            name VARCHAR(255) NOT NULL,
            value VARCHAR(255) NOT NULL,
            PRIMARY KEY (id))";
	}

	protected static function afterCreate(): void {
		$defaults = array(
			'MaxTeilnehmerProSchule' => '8',
			'MaxTeilnehmerGesamt' => '180',
		);
		flz_wpdb_objects\FlzWpdbTransaction::run(
			static function () use ( $defaults ): void {
				foreach ( $defaults as $name => $value ) {
					if ( static::get_by_fields( array( 'name' => $name ) ) === null ) {
						( new FlzPuSetting( array( 'name' => $name, 'value' => $value ) ) )->save();
					}
				}
			},
			'Anlegen fehlender Probeunterrichts-Standardeinstellungen'
		);

	}
	public static function get_value_by_name( string $name ): string {
		$setting = static::get_by_fields( [ 'name' => sanitize_text_field( $name ) ] );

		return $setting === null ? '' : (string) $setting->value;
	}

	public static function lock_capacity_limit(): int {
		$sql = 'SELECT * FROM ' . static::table_name() . ' WHERE name = %s FOR UPDATE';
		$models = static::query_models($sql, array('MaxTeilnehmerGesamt'), 'Sperren der globalen Probeunterrichtskapazität');
		if (!isset($models[0]) || !$models[0] instanceof self) {
			throw new UnexpectedValueException('Die globale Probeunterrichtskapazität ist nicht konfiguriert.');
		}

		return max(0, (int) $models[0]->value);
	}

	protected function prepareDataForSaving(): array {
		return [
			'name' => $this->name,
			'value' => $this->value,
		];
	}
}
