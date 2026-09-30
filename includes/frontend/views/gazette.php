<?php
/**
 * The Imperial Gazette: the galaxy's news, readable by anyone.
 * @var object|null $p the pilot, when one is signed in
 */
if (!defined('ABSPATH')) exit;
$news = IB_Messages::news(100);
$labels = [
    'forge' => 'Creation', 'new_player' => 'New pilot', 'destroyed' => 'Ship lost',
    'planet' => 'Planets', 'worldseed' => 'Worldseed', 'ship' => 'Shipyard',
    'team' => 'Teams', 'turns_spent' => 'Turns', 'bigbang' => 'Creation', 'discovery' => 'Frontier',
];
?>
<div class="ib-panel">
    <h2>The Imperial Gazette</h2>
    <p class="ib-small ib-dim">Dispatches from across the Imperium: pilots taking to the spacelanes and flying their
        last turn of the day, worlds claimed and seeded, ships bought and lost, houses founded, and what befalls pilots out on the frontier.
        <?php echo (int) IB_Settings::get('news_retention_days'); ?> days of news are kept.</p>

    <?php if (!$news) : ?>
        <p class="ib-dim">The Gazette has nothing to report yet.</p>
    <?php else : ?>
        <div class="ib-table-wrap">
        <table class="ib-table">
            <thead><tr><th>When</th><th>Dispatch</th><th>Kind</th></tr></thead>
            <tbody>
            <?php foreach ($news as $n) : ?>
                <tr>
                    <td class="ib-dim" style="white-space:nowrap"><?php echo esc_html(IB_UI::time_ago($n->created_at)); ?></td>
                    <td><?php echo esc_html($n->message); ?></td>
                    <td class="ib-dim ib-small"><?php echo esc_html($labels[$n->event_type] ?? ucfirst(str_replace('_', ' ', $n->event_type))); ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div>
    <?php endif; ?>

    <?php if (empty($p)) : ?>
        <p>
            <a class="ib-btn" href="<?php echo esc_url(wp_login_url(get_permalink())); ?>">Sign in to fly</a>
            <a class="ib-btn ib-btn-alt" href="<?php echo esc_url(IB_UI::url('howto')); ?>">How to play</a>
        </p>
    <?php endif; ?>
</div>
