<?php

function flzpu_probeunterricht_form($atts)
{
    //debug($_POST);
	//exit;
	$out = "";
    $atts = shortcode_atts(
        array(
            'essen' => false, // Standardwert für den Parameter "essen" ist false
            'danke' => ''
        ),
        $atts
    );
    $essen = filter_var($atts['essen'], FILTER_VALIDATE_BOOLEAN); // Konvertiere den Wert des Parameters in einen boolschen Wert


    // aktivierungslink geklickt
    if (isset($_GET['id']) && isset($_GET['token'])) {
        $participant=FlzPuParticipant::get_by_id(intval($_GET['id']));
        $out.= $participant->activate(sanitize_text_field($_GET['token']));
    }
    if (isset($_POST['participant'])) {
        $participant_post=$_POST['participant'];
        $school=FlzPuSchool::get_by_id($participant_post['school_id']);

        unset($participant_post['school_id']);
        $participant=new FlzPuParticipant([]);
        $participant->assignPostData($participant_post);
		$participant->school=$school;
		$school->take_seat();
		$participant->save();

        // verschicken der Aktivierungs-Mail
        $participant->send_activation_email();

        // Weiterleitung zur Danke-Seite
        if (!empty($atts['danke'])) {
            $thank_you_page_id = intval($atts['danke']);
            $thank_you_page_url = get_permalink($thank_you_page_id);

            wp_redirect($thank_you_page_url);
            exit;

        } else {
            echo 'Danke, alles verschickt.';
        }
    }



	$max_reached= (int) FlzPuSetting::get_value_by_name("MaxTeilnehmerGesamt") - FlzPuParticipant::count_by() <= 0;


    ob_start();
	$schools = FlzPuSchool::get_all_by( order_by: 'name' );
    echo $out;
    include(plugin_dir_path(__FILE__) . 'templates/frontend-form.php');
    return ob_get_clean();
}



// Hinzufügen des Formulars im Frontend
add_shortcode( 'flzpu', 'flzpu_probeunterricht_form' );
