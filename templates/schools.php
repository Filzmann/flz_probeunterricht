<?php
$flzpu_ui = flz_ui();
$flzpu_school_row = static function ( FlzPuSchool $school ) use ( $flzpu_ui ): string {
	$is_new = empty( $school->id );
	$row_id = $is_new ? 'flzpu-school-new' : 'flzpu-school-' . (int) $school->id;
	$delete_action = '';

	if ( ! $is_new ) {
		$delete_action .= $flzpu_ui->action_form_button(
			array(
				'preset' => 'delete',
				'label'  => 'Schule löschen',
				'method' => 'post',
				'nonce'  => 'flzpu_admin_action',
				'hidden' => array( 'school_delete' => $school->id ),
			)
		);
	}

	return $flzpu_ui->editable_row(
		array(
			'id'         => $row_id,
			'new'        => $is_new,
			'row_hidden' => $is_new,
			'form'       => array(
				'method' => 'post',
				'nonce'  => 'flzpu_admin_action',
				'hidden' => array( 'school_id' => $school->id ?? '' ),
			),
			'cells'      => array(
				array(
					'view'  => $school->name ?? '',
					'field' => array(
						'type'     => 'text',
						'name'     => 'name',
						'label'    => 'Name',
						'value'    => $school->name ?? '',
						'required' => true,
					),
				),
				array(
					'view'  => $school->available_seats ?? '',
					'field' => array(
						'type'     => 'number',
						'name'     => 'available_seats',
						'label'    => 'Freie Plätze',
						'value'    => $school->available_seats ?? '',
						'required' => true,
						'min'      => 0,
					),
				),
			),
			'edit'       => array( 'label' => 'Schule bearbeiten' ),
			'save'       => array(
				'label' => 'Schule speichern',
				'attrs' => array( 'name' => 'school_submit' ),
			),
			'extra_actions' => $delete_action,
		)
	);
};
?>
<div class="wrap">
	<h1>Schulen bearbeiten</h1>
	<div style="width: 100%; overflow: auto; max-height: 24em;">
		<p>
			<?php echo $flzpu_ui->button_new( array( 'label' => 'Neue Schule anlegen', 'attrs' => array( 'data-flz-ui-show-new-row' => 'flzpu-school-new' ) ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Renderer escaped die Komponente. ?>
		</p>
		<table>
			<thead>
				<tr>
					<th>Name</th>
					<th>Freie Plätze</th>
					<th>Aktionen</th>
				</tr>
			</thead>
			<tbody>
				<?php echo $flzpu_school_row( new FlzPuSchool( array() ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Renderer escaped die Tabellenzeile. ?>
				<?php foreach ( $schools as $school ) : ?>
					<?php echo $flzpu_school_row( $school ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Renderer escaped die Tabellenzeile. ?>
				<?php endforeach; ?>
			</tbody>
		</table>
	</div>
</div>
<?php
// phpcs:disable WordPress.Security.EscapeOutput.OutputNotEscaped -- Renderer escaped die CSV-Komponente inklusive URL, Labels und Formularfeldern.
echo $flzpu_ui->csv_panel(
	array(
		'title'       => 'Schulen CSV',
		'description' => 'Download als Vorlage oder Sammel-Upload für Grundschulen und Platzanzahl.',
		'format'      => '"Name der Schule";AnzahlPlätze',
		'export'      => array(
			'href'  => FlzPuSchool::get_csv_link(),
			'label' => 'Schulen-CSV herunterladen',
		),
		'upload'      => array(
			'nonce'        => 'flzpu_admin_action',
			'file_name'    => 'schools-csv',
			'file_id'      => 'schools-csv',
			'button_label' => 'Schulen-CSV hochladen',
		),
	)
);
// phpcs:enable WordPress.Security.EscapeOutput.OutputNotEscaped
?>
