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
		//insert defaults
		$setting=new FlzPuSetting(array('name'=>"MaxTeilnehmerProSchule", 'value'=>"8"));
		$setting->save();
		$setting=new FlzPuSetting(array('name'=>"MaxTeilnehmerGesamt", 'value'=>"180"));
		$setting->save();

	}
	public static function get_value_by_name( string $name ): string {
		$setting = static::get_by_fields( [ 'name' => sanitize_text_field( $name ) ] );

		return $setting === null ? '' : (string) $setting->value;
	}

	protected function prepareDataForSaving(): array {
		return [
			'name' => $this->name,
			'value' => $this->value,
		];
	}
}
