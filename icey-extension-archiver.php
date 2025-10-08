<?php
/**
 * Plugin Name: Icey - Extension archiver
 * Description: Securely archive inactive plugins to prevent security risks and avoid WordPress warnings. Restore anytime with a single click.
 * Version: 1.0.3
 * Author: Icey
 * Author URI: https://icey.se/
 * License: GPLv2 or later
 * License URI: http://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: icey-extension-archiver
 * Domain Path: /languages
 */

if (!defined('ABSPATH')) { exit; }

function icey25ea_load_textdomain() {
    load_plugin_textdomain('icey-extension-archiver', false, dirname(plugin_basename(__FILE__)) . '/languages');
}
add_action('plugins_loaded', 'icey25ea_load_textdomain');

require_once ABSPATH . 'wp-admin/includes/file.php';
WP_Filesystem();

global $wp_filesystem;

if (!defined('ICEY25EA_EXTENSION_ARCHIVE_DIR')) {
	define('ICEY25EA_EXTENSION_ARCHIVE_DIR', WP_CONTENT_DIR . '/_icey_extensions_archived');
}

if (!$wp_filesystem->is_dir(ICEY25EA_EXTENSION_ARCHIVE_DIR)) {
	$wp_filesystem->mkdir(ICEY25EA_EXTENSION_ARCHIVE_DIR, 0755);
}

add_action('admin_menu', function() {
	add_plugins_page(
		__('Archived Extensions', 'icey-extension-archiver'),
		__('Archived Extensions', 'icey-extension-archiver'),
		'manage_options',
		'icey25ea_extension_archive',
		'icey25ea_extension_archive_admin_page'
	);
});

function icey25ea_extension_archive_admin_page() {
	global $wp_filesystem;

	if (!$wp_filesystem->is_dir(ICEY25EA_EXTENSION_ARCHIVE_DIR)) {
		echo '<div class="wrap"><h1>' . esc_html__('Archived Extensions', 'icey-extension-archiver') . '</h1>';
		echo '<p>' . esc_html__('No archived extensions found.', 'icey-extension-archiver') . '</p></div>';
		return;
	}

	$archived_extensions = array_diff(scandir(ICEY25EA_EXTENSION_ARCHIVE_DIR), ['.', '..']);
	echo '<div class="wrap"><h1>' . esc_html__('Archived Extensions', 'icey-extension-archiver') . '</h1>';
	echo '<table class="wp-list-table widefat fixed striped">';
	echo '<thead><tr><th>' . esc_html__('Extension', 'icey-extension-archiver') . '</th><th>' . esc_html__('Actions', 'icey-extension-archiver') . '</th></tr></thead><tbody>';

	foreach ($archived_extensions as $extension) {
		echo '<tr><td>' . esc_html($extension) . '</td>';
		echo '<td><a href="' . esc_url(wp_nonce_url('?page=icey25ea_extension_archive&restore=' . urlencode($extension), 'icey25ea_restore_extension', 'nonce')) . '" class="button">' . esc_html__('Restore', 'icey-extension-archiver') . '</a></td></tr>';
	}
	echo '</tbody></table></div>';
	
	if (isset($_GET['restore']) && check_admin_referer('icey25ea_restore_extension', 'nonce')) {
		icey25ea_extension_restore(wp_unslash(sanitize_text_field(wp_unslash($_GET['restore']))));
	}
}

function icey25ea_extension_archive($extension) {
	global $wp_filesystem;

	if ($extension === 'icey-extension-archiver') {
		return;
	}

	$source      = WP_PLUGIN_DIR . '/' . $extension;
	$destination = ICEY25EA_EXTENSION_ARCHIVE_DIR . '/' . $extension;

	if ($wp_filesystem->is_dir($source)) {
		$wp_filesystem->move($source, $destination);
	}
}

function icey25ea_extension_restore($extension) {
	global $wp_filesystem;

	$source      = ICEY25EA_EXTENSION_ARCHIVE_DIR . '/' . $extension;
	$destination = WP_PLUGIN_DIR . '/' . $extension;

	if ($wp_filesystem->is_dir($source)) {
		$wp_filesystem->move($source, $destination);
		wp_redirect(admin_url('plugins.php'));
		exit;
	}
}

add_filter('plugin_action_links', function($actions, $plugin_file) {
	$plugin_folder = dirname($plugin_file);
	$plugin_status = is_plugin_active($plugin_file);

	if ($plugin_folder === 'icey-extension-archiver') {
		return $actions;
	}
	
	if (is_dir(WP_PLUGIN_DIR . '/' . $plugin_folder)) {
		if ($plugin_status) {
			$actions['archive'] = '<span style="color: gray; cursor: not-allowed;" title="' . esc_attr__('Disable extension before archiving', 'icey-extension-archiver') . '">' . esc_html__('Archive', 'icey-extension-archiver') . '</span>';
		} else {
			$actions['archive'] = '<a href="' . esc_url(wp_nonce_url('?archive_extension=' . urlencode($plugin_folder), 'icey25ea_archive_extension', 'nonce')) . '">' . esc_html__('Archive', 'icey-extension-archiver') . '</a>';
		}
	}

	return $actions;
}, 10, 2);

add_action('admin_init', function() {
	if (isset($_GET['archive_extension']) && $_GET['archive_extension'] !== 'icey-extension-archiver' && check_admin_referer('icey25ea_archive_extension', 'nonce')) {
		icey25ea_extension_archive(wp_unslash(sanitize_text_field(wp_unslash($_GET['archive_extension']))));
		wp_redirect(admin_url('plugins.php'));
		exit;
	}
});