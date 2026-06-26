<?php
$flzpu_ui = flz_ui();
$flzpu_disabled_attrs = $max_reached ? array( 'disabled' => true ) : array();
$flzpu_school_options = array();
if ( ! empty( $schools ) ) {
	foreach ( $schools as $school ) {
		$flzpu_school_options[ (string) $school->id ] = array(
			'label'    => $school->name . ' (' . $school->available_seats . ')',
			'disabled' => $school->available_seats <= 0,
		);
	}
}
?>

<?php echo $flzpu_ui->form_start( array( 'method' => 'post', 'nonce' => 'flzpu_register_participant', 'nonce_name' => 'flzpu_nonce', 'attrs' => array( 'data-max-reached' => $max_reached ? 'true' : 'false' ) ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Renderer escaped das Formular. ?>
	<?php echo $flzpu_ui->field( array( 'type' => 'select', 'name' => 'participant[school_id]', 'id' => 'participant_school_id', 'label' => 'Schule', 'required' => true, 'placeholder' => 'Bitte Grundschule auswählen', 'options' => $flzpu_school_options, 'attrs' => $flzpu_disabled_attrs ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Renderer escaped die Komponente. ?>
	<?php echo $flzpu_ui->input( 'text', array( 'name' => 'participant[name]', 'id' => 'participant_name', 'label' => 'Name', 'required' => true, 'attrs' => $flzpu_disabled_attrs ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Renderer escaped die Komponente. ?>
	<?php echo $flzpu_ui->input( 'text', array( 'name' => 'participant[firstName]', 'id' => 'participant_firstName', 'label' => 'Vorname', 'required' => true, 'attrs' => $flzpu_disabled_attrs ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Renderer escaped die Komponente. ?>
	<?php echo $flzpu_ui->input( 'text', array( 'name' => 'participant[class]', 'id' => 'participant_class', 'label' => 'Klasse', 'required' => true, 'attrs' => $flzpu_disabled_attrs ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Renderer escaped die Komponente. ?>
	<?php echo $flzpu_ui->input( 'email', array( 'name' => 'participant[email]', 'id' => 'participant_email', 'label' => 'E-Mail Eltern', 'required' => true, 'autocomplete' => 'email', 'attrs' => $flzpu_disabled_attrs ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Renderer escaped die Komponente. ?>

    <?php if (isset($essen)) {
        if($essen){
            echo $flzpu_ui->field( array( 'type' => 'checkbox', 'name' => 'participant[lunch]', 'id' => 'participant_lunch', 'label' => 'Teilnahme am Mittagessen', 'attrs' => $flzpu_disabled_attrs ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Renderer escaped die Komponente.

        }
    }

	    if ($max_reached) echo esc_html( 'Die maximale Teilnehmerzahl von ' . FlzPuSetting::get_value_by_name( 'MaxTeilnehmerGesamt' ) . ' ist erreicht. Derzeit sind keine weiteren Anmeldungen möglich.' );
    ?>

	<?php echo $flzpu_ui->button( array( 'label' => 'Anmeldung absenden', 'variant' => 'primary', 'type' => 'submit', 'icon' => 'check', 'icon_alt' => 'Anmeldung absenden', 'attrs' => $flzpu_disabled_attrs ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Renderer escaped die Komponente. ?>
<?php echo $flzpu_ui->form_end(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Renderer escaped das Formularende. ?>
<p>Es gibt aktuell <?php echo esc_html( FlzPuParticipant::count_by() ); ?> von maximal <?php echo esc_html( FlzPuSetting::get_value_by_name( 'MaxTeilnehmerGesamt' ) ); ?> registrierte bzw. vorgemerkte Teilnehmer:innen.</p>
