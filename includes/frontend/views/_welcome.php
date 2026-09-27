<?php
/**
 * What visitors see on the home page before signing in: the pitch, how to sign in,
 * the full "How to play" guide and the current standings.
 */
if (!defined('ABSPATH')) exit;
global $wpdb;
$has_universe = IB_Game::universe_exists();
?>
<div class="ib-panel">
    <h2>Welcome, would-be Baron</h2>
    <p>The Imperium has opened its frontier. From Aurelia, seat of the Imperial Throne, chartered traders set out to buy
        low and sell high, chart unknown space, seed new worlds and fight their way up the ranks of nobility, from humble
        Vagrant to Imperial Paragon.</p>
    <p><?php echo esc_html(IB_GAME_NAME); ?> is played right here in your browser, a few turns each day. You need an
        account on this site to fly: your pilot's alias is all other players ever see.</p>
    <p>
        <a class="ib-btn" href="<?php echo esc_url(wp_login_url(get_permalink())); ?>">Sign in</a>
        <?php if (get_option('users_can_register')) : ?>
            <a class="ib-btn ib-btn-alt" href="<?php echo esc_url(wp_registration_url()); ?>">Create an account</a>
        <?php endif; ?>
    </p>
    <?php if (!$has_universe) : ?>
        <p class="ib-dim">The galaxy has not been forged yet, so the game is not open to pilots just now.</p>
    <?php endif; ?>
</div>

<?php $ib_help_open = true; include IB_PATH . 'includes/frontend/views/_how-to-play.php'; ?>

<?php if ($has_universe) :
    $players = $wpdb->get_results('SELECT * FROM ' . IB_DB::t('players'));
    foreach ($players as $pl) $pl->worth = IB_Player::net_worth($pl);
    usort($players, function ($a, $b) { return $b->worth <=> $a->worth; });
    $leaders = array_slice($players, 0, 10);
    $teams = IB_Teams::all();
    foreach ($teams as $t) $t->worth = IB_Teams::net_worth($t->id);
    usort($teams, function ($a, $b) { return $b->worth <=> $a->worth; });
    $news = IB_Messages::news(6);
    ?>
    <div class="ib-grid">
        <div class="ib-panel">
            <h3>Leading Barons</h3>
            <?php if (!$leaders) : ?>
                <p class="ib-dim">No pilots have taken to the spacelanes yet. The first name on this board could be yours.</p>
            <?php else : ?>
                <table class="ib-table">
                    <thead><tr><th>#</th><th>Pilot</th><th>Experience</th><th>Net worth</th></tr></thead>
                    <tbody>
                    <?php foreach ($leaders as $i => $pl) : ?>
                        <tr>
                            <td><?php echo $i + 1; ?></td>
                            <td><?php echo esc_html(IB_Game::rank_title($pl->experience) . ' ' . $pl->alias_name); ?></td>
                            <td><?php echo IB_Game::fmt($pl->experience); ?></td>
                            <td><?php echo IB_Game::fmt($pl->worth); ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
                <p class="ib-small ib-dim"><?php echo IB_Game::fmt(count($players)); ?> pilots are flying in a galaxy of
                    <?php echo IB_Game::fmt((int) $wpdb->get_var('SELECT COUNT(*) FROM ' . IB_DB::t('sectors'))); ?> sectors.</p>
            <?php endif; ?>
        </div>
        <div class="ib-panel">
            <h3>Leading houses</h3>
            <?php if (!$teams) : ?>
                <p class="ib-dim">No teams have been founded yet.</p>
            <?php else : ?>
                <table class="ib-table">
                    <thead><tr><th>#</th><th>Team</th><th>Members</th><th>Net worth</th></tr></thead>
                    <tbody>
                    <?php foreach (array_slice($teams, 0, 10) as $i => $t) : ?>
                        <tr>
                            <td><?php echo $i + 1; ?></td>
                            <td><?php echo esc_html($t->team_name); ?></td>
                            <td><?php echo (int) $t->members; ?></td>
                            <td><?php echo IB_Game::fmt($t->worth); ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>

    <?php if ($news) : ?>
        <div class="ib-panel">
            <h3>Imperial Gazette</h3>
            <ul class="ib-news">
                <?php foreach ($news as $n) : ?>
                    <li><span class="ib-dim"><?php echo esc_html(IB_UI::time_ago($n->created_at)); ?></span> <?php echo esc_html($n->message); ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>
<?php endif; ?>
