<div class="wrap">
    <h1>Schulen bearbeiten</h1>
    <div style="display: inline-block; width: 50%; overflow: auto; height: 20em;">
	        <a href="<?php echo esc_url( FlzPuSchool::get_csv_link() ); ?>">CSV herunterladen</a>
        <table>
            <tr>
                <th>Name</th>
                <th>Freie Plätze</th>
                <th>Aktionen</th>
            </tr>
            <?php foreach ( $schools as $school ) : ?>
                <tr>
	                    <td><?php echo esc_html( $school->name ); ?></td>
	                    <td><?php echo esc_html( $school->available_seats ); ?></td>
                    <td>
                        <form method="post" style="display: inline;">
							<?php wp_nonce_field( 'flzpu_admin_action' ); ?>
	                            <input type="hidden" name="school_delete" value="<?php echo esc_attr( $school->id ); ?>" />
                            <button type="submit">Löschen</button>
                        </form>
                        <form method="post" style="display: inline;">
							<?php wp_nonce_field( 'flzpu_admin_action' ); ?>
	                            <input type="hidden" name="school_id" value="<?php echo esc_attr( $school->id ); ?>" />
                            <button type="submit" name="school_edit">Bearbeiten</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
        </table>
    </div>
    <div style="width: 40%; display: inline-block; vertical-align: top; text-align: start;">
        <h2>Schule hinzufügen/bearbeiten</h2>

        <form method="post">
			<?php wp_nonce_field( 'flzpu_admin_action' ); ?>
	            <input type="hidden" name="school_id" value="<?php echo esc_attr( $selected_school->id ); ?>" />
            <label for="school">Name:</label>
	            <input type="text" name="name" value="<?php echo esc_attr( $selected_school->name ); ?>" required /><br>
            <label for="available_seats">Freie Plätze:</label>
	            <input type="number" name="available_seats" value="<?php echo esc_attr( $selected_school->available_seats ); ?>" required /><br>
            <button type="submit" name="school_submit">Speichern</button>
        </form>
    </div>
</div>
<div>
    <h3>Schulen Sammel-Upload (CSV-Datei)</h3>
    <p>Achtung, die eindeutige identifikation der Lehrer*innen geschieht über die E-Mail-Adresse. Die Datei muss in folgendem Format sein: <br>
        <code>"Name der Schule";AnzahlPlätze</code><br/>
        Es bietet sich an, diese einfach vorher herunterzuladen und zu bearbeiten. </p>
    <form method="post" enctype="multipart/form-data">
		<?php wp_nonce_field( 'flzpu_admin_action' ); ?>
        <input type="file" name="schools-csv" id="schools-csv" accept=".csv">
        <input type="submit" value="Upload" name="submit_csv">
    </form>

</div>
