<?php
/** @var object|null $p */
if (!defined('ABSPATH')) exit;
global $wpdb;
$news = IB_Messages::news(12);
?>
<?php if (!$p) : ?>
    <div class="ib-panel">
        <h2>Pilot Registration</h2>
        <p>The galaxy is vast, lawless beyond the Imperial Core, and full of profit for a trader with nerve.
            Choose the alias other pilots will know you by and name your first ship.</p>
        <?php echo IB_UI::form_open('register', 'ib-stack'); ?>
            <label>Pilot alias <input type="text" name="alias" maxlength="41" required></label>
            <label>Ship name <input type="text" name="ship_name" maxlength="41" placeholder="Optional"></label>
            <button type="submit" class="ib-btn">Launch my career</button>
        </form>
    </div>
<?php else :
    $port = IB_Ports::in_sector($p->sector_id);
    $sector = $wpdb->get_row($wpdb->prepare('SELECT * FROM ' . IB_DB::t('sectors') . ' WHERE id = %d', $p->sector_id));
    $unread = IB_Messages::unread_count($p->id);
    $team = (int) $p->team_id ? IB_Teams::get($p->team_id) : null;
    ?>
    <div class="ib-grid">
        <div class="ib-panel">
            <h2>Captain's Log</h2>
            <table class="ib-table ib-kv">
                <tr><th>Pilot</th><td><?php echo esc_html(IB_Game::rank_title($p->experience) . ' ' . $p->alias_name); ?></td></tr>
                <tr><th>Ship</th><td><?php echo esc_html($p->ship_name); ?> <span class="ib-dim">(<?php echo esc_html(IB_Player::ship($p)['name']); ?>)</span></td></tr>
                <tr><th>Experience</th><td><?php echo IB_Game::fmt($p->experience); ?></td></tr>
                <tr><th>Alignment</th><td><?php echo esc_html(IB_Game::alignment_label($p->alignment)); ?> (<?php echo (int) $p->alignment; ?>)</td></tr>
                <tr><th>Net worth</th><td><?php echo IB_Game::fmt(IB_Player::net_worth($p)); ?> cr</td></tr>
                <tr><th>Team</th><td><?php echo $team ? esc_html($team->team_name) : '<span class="ib-dim">None</span>'; ?></td></tr>
                <tr><th>Turns today</th><td><?php echo (int) $p->turns_remaining; ?> of <?php echo (int) IB_Settings::get('turns_per_day'); ?></td></tr>
            </table>
            <?php if ($unread) : ?>
                <p><a class="ib-btn ib-btn-alt" href="<?php echo esc_url(IB_UI::url('messages')); ?>">You have <?php echo $unread; ?> unread message<?php echo $unread > 1 ? 's' : ''; ?></a></p>
            <?php endif; ?>
        </div>
        <div class="ib-panel">
            <h2>Current Position</h2>
            <p class="ib-big">Sector <?php echo (int) $p->sector_id; ?></p>
            <p class="ib-dim"><?php echo esc_html($sector ? $sector->nebula : ''); ?></p>
            <?php if ($port) : ?>
                <p>Port: <?php echo esc_html($port->port_name); ?> <?php echo IB_UI::pattern($port); ?></p>
            <?php endif; ?>
            <p>
                <a class="ib-btn" href="<?php echo esc_url(IB_UI::url('sector')); ?>">View sector</a>
                <a class="ib-btn ib-btn-alt" href="<?php echo esc_url(IB_UI::url('computer')); ?>">Computer</a>
            </p>
            <?php if (!(int) $p->turns_remaining) : ?>
                <p class="ib-bad">You are out of turns for today. You can still trade while docked, manage planets and talk to other pilots.</p>
            <?php endif; ?>
        </div>
    </div>
<?php endif; ?>

<div class="ib-panel">
    <h2>Imperial Gazette</h2>
    <?php if (!$news) : ?>
        <p class="ib-dim">All quiet across the galaxy.</p>
    <?php else : ?>
        <ul class="ib-news">
            <?php foreach ($news as $n) : ?>
                <li><span class="ib-dim"><?php echo esc_html(IB_UI::time_ago($n->created_at)); ?></span> <?php echo esc_html($n->message); ?></li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
</div>

<?php include IB_PATH . 'includes/frontend/views/_how-to-play.php'; ?>
