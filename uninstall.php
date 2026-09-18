<?php
/**
 * Runs when the plugin is deleted from the Plugins screen.
 * Removes every Imperial Barons Online table and option.
 */
if (!defined('WP_UNINSTALL_PLUGIN')) exit;

global $wpdb;
$tables = ['players', 'sectors', 'warps', 'ports', 'planets', 'teams', 'messages', 'fleets', 'explored', 'news', 'admin_log'];
foreach ($tables as $table) {
    $wpdb->query('DROP TABLE IF EXISTS ' . $wpdb->prefix . 'ib_' . $table);
}
foreach (['ib_settings', 'ib_db_version', 'ib_universe', 'ib_page_ids', 'ib_nav_post_id', 'ib_last_hourly', 'ib_last_daily'] as $option) {
    delete_option($option);
}
wp_clear_scheduled_hook('ib_hourly_maintenance');
wp_clear_scheduled_hook('ib_daily_maintenance');
