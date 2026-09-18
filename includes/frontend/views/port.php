<?php
/** @var object $p */
if (!defined('ABSPATH')) exit;
$port = IB_Ports::in_sector($p->sector_id);
?>
<?php if (!$port) : ?>
    <div class="ib-panel">
        <h2>No port</h2>
        <p>There is no port in sector <?php echo (int) $p->sector_id; ?>.</p>
        <p><a class="ib-btn" href="<?php echo esc_url(IB_UI::url('computer')); ?>">Find a port with the computer</a></p>
    </div>
<?php return; endif;

$docked = (int) $p->docked_port_id === (int) $port->id;
$class = (int) $port->port_class;
?>
<div class="ib-panel">
    <h2><?php echo esc_html($port->port_name); ?> <?php echo IB_UI::pattern($port); ?></h2>
    <?php if ($class === IB_Ports::ARMORY) : ?>
        <p>The Aurelian Armory. Holds, fighters and shields for sale. Aurelia Prime, the Crown World in this sector, supplies colonists.</p>
    <?php elseif ($class === IB_Ports::SHIPYARD) : ?>
        <p>The Imperial Drydock: shipwrights, hardware and Worldseeds.</p>
    <?php else : ?>
        <p>Commerce report for a class <?php echo $class; ?> (<?php echo esc_html(IB_Ports::class_code($class)); ?>) port.</p>
    <?php endif; ?>

    <?php if (!$docked) : ?>
        <p><?php echo IB_UI::button('dock', 'Dock (' . (int) IB_Settings::get('dock_turn_cost') . ' turn)'); ?></p>
    <?php else : ?>
        <p class="ib-good">Docked. Trading is free while you stay docked. <?php echo IB_UI::button('undock', 'Undock', [], 'ib-btn-alt'); ?></p>
    <?php endif; ?>
</div>

<?php if (IB_Ports::is_trading_port($port)) : ?>
<div class="ib-panel">
    <h3>Trading</h3>
    <div class="ib-table-wrap">
    <table class="ib-table ib-trade">
        <thead><tr><th>Commodity</th><th>Status</th><th>Units</th><th>Price/unit</th><th>In holds</th><?php if ($docked) : ?><th>Trade</th><?php endif; ?></tr></thead>
        <tbody>
        <?php foreach (IB_Game::COMMODITIES as $key => $c) :
            $mode = IB_Ports::mode($port, $key);
            $qty = IB_Ports::qty($port, $key);
            $max = IB_Ports::max($port, $key);
            $price = IB_Ports::price($port, $key);
            $pct = $max ? round(100 * $qty / $max) : 0;
            if ($mode === 'selling') {
                $suggest = max(0, min($qty, IB_Player::holds_free($p), (int) floor($p->credits / max(1, $price))));
            } else {
                $suggest = max(0, min($qty, (int) $p->$key));
            }
            $counter = IB_Ports::counter_offer($p, $port, $key);
            ?>
            <tr>
                <td><?php echo esc_html($c['label']); ?></td>
                <td class="<?php echo $mode === 'selling' ? 'ib-sell' : 'ib-buy'; ?>"><?php echo $mode === 'selling' ? 'Selling' : 'Buying'; ?></td>
                <td><?php echo IB_Game::fmt($qty); ?> <span class="ib-dim">(<?php echo $pct; ?>%)</span></td>
                <td><?php echo IB_Game::fmt($price); ?></td>
                <td><?php echo (int) $p->$key; ?></td>
                <?php if ($docked) : ?>
                <td>
                    <?php if ($suggest > 0) : ?>
                        <?php echo IB_UI::form_open('trade', 'ib-inline ib-trade-form'); ?>
                            <input type="hidden" name="commodity" value="<?php echo esc_attr($key); ?>">
                            <label class="ib-small">Qty <input type="number" name="qty" min="1" max="<?php echo $suggest; ?>" value="<?php echo $suggest; ?>" class="ib-num"></label>
                            <label class="ib-small">Offer <input type="number" name="offer" min="1" value="<?php echo $counter ?: $price; ?>" class="ib-num" title="Price per unit. Haggle for a better deal."></label>
                            <button type="submit" class="ib-btn"><?php echo $mode === 'selling' ? 'Buy' : 'Sell'; ?></button>
                        </form>
                    <?php else : ?>
                        <span class="ib-dim ib-small"><?php echo $mode === 'selling' ? 'No room, credits or stock' : 'Nothing to sell'; ?></span>
                    <?php endif; ?>
                </td>
                <?php endif; ?>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    </div>
    <p class="ib-small ib-dim">Tip: enter a lower offer when buying or a higher one when selling to haggle.
        You have <?php echo IB_Player::holds_free($p); ?> empty holds and <?php echo IB_Game::fmt($p->credits); ?> credits.</p>
</div>
<?php endif; ?>

<?php if (in_array($class, [IB_Ports::ARMORY, IB_Ports::SHIPYARD], true)) : ?>
<div class="ib-panel">
    <h3>Hardware</h3>
    <table class="ib-table">
        <thead><tr><th>Item</th><th>Price</th><th>You can add</th><?php if ($docked) : ?><th>Buy</th><?php endif; ?></tr></thead>
        <tbody>
        <?php foreach (IB_Ports::hardware_catalog($p, $port) as $item => $row) :
            list($label, $unit, $max) = $row;
            $price_label = $item === 'holds' ? IB_Game::fmt(IB_Ports::hold_cost($p->cargo_holds, 1)) . ' (rising)' : IB_Game::fmt($unit); ?>
            <tr>
                <td><?php echo esc_html($label); ?></td>
                <td><?php echo esc_html($price_label); ?></td>
                <td><?php echo IB_Game::fmt($max); ?></td>
                <?php if ($docked) : ?>
                <td>
                    <?php if ($max > 0) : ?>
                        <?php echo IB_UI::form_open('buy_hardware', 'ib-inline'); ?>
                            <input type="hidden" name="item" value="<?php echo esc_attr($item); ?>">
                            <input type="number" name="qty" min="1" max="<?php echo (int) $max; ?>" value="1" class="ib-num" aria-label="Quantity">
                            <button type="submit" class="ib-btn">Buy</button>
                        </form>
                    <?php else : ?><span class="ib-dim ib-small">At capacity</span><?php endif; ?>
                </td>
                <?php endif; ?>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php endif; ?>

<?php if (in_array($class, [IB_Ports::ARMORY, IB_Ports::SHIPYARD], true)) :
    $nebulae = IB_Discovery::nebulae($p); ?>
<div class="ib-panel">
    <h3>Chancery cartographers</h3>
    <p class="ib-small">A nebula chart adds every sector of one nebula to your map: its ports, planets and warp lanes,
        for <?php echo IB_Game::fmt(IB_Settings::get('price_nebula_chart')); ?> credits. Survey drones, sold above, chart everything within
        <?php echo IB_Discovery::DRONE_RADIUS; ?> warps of wherever you launch them.</p>
    <?php if ($docked && $nebulae) : ?>
        <?php echo IB_UI::form_open('buy_chart', 'ib-inline'); ?>
            <select name="nebula" aria-label="Nebula">
                <?php foreach ($nebulae as $name => $n) : ?>
                    <option value="<?php echo esc_attr($name); ?>"<?php disabled($n[1] >= $n[0]); ?>>
                        <?php echo esc_html(sprintf('%s (%d of %d charted)', $name, $n[1], $n[0])); ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <button type="submit" class="ib-btn">Buy chart</button>
        </form>
    <?php elseif (!$docked) : ?>
        <p class="ib-dim ib-small">Dock to buy charts.</p>
    <?php endif; ?>
</div>
<?php endif; ?>

<?php if ($class === IB_Ports::SHIPYARD) :
    $tradein = IB_Ports::trade_in_value($p); ?>
<div class="ib-panel">
    <h3>Shipyard</h3>
    <p class="ib-small">Trade-in value of your <?php echo esc_html(IB_Player::ship($p)['name']); ?>: <?php echo IB_Game::fmt($tradein); ?> credits.
        Cargo that doesn't fit in the new ship is lost.</p>
    <div class="ib-table-wrap">
    <table class="ib-table">
        <thead><tr><th>Ship</th><th>Holds</th><th>Fighters</th><th>Shields</th><th>Off/Def</th><th>Price</th><th>You pay</th><?php if ($docked) : ?><th></th><?php endif; ?></tr></thead>
        <tbody>
        <?php foreach (IB_Ships::all() as $type => $s) : ?>
            <tr<?php echo $type === $p->ship_type ? ' class="ib-current"' : ''; ?>>
                <td><strong><?php echo esc_html($s['name']); ?></strong><br><span class="ib-small ib-dim"><?php echo esc_html($s['desc']); ?></span></td>
                <td><?php echo (int) $s['base_holds']; ?>/<?php echo (int) $s['max_holds']; ?></td>
                <td><?php echo IB_Game::fmt($s['max_fighters']); ?></td>
                <td><?php echo IB_Game::fmt($s['max_shields']); ?></td>
                <td><?php echo esc_html($s['off'] . ' / ' . $s['def']); ?></td>
                <td><?php echo IB_Game::fmt($s['price']); ?></td>
                <td><?php echo $type === $p->ship_type ? '-' : IB_Game::fmt(max(0, $s['price'] - $tradein)); ?></td>
                <?php if ($docked) : ?>
                <td><?php echo $type === $p->ship_type ? '<span class="ib-dim">Current</span>'
                        : IB_UI::button('buy_ship', 'Buy', ['ship_type' => $type], '', 'Trade in your ship for a ' . $s['name'] . '?'); ?></td>
                <?php endif; ?>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    </div>
</div>
<?php endif; ?>
