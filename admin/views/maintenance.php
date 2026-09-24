<?php
if (!defined('ABSPATH')) exit;
$fmt_next = function ($hook) {
    $ts = wp_next_scheduled($hook);
    return $ts ? get_date_from_gmt(gmdate('Y-m-d H:i:s', $ts), 'Y-m-d H:i:s') : 'not scheduled';
};
if (!wp_next_scheduled(IB_Maintenance::HOURLY_HOOK) || !wp_next_scheduled(IB_Maintenance::DAILY_HOOK)) {
    IB_Maintenance::schedule();
}
$cron_url = home_url('/wp-cron.php?doing_wp_cron');
$php_bin = defined('PHP_BINARY') && PHP_BINARY ? PHP_BINARY : 'php';
$hourly_php = IB_PATH . 'maintenance/hourly_maintenance.php';
$daily_php = IB_PATH . 'maintenance/daily_maintenance.php';
$wp_cron_off = defined('DISABLE_WP_CRON') && DISABLE_WP_CRON;

/** A read-only field that selects itself when clicked, so the command is easy to copy. */
$cmd = function ($command) {
    echo '<p><input type="text" class="large-text code" readonly onfocus="this.select()" value="' . esc_attr($command) . '"></p>';
};
?>
<table class="widefat striped" style="max-width:860px">
    <thead><tr><th>Job</th><th>What it does</th><th>Last run</th><th>Next run</th><th></th></tr></thead>
    <tbody>
        <tr>
            <td><strong>Hourly</strong></td>
            <td>Ports restock, planets produce, Vraxori regenerate, roaming aliens move.</td>
            <td><?php echo esc_html(get_option('ib_last_hourly', 'never')); ?></td>
            <td><?php echo esc_html($fmt_next(IB_Maintenance::HOURLY_HOOK)); ?></td>
            <td><?php echo IB_Admin::form_open('run_maintenance'); ?><input type="hidden" name="which" value="hourly"><button class="button">Run now</button></form></td>
        </tr>
        <tr>
            <td><strong>Daily</strong></td>
            <td>Turns reset, colonies grow, old news and read mail purged.</td>
            <td><?php echo esc_html(get_option('ib_last_daily', 'never')); ?></td>
            <td><?php echo esc_html($fmt_next(IB_Maintenance::DAILY_HOOK)); ?></td>
            <td><?php echo IB_Admin::form_open('run_maintenance'); ?><input type="hidden" name="which" value="daily"><button class="button">Run now</button></form></td>
        </tr>
    </tbody>
</table>
<p class="description" style="max-width:860px">Turns also reset the first time a pilot visits on a new day, so play works even if cron is late.</p>

<div class="ib-box" style="max-width:860px">
    <h2>Set up a real cron job</h2>
    <p>WordPress's own scheduler (WP-Cron) only runs when someone visits the site, so on a quiet site ports can go
        hours without restocking and planets without producing. A real cron job, added in your hosting control panel, fixes that.</p>
    <p><strong>Running both is safe.</strong> Each job takes a database lock before it does anything, so only one run of a
        kind happens at a time no matter what started it, and a run that arrives while another is working stands down
        instead of repeating it. Each job also refuses to run twice in the same period. You do not need to disable
        WP-Cron, which many shared hosts will not let you do anyway.
        <em>WP-Cron is currently <?php echo $wp_cron_off ? 'disabled on this site (DISABLE_WP_CRON is set), so a real cron job is required' : 'enabled on this site'; ?>.</em></p>

    <h3>Recommended: one job, every 5 minutes</h3>
    <p>This runs everything WordPress has scheduled, including this game's two jobs, close to their proper times, and
        WordPress works out your site's timezone itself. In cPanel open <strong>Advanced → Cron Jobs</strong>, choose
        <strong>Once Per Five Minutes</strong> under <em>Common Settings</em>, and paste this into <strong>Command</strong>
        (click the box to select it):</p>
    <?php $cmd('curl -s ' . $cron_url . ' > /dev/null 2>&1'); ?>
    <p class="description">If the host has no <code>curl</code>, use <code>wget</code>:</p>
    <?php $cmd('wget -q -O - ' . $cron_url . ' > /dev/null 2>&1'); ?>
    <p class="description">Editing a crontab by hand instead? Paste the whole line:</p>
    <?php $cmd('*/5 * * * * curl -s ' . $cron_url . ' > /dev/null 2>&1'); ?>

    <h3>Alternative: run the game's jobs directly</h3>
    <p>Use these if your host blocks cron from making web requests, or you want the game's jobs on their own schedule.
        Run the first <strong>hourly</strong> (cPanel: <em>Once Per Hour</em>) and the second <strong>daily</strong> at your
        site's midnight (cPanel: <em>Once Per Day</em>, then set the hour).</p>
    <?php $cmd($php_bin . ' ' . $hourly_php); ?>
    <?php $cmd($php_bin . ' ' . $daily_php); ?>
    <p class="description">
        That is the PHP this site runs (<code><?php echo esc_html($php_bin); ?></code>). Command-line PHP is often
        <code>/usr/local/bin/php</code> on cPanel; if cron reports "command not found", ask your host for the right path
        or simply use <code>php</code>.<br>
        Cron follows the <em>server's</em> clock, often UTC, while the game uses the timezone in Settings → General
        (currently <code><?php echo esc_html(wp_timezone_string()); ?></code>, where the local time is now
        <?php echo esc_html(current_time('H:i')); ?>). Choose the hour that matches your local midnight.<br>
        These scripts run only the game's jobs, so keep the 5-minute job as well if you can; that is what keeps
        WordPress's own tasks, such as update checks and scheduled posts, running on time.
    </p>

    <h3>Checking it works</h3>
    <p>After an hour or so, <strong>Last run</strong> in the table above should keep moving. Running a command by hand in a
        terminal prints what it did, or tells you it was skipped because the job already ran this period.</p>
</div>
