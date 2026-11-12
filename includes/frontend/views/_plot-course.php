<?php
/**
 * The Ship's Computer course plotter: plot a course to any sector, see each warp on the way, then
 * engage autopilot or fly and dock. Shown on the Computer page and on the Sector page.
 *
 * @var object $p
 * @var string $ib_panel_page the page key this panel is rendered on, for its GET form
 */
if (!defined('ABSPATH')) exit;
if (empty($ib_panel_page)) $ib_panel_page = 'computer';

$ib_here = (int) $p->sector_id;
$ib_target = isset($_GET['target']) && is_scalar($_GET['target']) ? absint($_GET['target']) : 0;
$ib_move_cost = (int) IB_Settings::get('move_turn_cost');
?>
<div class="ib-panel">
    <h<?php echo $ib_panel_page === 'computer' ? '2' : '3'; ?>>Ship's Computer</h<?php echo $ib_panel_page === 'computer' ? '2' : '3'; ?>>
    <?php echo IB_UI::get_form_open($ib_panel_page); ?>
        <label>Plot course to sector <input type="number" name="target" min="1" value="<?php echo $ib_target ?: ''; ?>" class="ib-num" required></label>
        <button type="submit" class="ib-btn">Plot</button>
    </form>

    <?php if ($ib_target) :
        $ib_path = IB_Pathfinder::shortestPath(null, $ib_here, $ib_target); ?>
        <?php if (!$ib_path) : ?>
            <p class="ib-bad">No route to sector <?php echo $ib_target; ?> could be found.</p>
        <?php elseif (count($ib_path) === 1) : ?>
            <p>You are already in sector <?php echo $ib_target; ?>.</p>
        <?php else :
            // Ports are known in every charted sector (visited, swept by sensors, or surveyed).
            $ib_charted = array_flip(IB_Player::charted_ids($p->id));
            $ib_been = array_flip(IB_Player::visited_ids($p->id));
            $ib_hops = count($ib_path) - 1; ?>
            <p>Course: <?php echo $ib_hops; ?> warp<?php echo $ib_hops > 1 ? 's' : ''; ?>, <?php echo $ib_hops * $ib_move_cost; ?> turn(s).
                You have <?php echo (int) $p->turns_remaining; ?>.</p>
            <ol class="ib-path">
                <?php foreach ($ib_path as $ib_sid) :
                    $ib_port = isset($ib_charted[$ib_sid]) ? IB_Ports::in_sector($ib_sid) : null; ?>
                    <li class="<?php echo $ib_sid === $ib_here ? 'ib-current' : ''; ?>">
                        <?php echo $ib_port ? IB_UI::fly_to($p, $ib_sid, null, null, true) : (int) $ib_sid; ?><?php echo isset($ib_been[$ib_sid]) ? '' : '<span class="ib-dim">*</span>'; ?>
                        <?php echo $ib_port ? IB_UI::pattern($ib_port) : ''; ?>
                    </li>
                <?php endforeach; ?>
            </ol>
            <?php echo IB_UI::button('autopilot', 'Engage autopilot', ['target' => $ib_target]); ?>
            <?php if (IB_Ports::in_sector($ib_target)) : ?>
                <?php echo IB_UI::button('fly', 'Fly & dock', ['target' => $ib_target, 'dock' => 1], 'ib-btn-alt'); ?>
            <?php endif; ?>
            <p class="ib-small ib-dim">Autopilot stops early if you run out of turns or meet trouble.
                <strong>Fly &amp; dock</strong> does the same and docks at the port when you arrive,
                for <?php echo (int) IB_Settings::get('dock_turn_cost'); ?> turn more. * = not yet visited.</p>
        <?php endif; ?>
    <?php endif; ?>
</div>
