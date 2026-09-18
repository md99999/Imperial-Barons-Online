<?php
if (!defined('ABSPATH')) exit;
global $wpdb;
$log = $wpdb->get_results('SELECT * FROM ' . IB_DB::t('admin_log') . ' ORDER BY id DESC LIMIT 200');
$news = IB_Messages::news(100);
?>
<h2>Admin audit log</h2>
<table class="widefat striped">
    <thead><tr><th>When</th><th>Type</th><th>User</th><th>Message</th></tr></thead>
    <tbody>
    <?php foreach ($log as $row) :
        $user = $row->user_id ? get_userdata($row->user_id) : null; ?>
        <tr>
            <td><?php echo esc_html($row->created_at); ?></td>
            <td><?php echo esc_html($row->event_type); ?></td>
            <td><?php echo $user ? esc_html($user->user_login) : '&mdash;'; ?></td>
            <td><?php echo esc_html($row->message); ?></td>
        </tr>
    <?php endforeach; ?>
    <?php if (!$log) : ?><tr><td colspan="4">No entries.</td></tr><?php endif; ?>
    </tbody>
</table>

<h2>Imperial Gazette</h2>
<table class="widefat striped">
    <thead><tr><th>When</th><th>Type</th><th>Message</th></tr></thead>
    <tbody>
    <?php foreach ($news as $row) : ?>
        <tr><td><?php echo esc_html($row->created_at); ?></td><td><?php echo esc_html($row->event_type); ?></td><td><?php echo esc_html($row->message); ?></td></tr>
    <?php endforeach; ?>
    <?php if (!$news) : ?><tr><td colspan="3">No news.</td></tr><?php endif; ?>
    </tbody>
</table>
