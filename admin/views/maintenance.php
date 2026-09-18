<?php
if (!defined('ABSPATH')) exit;
$fmt_next = function ($hook) {
    $ts = wp_next_scheduled($hook);
    return $ts ? get_date_from_gmt(gmdate('Y-m-d H:i:s', $ts), 'Y-m-d H:i:s') : 'not scheduled';
};
if (!wp_next_scheduled(IB_Maintenance::HOURLY_HOOK) || !wp_next_scheduled(IB_Maintenance::DAILY_HOOK)) {
    IB_Maintenance::schedule();
}
?>
<table class="widefat striped" style="max-width:760px">
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
<p class="description" style="max-width:760px">Turns also reset automatically the first time a pilot visits on a new day, so play works even if cron is late.
    WP-Cron only fires when the site receives visits; for exact timing, see <code>maintenance/README.md</code> to use a system cron instead.</p>
