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

<details class="ib-panel ib-help">
    <summary>How to play</summary>
    <ul>
        <li><strong>The goal.</strong> The Imperium has opened its frontier to chartered traders. You start as a Vagrant with one Freetrader and a few thousand credits. Your aim is to rise through the ranks of nobility to Imperial Paragon and become the most powerful Baron in the galaxy. Your objectives:
            <ul>
                <li><em>Build a fortune:</em> trade between ports to grow your credits. Net worth, which counts your credits, cargo, ship, fighters and planets, is the main measure of success.</li>
                <li><em>Earn your title:</em> gain experience by trading, haggling, exploring, colonizing and fighting to climb the peerage from Vagrant through Knight, Baron and Duke to Imperial Paragon.</li>
                <li><em>Explore the frontier:</em> chart the galaxy to find the best trade routes, unclaimed planets and hidden discoveries.</li>
                <li><em>Build an empire:</em> claim or seed planets, settle them with colonists, and fortify them with bastions so they produce wealth and fighters for you.</li>
                <li><em>Command the spacelanes:</em> upgrade your ship, deploy fighters to hold territory, and defeat pirates, alien fleets and rival Barons.</li>
                <li><em>Rise with allies:</em> join or found a team to share planets and defenses and climb the team rankings together.</li>
            </ul>
            The <a href="<?php echo esc_url(IB_UI::url('rankings')); ?>">Rankings</a> show who leads the galaxy in net worth, experience and combat, and which team is strongest. You have <?php echo (int) IB_Settings::get('turns_per_day'); ?> turns a day, so plan carefully: the best Barons make every turn count.</li>
        <li><strong>Turns.</strong> You get <?php echo (int) IB_Settings::get('turns_per_day'); ?> turns per day. They reset at midnight (site time) and unused turns do not carry over. Only three things cost turns:
            <ul>
                <li><em>Warping</em> to another sector: <?php echo (int) IB_Settings::get('move_turn_cost'); ?> turn per jump, whether you steer yourself or use the autopilot.</li>
                <li><em>Docking</em> at a port: <?php echo (int) IB_Settings::get('dock_turn_cost'); ?> turn, charged once when you dock. Once docked you can buy, sell and haggle as much as you like at no further cost, and undocking is free. You stay docked until you warp away or land on a planet, and docking again later costs another turn.</li>
                <li><em>Attacking</em> a ship, planet or fighter group: <?php echo (int) IB_Settings::get('attack_turn_cost'); ?> turn per attack.</li>
            </ul>
            Everything else is free: landing on planets and moving cargo, colonists and fighters, building bastions, launching survey drones and Worldseeds, plotting courses, reading the Map and Computer, messages, teams and rankings. The bar at the top of every page shows the turns you have left.</li>
        <li><strong>Trading.</strong> Port classes show what a port Buys and Sells, in the order Ferrium Ore, Biostock, Machinery. A <span class="ib-port-code"><span class="ib-sell">S</span><span class="ib-buy">B</span><span class="ib-buy">B</span></span> port sells ferrium ore and buys biostock and machinery. Buy where a port sells, and sell where another port buys. Well-stocked ports sell cheaply; ports with strong demand pay the most.</li>
        <li><strong>Specialist goods.</strong> Beyond the three staples, some ports deal in one specialist good: <em>Rare Isotopes</em>, <em>Medicine</em> or <em>Luxuries</em>. They cost far more per hold and their prices swing further, so a single run can be worth many staple runs, but stocks are small and you must find a port that buys what another sells. They are scarce near Aurelia and more common out on the frontier; the Computer's port finder and known-port report both list them.</li>
        <li><strong>Haggling.</strong> Enter your own price per unit when trading. Offer a little better than the listed price and the port may accept, which earns extra experience. Push too hard and it will stop haggling with you for an hour.</li>
        <li><strong>Aurelia and the Imperial Drydock.</strong> The Aurelian Armory in Aurelia (sector 1) sells holds, fighters, shields, survey drones and charts, and recruits colonists (so does Aurelia Prime, the Crown World in the same sector, if you land on it). The Imperial Drydock also sells ships and Worldseeds. The Imperial Gazette gives its location.</li>
        <li><strong>Exploring.</strong> Your sensors sweep every neighbouring sector as you arrive, showing its port, planets and any hostile fighters before you jump. The first time you visit a sector beyond the Imperial Core you earn experience and may find salvage, a credit cache, abandoned fighters or an old survey beacon, or run into a pirate ambush. Survey drones and nebula charts from the Armory or Drydock put more of the galaxy on your map.</li>
        <li><strong>Using the <a href="<?php echo esc_url(IB_UI::url('computer')); ?>">Computer</a>.</strong> Your ship's Computer is your best tool for making every turn count.
            <ul>
                <li><em>Plot a course:</em> enter any sector number to see the shortest route, how many turns it will take, and the ports along the way. You can also click any sector on the Galaxy Map to plot a course there.</li>
                <li><em>Engage autopilot:</em> flies the plotted course one warp at a time, collecting discoveries as it goes. It stops if you run out of turns or come under attack.</li>
                <li><em>Port finder:</em> choose "buy" or "sell" and a commodity to list the nearest charted ports that deal in it, with their stock, price and distance in warps.</li>
                <li><em>Known ports:</em> every port in charted space with its current prices and distance, which is ideal for spotting trade routes.</li>
                <li><em>Deployed fighters:</em> where your fighters are stationed across the galaxy.</li>
            </ul>
            Finding a trade route: use the Port finder to locate a nearby port that <em>sells</em> a commodity cheaply, then one close to it that <em>buys</em> the same commodity at a high price. Two ports a single warp apart that buy and sell opposite goods (for example SBB and BSS) let you trade in both directions, earning a profit on every leg for just a few turns.</li>
        <li><strong>Planets.</strong> Claim an empty planet or create one with a Worldseed. Drop off colonists and they will produce commodities and fighters every hour. Build a bastion for a Vault and stronger defenses.</li>
        <li><strong>Danger.</strong> The Imperial Core (sectors 1-<?php echo (int) IB_Settings::get('core_sectors'); ?>) is safe. Beyond it, alien fleets and other pilots' fighters will attack you. If your ship is destroyed, you return to Aurelia in a new ship and cannot fly until tomorrow.</li>
    </ul>
</details>
