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

    /*
     * Running WP-Cron and a real cron job side by side is safe. Each tick takes a database lock
     * before doing anything, so only one run of a kind happens at a time whatever started it, and
     * a run that arrives while another is working stands down instead of repeating the work. Each
     * job also refuses to run twice in the same period. There is no need to disable WP-Cron, which
     * many shared hosts do not allow anyway.
     */

    /** Lock name, scoped to this database and table prefix so sites on shared hosting don't collide. */
    private static function lock_name($job) {
        global $wpdb;
        return 'ib_' . $job . '_' . substr(md5((defined('DB_NAME') ? DB_NAME : '') . $wpdb->prefix), 0, 16);
    }

    /**
     * Takes the named lock without waiting.
     * @return bool true if this process may proceed (also true where the database has no
     *              advisory locks, so maintenance still runs rather than never running)
     */
    private static function lock($job) {
        global $wpdb;
        $got = $wpdb->get_var($wpdb->prepare('SELECT GET_LOCK(%s, 0)', self::lock_name($job)));
        return $got === null ? true : (int) $got === 1;
    }

    private static function unlock($job) {
        global $wpdb;
        $wpdb->query($wpdb->prepare('SELECT RELEASE_LOCK(%s)', self::lock_name($job)));
    }

    private static function ran_within($option, $seconds) {
        $last = strtotime((string) get_option($option));
        return $last && current_time('timestamp') - $last < $seconds;
    }

    /**
     * Ports restock, planets produce, alien fleets regenerate and roam.
     * Skips itself if it ran within the last 50 minutes; $force (the admin "Run now" button)
     * overrides that, but still waits for the lock rather than running alongside another tick.
     */
    public static function hourly($force = false) {
        if (!IB_Game::universe_exists()) return 'No universe.';
        if (!$force && self::ran_within('ib_last_hourly', 50 * MINUTE_IN_SECONDS)) {
            return 'Hourly maintenance skipped: it already ran at ' . get_option('ib_last_hourly') . '.';
        }
        if (!self::lock('hourly')) {
            return 'Hourly maintenance is already running elsewhere; this run stood down.';
        }
        try {
            // Re-check inside the lock: the run we queued behind may have just finished this period.
            if (!$force && self::ran_within('ib_last_hourly', 50 * MINUTE_IN_SECONDS)) {
                return 'Hourly maintenance skipped: another run just completed it at ' . get_option('ib_last_hourly') . '.';
            }
            $ports = IB_Ports::regenerate();
            $planets = IB_Planets::produce();
            $moved = IB_Factions::tick();
            update_option('ib_last_hourly', current_time('mysql'), false);
            return sprintf('Hourly maintenance: %d ports restocked, %d planets produced, %d alien fleets moved.', (int) $ports, $planets, $moved);
        } finally {
            self::unlock('hourly');
        }
    }

    /**
     * Turns reset, colonies grow, old news and read mail are cleaned up.
     * Runs at most once per calendar day (site timezone) unless $force is set.
     */
    public static function daily($force = false) {
        if (!IB_Game::universe_exists()) return 'No universe.';
        $today = IB_Game::today();
        if (!$force && substr((string) get_option('ib_last_daily'), 0, 10) === $today) {
            return 'Daily maintenance skipped: it already ran today at ' . get_option('ib_last_daily') . '.';
        }
        if (!self::lock('daily')) {
            return 'Daily maintenance is already running elsewhere; this run stood down.';
        }
        try {
            if (!$force && substr((string) get_option('ib_last_daily'), 0, 10) === $today) {
                return 'Daily maintenance skipped: another run just completed it at ' . get_option('ib_last_daily') . '.';
            }
            return self::run_daily($today);
        } finally {
            self::unlock('daily');
        }
    }

    private static function run_daily($today) {
        global $wpdb;
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
