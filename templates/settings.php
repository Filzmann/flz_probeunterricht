<?php $flzpu_ui = flz_ui(); ?>
<div class="wrap">
    <h1>Probeunterricht – Einstellungen</h1>
	<?php if ( ! empty( $settings_notice ) ) : ?>
		<?php echo $flzpu_ui->notice( $settings_notice, 'success' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Renderer escaped die Notice. ?>
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
        <h2>Demo-Daten</h2>
        <p>Legt einige Beispielanmeldungen für den Probeunterricht an. Vorhandene Grundschulen werden bevorzugt genutzt; fehlen Schulen, werden Einträge aus der lokalen Grundschulliste ergänzt.</p>
        <p class="description">Vorhandene Demo-Anmeldungen werden wiederverwendet. Echte Daten werden nicht gelöscht.</p>
		<?php echo $flzpu_ui->form_start( array( 'method' => 'post', 'nonce' => 'flzpu_admin_action' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Renderer escaped das Formular. ?>
			<?php echo $flzpu_ui->button_new( array( 'label' => 'Demo-Daten anlegen oder auffrischen', 'type' => 'submit', 'attrs' => array( 'name' => 'flzpu_install_demo', 'value' => '1' ) ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Renderer escaped die Komponente. ?>
		<?php echo $flzpu_ui->form_end(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Renderer escaped das Formularende. ?>
    </div>
</div>
