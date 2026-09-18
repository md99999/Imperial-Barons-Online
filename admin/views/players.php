<?php
if (!defined('ABSPATH')) exit;
global $wpdb;
$players = $wpdb->get_results('SELECT * FROM ' . IB_DB::t('players') . ' ORDER BY alias_name');
?>
<?php if (!$players) : ?>
    <p>No pilots have registered yet.</p>
<?php else : ?>
<table class="widefat striped">
    <thead><tr><th>Pilot</th><th>WP user</th><th>Ship</th><th>Exp</th><th>Last seen</th><th>Sector / Turns / Credits / Fighters</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($players as $pl) :
        $user = get_userdata($pl->user_id); ?>
        <tr>
            <td><strong><?php echo esc_html($pl->alias_name); ?></strong><br><span class="description">#<?php echo (int) $pl->id; ?></span></td>
            <td><?php echo $user ? esc_html($user->user_login) : '<em>deleted</em>'; ?></td>
            <td><?php echo esc_html(IB_Ships::get($pl->ship_type)['name']); ?></td>
            <td><?php echo IB_Game::fmt($pl->experience); ?></td>
            <td><?php echo esc_html($pl->last_seen); ?></td>
            <td>
                <?php echo IB_Admin::form_open('player_update'); ?>
                    <input type="hidden" name="player_id" value="<?php echo (int) $pl->id; ?>">
                    <input type="number" name="sector_id" value="<?php echo (int) $pl->sector_id; ?>" class="small-text" min="1" title="Sector">
                    <input type="number" name="turns_remaining" value="<?php echo (int) $pl->turns_remaining; ?>" class="small-text" min="0" title="Turns">
                    <input type="number" name="credits" value="<?php echo (int) $pl->credits; ?>" class="small-text" min="0" title="Credits">
                    <input type="number" name="fighters" value="<?php echo (int) $pl->fighters; ?>" class="small-text" min="0" title="Fighters">
                    <button class="button button-small">Save</button>
                </form>
            </td>
            <td>
                <?php echo IB_Admin::form_open('player_delete', 'onsubmit="return confirm(\'Delete this pilot permanently?\')"'); ?>
                    <input type="hidden" name="player_id" value="<?php echo (int) $pl->id; ?>">
                    <button class="button button-small button-link-delete">Delete</button>
                </form>
            </td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>
<?php endif; ?>
