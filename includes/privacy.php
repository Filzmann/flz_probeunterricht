<?php

defined('ABSPATH') || exit;

add_filter('wp_privacy_personal_data_exporters', 'flzpu_register_privacy_exporter');
add_filter('wp_privacy_personal_data_erasers', 'flzpu_register_privacy_eraser');
add_action('flzpu_daily_privacy_cleanup', 'flzpu_run_privacy_cleanup');
add_action('admin_post_flzpu_delete_expired_participants', 'flzpu_handle_manual_privacy_cleanup');

function flzpu_register_privacy_exporter(array $exporters): array
{
	$exporters['flz-probeunterricht'] = array(
		'exporter_friendly_name' => __('FLZ Probeunterricht', 'flz-probeunterricht'),
		'callback' => 'flzpu_privacy_exporter',
	);

	return $exporters;
}

function flzpu_register_privacy_eraser(array $erasers): array
{
	$erasers['flz-probeunterricht'] = array(
		'eraser_friendly_name' => __('FLZ Probeunterricht', 'flz-probeunterricht'),
		'callback' => 'flzpu_privacy_eraser',
	);

	return $erasers;
}

function flzpu_privacy_exporter(string $email_address, int $page = 1): array
{
	if ($page > 1 || !is_email($email_address)) {
		return array('data' => array(), 'done' => true);
	}

	$data = array();
	foreach (FlzPuParticipant::get_all_by(array('email' => sanitize_email($email_address))) as $participant) {
		if (!$participant instanceof FlzPuParticipant) {
			continue;
		}
		$data[] = array(
			'group_id' => 'flz-probeunterricht',
			'group_label' => __('Probeunterrichtsanmeldungen', 'flz-probeunterricht'),
			'item_id' => 'flzpu-participant-' . (int) $participant->id,
			'data' => array(
				array('name' => __('Nachname', 'flz-probeunterricht'), 'value' => (string) $participant->name),
				array('name' => __('Vorname', 'flz-probeunterricht'), 'value' => (string) $participant->firstName),
				array('name' => __('Klasse', 'flz-probeunterricht'), 'value' => (string) $participant->class),
				array('name' => __('E-Mail', 'flz-probeunterricht'), 'value' => (string) $participant->email),
				array('name' => __('Grundschule', 'flz-probeunterricht'), 'value' => (string) ($participant->school?->name ?? '')),
				array('name' => __('Status', 'flz-probeunterricht'), 'value' => (string) $participant->status),
				array('name' => __('Anmeldedatum', 'flz-probeunterricht'), 'value' => (string) $participant->created_at),
			),
		);
	}

	return array('data' => $data, 'done' => true);
}

function flzpu_privacy_eraser(string $email_address, int $page = 1): array
{
	$result = array(
		'items_removed' => false,
		'items_retained' => false,
		'messages' => array(),
		'done' => true,
	);
	if ($page > 1 || !is_email($email_address)) {
		return $result;
	}

	foreach (FlzPuParticipant::get_all_by(array('email' => sanitize_email($email_address))) as $participant) {
		if (!$participant instanceof FlzPuParticipant) {
			continue;
		}
		try {
			FlzPuRegistrationService::delete($participant);
			$result['items_removed'] = true;
		} catch (Throwable $error) {
			flzpu_log_error($error, 'Löschen eines Probeunterricht-Datensatzes über den WordPress-Privacy-Eraser');
			$result['items_retained'] = true;
			$result['messages'][] = __('Ein Probeunterrichtsdatensatz konnte nicht sicher gelöscht werden.', 'flz-probeunterricht');
		}
	}

	return $result;
}

function flzpu_retention_months(): int
{
	return max(1, min(120, absint(get_option('flzpu_retention_months', 24))));
}

function flzpu_retention_cutoff(): string
{
	return current_datetime()->modify('-' . flzpu_retention_months() . ' months')->format('Y-m-d H:i:s');
}

function flzpu_schedule_privacy_cleanup(): void
{
	if (wp_next_scheduled('flzpu_daily_privacy_cleanup')) {
		return;
	}
	$scheduled = wp_schedule_event(time() + HOUR_IN_SECONDS, 'daily', 'flzpu_daily_privacy_cleanup', array(), true);
	if (is_wp_error($scheduled) || false === $scheduled) {
		throw new RuntimeException('Der tägliche Probeunterricht-Privacy-Job konnte nicht eingerichtet werden.');
	}
}

function flzpu_unschedule_privacy_cleanup(): void
{
	$cleared = wp_clear_scheduled_hook('flzpu_daily_privacy_cleanup', array(), true);
	if (is_wp_error($cleared)) {
		throw new RuntimeException('Der Probeunterricht-Privacy-Job konnte nicht entfernt werden.');
	}
}

function flzpu_run_privacy_cleanup(): int
{
	if (!(bool) get_option('flzpu_retention_enabled', 0)) {
		return 0;
	}

	return flzpu_delete_expired_participants();
}

function flzpu_delete_expired_participants(): int
{
	$deleted = 0;
	foreach (FlzPuParticipant::find_expired(flzpu_retention_cutoff()) as $participant) {
		FlzPuRegistrationService::delete($participant);
		++$deleted;
	}

	return $deleted;
}

function flzpu_handle_manual_privacy_cleanup(): void
{
	if (!current_user_can('flz_pu')) {
		wp_die(esc_html__('Keine Berechtigung.', 'flz-probeunterricht'));
	}
	check_admin_referer('flzpu_delete_expired_participants');
	if (!isset($_POST['confirm_delete_expired_participants'])) {
		wp_die(esc_html__('Die ausdrückliche Löschbestätigung fehlt.', 'flz-probeunterricht'));
	}

	try {
		$deleted = flzpu_delete_expired_participants();
		$url = add_query_arg(array('page' => 'flzpu_settings', 'privacy_deleted' => $deleted), admin_url('admin.php'));
		wp_safe_redirect($url);
		exit;
	} catch (Throwable $error) {
		flzpu_log_error($error, 'Manuelles Löschen abgelaufener Probeunterrichtsanmeldungen');
		wp_die(esc_html__('Die abgelaufenen Anmeldungen konnten nicht vollständig gelöscht werden.', 'flz-probeunterricht'));
	}
}

