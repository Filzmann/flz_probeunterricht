<?php

defined('ABSPATH') || exit;

function flzpu_unconfirmed_release_hours(): int
{
	return max(1, min(720, absint(get_option('flzpu_unconfirmed_release_hours', 48))));
}

function flzpu_unconfirmed_warning_hours(): int
{
	return max(0, min(flzpu_unconfirmed_release_hours() - 1, absint(get_option('flzpu_unconfirmed_warning_hours', 24))));
}

function flzpu_schedule_unconfirmed_release(): void
{
	if (!wp_next_scheduled('flzpu_hourly_unconfirmed_release')) {
		$result = wp_schedule_event(time() + HOUR_IN_SECONDS, 'hourly', 'flzpu_hourly_unconfirmed_release', array(), true);
		if (is_wp_error($result) || false === $result) {
			throw new RuntimeException('Der stündliche Freigabetermin konnte nicht eingerichtet werden.');
		}
	}
}

function flzpu_unschedule_unconfirmed_release(): void
{
	$result = wp_clear_scheduled_hook('flzpu_hourly_unconfirmed_release', array(), true);
	if (is_wp_error($result) || false === $result) {
		throw new RuntimeException('Der stündliche Freigabetermin konnte nicht entfernt werden.');
	}
}

function flzpu_send_unconfirmed_release_mail(FlzPuParticipant $participant, string $kind): void
{
	$email = sanitize_email((string) $participant->email);
	if (!is_email($email)) {
		return;
	}
	$warning = 'warning' === $kind;
	$subject = $warning ? 'Erinnerung: Probeunterricht bestätigen' : 'Probeunterrichtsvormerkung freigegeben';
	$message = $warning
		? 'Bitte bestätigen Sie die vorgemerkte Teilnahme mit dem bereits zugesandten Link. Andernfalls wird der Platz wieder freigegeben.'
		: 'Die vorgemerkte Teilnahme ist abgelaufen. Der reservierte Platz wurde wieder freigegeben.';
	if (!wp_mail($email, $subject, $message, array('Content-Type: text/plain; charset=UTF-8'))) {
		throw new RuntimeException('Die Benachrichtigung zur Probeunterrichtsfreigabe konnte nicht versendet werden.');
	}
}

function flzpu_run_unconfirmed_release(): void
{
	if (!(bool) get_option('flzpu_unconfirmed_release_enabled', 0)) {
		return;
	}
	$now = current_datetime()->format('Y-m-d H:i:s');
	$warning_end = current_datetime()->modify('+' . flzpu_unconfirmed_warning_hours() . ' hours')->format('Y-m-d H:i:s');
	try {
		foreach (FlzPuParticipant::find_pending_expiring_between($now, $warning_end) as $participant) {
			$participant->activationWarningSentAt = $now;
			$participant->updated_at = $now;
			$participant->save();
			flzpu_send_unconfirmed_release_mail($participant, 'warning');
		}
		foreach (FlzPuParticipant::find_pending_expired($now) as $participant) {
			$email_snapshot = (string) $participant->email;
			FlzPuRegistrationService::delete($participant);
			$participant->email = $email_snapshot;
			flzpu_send_unconfirmed_release_mail($participant, 'released');
		}
	} catch (Throwable $error) {
		flzpu_log_error($error, 'Automatische Freigabe vorgemerkter Probeunterrichtsanmeldungen');
	}
}

add_action('flzpu_hourly_unconfirmed_release', 'flzpu_run_unconfirmed_release');
