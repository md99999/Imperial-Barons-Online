<?php
/** @var object $p */
if (!defined('ABSPATH')) exit;
$ship = IB_Player::ship($p);
$cargo = ['ore' => 'Ferrium Ore', 'organics' => 'Biostock', 'equipment' => 'Machinery', 'colonists' => 'Colonists'];
?>
<div class="ib-grid">
    <div class="ib-panel">
        <h2><?php echo esc_html($p->ship_name); ?></h2>
        <p class="ib-dim"><?php echo esc_html($ship['name']); ?> &middot; <?php echo esc_html($ship['desc']); ?></p>
        <table class="ib-table ib-kv">
            <tr><th>Cargo holds</th><td><?php echo (int) $p->cargo_holds; ?> of <?php echo (int) $ship['max_holds']; ?> max (<?php echo IB_Player::holds_free($p); ?> empty)</td></tr>
            <tr><th>Fighters</th><td><?php echo IB_Game::fmt($p->fighters); ?> of <?php echo IB_Game::fmt($ship['max_fighters']); ?></td></tr>
            <tr><th>Shields</th><td><?php echo IB_Game::fmt($p->shield_points); ?> of <?php echo IB_Game::fmt($ship['max_shields']); ?></td></tr>
            <tr><th>Combat odds</th><td>Offense <?php echo esc_html($ship['off']); ?> &middot; Defense <?php echo esc_html($ship['def']); ?></td></tr>
            <tr><th>Worldseeds</th><td><?php echo (int) $p->worldseeds; ?></td></tr>
            <tr><th>Survey drones</th><td><?php echo (int) $p->survey_drones; ?> of <?php echo IB_Discovery::MAX_DRONES; ?></td></tr>
            <tr><th>Credits</th><td><?php echo IB_Game::fmt($p->credits); ?></td></tr>
        </table>
        <?php echo IB_UI::form_open('rename_ship', 'ib-inline'); ?>
            <input type="text" name="name" maxlength="41" value="<?php echo esc_attr($p->ship_name); ?>" aria-label="Ship name">
            <button type="submit" class="ib-btn ib-btn-alt">Rename ship</button>
        </form>
    </div>

    <div class="ib-panel">
        <h2>Pilot record</h2>
        <table class="ib-table ib-kv">
            <tr><th>Rank</th><td><?php echo esc_html(IB_Game::rank_title($p->experience)); ?></td></tr>
            <tr><th>Experience</th><td><?php echo IB_Game::fmt($p->experience); ?></td></tr>
            <tr><th>Alignment</th><td><?php echo esc_html(IB_Game::alignment_label($p->alignment)); ?> (<?php echo (int) $p->alignment; ?>)</td></tr>
            <tr><th>Ships destroyed</th><td><?php echo (int) $p->kills; ?></td></tr>
            <tr><th>Times destroyed</th><td><?php echo (int) $p->deaths; ?></td></tr>
            <tr><th>Net worth</th><td><?php echo IB_Game::fmt(IB_Player::net_worth($p)); ?></td></tr>
            <tr><th>Turns left today</th><td><?php echo (int) $p->turns_remaining; ?></td></tr>
            <tr><th>Flying since</th><td><?php echo esc_html(mysql2date(get_option('date_format'), $p->created_at)); ?></td></tr>
        </table>
    </div>
</div>

<div class="ib-panel">
    <h3>Cargo manifest</h3>
    <table class="ib-table">
        <thead><tr><th>Cargo</th><th>Units</th><th>Jettison</th></tr></thead>
        <tbody>
        <?php foreach ($cargo as $key => $label) : ?>
            <tr>
                <td><?php echo esc_html($label); ?></td>
                <td><?php echo IB_Game::fmt($p->$key); ?></td>
                <td>
                    <?php if ((int) $p->$key > 0) : ?>
                        <?php echo IB_UI::form_open('jettison', 'ib-inline'); ?>
                            <input type="hidden" name="commodity" value="<?php echo esc_attr($key); ?>">
                            <input type="number" name="qty" min="1" max="<?php echo (int) $p->$key; ?>" value="<?php echo (int) $p->$key; ?>" class="ib-num" aria-label="Units to jettison">
                            <button type="submit" class="ib-btn ib-btn-small ib-btn-danger" data-confirm="Jettison this cargo into space?">Jettison</button>
                        </form>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
        <tr><th>Total</th><th><?php echo IB_Player::holds_used($p); ?> / <?php echo (int) $p->cargo_holds; ?></th><td></td></tr>
        </tbody>
    </table>
    <p class="ib-small ib-dim">Buy more holds at the Aurelian Armory or the Imperial Drydock. New ships are sold at the Imperial Drydock.</p>
</div>
