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
        foreach ([self::check_duplicates(), self::check_git(), self::check_folder()] as $issue) {
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
