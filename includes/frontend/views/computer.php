<?php
/** @var object $p */
if (!defined('ABSPATH')) exit;
global $wpdb;
$here = (int) $p->sector_id;
$target = isset($_GET['target']) && is_scalar($_GET['target']) ? absint($_GET['target']) : 0;
$find = isset($_GET['find']) && is_string($_GET['find']) ? sanitize_key(wp_unslash($_GET['find'])) : '';
$want = isset($_GET['want']) && $_GET['want'] === 'sell' ? 'sell' : 'buy';
// Ports are known in every charted sector (visited, swept by sensors, or surveyed).
$explored = array_flip(IB_Player::charted_ids($p->id));
$visited = array_flip(IB_Player::visited_ids($p->id));
$dist = IB_Pathfinder::distances(null, $here);
$move_cost = (int) IB_Settings::get('move_turn_cost');

// Ports in explored sectors, nearest first.
$known_ports = [];
foreach ($wpdb->get_results('SELECT * FROM ' . IB_DB::t('ports')) as $port) {
    if (isset($explored[(int) $port->sector_id]) && isset($dist[(int) $port->sector_id])) {
        $port->distance = $dist[(int) $port->sector_id];
        $known_ports[] = $port;
    }
}
usort($known_ports, function ($a, $b) { return $a->distance <=> $b->distance; });
?>
<div class="ib-panel">
    <h2>Ship's Computer</h2>
    <?php echo IB_UI::get_form_open('computer'); ?>
        <label>Plot course to sector <input type="number" name="target" min="1" value="<?php echo $target ?: ''; ?>" class="ib-num" required></label>
        <button type="submit" class="ib-btn">Plot</button>
    </form>

    <?php if ($target) :
        $path = IB_Pathfinder::shortestPath(null, $here, $target); ?>
        <?php if (!$path) : ?>
            <p class="ib-bad">No route to sector <?php echo $target; ?> could be found.</p>
        <?php elseif (count($path) === 1) : ?>
            <p>You are already in sector <?php echo $target; ?>.</p>
        <?php else :
            $hops = count($path) - 1; ?>
            <p>Course: <?php echo $hops; ?> warp<?php echo $hops > 1 ? 's' : ''; ?>, <?php echo $hops * $move_cost; ?> turn(s).
                You have <?php echo (int) $p->turns_remaining; ?>.</p>
            <ol class="ib-path">
                <?php foreach ($path as $sid) :
                    $port = isset($explored[$sid]) ? IB_Ports::in_sector($sid) : null; ?>
                    <li class="<?php echo $sid === $here ? 'ib-current' : ''; ?>">
                        <?php echo (int) $sid; ?><?php echo isset($visited[$sid]) ? '' : '<span class="ib-dim">*</span>'; ?>
                        <?php echo $port ? IB_UI::pattern($port) : ''; ?>
                    </li>
                <?php endforeach; ?>
            </ol>
            <?php echo IB_UI::button('autopilot', 'Engage autopilot', ['target' => $target]); ?>
            <p class="ib-small ib-dim">Autopilot stops early if you run out of turns or meet trouble. * = not yet visited.</p>
        <?php endif; ?>
    <?php endif; ?>
</div>

<div class="ib-panel">
    <h3>Port finder</h3>
    <?php echo IB_UI::get_form_open('computer'); ?>
        <label>Where can I
            <select name="want">
                <option value="buy" <?php selected($want, 'buy'); ?>>buy</option>
                <option value="sell" <?php selected($want, 'sell'); ?>>sell</option>
            </select>
        </label>
        <select name="find" aria-label="Commodity">
            <?php foreach (IB_Game::COMMODITIES as $key => $c) : ?>
                <option value="<?php echo esc_attr($key); ?>" <?php selected($find, $key); ?>><?php echo esc_html($c['label']); ?></option>
            <?php endforeach; ?>
        </select>
        <button type="submit" class="ib-btn">Search</button>
    </form>
    <?php if (isset(IB_Game::COMMODITIES[$find])) :
        $matches = array_filter($known_ports, function ($port) use ($find, $want) {
            return IB_Ports::mode($port, $find) === ($want === 'buy' ? 'selling' : 'buying');
        });
        $matches = array_slice(array_values($matches), 0, 10); ?>
        <?php if (!$matches) : ?>
            <p class="ib-dim">No charted port matches. Explore further, or buy a survey drone or nebula chart.</p>
        <?php else : ?>
            <table class="ib-table">
                <thead><tr><th>Sector</th><th>Port</th><th>Class</th><th>Units</th><th>Price</th><th>Warps</th><th></th></tr></thead>
                <tbody>
                <?php foreach ($matches as $port) : ?>
                    <tr>
                        <td><?php echo (int) $port->sector_id; ?></td>
                        <td><?php echo esc_html($port->port_name); ?></td>
                        <td><?php echo IB_UI::pattern($port); ?></td>
                        <td><?php echo IB_Game::fmt(IB_Ports::qty($port, $find)); ?></td>
                        <td><?php echo IB_Game::fmt(IB_Ports::price($port, $find)); ?></td>
                        <td><?php echo (int) $port->distance; ?></td>
                        <td><a href="<?php echo esc_url(IB_UI::url('computer', ['target' => $port->sector_id])); ?>">Plot</a></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    <?php endif; ?>
</div>

<div class="ib-panel">
    <h3>Known ports <span class="ib-dim ib-small">(<?php echo count($known_ports); ?> in charted space)</span></h3>
    <?php if (!$known_ports) : ?>
        <p class="ib-dim">You haven't found any ports yet.</p>
    <?php else : ?>
        <div class="ib-table-wrap">
        <table class="ib-table">
            <thead><tr><th>Sector</th><th>Port</th><th>Class</th><th>Ore</th><th>Bio</th><th>Mach</th><th>Warps</th></tr></thead>
            <tbody>
            <?php foreach (array_slice($known_ports, 0, 60) as $port) : ?>
                <tr>
                    <td><a href="<?php echo esc_url(IB_UI::url('computer', ['target' => $port->sector_id])); ?>"><?php echo (int) $port->sector_id; ?></a></td>
                    <td><?php echo esc_html($port->port_name); ?></td>
                    <td><?php echo IB_UI::pattern($port); ?></td>
                    <?php foreach (array_keys(IB_Game::COMMODITIES) as $key) : ?>
                        <td><?php echo IB_Ports::is_trading_port($port) ? IB_Game::fmt(IB_Ports::price($port, $key)) : '-'; ?></td>
                    <?php endforeach; ?>
                    <td><?php echo (int) $port->distance; ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div>
        <p class="ib-small ib-dim">Prices shown are what the port currently charges or pays per unit.</p>
    <?php endif; ?>
</div>

<div class="ib-panel">
    <h3>Deployed fighters</h3>
    <?php $deployed = IB_Combat::deployed_by($p->id); ?>
    <?php if (!$deployed) : ?>
        <p class="ib-dim">You have no fighters deployed.</p>
    <?php else : ?>
        <table class="ib-table">
            <thead><tr><th>Sector</th><th>Fighters</th><th>Mode</th></tr></thead>
            <tbody>
            <?php foreach ($deployed as $f) : ?>
                <tr>
                    <td><a href="<?php echo esc_url(IB_UI::url('computer', ['target' => $f->sector_id])); ?>"><?php echo (int) $f->sector_id; ?></a></td>
                    <td><?php echo IB_Game::fmt($f->fighter_count); ?></td>
                    <td><?php echo esc_html($f->fleet_mode); ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
    <p class="ib-small">Charted sectors: <?php echo IB_Game::fmt(count($explored)); ?> (visited <?php echo IB_Game::fmt(count($visited)); ?>) of <?php echo IB_Game::fmt((int) $wpdb->get_var('SELECT COUNT(*) FROM ' . IB_DB::t('sectors'))); ?>.</p>
</div>
