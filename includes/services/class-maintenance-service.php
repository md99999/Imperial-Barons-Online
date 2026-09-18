<?php
if (!defined('ABSPATH')) exit;

/**
 * Scheduled upkeep. Runs from WP-Cron, from the admin Maintenance page,
 * or from the scripts in /maintenance for a real system cron.
 */
class IB_Maintenance {
    const HOURLY_HOOK = 'ib_hourly_maintenance';
    const DAILY_HOOK = 'ib_daily_maintenance';

    public static function regenerateVraxori(int $current, int $regen): int {
        return min(IB_Factions::VRAXORI_MAX_FIGHTERS, $current + $regen);
    }

    public static function schedule() {
        if (!wp_next_scheduled(self::HOURLY_HOOK)) {
            wp_schedule_event(time() + HOUR_IN_SECONDS, 'hourly', self::HOURLY_HOOK);
        }
        if (!wp_next_scheduled(self::DAILY_HOOK)) {
            // First run at the next local midnight.
            $midnight = new DateTime('tomorrow', wp_timezone());
            wp_schedule_event($midnight->getTimestamp(), 'daily', self::DAILY_HOOK);
        }
    }

    public static function unschedule() {
        wp_clear_scheduled_hook(self::HOURLY_HOOK);
        wp_clear_scheduled_hook(self::DAILY_HOOK);
    }

    /**
     * Ports restock, planets produce, alien fleets regenerate and roam.
     * Skips itself if it ran within the last 50 minutes, so WP-Cron and a system cron can both
     * be configured without doubling production; $force (the admin "Run now" button) overrides.
     */
    public static function hourly($force = false) {
        if (!IB_Game::universe_exists()) return 'No universe.';
        $last = strtotime((string) get_option('ib_last_hourly'));
        if (!$force && $last && current_time('timestamp') - $last < 50 * MINUTE_IN_SECONDS) {
            return 'Hourly maintenance skipped: it already ran at ' . get_option('ib_last_hourly') . '.';
        }
        $ports = IB_Ports::regenerate();
        $planets = IB_Planets::produce();
        $moved = IB_Factions::tick();
        update_option('ib_last_hourly', current_time('mysql'), false);
        return sprintf('Hourly maintenance: %d ports restocked, %d planets produced, %d alien fleets moved.', (int) $ports, $planets, $moved);
    }

    /**
     * Turns reset, colonies grow, old news and read mail are cleaned up.
     * Runs at most once per calendar day (site timezone) unless $force is set.
     */
    public static function daily($force = false) {
        global $wpdb;
        if (!IB_Game::universe_exists()) return 'No universe.';
        $today = IB_Game::today();
        if (!$force && substr((string) get_option('ib_last_daily'), 0, 10) === $today) {
            return 'Daily maintenance skipped: it already ran today at ' . get_option('ib_last_daily') . '.';
        }
        $wpdb->query($wpdb->prepare(
            'UPDATE ' . IB_DB::t('players') . ' SET turns_remaining = %d, last_turn_reset = %s WHERE last_turn_reset IS NULL OR last_turn_reset <> %s',
            (int) IB_Settings::get('turns_per_day'), $today, $today
        ));
        IB_Planets::grow();
        $cutoff = date('Y-m-d H:i:s', current_time('timestamp') - (int) IB_Settings::get('news_retention_days') * DAY_IN_SECONDS);
        $wpdb->query($wpdb->prepare('DELETE FROM ' . IB_DB::t('news') . ' WHERE created_at < %s', $cutoff));
        $mail_cutoff = date('Y-m-d H:i:s', current_time('timestamp') - 30 * DAY_IN_SECONDS);
        $wpdb->query($wpdb->prepare('DELETE FROM ' . IB_DB::t('messages') . ' WHERE is_read = 1 AND created_at < %s', $mail_cutoff));
        update_option('ib_last_daily', current_time('mysql'), false);
        return 'Daily maintenance: turns reset, colonies grew, old news and mail purged.';
    }
}
