<div class="wrap">
    <div style="width: 55%; display: inline-block; vertical-align: top; text-align: start;">
        <h1>Teilnehmer bearbeiten (Anzahl:

			<?php echo esc_html( FlzPuParticipant::count_by() ); ?>)</h1>

        <table>
            <tr>
                <th>ID</th>
                <th>Name</th>
                <th>Vorname</th>
                <th>Klasse</th>
                <th>Email Eltern</th>
                <th>Schule</th>
                <th>Teilnahme Essen</th>
                <th>Status</th>
                <th>Aktionen</th>
            </tr>
			<?php foreach ( $participants as $participant ) : ?>
                <tr>
                    <td><?php echo $participant->id; ?></td>
                    <td><?php echo $participant->name; ?></td>
                    <td><?php echo $participant->firstName; ?></td>
                    <td><?php echo $participant->class; ?></td>
                    <td><?php echo $participant->email; ?></td>
                    <td><?php echo $participant->school->name; ?></td>
                    <td><?php echo $participant->lunch ? "ja" : "nein"; ?></td>
                    <td><?php echo $participant->status; ?></td>
                    <td>
                        <form method="post" style="display: inline;">
                            <input type="hidden" name="participant_delete" value="<?php echo $participant->id; ?>"/>
                            <button type="submit">Löschen</button>
                        </form>
                    </td>
                    <td>
                        <form method="post" style="display: inline;">
                            <input type="hidden" name="participant_edit" value="<?php echo $participant->id; ?>"/>
                            <button type="submit">Bearbeiten</button>
                        </form>
                    </td>
                </tr>
			<?php endforeach; ?>
        </table>
        <a href="<?php echo admin_url(); ?>probeunterricht.csv">Teilnehmerliste herunterladen</a>

        <p>
            Die Teilnehmerliste ist im CSV-Format und lässt sich sowohl in Excel, als auch in OpenOffice öffnen.
        </p>

        <h2>Teilnehmerliste löschen und verfügbare Plätze zurücksetzen</h2>
        <form method="post" onsubmit="return confirm('Sind Sie sicher, dass Sie alle Teilnehmer löschen wollen?')">
            Neue Anzahl der verfügbaren Plätze für alle Schulen: <input type="number" name="available_seats"
                                                                        value="<?php echo (int) FlzPuSetting::get_value_by_name( 'MaxTeilnehmerProSchule' ) ?>"
                                                                        min="0" required/><br>
            <button type="submit" name="reset_participants">Teilnehmerliste leeren</button>
        </form>
    </div>
    <div style="width: 40%; display: inline-block; vertical-align: top; text-align: start;">
        <h2>Teilnehmer manuell hinzufügen/bearbeiten</h2>

        <form method="post">
            <input type="hidden" name="participant_save[id]" value="<?= $participant_edit->id ?? ''; ?> ">
            <input type="hidden" name="participant_save[old_school_id]" value="<?= $participant_edit->school->id ?? ''; ?> ">

            <label for="participant_save[school_id]">Schule:</label>

            <select name="participant_save[school_id]" id="participant_save[school_id]">
				<?php
				if ( ! empty( $schools ) ) {
					foreach ( $schools as $school ) {
    					$selected = $school->id === $participant_edit->school->id ? 'selected' : '';
						$disabled = $school->available_seats > 0 ? '' : 'disabled';
						echo '<option ' . $disabled . ' ' . $selected . ' value="' . $school->id . '">' . $school->name . ' (' . $school->available_seats . ')</option>';
					}
				}
				?>
            </select><br>
            <label for="participant_save[name]">Name:</label>
            <input type="text" name="participant_save[name]" id="participant_save[name]"
                   value="<?php echo $participant_edit->name ?? ''; ?>" required /><br>
            <label for="participant_save[firstName]">Vorname:</label>
            <input type="text" name="participant_save[firstName]" id="participant_save[firstName]"
                   value="<?php echo $participant_edit->firstName ?? ''; ?>""
            required /><br>
            <label for="participant_save[class]">Klasse:</label>
            <input type="text" name="participant_save[class]" id="participant_save[class]"
                   value="<?php echo $participant_edit->class ?? ''; ?>" required /><br>
            <label for="participant_save[email]">Email Eltern:</label>
            <input type="email" name="participant_save[email]" id="participant_save[email]"
                   value="<?php echo $participant_edit->email ?? ''; ?>" required /><br>

            <label for="participant_save[lunch]">Teilnahme Essen:</label>
            <input type="checkbox" name="participant_save[lunch]" id="participant_save[lunch]"
                   value="1" <?php echo $participant_edit->lunch ? 'checked' : ''; ?> /><br>

            <button type="submit" name="participant_submit">Speichern</button>
        </form>
    </div>
</div>
