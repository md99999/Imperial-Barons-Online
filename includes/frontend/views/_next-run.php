<?php
/**
 * "Where next": the ports that will buy what is in the holds, or, when the holds are empty,
 * the nearest ones selling. Each row flies there and docks in a single click, which is the
 * whole trade loop without leaving the page.
 *
 * @var object $p
 */
if (!defined('ABSPATH')) exit;

$ib_runs = IB_Ports::runs_for($p, 8);
$ib_carrying = IB_Player::holds_used($p) > 0;
?>
<div class="ib-panel">
    <h3><?php echo $ib_carrying ? 'Where to take this cargo' : 'What to pick up next'; ?></h3>

    <?php if (!$ib_runs) : ?>
        <p class="ib-dim"><?php echo $ib_carrying
            ? 'No charted port is buying what you carry. Explore further, or use the computer below to search.'
            : 'No charted port has anything to sell you. Explore further, or launch a survey drone.'; ?></p>
    <?php else : ?>
        <div class="ib-table-wrap">
        <table class="ib-table">
            <thead><tr>
                <th><?php echo $ib_carrying ? 'Sell' : 'Buy'; ?></th>
                <th>Port</th><th>Sector</th><th>Price</th><th>Units</th>
                <th><?php echo $ib_carrying ? 'Pays' : 'Costs'; ?></th><th>Turns</th><th></th>
            </tr></thead>
            <tbody>
            <?php foreach ($ib_runs as $run) : ?>
                <tr<?php echo $run['reachable'] ? '' : ' class="ib-dim"'; ?>>
                    <td><?php echo esc_html(IB_Game::label($run['commodity'])); ?>
                        <?php if (IB_Game::is_specialist($run['commodity'])) : ?><span class="ib-special" title="Specialist good">&#9670;</span><?php endif; ?></td>
                    <td><?php echo esc_html($run['port']->port_name); ?> <?php echo IB_UI::pattern($run['port']); ?></td>
                    <td><?php echo (int) $run['port']->sector_id; ?></td>
                    <td><?php echo IB_Game::fmt($run['price']); ?></td>
                    <td><?php echo IB_Game::fmt($run['units']); ?></td>
                    <td><?php echo IB_Game::fmt($run['value']); ?></td>
                    <td><?php echo (int) $run['turns']; ?> <span class="ib-dim ib-small">(<?php echo (int) $run['hops']; ?> warp<?php echo $run['hops'] === 1 ? '' : 's'; ?> + dock)</span></td>
                    <td><?php if ($run['reachable']) {
                            echo IB_UI::button('fly', 'Fly & dock', ['target' => (int) $run['port']->sector_id, 'dock' => 1], 'ib-btn-small');
                        } else {
                            echo '<span class="ib-small ib-bad">not enough turns</span>';
                        } ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div>
        <p class="ib-small ib-dim"><?php echo $ib_carrying
            ? 'Best payout first, counting only what each port can actually take and what you can reach today.'
            : 'Cheapest first, sized to your empty holds and credits.'; ?>
            <strong>Fly &amp; dock</strong> plots the course, flies it and docks when you arrive; it stops early
            on hostile contact or when the turns run out.</p>
    <?php endif; ?>
</div>
