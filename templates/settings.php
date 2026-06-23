<div class="wrap">
    <h1>Settings bearbeiten</h1>
    <div style="display: inline-block; width: 50%; overflow: auto; height: 20em;">
        <form method="post">
        <table>
            <tr>
                <th>Name</th>
                <th>Wert</th>
            </tr>
            <?php foreach ( $settings as $setting ) : ?>
                <tr>
                    <td><?php echo $setting->name; ?></td>
                    <td>
                        <input type="text"
                               name="settings[<?php echo $setting->id ?>]"
                               value="<?php echo $setting->value?>" required /><br>
                    </td>

                </tr>
            <?php endforeach; ?>
        </table>
            <button type="submit">Speichern</button>
        </form>
    </div>

</div>
