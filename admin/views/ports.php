<?php
if (!defined('ABSPATH')) exit;
global $wpdb;
$class = isset($_GET['class']) && $_GET['class'] !== '' ? (int) $_GET['class'] : null;
$paged = max(1, isset($_GET['paged']) ? (int) $_GET['paged'] : 1);
$per = 100;
$where = $class === null ? '' : $wpdb->prepare(' WHERE port_class = %d', $class);
$total = (int) $wpdb->get_var('SELECT COUNT(*) FROM ' . IB_DB::t('ports') . $where);
$ports = $wpdb->get_results('SELECT * FROM ' . IB_DB::t('ports') . $where . $wpdb->prepare(' ORDER BY sector_id LIMIT %d OFFSET %d', $per, ($paged - 1) * $per));
$base = admin_url('admin.php?page=ib_ports');
?>
<form method="get" style="margin:12px 0">
    <input type="hidden" name="page" value="ib_ports">
    <label>Class
        <select name="class">
            <option value="">All</option>
            <?php foreach ([0 => 'Armory', 1 => 'BBS', 2 => 'BSB', 3 => 'SBB', 4 => 'SSB', 5 => 'SBS', 6 => 'BSS', 7 => 'SSS', 8 => 'BBB', 9 => 'Drydock'] as $c => $label) : ?>
                <option value="<?php echo $c; ?>" <?php selected($class, $c); ?>><?php echo $c . ' - ' . esc_html($label); ?></option>
            <?php endforeach; ?>
        </select>
    </label>
    <button class="button">Filter</button>
</form>

<?php echo IB_Admin::form_open('ports_restock', 'style="margin-bottom:12px"'); ?>
    <button class="button">Restock all ports to capacity</button>
</form>

<p><?php echo IB_Game::fmt($total); ?> ports.</p>
<table class="widefat striped">
    <thead><tr><th>Sector</th><th>Name</th><th>Class</th><th>Ferrium Ore</th><th>Biostock</th><th>Machinery</th><th>Specialist</th></tr></thead>
    <tbody>
    <?php foreach ($ports as $port) : ?>
        <tr>
            <td><?php echo (int) $port->sector_id; ?></td>
            <td><?php echo esc_html($port->port_name); ?></td>
            <td><?php echo (int) $port->port_class; ?> (<?php echo esc_html(IB_Ports::class_code($port->port_class)); ?>)</td>
            <?php foreach (IB_Game::commodities() as $key) : ?>
                <td><?php if (IB_Ports::is_trading_port($port)) : ?>
                    <?php echo IB_Ports::mode($port, $key) === 'selling' ? 'S' : 'B'; ?>
                    <?php echo IB_Game::fmt(IB_Ports::qty($port, $key)); ?>/<?php echo IB_Game::fmt(IB_Ports::max($port, $key)); ?>
                    @ <?php echo IB_Ports::price($port, $key); ?>
                <?php else : ?>&mdash;<?php endif; ?></td>
            <?php endforeach; ?>
            <td><?php $spec = IB_Ports::specialty($port);
                if ($spec) {
                    echo esc_html(IB_Game::label($spec)) . ' ' . (IB_Ports::mode($port, $spec) === 'selling' ? 'S' : 'B') . ' '
                        . IB_Game::fmt(IB_Ports::qty($port, $spec)) . '/' . IB_Game::fmt(IB_Ports::max($port, $spec))
                        . ' @ ' . IB_Ports::price($port, $spec);
                } else { echo '&mdash;'; } ?></td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>
<?php if ($total > $per) : ?>
    <p>
        <?php for ($i = 1; $i <= ceil($total / $per); $i++) : ?>
            <?php if ($i === $paged) : ?><strong><?php echo $i; ?></strong><?php else : ?>
                <a href="<?php echo esc_url(add_query_arg(['paged' => $i, 'class' => $class], $base)); ?>"><?php echo $i; ?></a><?php endif; ?>
        <?php endfor; ?>
    </p>
<?php endif; ?>
