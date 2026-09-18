<?php
/** @var object $p */
if (!defined('ABSPATH')) exit;
$team = (int) $p->team_id ? IB_Teams::get($p->team_id) : null;
?>
<?php if (!$team) :
    $teams = IB_Teams::all(); ?>
    <div class="ib-grid">
        <div class="ib-panel">
            <h2>Found a team</h2>
            <p>Teammates share planets and sector fighters, can't attack each other, and rank together.</p>
            <?php echo IB_UI::form_open('team_create', 'ib-stack'); ?>
                <label>Team name <input type="text" name="team_name" maxlength="41" required></label>
                <label>Team password <input type="password" name="password" minlength="4" required autocomplete="new-password"></label>
                <button type="submit" class="ib-btn">Found team</button>
            </form>
        </div>
        <div class="ib-panel">
            <h2>Join a team</h2>
            <?php if (!$teams) : ?>
                <p class="ib-dim">No teams exist yet. Be the first!</p>
            <?php else : ?>
                <?php echo IB_UI::form_open('team_join', 'ib-stack'); ?>
                    <label>Team
                        <select name="team_id">
                            <?php foreach ($teams as $t) : ?>
                                <option value="<?php echo (int) $t->id; ?>"><?php echo esc_html($t->team_name); ?> (<?php echo (int) $t->members; ?> members)</option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <label>Password <input type="password" name="password" required autocomplete="off"></label>
                    <button type="submit" class="ib-btn">Join</button>
                </form>
                <p class="ib-small ib-dim">Ask the team's captain for the password.</p>
            <?php endif; ?>
        </div>
    </div>

<?php else :
    $members = IB_Teams::members($team->id);
    $captain = IB_Teams::is_captain($p);
    $planets = IB_Planets::team_planets($team->id);
    $board = IB_Teams::board($team->id); ?>
    <div class="ib-panel">
        <h2><?php echo esc_html($team->team_name); ?></h2>
        <p class="ib-dim">Combat medals: <?php echo (int) $team->combat_medals; ?> &middot; Net worth: <?php echo IB_Game::fmt(IB_Teams::net_worth($team->id)); ?></p>
        <table class="ib-table">
            <thead><tr><th>Pilot</th><th>Sector</th><th>Ship</th><th>Fighters</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($members as $m) : ?>
                <tr>
                    <td><?php echo esc_html(IB_Game::rank_title($m->experience) . ' ' . $m->alias_name); ?>
                        <?php if ((int) $m->id === (int) $team->captain_player_id) : ?><span class="ib-special">Captain</span><?php endif; ?></td>
                    <td><a href="<?php echo esc_url(IB_UI::url('computer', ['target' => $m->sector_id])); ?>"><?php echo (int) $m->sector_id; ?></a></td>
                    <td><?php echo esc_html(IB_Ships::get($m->ship_type)['name']); ?></td>
                    <td><?php echo IB_Game::fmt($m->fighters); ?></td>
                    <td>
                        <?php if ($captain && (int) $m->id !== (int) $p->id) : ?>
                            <?php echo IB_UI::button('team_captain', 'Make captain', ['member_id' => $m->id], 'ib-btn-small ib-btn-alt', 'Hand over captaincy to ' . $m->alias_name . '?'); ?>
                            <?php echo IB_UI::button('team_kick', 'Remove', ['member_id' => $m->id], 'ib-btn-small ib-btn-danger', 'Remove ' . $m->alias_name . ' from the team?'); ?>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <p>
            <?php echo IB_UI::button('team_leave', 'Leave team', [], 'ib-btn-alt', 'Leave ' . $team->team_name . '?'); ?>
            <?php if ($captain) echo IB_UI::button('team_disband', 'Disband team', [], 'ib-btn-danger', 'Disband the team permanently?'); ?>
        </p>
    </div>

    <div class="ib-panel">
        <h3>Team planets</h3>
        <?php if (!$planets) : ?><p class="ib-dim">Your team has no planets.</p><?php else : ?>
            <table class="ib-table">
                <thead><tr><th>Planet</th><th>Sector</th><th>Owner</th><th>Colonists</th><th>Fighters</th><th>Bastion</th></tr></thead>
                <tbody>
                <?php foreach ($planets as $pl) : ?>
                    <tr>
                        <td><?php echo esc_html($pl->planet_name); ?></td>
                        <td><?php echo (int) $pl->sector_id; ?></td>
                        <td><?php echo esc_html(IB_Player::name($pl->owner_player_id)); ?></td>
                        <td><?php echo IB_Game::fmt($pl->colonists); ?></td>
                        <td><?php echo IB_Game::fmt($pl->fighters); ?></td>
                        <td><?php echo (int) $pl->bastion_level; ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>

    <div class="ib-panel">
        <h3>Team channel</h3>
        <?php echo IB_UI::form_open('team_post', 'ib-stack'); ?>
            <textarea name="message" rows="3" maxlength="2000" required aria-label="Team message"></textarea>
            <button type="submit" class="ib-btn">Transmit</button>
        </form>
        <ul class="ib-news">
            <?php foreach ($board as $msg) : ?>
                <li><span class="ib-dim"><?php echo esc_html(IB_UI::time_ago($msg->created_at)); ?></span>
                    <strong><?php echo esc_html(IB_Messages::sender_label($msg)); ?>:</strong>
                    <?php echo nl2br(esc_html($msg->message_text)); ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>
