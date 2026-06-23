jQuery(document).ready(function($){
    $('#participant\\[school_id\\], #participant\\[name\\], #participant\\[firstName\\], #participant\\[class\\], #participant\\[email\\]').change(checkFormSubmissionCriteria);

    var placeholder_value = "9999"; // Ersetzen Sie dies durch den tatsächlichen Platzhalterwert

    function checkFormSubmissionCriteria() {
        console.log($('#participant\\[school_id\\]').val());
        var isMaxReached = $('form').data('max-reached') == 'true';
        var areFieldsFilled =
            $('#participant\\[school_id\\]').val() != null
            && $('#participant\\[school_id\\]').val() != ''
            && $('#participant\\[school_id\\]').val() != placeholder_value
            && $('#participant\\[name\\]').val() != ""
            && $('#participant\\[firstName\\]').val() != ""
            && $('#participant\\[class\\]').val() != ""
            && $('#participant\\[email\\]').val() != "";

        // disable button if max participants reached or if not all fields are filled and dropdown is not set to placeholder value
        $('button[name=submit]').prop('disabled', isMaxReached || !areFieldsFilled);
    }

    // Überprüfen Sie die Bedingungen, sobald die Seite geladen ist
    checkFormSubmissionCriteria();
});