<?php

use flz_wpdb_objects\FlzWpdbObject;

class FlzPuSetting extends FlzWpdbObject {

	public string $name;
	public string $value;

	public function __construct(array $data ) {
		//write_log("in construct");
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
		//write_log("in afterCreate");
		$setting=new FlzPuSetting(array('name'=>"MaxTeilnehmerProSchule", 'value'=>"8"));
		$setting->save();
		$setting=new FlzPuSetting(array('name'=>"MaxTeilnehmerGesamt", 'value'=>"180"));
		$setting->save();

	}
	public static function get_value_by_name( string $name ): string {
		global $wpdb;
		$setting = $wpdb->get_row( "SELECT * FROM " . static::table_name() . " WHERE name = '$name'" );
		return $setting->value;
	}

	protected function prepareDataForSaving(): array {
		return [
			'name' => $this->name,
			'value' => $this->value,
		];
	}
}