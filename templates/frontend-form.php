

<form method="post" data-max-reached="<?php echo $max_reached ? 'true' : 'false'; ?>">
    <label for="participant[school_id]">Schule:</label>
    <select name="participant[school_id]" id="participant[school_id]" required>
        <option disabled selected value="">Bitte Grundschule auswählen</option>
        <?php
            if (!empty($schools)) {
                foreach ( $schools as $school ) {
                    $disabled=$school->available_seats>0?'':"disabled";
                    echo '<option '.$disabled.' value="' . $school->id . '">' . $school->name . ' ('.$school->available_seats.')</option>';
                }
            }
        ?>
    </select><br>
    <label for="participant[name]">Name:</label>
    <input type="text" name="participant[name]" id="participant[name]" required <?php if($max_reached) echo "disabled" ?>/><br>
    <label for="participant[firstName]">Vorname:</label>
    <input type="text" name="participant[firstName]" id="participant[firstName]" required <?php if($max_reached) echo "disabled" ?>/><br>
    <label for="participant[class]">Klasse:</label>
    <input type="text" name="participant[class]" id="participant[class]" required <?php if($max_reached) echo "disabled" ?>/><br>
    <label for="participant[email]">Email Eltern:</label>
    <input type="email" name="participant[email]" id="participant[email]" required <?php if($max_reached) echo "disabled" ?>/><br>

    <?php if (isset($essen)) {
        if($essen){
            echo 'Teilnahme am Mittagessen: <input type="checkbox" name="participant[lunch]" id="participant[lunch]"  value="1"  /><br>';

        }
    }

    if ($max_reached) echo "Die maximale Teilnehmerzahl von ".FlzPuSetting::get_value_by_name("MaxTeilnehmerGesamt")." ist erreicht. derzeit sind keine weiteren Anmeldungen möglich."
    ?>

    <button type="submit" name="submit">Absenden</button>
</form>
<p>Es gibt aktuell <?php echo esc_html( FlzPuParticipant::count_by() ); ?> von maximal <?php echo esc_html( FlzPuSetting::get_value_by_name( 'MaxTeilnehmerGesamt' ) ); ?> registrierte bzw. vorgemerkte Teilnehmer:innen.</p>
