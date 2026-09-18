<?php
/** @var object $p */
if (!defined('ABSPATH')) exit;
global $wpdb;
$show_all = current_user_can('manage_options') && !empty($_GET['all']);
$graph = IB_Pathfinder::graph();
// "Explored" here means charted: the pilot knows the sector's contents, from a visit or a scan.
$explored = array_flip(IB_Player::charted_ids($p->id));
$visited = array_flip(IB_Player::visited_ids($p->id));
if ($show_all) {
    $explored = array_flip(array_map('intval', $wpdb->get_col('SELECT id FROM ' . IB_DB::t('sectors'))));
    $visited = $explored;
}

// Known sectors = charted ones plus anything a warp from them leads to.
$known = $explored;
foreach (array_keys($explored) as $sid) {
    foreach ($graph[$sid] ?? [] as $to) $known[$to] = true;
}
$ids = array_map('intval', array_keys($known));
$sectors = [];
foreach (array_chunk($ids, 1000) as $chunk) {
    $rows = $wpdb->get_results('SELECT id, x, y, is_core FROM ' . IB_DB::t('sectors') . ' WHERE id IN (' . implode(',', $chunk) . ')');
    foreach ($rows as $r) $sectors[(int) $r->id] = $r;
}
$ports = [];
foreach ($wpdb->get_results('SELECT sector_id, port_class FROM ' . IB_DB::t('ports')) as $r) $ports[(int) $r->sector_id] = (int) $r->port_class;
$planets = [];
foreach ($wpdb->get_results('SELECT sector_id, owner_player_id, team_id FROM ' . IB_DB::t('planets')) as $r) {
    $mine = (int) $r->owner_player_id === (int) $p->id || ((int) $p->team_id && (int) $r->team_id === (int) $p->team_id);
    $already_mine = ($planets[(int) $r->sector_id] ?? '') === 'mine';
    $planets[(int) $r->sector_id] = ($mine || $already_mine) ? 'mine' : 'other';
}

$edges = '';
$drawn = [];
foreach (array_keys($explored) as $a) {
    if (!isset($sectors[$a])) continue;
    foreach ($graph[$a] ?? [] as $b) {
        if (!isset($sectors[$b])) continue;
        $key = min($a, $b) . '-' . max($a, $b);
        if (isset($drawn[$key])) continue;
        $drawn[$key] = true;
        $two_way = in_array($a, $graph[$b] ?? [], true);
        // A lane only counts as one-way once both ends are explored.
        $one_way = !$two_way && isset($explored[$b]);
        $edges .= sprintf('<line x1="%.1f" y1="%.1f" x2="%.1f" y2="%.1f" class="%s"%s/>',
            $sectors[$a]->x, $sectors[$a]->y, $sectors[$b]->x, $sectors[$b]->y,
            $one_way ? 'ib-e-oneway' : 'ib-e', $one_way ? ' marker-end="url(#ib-arrow)"' : '');
    }
}

$nodes = '';
foreach ($sectors as $id => $s) {
    $is_explored = isset($explored[$id]);
    $classes = ['ib-n'];
    $title = 'Sector ' . $id;
    if (!$is_explored) {
        $classes[] = 'ib-n-unexplored';
        $title .= ' (uncharted)';
    } else {
        if ($s->is_core) $classes[] = 'ib-n-fed';
        if (!isset($visited[$id])) {
            $classes[] = 'ib-n-charted';
            $title .= ' (charted, not visited)';
        }
        if (isset($ports[$id])) {
            $classes[] = in_array($ports[$id], [IB_Ports::ARMORY, IB_Ports::SHIPYARD], true) ? 'ib-n-special' : 'ib-n-port';
            $title .= ' - port ' . IB_Ports::class_code($ports[$id]);
        }
        if (isset($planets[$id])) $title .= $planets[$id] === 'mine' ? ' - your planet' : ' - planet';
    }
    if ($id === (int) $p->sector_id) {
        $classes[] = 'ib-n-current';
        $title .= ' - YOU ARE HERE';
    }
    $ring = ($is_explored && isset($planets[$id]))
        ? sprintf('<circle cx="%.1f" cy="%.1f" r="7" class="ib-ring ib-ring-%s"/>', $s->x, $s->y, $planets[$id]) : '';
    $nodes .= sprintf(
        '<a href="%s">%s<circle cx="%.1f" cy="%.1f" r="%d" class="%s"><title>%s</title></circle><text x="%.1f" y="%.1f" class="ib-lbl">%d</text></a>',
        esc_url(IB_UI::url('computer', ['target' => $id])), $ring, $s->x, $s->y, $id === (int) $p->sector_id ? 6 : 4,
        esc_attr(implode(' ', $classes)), esc_html($title), $s->x + 6, $s->y - 6, $id
    );
}
$here = $sectors[(int) $p->sector_id] ?? (object) ['x' => 500, 'y' => 500];
?>
<div class="ib-panel">
    <h2>Galaxy Map</h2>
    <p class="ib-small ib-dim">
        <?php echo $show_all ? 'Administrator view: the whole universe.' : sprintf('%s sectors charted, %s visited.', IB_Game::fmt(count($explored)), IB_Game::fmt(count($visited))); ?>
        Drag to pan, scroll or use the buttons to zoom. Click a sector to plot a course there.
    </p>
    <div class="ib-map-controls">
        <button type="button" class="ib-btn ib-btn-small" data-map="in" aria-label="Zoom in">+</button>
        <button type="button" class="ib-btn ib-btn-small" data-map="out" aria-label="Zoom out">&minus;</button>
        <button type="button" class="ib-btn ib-btn-small" data-map="home">Center on ship</button>
        <button type="button" class="ib-btn ib-btn-small" data-map="all">Whole galaxy</button>
        <?php if (current_user_can('manage_options')) : ?>
            <a class="ib-btn ib-btn-small ib-btn-alt" href="<?php echo esc_url(IB_UI::url('map', $show_all ? [] : ['all' => 1])); ?>"><?php echo $show_all ? 'My explored map' : 'Admin: reveal all'; ?></a>
        <?php endif; ?>
    </div>
    <div class="ib-map-wrap">
        <svg class="ib-map" viewBox="<?php echo esc_attr(sprintf('%.1f %.1f 250 250', $here->x - 125, $here->y - 125)); ?>"
             data-cx="<?php echo esc_attr($here->x); ?>" data-cy="<?php echo esc_attr($here->y); ?>"
             role="img" aria-label="Map of charted sectors" xmlns="http://www.w3.org/2000/svg">
            <defs>
                <marker id="ib-arrow" viewBox="0 0 10 10" refX="16" refY="5" markerWidth="5" markerHeight="5" orient="auto-start-reverse">
                    <path d="M0,0 L10,5 L0,10 z" class="ib-arrow"/>
                </marker>
            </defs>
            <g class="ib-edges"><?php echo $edges; ?></g>
            <g class="ib-nodes"><?php echo $nodes; ?></g>
        </svg>
    </div>
    <ul class="ib-legend">
        <li><span class="ib-key ib-n-current"></span> You</li>
        <li><span class="ib-key ib-n-fed"></span> Imperial Core</li>
        <li><span class="ib-key ib-n-port"></span> Port</li>
        <li><span class="ib-key ib-n-special"></span> Armory / Drydock</li>
        <li><span class="ib-key ib-ring-mine"></span> Your planet</li>
        <li><span class="ib-key ib-ring-other"></span> Other planet</li>
        <li><span class="ib-key ib-n-charted"></span> Charted, not visited</li>
        <li><span class="ib-key ib-n-unexplored"></span> Uncharted</li>
        <li><span class="ib-key-line"></span> One-way lane</li>
    </ul>
</div>
