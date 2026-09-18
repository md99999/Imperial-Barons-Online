<?php
if (!defined('ABSPATH')) exit;
global $wpdb;
$planets = $wpdb->get_results('SELECT * FROM ' . IB_DB::t('planets') . ' ORDER BY owner_player_id DESC, sector_id LIMIT 500');
?>
<p>Owned planets are listed first (up to 500).</p>
<table class="widefat striped">
    <thead><tr><th>Sector</th><th>Name</th><th>Class</th><th>Owner</th><th>Colonists</th><th>Ore / Bio / Mach</th><th>Fighters</th><th>Bastion</th><th>Vault</th></tr></thead>
    <tbody>
    <?php foreach ($planets as $pl) : ?>
        <tr>
            <td><?php echo (int) $pl->sector_id; ?></td>
            <td><?php echo esc_html($pl->planet_name); ?></td>
            <td><?php echo esc_html(IB_Planets::class_name($pl->planet_class)); ?></td>
            <td><?php echo esc_html(IB_Planets::owner_label($pl)); ?></td>
            <td><?php echo IB_Game::fmt($pl->colonists); ?></td>
            <td><?php echo IB_Game::fmt($pl->ore) . ' / ' . IB_Game::fmt($pl->organics) . ' / ' . IB_Game::fmt($pl->equipment); ?></td>
            <td><?php echo IB_Game::fmt($pl->fighters); ?></td>
            <td><?php echo (int) $pl->bastion_level; ?></td>
            <td><?php echo IB_Game::fmt($pl->bastion_vault); ?></td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>
