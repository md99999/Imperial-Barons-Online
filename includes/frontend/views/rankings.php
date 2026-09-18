<?php
/** @var object $p */
if (!defined('ABSPATH')) exit;
global $wpdb;
$tabs = ['networth' => 'Net Worth', 'experience' => 'Experience', 'kills' => 'Combat', 'teams' => 'Teams'];
$by = isset($_GET['by']) && is_string($_GET['by']) ? sanitize_key($_GET['by']) : '';
if (!isset($tabs[$by])) $by = 'networth';
?>
<div class="ib-panel">
    <h2>Rankings</h2>
    <nav class="ib-tabs" aria-label="Ranking type">
        <?php foreach ($tabs as $key => $label) : ?>
            <a href="<?php echo esc_url(IB_UI::url('rankings', ['by' => $key])); ?>"<?php echo $key === $by ? ' class="ib-active" aria-current="page"' : ''; ?>><?php echo esc_html($label); ?></a>
        <?php endforeach; ?>
    </nav>

<?php if ($by === 'teams') :
    $teams = IB_Teams::all();
    foreach ($teams as $t) $t->worth = IB_Teams::net_worth($t->id);
    usort($teams, function ($a, $b) { return $b->worth <=> $a->worth; }); ?>
    <?php if (!$teams) : ?><p class="ib-dim">No teams have been formed yet.</p><?php else : ?>
    <table class="ib-table">
        <thead><tr><th>#</th><th>Team</th><th>Members</th><th>Combat medals</th><th>Net worth</th></tr></thead>
        <tbody>
        <?php foreach ($teams as $i => $t) : ?>
            <tr<?php echo (int) $t->id === (int) $p->team_id ? ' class="ib-current"' : ''; ?>>
                <td><?php echo $i + 1; ?></td>
                <td><?php echo esc_html($t->team_name); ?></td>
                <td><?php echo (int) $t->members; ?></td>
                <td><?php echo (int) $t->combat_medals; ?></td>
                <td><?php echo IB_Game::fmt($t->worth); ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>

<?php else :
    $players = $wpdb->get_results('SELECT * FROM ' . IB_DB::t('players'));
    foreach ($players as $pl) $pl->worth = IB_Player::net_worth($pl);
    $sorters = [
        'networth' => function ($a, $b) { return $b->worth <=> $a->worth; },
        'experience' => function ($a, $b) { return $b->experience <=> $a->experience; },
        'kills' => function ($a, $b) { return [$b->kills, $a->deaths] <=> [$a->kills, $b->deaths]; },
    ];
    usort($players, $sorters[$by]);
    $teams = [];
    foreach (IB_Teams::all() as $t) $teams[(int) $t->id] = $t->team_name; ?>
    <div class="ib-table-wrap">
    <table class="ib-table">
        <thead><tr><th>#</th><th>Pilot</th><th>Team</th><th>Ship</th><th>Experience</th><th>Alignment</th><th>Kills</th><th>Net worth</th></tr></thead>
        <tbody>
        <?php foreach (array_slice($players, 0, 100) as $i => $pl) : ?>
            <tr<?php echo (int) $pl->id === (int) $p->id ? ' class="ib-current"' : ''; ?>>
                <td><?php echo $i + 1; ?></td>
                <td><?php echo esc_html(IB_Game::rank_title($pl->experience) . ' ' . $pl->alias_name); ?></td>
                <td><?php echo esc_html($teams[(int) $pl->team_id] ?? ''); ?></td>
                <td><?php echo esc_html(IB_Ships::get($pl->ship_type)['name']); ?></td>
                <td><?php echo IB_Game::fmt($pl->experience); ?></td>
                <td><?php echo esc_html(IB_Game::alignment_label($pl->alignment)); ?></td>
                <td><?php echo (int) $pl->kills; ?></td>
                <td><?php echo IB_Game::fmt($pl->worth); ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    </div>
<?php endif; ?>
</div>
