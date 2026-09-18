<?php
if (!defined('ABSPATH')) exit;
$teams = IB_Teams::all();
?>
<?php if (!$teams) : ?>
    <p>No teams have been formed.</p>
<?php else : ?>
<table class="widefat striped">
    <thead><tr><th>Team</th><th>Captain</th><th>Members</th><th>Combat medals</th><th>Founded</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($teams as $t) : ?>
        <tr>
            <td><strong><?php echo esc_html($t->team_name); ?></strong></td>
            <td><?php echo esc_html(IB_Player::name($t->captain_player_id)); ?></td>
            <td><?php echo esc_html(implode(', ', wp_list_pluck(IB_Teams::members($t->id), 'alias_name'))); ?></td>
            <td><?php echo (int) $t->combat_medals; ?></td>
            <td><?php echo esc_html($t->created_at); ?></td>
            <td>
                <?php echo IB_Admin::form_open('team_disband', 'onsubmit="return confirm(\'Disband this team?\')"'); ?>
                    <input type="hidden" name="team_id" value="<?php echo (int) $t->id; ?>">
                    <button class="button button-small button-link-delete">Disband</button>
                </form>
            </td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>
<?php endif; ?>
