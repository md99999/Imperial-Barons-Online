<?php
/**
 * "Where do I buy...?" — every piece of hardware, the ships and the colonists, with the port or
 * planet that sells it, how far away it is, and the name itself as the way to get there.
 *
 * The Crown sells hardware in only two places and recruits settlers in two more, which is obvious
 * once you know and impossible to work out from the Sector page. Nearest supplier first.
 *
 * @var object $p
 */
if (!defined('ABSPATH')) exit;

$ib_s = IB_Settings::all();
$ib_move = (int) $ib_s['move_turn_cost'];
$ib_dock = (int) $ib_s['dock_turn_cost'];
$ib_dist = IB_Pathfinder::distances(null, (int) $p->sector_id);

// Where things are sold: the two Crown ports, and Aurelia Prime for settlers.
$ib_venues = [];
foreach (IB_Ports::special_venues() as $ib_key => $ib_port) {
    $ib_venues[$ib_key] = [
        'kind'   => 'port',
        'name'   => $ib_port->port_name,
        'sector' => (int) $ib_port->sector_id,
        'row'    => $ib_port,
        'verb'   => 'dock',
    ];
}
$ib_crown = IB_Planets::crown_world();
if ($ib_crown) {
    $ib_venues['crown'] = [
        'kind'   => 'planet',
        'name'   => $ib_crown->planet_name,
        'sector' => (int) $ib_crown->sector_id,
        'row'    => $ib_crown,
        'verb'   => 'land',
    ];
}

$ib_ships = IB_Ships::all();
$ib_prices = array_column($ib_ships, 'price');
$ib_catalogue = [
    ['Colonists', sprintf('%s cr each, %d to a hold', IB_Game::fmt($ib_s['price_colonist']), IB_Player::colonists_per_hold()), ['armory', 'crown']],
    ['Cargo holds', sprintf('from %s cr for the next one', IB_Game::fmt(IB_Ports::hold_cost($p->cargo_holds, 1))), ['armory', 'drydock']],
    ['Fighters', sprintf('%s cr each', IB_Game::fmt($ib_s['price_fighter'])), ['armory', 'drydock']],
    ['Shield points', sprintf('%s cr each', IB_Game::fmt($ib_s['price_shield'])), ['armory', 'drydock']],
    ['Survey drones', sprintf('%s cr each', IB_Game::fmt($ib_s['price_survey_drone'])), ['armory', 'drydock']],
    ['Nebula charts', sprintf('%s cr each', IB_Game::fmt($ib_s['price_nebula_chart'])), ['armory', 'drydock']],
    ['Worldseeds', sprintf('%s cr each', IB_Game::fmt($ib_s['price_worldseed'])), ['drydock']],
    ['A bigger ship', sprintf('%s to %s cr, less trade-in', IB_Game::fmt(min($ib_prices)), IB_Game::fmt(max($ib_prices))), ['drydock']],
];
?>
<div class="ib-panel">
    <h3>Where do I buy&hellip;?</h3>
    <?php if (!$ib_venues) : ?>
        <p class="ib-dim">The Chancery's suppliers have not been founded yet.</p>
    <?php else : ?>
        <div class="ib-table-wrap">
        <table class="ib-table">
            <thead><tr><th>Item</th><th>Price</th><th>Sold at</th><th>Sector</th><th>Warps</th><th>Turns</th></tr></thead>
            <tbody>
            <?php foreach ($ib_catalogue as $ib_entry) :
                list($ib_item, $ib_price, $ib_where) = $ib_entry;

                // Nearest supplier first, and anything off the charts last.
                $ib_rows = [];
                foreach ($ib_where as $ib_key) {
                    if (!isset($ib_venues[$ib_key])) continue;
                    $ib_v = $ib_venues[$ib_key];
                    $ib_v['hops'] = isset($ib_dist[$ib_v['sector']]) ? (int) $ib_dist[$ib_v['sector']] : null;
                    $ib_rows[] = $ib_v;
                }
                usort($ib_rows, function ($a, $b) {
                    if ($a['hops'] === null || $b['hops'] === null) return $a['hops'] === null ? 1 : -1;
                    return $a['hops'] <=> $b['hops'];
                });
                $ib_first = true;
                foreach ($ib_rows as $ib_v) :
                    $ib_here = $ib_v['sector'] === (int) $p->sector_id;
                    $ib_turns = $ib_v['hops'] === null ? null
                        : $ib_v['hops'] * $ib_move + ($ib_here || $ib_v['kind'] === 'planet' ? 0 : $ib_dock);
                    // The turn cost is shown but not enforced on the link: the Armory can be a
                    // fortnight away, and autopilot flies as far as today's turns reach and stops.
                    $ib_link = $ib_v['kind'] === 'planet'
                        ? IB_UI::fly_to_planet($p, $ib_v['row'], null, $ib_v['name'])
                        : IB_UI::fly_to($p, $ib_v['sector'], null, $ib_v['name'], true);
                    ?>
                    <tr<?php echo $ib_here ? ' class="ib-current"' : ''; ?>>
                        <td><?php echo $ib_first ? esc_html($ib_item) : '<span class="ib-dim">&#8220;</span>'; ?></td>
                        <td class="ib-small"><?php echo $ib_first ? esc_html($ib_price) : ''; ?></td>
                        <td><?php echo $ib_link; ?>
                            <?php if ($ib_v['kind'] === 'planet') : ?><span class="ib-dim ib-small">(land)</span><?php endif; ?></td>
                        <td><?php echo (int) $ib_v['sector']; ?></td>
                        <td><?php echo $ib_here ? '<span class="ib-good">here</span>'
                                : ($ib_v['hops'] === null ? '<span class="ib-dim">no route</span>' : (int) $ib_v['hops']); ?></td>
                        <td><?php echo $ib_here ? '0' : ($ib_turns === null ? '-' : (int) $ib_turns); ?></td>
                    </tr>
                <?php $ib_first = false; endforeach; ?>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div>
        <p class="ib-small ib-dim">The name of a supplier flies you there and docks &mdash; or lands, for Aurelia Prime,
            where landing is free and costs no turn beyond the warps. <strong>Turns</strong> is what the whole journey
            costs: if that is more than you have left today, autopilot flies as far as it can and stops, and you
            carry on tomorrow. Hardware and charts are sold only at the
            <strong>Aurelian Armory</strong> and the <strong>Imperial Drydock</strong>; ships and Worldseeds only at the
            Drydock; settlers at the Armory or on the Crown World itself. For ordinary cargo, the
            <a href="<?php echo esc_url(IB_UI::url('port')); ?>">Port</a> page ranks the nearest buyers and sellers, and
            the <a href="<?php echo esc_url(IB_UI::url('computer')); ?>">Computer</a> searches every charted port.</p>
    <?php endif; ?>
</div>
