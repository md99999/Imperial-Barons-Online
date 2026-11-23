<?php
/**
 * Runs when the plugin is deleted from the Plugins screen.
 * Removes every Imperial Barons Online table and option.
 */
if (!defined('WP_UNINSTALL_PLUGIN')) exit;

/**
 * Removes everything the plugin stored on one site: its tables, its options and its transients
 * (flash notices, haggling state, den visits, rate-limit markers, cached health checks and the
 * leaderboard cache), and its scheduled jobs.
 */
function ib_uninstall_site() {
    global $wpdb;
    $tables = ['players', 'sectors', 'warps', 'ports', 'planets', 'teams', 'messages', 'fleets', 'explored', 'news', 'admin_log'];
    foreach ($tables as $table) {
        $wpdb->query('DROP TABLE IF EXISTS ' . $wpdb->prefix . 'ib_' . $table);
    }
    $options = ['ib_settings', 'ib_db_version', 'ib_universe', 'ib_page_ids', 'ib_nav_post_id', 'ib_last_hourly',
                'ib_last_daily', 'ib_cron_log', 'ib_fines_fund', 'ib_strongbox', 'ib_strongbox_hint'];
    foreach ($options as $option) {
        delete_option($option);
    }
    foreach (['_transient_ib_', '_transient_timeout_ib_'] as $prefix) {
        $wpdb->query($wpdb->prepare("DELETE FROM {$wpdb->options} WHERE option_name LIKE %s", $wpdb->esc_like($prefix) . '%'));
    }
    wp_clear_scheduled_hook('ib_hourly_maintenance');
    wp_clear_scheduled_hook('ib_daily_maintenance');
}

if (is_multisite()) {
    foreach (get_sites(['fields' => 'ids', 'number' => 0]) as $site_id) {
        switch_to_blog($site_id);
        ib_uninstall_site();
        restore_current_blog();
    }
} else {
    ib_uninstall_site();
}
