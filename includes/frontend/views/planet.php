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
        <?php else : ?>
            <table class="ib-table">
                <thead><tr><th>Planet</th><th>Sector</th><th>Colonists</th><th>Fighters</th><th>Bastion</th></tr></thead>
                <tbody>
                <?php foreach ($mine as $pl) : ?>
                    <tr>
                        <td><?php echo esc_html($pl->planet_name); ?></td>
                        <td><a href="<?php echo esc_url(IB_UI::url('computer', ['target' => $pl->sector_id])); ?>"><?php echo (int) $pl->sector_id; ?></a></td>
                        <td><?php echo IB_Game::fmt($pl->colonists); ?></td>
                        <td><?php echo IB_Game::fmt($pl->fighters); ?></td>
                        <td><?php echo (int) $pl->bastion_level; ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>

<?php elseif ($planet->planet_class === IB_Planets::CROWN_WORLD) : ?>
    <div class="ib-panel">
        <h2>Aurelia Prime, the Crown World</h2>
        <p>The cradle of humanity. Colonists crowd the spaceport, eager for a new life on the frontier.</p>
        <p>Colonists cost <?php echo (int) IB_Settings::get('price_colonist'); ?> credits each and take one hold apiece.
            You have <?php echo IB_Player::holds_free($p); ?> empty holds.</p>
        <?php if (IB_Player::holds_free($p) > 0) : ?>
            <?php echo IB_UI::form_open('buy_colonists', 'ib-inline'); ?>
                <input type="number" name="qty" min="1" max="<?php echo IB_Player::holds_free($p); ?>" value="<?php echo IB_Player::holds_free($p); ?>" class="ib-num" aria-label="Colonists">
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
            <tr><th>Daily output per 1,000 colonists</th><td><?php echo (int) $def['ore']; ?> ferrium ore, <?php echo (int) $def['organics']; ?> biostock, <?php echo (int) $def['equipment']; ?> machinery, <?php echo (int) $def['fighters']; ?> fighters</td></tr>
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
            <?php foreach (IB_Planets::TRANSFERABLE as $what) : ?>
                <tr>
                    <td><?php echo esc_html(IB_Game::label($what)); ?></td>
                    <td><?php echo IB_Game::fmt($planet->$what); ?></td>
                    <td><?php echo IB_Game::fmt($p->$what); ?></td>
                    <td>
                        <?php echo IB_UI::form_open('planet_transfer', 'ib-inline'); ?>
                            <input type="hidden" name="what" value="<?php echo esc_attr($what); ?>">
                            <input type="hidden" name="dir" value="take">
                            <input type="number" name="qty" min="1" value="<?php echo (int) $planet->$what; ?>" class="ib-num" aria-label="Take <?php echo esc_attr($what); ?>">
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
                <?php echo IB_UI::form_open('Vault', 'ib-inline'); ?>
                    <input type="hidden" name="dir" value="deposit">
                    <input type="number" name="amount" min="1" max="<?php echo (int) $p->credits; ?>" value="<?php echo (int) $p->credits; ?>" class="ib-num" aria-label="Deposit amount">
                    <button type="submit" class="ib-btn">Deposit</button>
                </form>
                <?php echo IB_UI::form_open('Vault', 'ib-inline'); ?>
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
