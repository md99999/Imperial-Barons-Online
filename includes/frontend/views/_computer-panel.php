<?php
/**
 * The ship's computer in miniature, for pages other than the Computer page: fly to a sector,
 * and find a port that buys or sells a given commodity. Every result flies and docks in one
 * click. The Computer page keeps the full set: the plotted course, every known port and the
 * charted-space figures.
 *
 * @var object $p
 * @var string $ib_panel_page the page key this panel is rendered on, for its own GET forms
 */
if (!defined('ABSPATH')) exit;
if (empty($ib_panel_page)) $ib_panel_page = 'port';

$ib_find = isset($_GET['find']) && is_string($_GET['find']) ? sanitize_key(wp_unslash($_GET['find'])) : '';
$ib_want = isset($_GET['want']) && $_GET['want'] === 'sell' ? 'sell' : 'buy';
$ib_move = (int) IB_Settings::get('move_turn_cost');
$ib_dock = (int) IB_Settings::get('dock_turn_cost');
?>
<div class="ib-panel">
    <h3>Ship's computer</h3>

    <?php echo IB_UI::form_open('fly', 'ib-inline'); ?>
        <label>Fly to sector <input type="number" name="target" min="1" class="ib-num" required aria-label="Destination sector"></label>
        <label class="ib-small"><input type="checkbox" name="dock" value="1" checked> dock on arrival</label>
        <button type="submit" class="ib-btn">Engage</button>
    </form>
    <p class="ib-small ib-dim">Each warp costs <?php echo $ib_move; ?> turn, docking <?php echo $ib_dock; ?>.
        You have <?php echo (int) $p->turns_remaining; ?>.
        <a href="<?php echo esc_url(IB_UI::url('computer')); ?>">Open the full computer</a> to see the course first,
        read the known-port table or check your deployed fighters.</p>

    <?php echo IB_UI::get_form_open($ib_panel_page); ?>
        <label>Where can I
            <select name="want">
                <option value="buy" <?php selected($ib_want, 'buy'); ?>>buy</option>
                <option value="sell" <?php selected($ib_want, 'sell'); ?>>sell</option>
            </select>
        </label>
        <select name="find" aria-label="Commodity">
            <?php foreach (IB_Game::COMMODITIES as $key => $c) : ?>
                <option value="<?php echo esc_attr($key); ?>" <?php selected($ib_find, $key); ?>><?php echo esc_html($c['label']); ?></option>
            <?php endforeach; ?>
        </select>
        <button type="submit" class="ib-btn ib-btn-alt">Search</button>
    </form>

    <?php if (isset(IB_Game::COMMODITIES[$ib_find])) :
        $ib_matches = IB_Ports::search($p, $ib_find, $ib_want, 10); ?>
        <?php if (!$ib_matches) : ?>
            <p class="ib-dim ib-small">No charted port <?php echo $ib_want === 'buy' ? 'sells' : 'buys'; ?>
                <?php echo esc_html(IB_Game::label($ib_find)); ?>. Explore further, or buy a survey drone or nebula chart.</p>
        <?php else : ?>
            <div class="ib-table-wrap">
            <table class="ib-table">
                <thead><tr><th>Sector</th><th>Port</th><th>Class</th><th>Units</th><th>Price</th><th>Turns</th><th></th></tr></thead>
                <tbody>
                <?php foreach ($ib_matches as $m) : ?>
                    <tr<?php echo $m['reachable'] ? '' : ' class="ib-dim"'; ?>>
                        <td><?php echo IB_UI::fly_to($p, $m['port']->sector_id, $m['turns'], null, true); ?></td>
                        <td><?php echo esc_html($m['port']->port_name); ?></td>
                        <td><?php echo IB_UI::pattern($m['port']); ?></td>
                        <td><?php echo IB_Game::fmt($m['units']); ?></td>
                        <td><?php echo IB_Game::fmt($m['price']); ?></td>
                        <td><?php echo (int) $m['turns']; ?></td>
                        <td><?php if ($m['reachable']) {
                                echo IB_UI::button('fly', 'Fly & dock', ['target' => (int) $m['port']->sector_id, 'dock' => 1], 'ib-btn-small');
                            } else {
                                echo '<span class="ib-small ib-bad">not enough turns</span>';
                            } ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            </div>
        <?php endif; ?>
    <?php endif; ?>
</div>
