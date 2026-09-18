<?php
/** @var object $p */
if (!defined('ABSPATH')) exit;
global $wpdb;
$sid = (int) $p->sector_id;
$sector = $wpdb->get_row($wpdb->prepare('SELECT * FROM ' . IB_DB::t('sectors') . ' WHERE id = %d', $sid));
$warps = IB_Player::warps_from($sid);
$visited = array_flip(IB_Player::visited_ids($p->id));

// Sensor sweep: live readings for every sector one warp away.
$scan = [];
foreach ($warps as $to) {
    $hostile = 0;
    foreach (IB_Combat::fleets_in_sector($to) as $f) {
        if (!IB_Combat::fleet_is_friendly($p, $f)) $hostile += (int) $f->fighter_count;
    }
    $scan[$to] = [
        'sector' => $wpdb->get_row($wpdb->prepare('SELECT * FROM ' . IB_DB::t('sectors') . ' WHERE id = %d', $to)),
        'port' => IB_Ports::in_sector($to),
        'planets' => count(IB_Planets::in_sector($to)),
        'hostile' => $hostile,
    ];
}
$port = IB_Ports::in_sector($sid);
$planets = IB_Planets::in_sector($sid);
$ships = IB_Player::in_sector($sid, $p->id);
$fleets = IB_Combat::fleets_in_sector($sid);
$fed = (bool) $sector->is_core;
$can_move = (int) $p->turns_remaining >= (int) IB_Settings::get('move_turn_cost');
$hostile_fleets = array_filter($fleets, function ($f) use ($p) { return !IB_Combat::fleet_is_friendly($p, $f); });
?>
<div class="ib-panel ib-sector-head">
    <h2>Sector <?php echo $sid; ?> <span class="ib-dim">in <?php echo esc_html($sector->nebula); ?></span></h2>
    <?php if ($sector->beacon) : ?><p class="ib-beacon">Beacon: <?php echo esc_html($sector->beacon); ?></p><?php endif; ?>
    <?php if ((int) $p->landed_planet_id) : ?>
        <p>You are landed on a planet. <a href="<?php echo esc_url(IB_UI::url('planet')); ?>">Go to planet</a></p>
    <?php elseif ((int) $p->docked_port_id) : ?>
        <p>You are docked at the port. <a href="<?php echo esc_url(IB_UI::url('port')); ?>">Go to port</a></p>
    <?php endif; ?>
</div>

<div class="ib-panel">
    <h3>Warp lanes</h3>
    <div class="ib-warps">
        <?php foreach ($warps as $to) {
            $label = 'Sector ' . $to . (isset($visited[$to]) ? '' : ' *');
            echo IB_UI::button('move', $label, ['to' => $to], isset($visited[$to]) ? '' : 'ib-btn-alt');
        } ?>
    </div>
    <p class="ib-dim ib-small">* not yet visited: first visits may turn up salvage, caches or survey data. Each warp costs <?php echo (int) IB_Settings::get('move_turn_cost'); ?> turn.
        <?php if (!$can_move) : ?><span class="ib-bad">You are out of turns.</span><?php endif; ?></p>
</div>

<div class="ib-panel">
    <h3>Sensor sweep</h3>
    <div class="ib-table-wrap">
    <table class="ib-table">
        <thead><tr><th>Sector</th><th>Nebula</th><th>Port</th><th>Planets</th><th>Hostile fighters</th><th>Status</th></tr></thead>
        <tbody>
        <?php foreach ($scan as $to => $s) : ?>
            <tr>
                <td><?php echo (int) $to; ?></td>
                <td><?php echo $s['sector'] ? esc_html($s['sector']->nebula) : ''; ?></td>
                <td><?php echo $s['port'] ? IB_UI::pattern($s['port']) : '<span class="ib-dim">none</span>'; ?></td>
                <td><?php echo $s['planets'] ?: '<span class="ib-dim">0</span>'; ?></td>
                <td><?php echo $s['hostile'] ? '<span class="ib-bad">' . IB_Game::fmt($s['hostile']) . '</span>' : '<span class="ib-dim">none</span>'; ?></td>
                <td><?php echo isset($visited[$to]) ? 'Visited' : '<span class="ib-special">Unvisited</span>'; ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    </div>
    <?php if ((int) $p->survey_drones > 0) : ?>
        <?php echo IB_UI::button('launch_drone', sprintf('Launch survey drone (%d left): chart everything within %d warps', (int) $p->survey_drones, IB_Discovery::DRONE_RADIUS), [], 'ib-btn-alt'); ?>
    <?php else : ?>
        <p class="ib-dim ib-small">Survey drones and nebula charts, sold at the Aurelian Armory and the Imperial Drydock, chart sectors beyond sensor range.</p>
    <?php endif; ?>
</div>

<div class="ib-grid">
    <div class="ib-panel">
        <h3>Port</h3>
        <?php if ($port) : ?>
            <p><?php echo esc_html($port->port_name); ?> <?php echo IB_UI::pattern($port); ?></p>
            <?php if ((int) $p->docked_port_id === (int) $port->id) : ?>
                <a class="ib-btn" href="<?php echo esc_url(IB_UI::url('port')); ?>">Trade</a>
            <?php else : ?>
                <?php echo IB_UI::button('dock', 'Dock (' . (int) IB_Settings::get('dock_turn_cost') . ' turn)'); ?>
            <?php endif; ?>
        <?php else : ?>
            <p class="ib-dim">No port in this sector.</p>
        <?php endif; ?>
    </div>

    <div class="ib-panel">
        <h3>Planets</h3>
        <?php if (!$planets) : ?><p class="ib-dim">No planets.</p><?php endif; ?>
        <?php foreach ($planets as $pl) :
            $hostile = IB_Planets::is_hostile($p, $pl); ?>
            <div class="ib-row">
                <div>
                    <strong><?php echo esc_html($pl->planet_name); ?></strong>
                    <span class="ib-dim">(<?php echo esc_html(IB_Planets::class_name($pl->planet_class)); ?>)</span><br>
                    <span class="ib-small">Owner: <?php echo esc_html(IB_Planets::owner_label($pl)); ?>
                        <?php if ($hostile) : ?> &middot; <span class="ib-bad"><?php echo IB_Game::fmt($pl->fighters); ?> fighters</span><?php endif; ?>
                        <?php if ((int) $pl->bastion_level) : ?> &middot; Bastion L<?php echo (int) $pl->bastion_level; ?><?php endif; ?>
                    </span>
                </div>
                <div>
                    <?php echo IB_UI::button('land', 'Land', ['planet_id' => $pl->id]); ?>
                    <?php if ($hostile && (int) $pl->fighters > 0 && !$fed) : ?>
                        <?php echo IB_UI::form_open('attack_planet', 'ib-inline'); ?>
                            <input type="hidden" name="planet_id" value="<?php echo (int) $pl->id; ?>">
                            <input type="number" name="fighters" min="1" max="<?php echo (int) $p->fighters; ?>" value="<?php echo (int) $p->fighters; ?>" class="ib-num" aria-label="Fighters to commit">
                            <button type="submit" class="ib-btn ib-btn-danger" data-confirm="Attack this planet?">Attack</button>
                        </form>
                    <?php endif; ?>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<div class="ib-panel">
    <h3>Ships in sector</h3>
    <?php if (!$ships) : ?><p class="ib-dim">No other ships detected.</p><?php endif; ?>
    <?php foreach ($ships as $s) :
        $mate = (int) $p->team_id && (int) $s->team_id === (int) $p->team_id;
        $team = (int) $s->team_id ? IB_Teams::get($s->team_id) : null; ?>
        <div class="ib-row">
            <div>
                <strong><?php echo esc_html(IB_Game::rank_title($s->experience) . ' ' . $s->alias_name); ?></strong>
                <span class="ib-dim">aboard the <?php echo esc_html($s->ship_name); ?> (<?php echo esc_html(IB_Ships::get($s->ship_type)['name']); ?>)</span><br>
                <span class="ib-small"><?php echo IB_Game::fmt($s->fighters); ?> fighters
                    <?php if ($team) : ?> &middot; Team <?php echo esc_html($team->team_name); ?><?php endif; ?>
                    <?php if ($mate) : ?> <span class="ib-good">(teammate)</span><?php endif; ?></span>
            </div>
            <div>
                <a class="ib-btn ib-btn-alt" href="<?php echo esc_url(IB_UI::url('messages', ['to' => $s->id])); ?>">Hail</a>
                <?php if (!$mate && !$fed) : ?>
                    <?php echo IB_UI::form_open('attack_player', 'ib-inline'); ?>
                        <input type="hidden" name="target_id" value="<?php echo (int) $s->id; ?>">
                        <input type="number" name="fighters" min="1" max="<?php echo (int) $p->fighters; ?>" value="<?php echo (int) $p->fighters; ?>" class="ib-num" aria-label="Fighters to commit">
                        <button type="submit" class="ib-btn ib-btn-danger" data-confirm="Attack <?php echo esc_attr($s->alias_name); ?>?">Attack</button>
                    </form>
                <?php endif; ?>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<div class="ib-panel">
    <h3>Deployed fighters</h3>
    <?php if (!$fleets) : ?><p class="ib-dim">No fighters in this sector.</p><?php endif; ?>
    <?php foreach ($fleets as $f) :
        $friendly = IB_Combat::fleet_is_friendly($p, $f);
        $faction = (int) $f->owner_player_id ? null : IB_Factions::get($f->faction); ?>
        <div class="ib-row">
            <div>
                <strong class="<?php echo $friendly ? 'ib-good' : 'ib-bad'; ?>"><?php echo IB_Game::fmt($f->fighter_count); ?> fighters</strong>
                belonging to <?php echo esc_html(IB_Combat::fleet_owner_label($f)); ?>
                <span class="ib-dim">(<?php echo esc_html($f->fleet_mode); ?>)</span>
                <?php if ($faction) : ?><br><span class="ib-small ib-dim"><?php echo esc_html($faction['desc']); ?></span><?php endif; ?>
            </div>
            <div>
                <?php if ($friendly) : ?>
                    <?php echo IB_UI::form_open('recall', 'ib-inline'); ?>
                        <input type="hidden" name="fleet_id" value="<?php echo (int) $f->id; ?>">
                        <input type="number" name="qty" min="1" max="<?php echo (int) $f->fighter_count; ?>" value="<?php echo (int) $f->fighter_count; ?>" class="ib-num" aria-label="Fighters to recall">
                        <button type="submit" class="ib-btn ib-btn-alt">Recall</button>
                    </form>
                <?php elseif (!$fed) : ?>
                    <?php echo IB_UI::form_open('attack_fleet', 'ib-inline'); ?>
                        <input type="hidden" name="fleet_id" value="<?php echo (int) $f->id; ?>">
                        <input type="number" name="fighters" min="1" max="<?php echo (int) $p->fighters; ?>" value="<?php echo (int) $p->fighters; ?>" class="ib-num" aria-label="Fighters to commit">
                        <button type="submit" class="ib-btn ib-btn-danger" data-confirm="Attack these fighters?">Attack</button>
                    </form>
                <?php endif; ?>
            </div>
        </div>
    <?php endforeach; ?>

    <?php if (!$fed && (int) $p->fighters > 0 && !$hostile_fleets) : ?>
        <?php echo IB_UI::form_open('deploy', 'ib-inline ib-deploy'); ?>
            <label>Deploy <input type="number" name="qty" min="1" max="<?php echo (int) $p->fighters; ?>" value="<?php echo min(50, (int) $p->fighters); ?>" class="ib-num"> fighters as</label>
            <select name="mode" aria-label="Fighter mode">
                <option value="defensive">Defensive (guard only)</option>
                <option value="offensive">Offensive (attack intruders)</option>
            </select>
            <button type="submit" class="ib-btn">Deploy</button>
        </form>
    <?php elseif ($fed) : ?>
        <p class="ib-dim ib-small">Fighters cannot be deployed in the Imperial Core.</p>
    <?php endif; ?>
</div>
