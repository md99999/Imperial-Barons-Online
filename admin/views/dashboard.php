<?php
if (!defined('ABSPATH')) exit;
global $wpdb;
$count = function ($table, $where = '') use ($wpdb) {
    return (int) $wpdb->get_var('SELECT COUNT(*) FROM ' . IB_DB::t($table) . ($where ? ' WHERE ' . $where : ''));
};
$universe = get_option('ib_universe');
$ids = get_option('ib_page_ids', []);
$locations = get_registered_nav_menus();
?>
<div class="ib-cards">
    <div class="ib-card"><strong><?php echo IB_Game::fmt($count('sectors')); ?></strong>Sectors</div>
    <div class="ib-card"><strong><?php echo IB_Game::fmt($count('ports')); ?></strong>Ports</div>
    <div class="ib-card"><strong><?php echo IB_Game::fmt($count('planets')); ?></strong>Planets</div>
    <div class="ib-card"><strong><?php echo IB_Game::fmt($count('players')); ?></strong>Pilots</div>
    <div class="ib-card"><strong><?php echo IB_Game::fmt($count('players', "last_seen >= '" . esc_sql(date('Y-m-d H:i:s', current_time('timestamp') - DAY_IN_SECONDS)) . "'")); ?></strong>Active today</div>
    <div class="ib-card"><strong><?php echo IB_Game::fmt($count('teams')); ?></strong>Teams</div>
    <div class="ib-card"><strong><?php echo IB_Game::fmt($count('fleets', 'owner_player_id = 0')); ?></strong>Alien fleets</div>
</div>

<?php if (!$universe) : ?>
    <div class="ib-danger"><p><strong>No universe exists yet.</strong> Players cannot start until you forge the universe.</p>
        <p><a class="button button-primary" href="<?php echo esc_url(admin_url('admin.php?page=ib_universe')); ?>">Go to Universe Management</a></p></div>
<?php else : ?>
    <p>Universe generated <?php echo esc_html($universe['generated_at']); ?> &middot; Drydock in sector <?php echo (int) $universe['drydock']; ?> &middot; seed <?php echo (int) $universe['seed']; ?></p>
<?php endif; ?>

<?php $ib_issues = IB_Health::issues(); ?>
<div class="ib-box" style="max-width:860px<?php echo $ib_issues ? ';border-left:4px solid #d63638' : ''; ?>">
    <h2 style="margin-top:0">Install health</h2>
    <p class="description">How this copy of the plugin was installed, and whether that will cause trouble later.
        Installed from <code><?php echo esc_html(IB_Health::folder()); ?></code>.</p>
    <?php if (!$ib_issues) : ?>
        <p class="ib-ok"><strong>Nothing to report.</strong> The plugin is in the folder updates expect, there is only
            one copy of it, and no repository metadata or development leftovers are sitting in your web root.</p>
    <?php else : foreach ($ib_issues as $issue) : ?>
        <h3 class="<?php echo $issue['level'] === 'error' ? 'ib-error' : 'ib-warning'; ?>" style="margin-bottom:4px">
            <?php echo esc_html($issue['title']); ?>
        </h3>
        <?php echo $issue['body']; ?>
    <?php endforeach; endif; ?>
    <p class="description">Building an installable zip from the repository is covered in the plugin's
        <code>README.md</code>, under <em>Building a release zip</em>.</p>
</div>

<div class="ib-box" style="border-left:4px solid #996800;max-width:860px">
    <h2 style="margin-top:0">Disclaimer</h2>
    <p><strong>You run this plugin at your own risk. The author accepts no responsibility or liability for any loss,
        damage or compromise arising from its use.</strong> Every effort has been made to write it safely, but new
        vulnerabilities appear in software of every kind every day and no website can be guaranteed secure. It is
        provided as is, without warranty of any kind, under the GNU General Public License v2.</p>
    <p class="description">Test on a staging site first, keep backups of your database and files, keep WordPress, PHP,
        your theme and plugins up to date, and serve the site over HTTPS. The Universe Forge and Reset tools erase game
        data permanently and cannot be undone. Full details are in the plugin's <code>README.md</code>.
        Found a security problem? Please report it privately to
        <a href="mailto:sysop@maddogproductions.online">sysop@maddogproductions.online</a>.</p>
</div>

<div class="ib-box">
    <h2>Game pages &amp; navigation menu</h2>
    <p>Creates any missing game pages, each containing its shortcode, and an <strong>Imperial Barons Online</strong> navigation menu with a single <em>Imperial Barons Online Home</em> link. Running it again repairs the menu.
        Players move between the game pages with the in-game navigation bar shown on every game page.</p>
    <table class="widefat striped" style="max-width:760px">
        <thead><tr><th>Page</th><th>Slug</th><th>Shortcode</th><th>Status</th></tr></thead>
        <tbody>
        <?php foreach (IB_UI::PAGES as $key => $def) :
            $page = !empty($ids[$key]) ? get_post($ids[$key]) : get_page_by_path($def[1]); ?>
            <tr>
                <td><?php echo esc_html($def[0]); ?></td>
                <td><code><?php echo esc_html($def[1]); ?></code></td>
                <td><code>[<?php echo esc_html($def[2]); ?>]</code></td>
                <td><?php if ($page && $page->post_status === 'publish') : ?>
                        <a href="<?php echo esc_url(get_permalink($page)); ?>" target="_blank">View</a>
                    <?php else : ?><span class="ib-warning">Missing</span><?php endif; ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <?php echo IB_Admin::form_open('setup_pages'); ?>
        <?php $block_theme = function_exists('wp_is_block_theme') && wp_is_block_theme(); ?>
        <p>
            <label for="ib-menu-location">Assign menu to theme location:</label>
            <select id="ib-menu-location" name="menu_location">
                <option value="">Don't assign</option>
                <?php if ($block_theme) : ?>
                    <option value="block_header">Theme header (block navigation menu)</option>
                <?php endif; ?>
                <?php foreach ($locations as $slug => $label) : ?>
                    <option value="<?php echo esc_attr($slug); ?>"><?php echo esc_html($label); ?></option>
                <?php endforeach; ?>
            </select>
        </p>
        <p class="description" style="max-width:760px">
            The menu holds a single link to the game's home page; players use the in-game navigation bar for the rest.
            Leave this on <em>Don't assign</em> to place the link yourself.
            <?php if ($block_theme) : ?>
                Your theme is a block theme, so its header uses a Navigation block rather than a classic menu location:
                choose <em>Theme header</em> to create an "Imperial Barons Online" block navigation menu for it.
            <?php elseif (!$locations) : ?>
                Your theme registers no menu locations, so the link must be placed manually.
            <?php endif; ?>
        </p>
        <?php submit_button('Create pages & menu', 'primary', 'submit', false); ?>
    </form>
</div>
