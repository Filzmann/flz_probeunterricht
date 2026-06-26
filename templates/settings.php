<?php $flzpu_ui = flz_ui(); ?>
<div class="wrap">
    <h1>Settings bearbeiten</h1>
    <div style="display: inline-block; width: 50%; overflow: auto; height: 20em;">
		<?php echo $flzpu_ui->form_start( array( 'method' => 'post', 'nonce' => 'flzpu_admin_action' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Renderer escaped das Formular. ?>
        <table>
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
			<?php echo $flzpu_ui->button_save( array( 'label' => 'Settings speichern' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Renderer escaped die Komponente. ?>
		<?php echo $flzpu_ui->form_end(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Renderer escaped das Formularende. ?>
    </div>

</div>
