<?php
/**
 * The Imperial Gazette: the galaxy's news, readable by anyone, signed in or not.
 *
 * Shortcode attributes, for putting the feed elsewhere (a sidebar widget, say):
 *   [ib_gazette limit="10" compact="1"]
 * limit   how many dispatches to show; 40 by default on a page, 10 when compact, 200 at most
 * compact "1" for a plain list with no table or buttons, which suits a narrow column
 *
 * @var object|null $p the pilot, when one is signed in
 */
if (!defined('ABSPATH')) exit;

$compact = (bool) IB_Shortcodes::att('compact', false);
$limit = (int) IB_Shortcodes::att('limit', $compact ? 10 : 40);
$limit = max(1, min(200, $limit));
$news = IB_Messages::news($limit);

$labels = [
    'forge' => 'Creation', 'bigbang' => 'Creation', 'new_player' => 'New pilot', 'destroyed' => 'Ship lost',
    'planet' => 'Planets', 'worldseed' => 'Worldseed', 'ship' => 'Shipyard', 'team' => 'Teams',
    'turns_spent' => 'Turns', 'discovery' => 'Frontier', 'promotion' => 'Promotion',
    'trade' => 'Trade', 'bastion' => 'Bastion', 'combat' => 'Combat',
];
?>
<?php if ($compact) : ?>
    <div class="ib-panel ib-gazette-compact">
        <h3>The Imperial Gazette</h3>
        <?php if (!$news) : ?>
            <p class="ib-dim ib-small">No dispatches yet.</p>
        <?php else : ?>
            <ul class="ib-news">
                <?php foreach ($news as $n) : ?>
                    <li class="ib-small"><span class="ib-dim"><?php echo esc_html(IB_UI::time_ago($n->created_at)); ?></span>
                        <?php echo esc_html($n->message); ?></li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
        <p class="ib-small"><a href="<?php echo esc_url(IB_UI::url('gazette')); ?>">All the news</a></p>
    </div>
<?php return; endif; ?>

<div class="ib-panel">
    <h2>The Imperial Gazette</h2>
    <p class="ib-small ib-dim">Dispatches from across the Imperium: pilots joining and flying their last turn of the day,
        worlds claimed, seeded and fortified, great trades struck, ships bought and lost, alien fleets broken, titles won,
        houses founded, and what befalls pilots out on the frontier.
        Showing the last <?php echo (int) $limit; ?> dispatches;
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
            <?php if (get_option('users_can_register')) : ?>
                <a class="ib-btn ib-btn-alt" href="<?php echo esc_url(wp_registration_url()); ?>">Create an account</a>
            <?php endif; ?>
            <a class="ib-btn ib-btn-alt" href="<?php echo esc_url(IB_UI::url('howto')); ?>">How to play</a>
        </p>
    <?php endif; ?>
</div>
