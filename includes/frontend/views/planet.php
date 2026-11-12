<?php
/** @var object $p */
if (!defined('ABSPATH')) exit;
$planet = (int) $p->landed_planet_id ? IB_Planets::get($p->landed_planet_id) : null;
if ($planet && (int) $planet->sector_id !== (int) $p->sector_id) $planet = null;
?>

<?php if (!$planet) :
    $planets = IB_Planets::in_sector($p->sector_id);
    $mine = IB_Planets::owned_by($p->id); ?>
    <div class="ib-panel">
        <h2>Planets in sector <?php echo (int) $p->sector_id; ?></h2>
        <?php if (!$planets) : ?><p class="ib-dim">There are no planets here.</p><?php endif; ?>
        <?php foreach ($planets as $pl) : ?>
            <div class="ib-row">
                <div><strong><?php echo esc_html($pl->planet_name); ?></strong>
                    <span class="ib-dim">(<?php echo esc_html(IB_Planets::class_name($pl->planet_class)); ?>) owned by <?php echo esc_html(IB_Planets::owner_label($pl)); ?></span></div>
                <div><?php echo IB_UI::button('land', 'Land', ['planet_id' => $pl->id]); ?></div>
            </div>
        <?php endforeach; ?>
    </div>

    <?php if ((int) $p->worldseeds > 0) : ?>
    <div class="ib-panel">
        <h3>Worldseed</h3>
        <?php if (IB_Game::is_core($p->sector_id)) : ?>
            <p class="ib-dim">Worldseeds may not be used in the Imperial Core.</p>
        <?php else : ?>
            <p>You carry <?php echo (int) $p->worldseeds; ?> Worldseed(s). Launch one to create a new planet in this sector.</p>
            <?php echo IB_UI::form_open('launch_worldseed', 'ib-inline'); ?>
                <label>Planet name <input type="text" name="name" maxlength="100" required></label>
                <button type="submit" class="ib-btn" data-confirm="Launch a Worldseed here?">Launch</button>
            </form>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <div class="ib-panel">
        <h3>Your planets</h3>
        <?php if (!$mine) : ?>
            <p class="ib-dim">You don't own any planets yet. Claim an unowned planet or buy a Worldseed at the Imperial Drydock.</p>
        <?php else :
            // One search of the warp map answers "how far is each of my worlds?" for the whole table.
            $dist = IB_Pathfinder::distances(null, (int) $p->sector_id);
            $move_cost = (int) IB_Settings::get('move_turn_cost');
            $ib_vend = (int) IB_Settings::get('vending_per_1000') > 0; ?>
            <div class="ib-table-wrap">
            <table class="ib-table">
                <thead><tr><th>Planet</th><th>Sector</th><th>Warps</th><th>Turns</th><th>Risk</th><th>Colonists</th><th>Fighters</th><?php if ($ib_vend) : ?><th>Vending</th><?php endif; ?><th>Bastion</th><th></th></tr></thead>
                <tbody>
                <?php foreach ($mine as $pl) :
                    $here = (int) $pl->sector_id === (int) $p->sector_id;
                    $hops = isset($dist[(int) $pl->sector_id]) ? (int) $dist[(int) $pl->sector_id] : null;
                    $turns = $hops === null ? null : $hops * $move_cost;
                    $reachable = $turns !== null && (int) $p->turns_remaining >= $turns;
                    $risk = ($here || $hops === null)
                        ? null
                        : IB_Pathfinder::route_risk($p, IB_Pathfinder::shortestPath(null, (int) $p->sector_id, (int) $pl->sector_id)); ?>
                    <tr<?php echo ($here || $reachable) ? '' : ' class="ib-dim"'; ?>>
                        <td><?php echo esc_html($pl->planet_name); ?>
                            <span class="ib-dim ib-small">(<?php echo esc_html(IB_Planets::class_name($pl->planet_class)); ?>)</span></td>
                        <td><?php echo IB_UI::fly_to_planet($p, $pl, $turns); ?></td>
                        <td><?php echo $here ? '<span class="ib-good">here</span>' : ($hops === null ? '<span class="ib-dim">no route</span>' : $hops); ?></td>
                        <td><?php echo $here ? '0' : ($turns === null ? '-' : (int) $turns); ?></td>
                        <td><?php echo $risk ? IB_UI::risk_badge($risk) : '<span class="ib-dim">-</span>'; ?></td>
                        <td><?php echo IB_Game::fmt($pl->colonists); ?></td>
                        <td><?php echo IB_Game::fmt($pl->fighters); ?></td>
                        <?php if ($ib_vend) :
                            $ib_vn = IB_Planets::vending_needed($pl);
                            $ib_vc = IB_Planets::vending_cost($pl);
                            $ib_vd = IB_Planets::vending_days_left($pl);
                            $ib_vw = (int) IB_Settings::get('vending_warn_days');
                            $ib_bad = (int) $pl->vending_short_days > 0 || (int) $pl->vending_machines < $ib_vn; ?>
                            <td><span class="<?php echo $ib_bad ? 'ib-bad' : 'ib-good'; ?>"><?php echo IB_Game::fmt($pl->vending_machines); ?> / <?php echo IB_Game::fmt($ib_vn); ?></span>
                                <?php if ($ib_vd !== null) : ?>
                                    <br><span class="ib-small <?php echo ($ib_vw && $ib_vd < $ib_vw) ? 'ib-bad' : 'ib-dim'; ?>"><?php echo $ib_vd === 1 ? '1 day' : IB_Game::fmt($ib_vd) . ' days'; ?> of fund</span>
                                <?php endif; ?>
                                <?php if ((int) $p->credits > 0) : ?>
                                    <?php echo IB_UI::form_open('vending_fund', 'ib-inline'); ?>
                                        <input type="hidden" name="planet_id" value="<?php echo (int) $pl->id; ?>">
                                        <input type="number" name="amount" min="1" max="<?php echo (int) $p->credits; ?>" value="<?php echo (int) max(1, min((int) $p->credits, $ib_vc * 7)); ?>" class="ib-num" aria-label="Add to the supply fund of <?php echo esc_attr($pl->planet_name); ?>">
                                        <button type="submit" class="ib-btn ib-btn-small">Fund</button>
                                    </form>
                                <?php endif; ?></td>
                        <?php endif; ?>
                        <td><?php echo (int) $pl->bastion_level; ?></td>
                        <td><?php if ($here) {
                                echo IB_UI::button('land', 'Land', ['planet_id' => $pl->id], 'ib-btn-small');
                            } elseif ($reachable) {
                                echo IB_UI::button('fly', 'Fly & land', ['target' => (int) $pl->sector_id, 'planet' => (int) $pl->id], 'ib-btn-small');
                            } elseif ($hops === null) {
                                echo '<span class="ib-small ib-dim">no charted route</span>';
                            } else {
                                echo '<span class="ib-small ib-bad">not enough turns</span>';
                            }
                            // Selling to the factors needs no visit: they pay into the Vault wherever you are.
                            if ((int) $pl->bastion_level >= 1 && (int) $pl->owner_player_id === (int) $p->id && IB_Planets::factors_open()) {
                                $ib_stock = 0;
                                foreach (IB_Game::commodities() as $ib_key) if (IB_Planets::sells($pl, $ib_key)) $ib_stock += (int) $pl->$ib_key;
                                if ($ib_stock > 0) echo ' ' . IB_UI::button('sell_now', 'Sell stock', ['planet_id' => (int) $pl->id], 'ib-btn-small ib-btn-alt');
                            } ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            </div>
            <p class="ib-small ib-dim">Turns counts the warps there; landing itself is free, as are cargo transfers
                once you are down. <strong>Fly &amp; land</strong> flies the course and sets you down in one click,
                and so does the sector number. Autopilot stops early on hostile contact or when the turns run out.<br>
                <strong>Risk</strong> is your navigator's reading of the course ahead &mdash; how much of it runs outside
                the Crown's Peace, how much of it you have never charted, and what is deployed along the way. It will not
                tell you what is out there: sweep a sector, or send a survey drone, if you want to know before you fly.
                <?php if ($ib_vend) : ?><br><strong>Vending</strong> is the machines on each world against what its garrison
                    needs, and how many days its supply fund will restock them. <strong>Fund</strong> tops the fund up from
                    anywhere (it suggests a week).<?php endif; ?>
                <br><strong>Sell stock</strong> calls the Crown's factors in now rather than tonight: they buy what that world
                    is set to sell and pay into its Vault, from wherever you are. Choose what each world sells on its Planet page.</p>
        <?php endif; ?>
    </div>

    <?php
    /* A colony fills the holds; this is where to take them, without a trip to the Port page. */
    include IB_PATH . 'includes/frontend/views/_nearby-ports.php'; ?>

<?php elseif ($planet->planet_class === IB_Planets::CROWN_WORLD) : ?>
    <div class="ib-panel">
        <h2>Aurelia Prime, the Crown World</h2>
        <p>The cradle of humanity. Colonists crowd the spaceport, eager for a new life on the frontier.</p>
        <?php $berths = IB_Player::colonist_room($p); ?>
        <p>Colonists cost <?php echo (int) IB_Settings::get('price_colonist'); ?> credits each and travel in berths,
            <?php echo (int) IB_Player::colonists_per_hold(); ?> to a cargo hold.
            You have <?php echo IB_Player::holds_free($p); ?> empty holds: berths for
            <?php echo IB_Game::fmt($berths); ?> settlers.</p>
        <?php if ($berths > 0) : ?>
            <?php echo IB_UI::form_open('buy_colonists', 'ib-inline'); ?>
                <input type="number" name="qty" min="1" max="<?php echo (int) $berths; ?>" value="<?php echo (int) $berths; ?>" class="ib-num" aria-label="Colonists">
                <button type="submit" class="ib-btn">Take colonists aboard</button>
            </form>
        <?php endif; ?>
        <p><?php echo IB_UI::button('leave_planet', 'Lift off', [], 'ib-btn-alt'); ?></p>
    </div>

<?php else :
    $friendly = IB_Planets::is_friendly($p, $planet);
    $def = IB_Planets::CLASSES[$planet->planet_class];
    $level = (int) $planet->bastion_level;
    $next = IB_Planets::BASTION[$level + 1] ?? null; ?>
    <div class="ib-panel">
        <h2><?php echo esc_html($planet->planet_name); ?> <span class="ib-dim">(<?php echo esc_html($def['name']); ?>)</span></h2>
        <table class="ib-table ib-kv">
            <tr><th>Owner</th><td><?php echo esc_html(IB_Planets::owner_label($planet)); ?></td></tr>
            <tr><th>Colonists</th><td><?php echo IB_Game::fmt($planet->colonists); ?> / <?php echo IB_Game::fmt($def['max_colonists']); ?></td></tr>
            <tr><th>Bastion</th><td><?php echo $level ? 'Level ' . $level . ' - ' . esc_html(IB_Planets::BASTION[$level]['name']) : 'None'; ?></td></tr>
            <?php if ($friendly && $level) : ?><tr><th>Vault</th><td><?php echo IB_Game::fmt($planet->bastion_vault); ?> cr</td></tr><?php endif; ?>
            <?php if ($friendly) :
                $ib_share = max(0, min(100, (int) IB_Settings::get('planet_autosell_percent')));
                $ib_rate = max(0, (int) IB_Settings::get('planet_autosell_price_percent')); ?>
                <tr><th>Daily sale</th><td>
                    <?php if (!$ib_share || !$ib_rate) : ?>
                        <span class="ib-dim">The factors are not buying; stock piles up until you collect it.</span>
                    <?php elseif (!$level) : ?>
                        <span class="ib-dim">Build a bastion and the Crown's factors will buy
                            <?php echo $ib_share; ?>% of this world's staples each day, paying into its Vault.</span>
                    <?php else :
                        $ib_due = 0;
                        $ib_owner = (int) $planet->owner_player_id === (int) $p->id;
                        foreach (IB_Game::commodities() as $ib_key) {
                            if (!IB_Planets::sells($planet, $ib_key)) continue;
                            $ib_due += (int) floor((int) $planet->$ib_key * $ib_share / 100) * IB_Planets::autosell_price($ib_key);
                        } ?>
                        The factors buy <?php echo $ib_share; ?>% of the staples you choose each night at
                        <?php echo $ib_rate; ?>% of base price, paying into the Vault. Anything you keep piles up
                        here for you to haul to a port or put into the next bastion.
                        <span class="ib-dim ib-small">A port pays full price or better, so collecting it yourself still earns more.</span>
                        <div class="ib-small">
                        <?php foreach (IB_Game::commodities() as $ib_key) :
                            $ib_on = IB_Planets::sells($planet, $ib_key); ?>
                            <div><?php echo esc_html(IB_Game::label($ib_key)); ?>:
                                <?php echo $ib_on ? '<span class="ib-good">sold nightly</span>' : '<span class="ib-dim">kept</span>'; ?>
                                (<?php echo IB_Game::fmt($planet->$ib_key); ?> here, <?php echo IB_Game::fmt(IB_Planets::autosell_price($ib_key)); ?> cr each to the factors)
                                <?php if ($ib_owner) echo IB_UI::button('set_factors', $ib_on ? 'Keep it' : 'Sell it nightly', ['what' => $ib_key, 'on' => $ib_on ? 0 : 1], 'ib-btn-small ib-btn-alt'); ?>
                            </div>
                        <?php endforeach; ?>
                        </div>
                        <?php if ($ib_due > 0) : ?>
                            <span class="ib-good">Today's stock you sell would fetch <?php echo IB_Game::fmt($ib_due); ?> credits.</span>
                            <?php if ($ib_owner) echo IB_UI::button('sell_now', 'Sell it now', [], 'ib-btn-small'); ?>
                        <?php endif; ?>
                    <?php endif; ?>
                </td></tr>
            <?php endif; ?>
            <?php $rates = IB_Planets::output_rates($planet); ?>
            <tr><th>Daily output per 1,000 colonists</th><td><?php printf('%s ferrium ore, %s biostock, %s machinery, %s fighters',
                    IB_Game::fmt(round($rates['ore'], 1)), IB_Game::fmt(round($rates['organics'], 1)),
                    IB_Game::fmt(round($rates['equipment'], 1)), IB_Game::fmt(round($rates['fighters'], 1))); ?>
                <?php if ($level) : ?>
                    <span class="ib-good ib-small">includes +<?php echo 10 * $level; ?>% from the bastion</span>
                <?php endif; ?>
                <span class="ib-dim ib-small">(base rate for a <?php echo esc_html($def['name']); ?>:
                    <?php echo (int) $def['ore']; ?>/<?php echo (int) $def['organics']; ?>/<?php echo (int) $def['equipment']; ?>
                    + <?php echo (int) $def['fighters']; ?> fighters)</span></td></tr>
            <?php if ((int) $planet->colonists > 0) :
                $share = $planet->colonists / 1000; ?>
                <tr><th>At this colony's size</th><td><?php printf('%s ferrium ore, %s biostock, %s machinery, %s fighters a day',
                    IB_Game::fmt(round($rates['ore'] * $share, 1)), IB_Game::fmt(round($rates['organics'] * $share, 1)),
                    IB_Game::fmt(round($rates['equipment'] * $share, 1)), IB_Game::fmt(round($rates['fighters'] * $share, 1))); ?>
                    <span class="ib-dim">(the colony also grows 5% a day on its own)</span></td></tr>
            <?php endif; ?>
        </table>

        <?php if (!$friendly) : ?>
            <?php if ((int) $planet->fighters === 0) : ?>
                <p><?php echo IB_UI::button('claim_planet', 'Claim this planet'); ?></p>
            <?php endif; ?>
        <?php endif; ?>
        <p><?php echo IB_UI::button('leave_planet', 'Lift off', [], 'ib-btn-alt'); ?></p>
    </div>

    <?php if ($friendly) : ?>
    <div class="ib-panel">
        <h3>Cargo transfer</h3>
        <div class="ib-table-wrap">
        <table class="ib-table">
            <thead><tr><th>Item</th><th>Planet</th><th>Ship</th><th>Take</th><th>Leave</th></tr></thead>
            <tbody>
            <?php foreach (IB_Planets::TRANSFERABLE as $what) :
                // Offer what will actually fit, so the first click is not an error message.
                if ($what === 'vending_machines' && !(int) IB_Settings::get('vending_per_1000') && !(int) $planet->vending_machines && !(int) $p->vending_machines) continue;
                $room = max(0, IB_Planets::ship_room($p, $what));
                $take = max(1, min((int) $planet->$what, $room)); ?>
                <tr>
                    <td><?php echo esc_html(IB_Game::label($what)); ?></td>
                    <td><?php echo IB_Game::fmt($planet->$what); ?></td>
                    <td><?php echo IB_Game::fmt($p->$what); ?></td>
                    <td>
                        <?php echo IB_UI::form_open('planet_transfer', 'ib-inline'); ?>
                            <input type="hidden" name="what" value="<?php echo esc_attr($what); ?>">
                            <input type="hidden" name="dir" value="take">
                            <input type="number" name="qty" min="1" value="<?php echo (int) $take; ?>" class="ib-num" aria-label="Take <?php echo esc_attr($what); ?>">
                            <button type="submit" class="ib-btn ib-btn-small">Take</button>
                        </form>
                    </td>
                    <td>
                        <?php echo IB_UI::form_open('planet_transfer', 'ib-inline'); ?>
                            <input type="hidden" name="what" value="<?php echo esc_attr($what); ?>">
                            <input type="hidden" name="dir" value="leave">
                            <input type="number" name="qty" min="1" value="<?php echo (int) $p->$what; ?>" class="ib-num" aria-label="Leave <?php echo esc_attr($what); ?>">
                            <button type="submit" class="ib-btn ib-btn-small">Leave</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div>
    </div>

    <?php if ((int) IB_Settings::get('vending_per_1000') > 0) :
        $ib_per = (int) IB_Settings::get('vending_per_1000');
        $ib_need = IB_Planets::vending_needed($planet);
        $ib_have = (int) $planet->vending_machines;
        $ib_cost = IB_Planets::vending_cost($planet);
        $ib_fund = (int) $planet->vending_fund;
        $ib_grace = (int) IB_Settings::get('vending_grace_days');
        $ib_short = (int) $planet->vending_short_days;
        $ib_vault_pays = (int) IB_Settings::get('vending_vault_pays') === 1;
        $ib_days = IB_Planets::vending_days_left($planet);
        $ib_warn = (int) IB_Settings::get('vending_warn_days');
        $ib_feeds = (int) floor($ib_have * 1000 / $ib_per); ?>
    <div class="ib-panel">
        <h3>Vending machines</h3>
        <p class="ib-small">A garrison marches on its stomach: every 1,000 fighters stationed here need <?php echo $ib_per; ?>
            stocked vending machine<?php echo $ib_per === 1 ? '' : 's'; ?> of rations and ale. Buy machines at the Aurelian Armory
            or the Imperial Drydock (<?php echo IB_Game::fmt(IB_Settings::get('price_vending')); ?> credits each) and leave them here
            with the cargo transfer above. Each machine in use is restocked at daily maintenance for
            <?php echo IB_Game::fmt(IB_Planets::vending_unit_cost($planet)); ?> credits<?php echo $level ? ' (more at higher bastion levels)' : ''; ?>,
            paid from this world's supply fund<?php echo $ib_vault_pays ? ' and then its Vault' : ''; ?>.
            A world short of stocked machines raises no new fighters, and after <?php echo $ib_grace; ?> day<?php echo $ib_grace === 1 ? '' : 's'; ?>
            its hungry fighters start to desert. The garrison only grows as far as its machines can feed:
            fighters aboard ships and in deployed fleets are fed by the Crown.</p>
        <table class="ib-table ib-kv">
            <tr><th>Machines</th><td><span class="<?php echo $ib_have < $ib_need ? 'ib-bad' : 'ib-good'; ?>"><?php echo IB_Game::fmt($ib_have); ?> here, <?php echo IB_Game::fmt($ib_need); ?> needed</span>
                <span class="ib-small ib-dim">(enough for <?php echo IB_Game::fmt($ib_feeds); ?> fighters; <?php echo IB_Game::fmt($planet->fighters); ?> stationed)</span></td></tr>
            <tr><th>Restock</th><td><?php echo IB_Game::fmt($ib_cost); ?> credits a day</td></tr>
            <tr><th>Supply fund</th><td><?php echo IB_Game::fmt($ib_fund); ?> credits
                <?php if ($ib_days !== null) : ?>
                    <span class="<?php echo ($ib_warn && $ib_days < $ib_warn) ? 'ib-bad' : 'ib-dim'; ?>">(<?php echo $ib_days === 1 ? '1 day' : IB_Game::fmt($ib_days) . ' days'; ?> of restocking)</span>
                <?php endif; ?></td></tr>
            <?php if ($ib_short) : ?>
                <tr><th>Status</th><td class="ib-bad">Short for <?php echo $ib_short; ?> day<?php echo $ib_short === 1 ? '' : 's'; ?>:
                    <?php echo $ib_short > $ib_grace ? 'fighters are deserting every day.' : sprintf('fighters start to desert in %d day%s.', $ib_grace - $ib_short + 1, $ib_grace - $ib_short + 1 === 1 ? '' : 's'); ?></td></tr>
            <?php endif; ?>
        </table>
        <?php if ((int) $p->credits > 0) : ?>
            <?php echo IB_UI::form_open('vending_fund', 'ib-inline'); ?>
                <input type="number" name="amount" min="1" max="<?php echo (int) $p->credits; ?>" value="<?php echo (int) max(1, min((int) $p->credits, $ib_cost * 7)); ?>" class="ib-num" aria-label="Add to the supply fund">
                <button type="submit" class="ib-btn">Add to the supply fund</button>
            </form>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <div class="ib-grid">
        <div class="ib-panel">
            <h3>Bastion</h3>
            <?php if ($next) : ?>
                <p>Next: <strong>Level <?php echo $level + 1; ?> - <?php echo esc_html($next['name']); ?></strong></p>
                <p class="ib-small">Requires <?php echo IB_Game::fmt($next['credits']); ?> credits from you, and on the planet:
                    <?php echo IB_Game::fmt($next['colonists']); ?> colonists, <?php echo IB_Game::fmt($next['ore']); ?> ferrium ore,
                    <?php echo IB_Game::fmt($next['organics']); ?> biostock, <?php echo IB_Game::fmt($next['equipment']); ?> machinery
                    (materials are consumed).</p>
                <?php echo IB_UI::button('build_bastion', 'Build'); ?>
            <?php else : ?>
                <p>The bastion is fully built.</p>
            <?php endif; ?>
            <p class="ib-small ib-dim">Each level makes the planet's fighters fight harder. Level 3+ adds a Lance Battery that fires on hostile ships entering the sector, using the planet's fuel ore.</p>
        </div>
        <div class="ib-panel">
            <h3>Vault</h3>
            <?php if ($level) : ?>
                <?php echo IB_UI::form_open('vault', 'ib-inline'); ?>
                    <input type="hidden" name="dir" value="deposit">
                    <input type="number" name="amount" min="1" max="<?php echo (int) $p->credits; ?>" value="<?php echo (int) $p->credits; ?>" class="ib-num" aria-label="Deposit amount">
                    <button type="submit" class="ib-btn">Deposit</button>
                </form>
                <?php echo IB_UI::form_open('vault', 'ib-inline'); ?>
                    <input type="hidden" name="dir" value="withdraw">
                    <input type="number" name="amount" min="1" max="<?php echo (int) $planet->bastion_vault; ?>" value="<?php echo (int) $planet->bastion_vault; ?>" class="ib-num" aria-label="Withdraw amount">
                    <button type="submit" class="ib-btn ib-btn-alt">Withdraw</button>
                </form>
            <?php else : ?>
                <p class="ib-dim">Build a bastion to keep credits safely on this planet.</p>
            <?php endif; ?>
            <?php if ((int) $planet->owner_player_id === (int) $p->id) : ?>
                <h3>Rename</h3>
                <?php echo IB_UI::form_open('rename_planet', 'ib-inline'); ?>
                    <input type="text" name="name" maxlength="100" value="<?php echo esc_attr($planet->planet_name); ?>" aria-label="Planet name">
                    <button type="submit" class="ib-btn ib-btn-alt">Rename</button>
                </form>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>
<?php endif; ?>
