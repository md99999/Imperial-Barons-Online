<?php
if (!defined('ABSPATH')) exit;

/**
 * Install health: what to warn an administrator about when the plugin was not installed
 * from a release zip.
 *
 * Plenty of people will take GitHub's "Download ZIP", or copy a working clone, and upload
 * that. It runs, but it brings two problems that are invisible until they bite: the folder is
 * named after the branch, so the next proper install becomes a second copy of the plugin; and a
 * clone carries a .git directory that some servers will happily serve. Documentation only helps
 * the people who read it, so the plugin checks itself and says so in wp-admin.
 */
class IB_Health {
    /** The folder name a release zip unpacks to, and the one updates will use. */
    const SLUG = 'imperial-barons-online';

    /**
     * @return array list of ['level' => 'error'|'warning', 'title' => string, 'body' => string (HTML)]
     */
    public static function issues() {
        $out = [];
        foreach ([self::check_duplicates(), self::check_git(), self::check_unexpected(), self::check_folder()] as $issue) {
            if ($issue) $out[] = $issue;
        }
        return $out;
    }

    /** The folder this copy lives in, e.g. "Imperial-Barons-Online-main". */
    public static function folder() {
        return basename(untrailingslashit(IB_PATH));
    }

    /**
     * A second copy of the plugin in wp-content/plugins. Both copies share one set of tables and
     * one set of scheduled jobs, and WordPress will happily update or deactivate the wrong one.
     */
    private static function check_duplicates() {
        $others = self::other_copies();
        if (!$others) return null;
        $list = '<code>' . implode('</code>, <code>', array_map('esc_html', $others)) . '</code>';
        return [
            'level' => 'error',
            'title' => 'There is more than one copy of this plugin installed',
            'body'  => '<p>This copy is running from <code>' . esc_html(self::folder()) . '</code>, and these other copies'
                . ' are also in <code>wp-content/plugins</code>: ' . $list . '.</p>'
                . '<p>Every copy shares the same database tables and the same scheduled jobs, and WordPress treats them'
                . ' as separate plugins, so an update or a deactivation can easily land on the wrong one. Keep the copy'
                . ' in <code>' . esc_html(self::SLUG) . '</code>, and delete the others from the'
                . ' <a href="' . esc_url(admin_url('plugins.php')) . '">Plugins</a> screen. Deleting a plugin copy does'
                . ' not touch the game data: the tables belong to the site, not to the folder.</p>',
        ];
    }

    /** A .git directory inside the plugin means the whole project history is sitting in the web root. */
    private static function check_git() {
        if (!is_dir(IB_PATH . '.git')) return null;
        $reachable = self::git_reachable();
        $htaccess = file_exists(IB_PATH . '.htaccess');
        $body = '<p>This copy was installed from a git clone, so <code>' . esc_html(self::folder())
            . '/.git</code> sits inside <code>wp-content/plugins</code>. That directory holds the project\'s entire'
            . ' history, and on many servers it can be read by anyone who knows the path.</p>';
        if ($reachable === true) {
            $body .= '<p><strong>It is readable over the web on this site right now.</strong> The plugin ships an'
                . ' <code>.htaccess</code> that blocks it, but your server is not applying it'
                . ($htaccess ? ' (nginx does not read <code>.htaccess</code> at all).' : ', and the file is missing from this copy.')
                . '</p>';
        } elseif ($reachable === false) {
            $body .= '<p>A request for it from outside was refused, so your server is not serving it today. That can'
                . ' change with a server or host configuration change, which is why it is worth removing anyway.</p>';
        } else {
            $body .= '<p>Whether your server serves it could not be checked from here.</p>';
        }
        $body .= '<p>The fix is to not keep <code>.git</code> on the server: install the release zip, or build one with'
            . ' <code>git archive</code> or <code>php tools/build-zip.php</code> as the README describes, and upload'
            . ' that. Deleting the <code>.git</code> directory by hand works too, and leaves the game untouched.</p>';
        return [
            'level' => $reachable === true ? 'error' : 'warning',
            'title' => $reachable === true ? 'The repository history is exposed on this site' : 'This copy contains a .git directory',
            'body'  => $body,
        ];
    }

    /**
     * Anything in the plugin folder that this version does not ship: leftovers from a repository
     * zipped by hand, files from an older version that the new one no longer has, a backup someone
     * made in place, or something that has no business being there at all.
     *
     * It works from a manifest of what a release contains rather than a list of known rubbish, so
     * it notices things nobody thought to look for. .git is left to check_git(), which has more to
     * say about it.
     */
    private static function check_unexpected() {
        $found = self::unexpected_entries();
        if (!$found) return null;
        $shown = array_slice($found, 0, 20);
        $more = count($found) - count($shown);
        $list = '<code>' . implode('</code>, <code>', array_map('esc_html', $shown)) . '</code>'
            . ($more > 0 ? sprintf(' and %d more', $more) : '');
        return [
            'level' => 'warning',
            'title' => count($found) === 1 ? 'A file that is not part of this version is installed'
                                           : 'Files that are not part of this version are installed',
            'body'  => '<p>These are inside <code>' . esc_html(self::folder()) . '</code> but are no part of '
                . esc_html(IB_GAME_NAME) . ' ' . esc_html(IB_VERSION) . ': ' . $list . '.</p>'
                . '<p>Usually that means the repository was zipped by hand instead of built, or an older version'
                . ' was copied over rather than replaced. It can also mean something was put there that should'
                . ' not be, which is worth a look either way. Nothing here is loaded by the game, and removing'
                . ' them over FTP or your host\'s file manager does not affect it. A zip built the way the'
                . ' README describes contains the manifest and nothing else.</p>',
        ];
    }

    /**
     * Paths in the plugin folder that are not in MANIFEST, as relative paths with a trailing
     * slash on directories. An unexpected directory is reported once rather than walked, so one
     * stray node_modules does not produce a thousand lines. The answer is cached for an hour,
     * against this version, because the notice runs on every admin page load.
     *
     * @param bool $fresh skip the cache
     */
    public static function unexpected_entries($fresh = false) {
        $key = 'ib_unexpected_files';
        if (!$fresh) {
            $cached = get_transient($key);
            if (is_array($cached) && ($cached['version'] ?? '') === IB_VERSION) return $cached['found'];
        }
        $expected = array_flip(self::MANIFEST);
        $found = [];
        self::scan_unexpected(untrailingslashit(IB_PATH), '', $expected, $found, 0);
        sort($found);
        set_transient($key, ['version' => IB_VERSION, 'found' => $found], HOUR_IN_SECONDS);
        return $found;
    }

    private static function scan_unexpected($dir, $prefix, $expected, &$found, $depth) {
        if ($depth > 8 || count($found) > 200) return;
        $entries = @scandir($dir);
        if ($entries === false) return;
        foreach ($entries as $entry) {
            if ($entry === '.' || $entry === '..') continue;
            if ($prefix === '' && $entry === '.git') continue;      // check_git() reports that one
            $path = $dir . '/' . $entry;
            $rel = $prefix . $entry;
            if (is_dir($path)) {
                if (!isset($expected[$rel . '/'])) { $found[] = $rel . '/'; continue; }
                self::scan_unexpected($path, $rel . '/', $expected, $found, $depth + 1);
            } elseif (!isset($expected[$rel])) {
                $found[] = $rel;
            }
        }
    }

    /**
     * Every path a release of this plugin contains, directories included. Generated from the
     * built zip; tools/build-zip.php compares what it packs against this list and refuses to
     * build if the two have drifted, so adding a file to the plugin means adding it here.
     */
    const MANIFEST = [
        '.htaccess', 'LICENSE', 'README.md', 'SECURITY.md', 'admin/', 'admin/class-ib-admin.php',
        'admin/index.php', 'admin/views/', 'admin/views/dashboard.php', 'admin/views/index.php',
        'admin/views/logs.php', 'admin/views/maintenance.php', 'admin/views/planets.php',
        'admin/views/players.php', 'admin/views/ports.php', 'admin/views/settings.php',
        'admin/views/teams.php', 'admin/views/universe.php', 'assets/', 'assets/css/',
        'assets/css/imperial-barons-online.css', 'assets/css/index.php', 'assets/index.php', 'assets/js/',
        'assets/js/imperial-barons-online.js', 'assets/js/index.php', 'docs/', 'docs/ADMIN-MENU.md',
        'docs/DATABASE-TABLES.md', 'docs/UNIVERSE_FORGE.md', 'docs/WORDPRESS-PAGES.md', 'docs/index.php', 'docs/setup-bbs-on-wordpress.md',
        'imperial-barons-online.php', 'includes/', 'includes/class-ib-core.php',
        'includes/class-ib-health.php', 'includes/class-ib-installer.php', 'includes/data/',
        'includes/data/class-ib-ships.php', 'includes/data/index.php', 'includes/frontend/',
        'includes/frontend/class-ib-actions.php', 'includes/frontend/class-ib-shortcodes.php',
        'includes/frontend/class-ib-ui.php', 'includes/frontend/index.php', 'includes/frontend/views/',
        'includes/frontend/views/_computer-panel.php', 'includes/frontend/views/_how-to-play.php',
        'includes/frontend/views/_nearby-ports.php', 'includes/frontend/views/_next-run.php',
        'includes/frontend/views/_welcome.php', 'includes/frontend/views/computer.php',
        'includes/frontend/views/dashboard.php', 'includes/frontend/views/gazette.php',
        'includes/frontend/views/howto.php', 'includes/frontend/views/index.php',
        'includes/frontend/views/map.php', 'includes/frontend/views/messages.php',
        'includes/frontend/views/planet.php', 'includes/frontend/views/port.php',
        'includes/frontend/views/rankings.php', 'includes/frontend/views/sector.php',
        'includes/frontend/views/ship.php', 'includes/frontend/views/team.php', 'includes/importers/',
        'includes/importers/class-legacy-importer.php', 'includes/importers/index.php',
        'includes/index.php', 'includes/services/', 'includes/services/class-combat-service.php',
        'includes/services/class-discovery-service.php', 'includes/services/class-factions.php',
        'includes/services/class-maintenance-service.php', 'includes/services/class-message-service.php',
        'includes/services/class-pathfinder.php', 'includes/services/class-planet-service.php',
        'includes/services/class-player-service.php', 'includes/services/class-port-service.php',
        'includes/services/class-team-service.php', 'includes/services/class-universe-forge.php',
        'includes/services/index.php', 'index.php', 'maintenance/', 'maintenance/README.md',
        'maintenance/bootstrap.php', 'maintenance/daily_maintenance.php',
        'maintenance/hourly_maintenance.php', 'maintenance/index.php', 'sql/',
        'sql/index.php', 'sql/install-schema.php', 'uninstall.php',
    ];

    /** Installed under a branch-named folder, which makes the next proper install a second copy. */
    private static function check_folder() {
        if (self::folder() === self::SLUG) return null;
        return [
            'level' => 'warning',
            'title' => 'The plugin folder is not named ' . self::SLUG,
            'body'  => '<p>This copy is installed as <code>wp-content/plugins/' . esc_html(self::folder()) . '</code>,'
                . ' which is what GitHub\'s <em>Download ZIP</em> produces: it names the folder after the branch.</p>'
                . '<p>The game runs perfectly well like this, but WordPress identifies a plugin by its folder, so'
                . ' installing a release zip later adds a <em>second</em> copy rather than updating this one, and you'
                . ' end up with two plugins sharing one set of tables. Rename the folder to <code>'
                . esc_html(self::SLUG) . '</code> and activate it again: deactivate the plugin first, rename the folder'
                . ' over FTP, SFTP or your host\'s file manager, then activate <strong>' . esc_html(IB_GAME_NAME)
                . '</strong> on the Plugins screen. Your game data is in the database and is not affected.</p>',
        ];
    }

    /** Other directories in wp-content/plugins holding this plugin's main file. */
    private static function other_copies() {
        $dir = defined('WP_PLUGIN_DIR') ? WP_PLUGIN_DIR : WP_CONTENT_DIR . '/plugins';
        $here = self::folder();
        $found = [];
        $entries = @scandir($dir);
        if (!$entries) return $found;
        foreach ($entries as $entry) {
            if ($entry === '.' || $entry === '..' || $entry === $here) continue;
            if (!is_dir($dir . '/' . $entry)) continue;
            if (file_exists($dir . '/' . $entry . '/' . self::SLUG . '.php')) $found[] = $entry;
        }
        return $found;
    }

    /**
     * Asks this site, over HTTP, whether it will serve the clone's .git/HEAD.
     * Cached for a day: it is one request, but there is no reason to repeat it on every page.
     *
     * @return bool|null true served, false refused, null could not tell
     */
    public static function git_reachable($fresh = false) {
        $key = 'ib_git_reachable';
        if (!$fresh) {
            $cached = get_transient($key);
            if ($cached !== false) return $cached === 'yes' ? true : ($cached === 'no' ? false : null);
        }
        $url = plugins_url('.git/HEAD', IB_PATH . self::SLUG . '.php');
        // Follow redirects, or a site that sends http to https would look safe when it is not.
        $response = wp_remote_get($url, ['timeout' => 5, 'redirection' => 3, 'sslverify' => false]);
        if (is_wp_error($response)) {
            $result = null;
        } else {
            $code = (int) wp_remote_retrieve_response_code($response);
            if ($code === 200 && strpos((string) wp_remote_retrieve_body($response), 'ref:') === 0) {
                $result = true;                                  // served: the file came back
            } elseif (in_array($code, [401, 403, 404, 410, 451], true)) {
                $result = false;                                 // refused outright
            } else {
                $result = null;                                  // something else answered; do not guess
            }
        }
        set_transient($key, $result === true ? 'yes' : ($result === false ? 'no' : 'unknown'), DAY_IN_SECONDS);
        return $result;
    }

    /** The admin notice, on the Plugins screen and the game's own screens. */
    public static function notice() {
        if (!current_user_can('manage_options')) return;
        $screen = function_exists('get_current_screen') ? get_current_screen() : null;
        $id = $screen ? $screen->id : '';
        if ($id !== 'plugins' && strpos($id, 'ib_') === false) return;
        foreach (self::issues() as $issue) {
            printf('<div class="notice notice-%s"><p><strong>%s &mdash; %s</strong></p>%s</div>',
                $issue['level'] === 'error' ? 'error' : 'warning',
                esc_html(IB_GAME_NAME), esc_html($issue['title']), $issue['body']);
        }
    }
}
