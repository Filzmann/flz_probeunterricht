<?php
$flzpu_ui = flz_ui();
$flzpu_participant_sort_link = static function ( string $label, string $sort ) use ( $flzpu_ui, $participant_orderby, $participant_order, $participant_base_args ): string {
	return $flzpu_ui->admin_table_sort_link(
		array(
			'label'         => $label,
			'sort'          => $sort,
			'current_sort'  => $participant_orderby,
			'current_order' => $participant_order,
			'url_args'      => $participant_base_args,
		)
	);
};

$flzpu_school_options_for = static function ( int $current_school_id = 0 ) use ( $schools ): array {
	$options = array();

	foreach ( $schools as $school ) {
		if ( empty( $school->id ) || 9999 === (int) $school->id ) {
			continue;
		}

		$options[ (string) $school->id ] = array(
			'label'    => $school->name . ' (' . $school->available_seats . ')',
			'disabled' => $school->available_seats <= 0 && (int) $school->id !== $current_school_id,
		);
	}

	return $options;
};

$flzpu_participant_row = static function ( FlzPuParticipant $participant ) use ( $flzpu_ui, $flzpu_school_options_for ): string {
	$is_new = empty( $participant->id );
	$row_id = $is_new ? 'flzpu-participant-new' : 'flzpu-participant-' . (int) $participant->id;
	$current_school_id = ! empty( $participant->school?->id ) ? (int) $participant->school->id : 0;
	$delete_action = '';

	if ( ! $is_new ) {
		$delete_action .= $flzpu_ui->action_form_button(
			array(
				'preset' => 'delete',
				'label'  => 'Teilnehmer löschen',
				'method' => 'post',
				'nonce'  => 'flzpu_admin_action',
				'hidden' => array( 'participant_delete' => $participant->id ),
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
				'hidden' => array(
					'participant_save[id]'            => $participant->id ?? '',
					'participant_save[old_school_id]' => $current_school_id ?: '',
				),
			),
			'cells'      => array(
				array( 'view' => $participant->id ?? 'neu' ),
				array(
					'view'  => $participant->name ?? '',
					'field' => array(
						'type'     => 'text',
						'name'     => 'participant_save[name]',
						'label'    => 'Name',
						'value'    => $participant->name ?? '',
						'required' => true,
					),
				),
				array(
					'view'  => $participant->firstName ?? '',
					'field' => array(
						'type'     => 'text',
						'name'     => 'participant_save[firstName]',
						'label'    => 'Vorname',
						'value'    => $participant->firstName ?? '',
						'required' => true,
					),
				),
				array(
					'view'  => $participant->class ?? '',
					'field' => array(
						'type'     => 'text',
						'name'     => 'participant_save[class]',
						'label'    => 'Klasse',
						'value'    => $participant->class ?? '',
						'required' => true,
					),
				),
				array(
					'view'  => $participant->email ?? '',
					'field' => array(
						'type'         => 'email',
						'name'         => 'participant_save[email]',
						'label'        => 'E-Mail Eltern',
						'value'        => $participant->email ?? '',
						'required'     => true,
						'autocomplete' => 'email',
					),
				),
				array(
					'view'  => $participant->school?->name ?? '',
					'field' => array(
						'type'        => 'select',
						'name'        => 'participant_save[school_id]',
						'label'       => 'Schule',
						'value'       => $current_school_id ? (string) $current_school_id : '',
						'placeholder' => 'Bitte Grundschule auswählen',
						'options'     => $flzpu_school_options_for( $current_school_id ),
						'required'    => true,
					),
				),
				array(
					'view'  => $participant->lunch ? 'ja' : 'nein',
					'field' => array(
						'type'            => 'checkbox',
						'name'            => 'participant_save[lunch]',
						'label'           => 'Teilnahme Essen',
						'checked'         => ! empty( $participant->lunch ),
						'unchecked_value' => '0',
					),
				),
				array( 'view' => $participant->status ?? 'neu' ),
			),
			'edit'       => array( 'label' => 'Teilnehmer bearbeiten' ),
			'save'       => array(
				'label' => 'Teilnehmer speichern',
			),
			'extra_actions' => $delete_action,
		)
	);
};

$flzpu_new_participant = new FlzPuParticipant( array() );
$flzpu_new_participant->school = new FlzPuSchool( array() );
?>
<div class="wrap">
	<h1>Teilnehmer bearbeiten (Anzeige: <?php echo esc_html( count( $participants ) ); ?> / Gesamt: <?php echo esc_html( FlzPuParticipant::count_by() ); ?>)</h1>
	<p>
		<?php echo $flzpu_ui->button_new( array( 'label' => 'Neuen Teilnehmer anlegen', 'attrs' => array( 'data-flz-ui-show-new-row' => 'flzpu-participant-new' ) ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Renderer escaped die Komponente. ?>
	</p>

	<?php
	// phpcs:disable WordPress.Security.EscapeOutput.OutputNotEscaped -- Renderer escaped die CSV-Komponente inklusive URL und Labels.
	echo $flzpu_ui->csv_panel(
		array(
			'title'       => 'Teilnehmerliste CSV',
			'description' => 'Die Teilnehmerliste ist im CSV-Format und lässt sich sowohl in Excel als auch in OpenOffice öffnen.',
			'export'      => array(
				'href'  => $csv_file,
				'label' => 'Teilnehmerliste herunterladen',
			),
		)
	);
	// phpcs:enable WordPress.Security.EscapeOutput.OutputNotEscaped
	?>

	<table class="widefat striped flz-ui-admin-table">
		<thead>
			<tr>
				<th><?php echo $flzpu_participant_sort_link( 'ID', 'id' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Renderer escaped den Sortierlink. ?></th>
				<th>
					<div class="flz-ui-table-head">
						<?php echo $flzpu_participant_sort_link( 'Name', 'name' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Renderer escaped den Sortierlink. ?>
						<form method="get" class="flz-ui-table-filter-form">
							<input type="hidden" name="page" value="flzpu_participants" />
							<input type="hidden" name="orderby" value="<?php echo esc_attr( $participant_orderby ); ?>" />
							<input type="hidden" name="order" value="<?php echo esc_attr( $participant_order ); ?>" />
							<?php if ( '' !== $participant_class_filter ) : ?>
								<input type="hidden" name="class_filter" value="<?php echo esc_attr( $participant_class_filter ); ?>" />
							<?php endif; ?>
							<?php if ( $participant_school_filter > 0 ) : ?>
								<input type="hidden" name="school_filter" value="<?php echo esc_attr( (string) $participant_school_filter ); ?>" />
							<?php endif; ?>
							<?php if ( '' !== $participant_status_filter ) : ?>
								<input type="hidden" name="status_filter" value="<?php echo esc_attr( $participant_status_filter ); ?>" />
							<?php endif; ?>
							<?php echo $flzpu_ui->input( 'text', array( 'name' => 'participant_search', 'label' => 'Teilnehmer nach Nachname filtern', 'value' => $participant_search, 'placeholder' => 'Nachname' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Renderer escaped die Komponente. ?>
							<?php echo $flzpu_ui->button_filter( array( 'label' => 'Teilnehmer filtern' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Renderer escaped die Komponente. ?>
						</form>
					</div>
				</th>
					<th><?php echo $flzpu_participant_sort_link( 'Vorname', 'firstName' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Renderer escaped den Sortierlink. ?></th>
					<th>
						<div class="flz-ui-table-head">
							<?php echo $flzpu_participant_sort_link( 'Klasse', 'class' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Renderer escaped den Sortierlink. ?>
							<form method="get" class="flz-ui-table-filter-form">
								<input type="hidden" name="page" value="flzpu_participants" />
								<input type="hidden" name="orderby" value="<?php echo esc_attr( $participant_orderby ); ?>" />
								<input type="hidden" name="order" value="<?php echo esc_attr( $participant_order ); ?>" />
								<?php if ( '' !== $participant_search ) : ?>
									<input type="hidden" name="participant_search" value="<?php echo esc_attr( $participant_search ); ?>" />
								<?php endif; ?>
								<?php if ( $participant_school_filter > 0 ) : ?>
									<input type="hidden" name="school_filter" value="<?php echo esc_attr( (string) $participant_school_filter ); ?>" />
								<?php endif; ?>
								<?php if ( '' !== $participant_status_filter ) : ?>
									<input type="hidden" name="status_filter" value="<?php echo esc_attr( $participant_status_filter ); ?>" />
								<?php endif; ?>
								<?php echo $flzpu_ui->field( array( 'type' => 'select', 'name' => 'class_filter', 'label' => 'Klasse filtern', 'value' => $participant_class_filter, 'options' => $participant_class_options ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Renderer escaped die Komponente. ?>
								<?php echo $flzpu_ui->button_filter( array( 'label' => 'Klasse filtern' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Renderer escaped die Komponente. ?>
							</form>
						</div>
					</th>
					<th><?php echo $flzpu_participant_sort_link( 'Email Eltern', 'email' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Renderer escaped den Sortierlink. ?></th>
					<th>
						<div class="flz-ui-table-head">
							<?php echo $flzpu_participant_sort_link( 'Schule', 'school' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Renderer escaped den Sortierlink. ?>
							<form method="get" class="flz-ui-table-filter-form">
								<input type="hidden" name="page" value="flzpu_participants" />
								<input type="hidden" name="orderby" value="<?php echo esc_attr( $participant_orderby ); ?>" />
								<input type="hidden" name="order" value="<?php echo esc_attr( $participant_order ); ?>" />
								<?php if ( '' !== $participant_search ) : ?>
									<input type="hidden" name="participant_search" value="<?php echo esc_attr( $participant_search ); ?>" />
								<?php endif; ?>
								<?php if ( '' !== $participant_class_filter ) : ?>
									<input type="hidden" name="class_filter" value="<?php echo esc_attr( $participant_class_filter ); ?>" />
								<?php endif; ?>
								<?php if ( '' !== $participant_status_filter ) : ?>
									<input type="hidden" name="status_filter" value="<?php echo esc_attr( $participant_status_filter ); ?>" />
								<?php endif; ?>
								<?php echo $flzpu_ui->field( array( 'type' => 'select', 'name' => 'school_filter', 'label' => 'Schule filtern', 'value' => (string) $participant_school_filter, 'options' => $participant_school_filter_options ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Renderer escaped die Komponente. ?>
								<?php echo $flzpu_ui->button_filter( array( 'label' => 'Schule filtern' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Renderer escaped die Komponente. ?>
							</form>
						</div>
					</th>
					<th><?php echo $flzpu_participant_sort_link( 'Teilnahme Essen', 'lunch' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Renderer escaped den Sortierlink. ?></th>
					<th>
						<div class="flz-ui-table-head">
							<?php echo $flzpu_participant_sort_link( 'Status', 'status' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Renderer escaped den Sortierlink. ?>
							<form method="get" class="flz-ui-table-filter-form">
								<input type="hidden" name="page" value="flzpu_participants" />
								<input type="hidden" name="orderby" value="<?php echo esc_attr( $participant_orderby ); ?>" />
								<input type="hidden" name="order" value="<?php echo esc_attr( $participant_order ); ?>" />
								<?php if ( '' !== $participant_search ) : ?>
									<input type="hidden" name="participant_search" value="<?php echo esc_attr( $participant_search ); ?>" />
								<?php endif; ?>
								<?php if ( '' !== $participant_class_filter ) : ?>
									<input type="hidden" name="class_filter" value="<?php echo esc_attr( $participant_class_filter ); ?>" />
								<?php endif; ?>
								<?php if ( $participant_school_filter > 0 ) : ?>
									<input type="hidden" name="school_filter" value="<?php echo esc_attr( (string) $participant_school_filter ); ?>" />
								<?php endif; ?>
								<?php echo $flzpu_ui->field( array( 'type' => 'select', 'name' => 'status_filter', 'label' => 'Status filtern', 'value' => $participant_status_filter, 'options' => $participant_status_options ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Renderer escaped die Komponente. ?>
								<?php echo $flzpu_ui->button_filter( array( 'label' => 'Status filtern' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Renderer escaped die Komponente. ?>
							</form>
						</div>
					</th>
					<th>Aktionen</th>
				</tr>
			</thead>
			<tbody>
				<?php echo $flzpu_participant_row( $flzpu_new_participant ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Renderer escaped die Tabellenzeile. ?>
				<?php foreach ( $participants as $participant ) : ?>
					<?php echo $flzpu_participant_row( $participant ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Renderer escaped die Tabellenzeile. ?>
				<?php endforeach; ?>
			</tbody>
		</table>
		<?php if ( '' !== $participant_search || '' !== $participant_class_filter || $participant_school_filter > 0 || '' !== $participant_status_filter ) : ?>
			<p>
				<?php echo $flzpu_ui->button( array( 'href' => admin_url( 'admin.php?page=flzpu_participants' ), 'label' => 'Filter zurücksetzen', 'variant' => 'secondary' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Renderer escaped die Komponente. ?>
			</p>
		<?php endif; ?>

		<h2>Teilnehmerliste löschen und verfügbare Plätze zurücksetzen</h2>
		<?php echo $flzpu_ui->form_start( array( 'method' => 'post', 'nonce' => 'flzpu_admin_action' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Renderer escaped das Formular. ?>
			<?php echo $flzpu_ui->input( 'number', array( 'name' => 'available_seats', 'label' => 'Neue Anzahl der verfügbaren Plätze für alle Schulen', 'value' => (int) FlzPuSetting::get_value_by_name( 'MaxTeilnehmerProSchule' ), 'min' => 0, 'required' => true ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Renderer escaped die Komponente. ?>
			<?php echo $flzpu_ui->button_reset( array( 'label' => 'Teilnehmerliste leeren', 'confirm' => 'Sind Sie sicher, dass Sie alle Teilnehmer löschen und die verfügbaren Plätze zurücksetzen wollen?', 'attrs' => array( 'name' => 'reset_participants' ) ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Renderer escaped die Komponente. ?>
		<?php echo $flzpu_ui->form_end(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Renderer escaped das Formularende. ?>
</div>
