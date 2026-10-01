<?php
if (!defined('ABSPATH')) exit;

/**
 * Scheduled upkeep. Runs from WP-Cron, from the admin Maintenance page,
 * or from the scripts in /maintenance for a real system cron.
 */
class IB_Maintenance {
    const HOURLY_HOOK = 'ib_hourly_maintenance';
    const DAILY_HOOK = 'ib_daily_maintenance';

    /** The Cron Maintenance Log: one line per job per day, the most recent LOG_DAYS days. */
    const LOG_OPTION = 'ib_cron_log';
    const LOG_DAYS = 10;

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

    /** WP-Cron entry points, so the log can say what started a run. */
    public static function cron_daily() {
        self::daily(false, 'wpcron');
    }

    public static function cron_hourly() {
        self::hourly(false, 'wpcron');
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
     * Every start is written to the Cron Maintenance Log, whether it did the work or stood down.
     */
    public static function hourly($force = false, $source = null) {
        $source = self::source($source);
        $today = IB_Game::today();
        if (!IB_Game::universe_exists()) return self::log_run('hourly', $today, $source, 'No universe.', false);
        if (!$force && self::ran_within('ib_last_hourly', 50 * MINUTE_IN_SECONDS)) {
            return self::log_run('hourly', $today, $source,
                'Hourly maintenance skipped: it already ran at ' . get_option('ib_last_hourly') . '.', false);
        }
        if (!self::lock('hourly')) {
            return self::log_run('hourly', $today, $source,
                'Hourly maintenance is already running elsewhere; this run stood down.', false);
        }
        try {
            // Re-check inside the lock: the run we queued behind may have just finished this period.
            if (!$force && self::ran_within('ib_last_hourly', 50 * MINUTE_IN_SECONDS)) {
                return self::log_run('hourly', $today, $source,
                    'Hourly maintenance skipped: another run just completed it at ' . get_option('ib_last_hourly') . '.', false);
            }
            $ports = IB_Ports::regenerate();
            $planets = IB_Planets::produce();
            $moved = IB_Factions::tick();
            update_option('ib_last_hourly', current_time('mysql'), false);
            return self::log_run('hourly', $today, $source, sprintf(
                'Hourly maintenance: %d ports restocked, %d planets produced, %d alien fleets moved.',
                (int) $ports, $planets, $moved), true);
        } finally {
            self::unlock('hourly');
        }
    }

    /**
     * Turns reset, colonies grow, old news and read mail are cleaned up.
     * Runs at most once per calendar day (site timezone) unless $force is set.
     * Every start is written to the Cron Maintenance Log, whether it did the work or stood down.
     *
     * @param bool        $force  true from the admin "Run now" button
     * @param string|null $source what started this run: wpcron, admin, cli, or null to work it out
     */
    public static function daily($force = false, $source = null) {
        $source = self::source($source);
        $today = IB_Game::today();
        if (!IB_Game::universe_exists()) return self::log_run('daily', $today, $source, 'No universe.', false);
        if (!$force && substr((string) get_option('ib_last_daily'), 0, 10) === $today) {
            return self::log_run('daily', $today, $source,
                'Daily maintenance skipped: it already ran today at ' . get_option('ib_last_daily') . '.', false);
        }
        if (!self::lock('daily')) {
            return self::log_run('daily', $today, $source,
                'Daily maintenance is already running elsewhere; this run stood down.', false);
        }
        try {
            if (!$force && substr((string) get_option('ib_last_daily'), 0, 10) === $today) {
                return self::log_run('daily', $today, $source,
                    'Daily maintenance skipped: another run just completed it at ' . get_option('ib_last_daily') . '.', false);
            }
            return self::log_run('daily', $today, $source, self::run_daily($today), true);
        } finally {
            self::unlock('daily');
        }
    }

    /** Works out what started a run when the caller did not say. */
    private static function source($source) {
        if ($source) return $source;
        if (PHP_SAPI === 'cli') return 'cli';
        if (defined('DOING_CRON') && DOING_CRON) return 'wpcron';
        return 'other';
    }

    /**
     * Records one start in the Cron Maintenance Log and hands back the message unchanged.
     *
     * A job and a day make one line. The line belongs to the run that did the work: a start that
     * skips, because the period is already done or another run holds the lock, only adds to the
     * count and never overwrites it. Until a run does the work the line shows the latest start,
     * so a day that only ever stood down still says so.
     */
    private static function log_run($job, $day, $source, $result, $worked) {
        $log = get_option(self::LOG_OPTION, []);
        if (!is_array($log)) $log = [];
        $key = $day . '|' . $job;
        $entry = isset($log[$key]) && is_array($log[$key]) ? $log[$key] : [];
        $entry += ['job' => $job, 'day' => $day, 'starts' => 0, 'runs' => 0, 'worked' => false,
                   'time' => '', 'source' => '', 'who' => '', 'result' => ''];
        $entry['starts']++;
        if ($worked) $entry['runs']++;
        if ($worked || empty($entry['worked'])) {
            $user = $source === 'admin' && function_exists('wp_get_current_user') ? wp_get_current_user() : null;
            $entry['time'] = current_time('mysql');
            $entry['source'] = $source;
            $entry['who'] = $user && $user->exists() ? $user->user_login : '';
            $entry['result'] = $result;
            $entry['worked'] = $worked;
        }
        $log[$key] = $entry;
        self::trim_log($log);
        update_option(self::LOG_OPTION, $log, false);
        return $result;
    }

    /** Keeps the most recent LOG_DAYS days, newest first, with both jobs inside them. */
    private static function trim_log(&$log) {
        krsort($log);
        $days = [];
        foreach ($log as $key => $entry) {
            $day = isset($entry['day']) ? $entry['day'] : substr((string) $key, 0, 10);
            if (!in_array($day, $days, true)) $days[] = $day;
            if (count($days) > self::LOG_DAYS) unset($log[$key]);
        }
    }

    /** The Cron Maintenance Log, newest day first and the daily job above the hourly one. */
    public static function cron_log() {
        $log = get_option(self::LOG_OPTION, []);
        if (!is_array($log)) return [];
        self::trim_log($log);
        uasort($log, function ($a, $b) {
            $day = strcmp((string) $b['day'], (string) $a['day']);
            return $day ?: strcmp((string) $a['job'], (string) $b['job']);
        });
        return $log;
    }

    /** "WP-Cron", "Run now by alice", "Server cron" - what started the run on a log line. */
    public static function source_label($entry) {
        switch (isset($entry['source']) ? $entry['source'] : '') {
            case 'wpcron': return 'WP-Cron';
            case 'cli': return 'Server cron';
            case 'admin': return !empty($entry['who']) ? 'Run now by ' . $entry['who'] : 'Run now';
            default: return 'Unknown';
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
