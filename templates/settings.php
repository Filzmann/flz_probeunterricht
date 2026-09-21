<?php $flzpu_ui = flz_ui(); ?>
<div class="wrap">
    <h1>Probeunterricht – Einstellungen</h1>
	<?php if ( ! empty( $settings_notice ) ) : ?>
		<?php echo $flzpu_ui->notice( $settings_notice, 'success' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Renderer escaped die Notice. ?>
	<?php endif; ?>
	<?php if ( null !== $privacy_deleted ) : ?>
		<?php echo $flzpu_ui->notice( sprintf( 'Abgelaufene Anmeldungen gelöscht: %d.', $privacy_deleted ), 'success' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Renderer escaped die Notice. ?>
	<?php endif; ?>
    <div class="flz-ui-panel">
        <h2>Grundeinstellungen</h2>
        <p class="description">Diese Werte steuern die verfügbaren Plätze für den Probeunterricht.</p>
		<?php echo $flzpu_ui->form_start( array( 'method' => 'post', 'nonce' => 'flzpu_admin_action' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Renderer escaped das Formular. ?>
        <table class="widefat striped flz-ui-admin-table">
            <tr>
                <th>Name</th>
                <th>Wert</th>
            </tr>
            <?php foreach ( $settings as $setting ) : ?>
                <tr>
	                    <td><?php echo esc_html( $setting->name ); ?></td>
                    <td>
						<?php echo $flzpu_ui->input( 'text', array( 'name' => 'settings[' . $setting->id . ']', 'label' => $setting->name, 'value' => $setting->value, 'required' => true ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Renderer escaped die Komponente. ?>
                    </td>

                </tr>
            <?php endforeach; ?>
        </table>
			<?php echo $flzpu_ui->button_save( array( 'label' => 'Einstellungen speichern' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Renderer escaped die Komponente. ?>
		<?php echo $flzpu_ui->form_end(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Renderer escaped das Formularende. ?>
    </div>

	<div class="flz-ui-panel">
		<h2>Datenschutz und Aufbewahrung</h2>
		<p>Automatische Löschung ist standardmäßig deaktiviert. Die vorgeschlagene Frist beträgt 24 Monate ab dem ursprünglichen Anmeldedatum.</p>
		<?php echo $flzpu_ui->form_start( array( 'method' => 'post', 'nonce' => 'flzpu_admin_action' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Renderer escaped das Formular. ?>
			<?php echo $flzpu_ui->hidden( 'flzpu_privacy_settings', '1' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Renderer escaped das Feld. ?>
			<?php echo $flzpu_ui->field( array( 'type' => 'checkbox', 'name' => 'flzpu_retention_enabled', 'label' => 'Automatische Löschung aktivieren', 'checked' => $retention_enabled ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Renderer escaped das Feld. ?>
			<?php echo $flzpu_ui->input( 'number', array( 'name' => 'flzpu_retention_months', 'label' => 'Aufbewahrungsfrist in Monaten', 'value' => $retention_months, 'min' => 1, 'max' => 120, 'required' => true ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Renderer escaped das Feld. ?>
			<?php echo $flzpu_ui->button_save( array( 'label' => 'Aufbewahrungseinstellungen speichern' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Renderer escaped den Button. ?>
		<?php echo $flzpu_ui->form_end(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Renderer escaped das Formularende. ?>

		<?php echo $flzpu_ui->form_start( array( 'method' => 'post', 'action' => admin_url( 'admin-post.php' ), 'nonce' => 'flzpu_delete_expired_participants', 'hidden' => array( 'action' => 'flzpu_delete_expired_participants', 'confirm_delete_expired_participants' => '1' ) ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Renderer escaped das Formular. ?>
			<?php echo $flzpu_ui->button_delete( array( 'label' => 'Jetzt abgelaufene Anmeldungen löschen', 'confirm' => 'Sollen alle Anmeldungen außerhalb der gewählten Aufbewahrungsfrist endgültig gelöscht werden?' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Renderer escaped den Button. ?>
		<?php echo $flzpu_ui->form_end(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Renderer escaped das Formularende. ?>
	</div>

    <div class="flz-ui-panel">
        <h2>Demo-Daten</h2>
        <p>Legt einige Beispielanmeldungen für den Probeunterricht an. Vorhandene Grundschulen werden bevorzugt genutzt; fehlen Schulen, werden Einträge aus der lokalen Grundschulliste ergänzt.</p>
        <p class="description">Vorhandene Demo-Anmeldungen werden wiederverwendet. Echte Daten werden nicht gelöscht.</p>
		<?php echo $flzpu_ui->form_start( array( 'method' => 'post', 'nonce' => 'flzpu_admin_action' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Renderer escaped das Formular. ?>
			<?php echo $flzpu_ui->button_new( array( 'label' => 'Demo-Daten anlegen oder auffrischen', 'type' => 'submit', 'attrs' => array( 'name' => 'flzpu_install_demo', 'value' => '1' ) ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Renderer escaped die Komponente. ?>
		<?php echo $flzpu_ui->form_end(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Renderer escaped das Formularende. ?>
    </div>
</div>
