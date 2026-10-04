<?php
/**
 * The nearest charted ports, with their class, distance and route risk, each one click away.
 * Shown under the planet list so a pilot who has just filled the holds from a colony can see
 * where to take it without going to the Port or Computer page.
 *
 * @var object $p
 * @var int    $ib_nearby_limit how many to list; 8 by default
 */
if (!defined('ABSPATH')) exit;

$ib_limit = isset($ib_nearby_limit) ? max(1, (int) $ib_nearby_limit) : 8;
$ib_move = (int) IB_Settings::get('move_turn_cost');
$ib_dock = (int) IB_Settings::get('dock_turn_cost');

$ib_nearby = [];
foreach (IB_Ports::charted($p) as $ib_entry) {
    list($ib_port, $ib_hops) = $ib_entry;
    if ((int) $ib_port->sector_id === (int) $p->sector_id) continue;
    $ib_nearby[] = [$ib_port, $ib_hops];
    if (count($ib_nearby) >= $ib_limit) break;
}
?>
<div class="ib-panel">
    <h3>Nearest ports</h3>
    <?php if (!$ib_nearby) : ?>
        <p class="ib-dim">You have not charted a port yet. Explore, or launch a survey drone.</p>
    <?php else : ?>
        <div class="ib-table-wrap">
        <table class="ib-table">
            <thead><tr><th>Sector</th><th>Port</th><th>Class</th><th>Warps</th><th>Turns</th><th>Risk</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($ib_nearby as $ib_row) :
                list($ib_port, $ib_hops) = $ib_row;
                $ib_turns = $ib_hops * $ib_move + $ib_dock;
                $ib_reachable = (int) $p->turns_remaining >= $ib_turns;
                $ib_risk = IB_Pathfinder::route_risk($p, IB_Pathfinder::shortestPath(null, (int) $p->sector_id, (int) $ib_port->sector_id)); ?>
                <tr<?php echo $ib_reachable ? '' : ' class="ib-dim"'; ?>>
                    <td><?php echo IB_UI::fly_to($p, $ib_port->sector_id, $ib_turns, null, true); ?></td>
                    <td><?php echo esc_html($ib_port->port_name); ?></td>
                    <td><?php echo IB_UI::pattern($ib_port); ?></td>
                    <td><?php echo (int) $ib_hops; ?></td>
                    <td><?php echo (int) $ib_turns; ?></td>
                    <td><?php echo IB_UI::risk_badge($ib_risk); ?></td>
                    <td><?php if ($ib_reachable) {
                            echo IB_UI::button('fly', 'Fly & dock', ['target' => (int) $ib_port->sector_id, 'dock' => 1], 'ib-btn-small');
                        } else {
                            echo '<span class="ib-small ib-bad">not enough turns</span>';
                        } ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div>
        <p class="ib-small ib-dim">Nearest first. The three letters are what the port does with the staples &mdash;
            ferrium ore, biostock, machinery, in that order, <span class="ib-buy">B</span> where it buys and
            <span class="ib-sell">S</span> where it sells &mdash; and a <span class="ib-spec-chip ib-sell">&#9670;I</span>,
            <span class="ib-spec-chip ib-sell">&#9670;M</span> or <span class="ib-spec-chip ib-sell">&#9670;L</span> marks
            one dealing in Rare Isotopes, Medicine or Luxuries. Turns counts the warps plus docking.
            <a href="<?php echo esc_url(IB_UI::url('port')); ?>">The Port page</a> ranks them by what your cargo would fetch.</p>
    <?php endif; ?>
</div>
