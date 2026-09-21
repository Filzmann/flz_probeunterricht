<?php

defined('ABSPATH') || exit;

require_once __DIR__ . '/settings.php';
require_once __DIR__ . '/schools.php';
require_once __DIR__ . '/participants.php';

/**
 * Registriert die Backend-Seiten des Probeunterrichts.
 */
function flzpu_probeunterricht_menu(): void
{
	add_menu_page(
		'FLZ Probeunterricht',
		'FLZ Probeunterricht',
		'flz_pu',
		'flzpu_participants',
		'flzpu_participants_page',
		'dashicons-welcome-learn-more',
		28
	);
	add_submenu_page(
		'flzpu_participants',
		'Schulen',
		'Schulen',
		'flz_pu',
		'flzpu_schools',
		'flzpu_schools_page'
	);
	add_submenu_page(
		'flzpu_participants',
		'Einstellungen',
		'Einstellungen',
		'flz_pu',
		'flzpu_settings',
		'flzpu_settings_page'
	);
}

add_action('admin_menu', 'flzpu_probeunterricht_menu');
add_action('admin_post_flzpu_export_participants_csv', 'flzpu_export_participants_csv');
add_action('admin_post_flzpu_export_schools_csv', 'flzpu_export_schools_csv');
add_action('admin_enqueue_scripts', 'flzpu_maybe_enqueue_admin_ui_assets');

function flzpu_maybe_enqueue_admin_ui_assets(string $hook_suffix): void
{
	if (str_contains($hook_suffix, 'flzpu_') && function_exists('flz_ui_components_enqueue_assets')) {
		flz_ui_components_enqueue_assets();
	}
}
