<?php
if (!defined('ABSPATH')) exit;

/**
 * wp-admin screens: dashboard, universe management (Universe Forge), settings,
 * players, teams, ports, planets, maintenance and logs.
 */
class IB_Admin {
    const CAP = 'manage_options';

    const SCREENS = [
        'ib_dashboard'   => ['Dashboard', 'dashboard'],
        'ib_universe'    => ['Universe Management', 'universe'],
        'ib_settings'    => ['Settings', 'settings'],
        'ib_players'     => ['Players', 'players'],
        'ib_teams'       => ['Teams', 'teams'],
        'ib_ports'       => ['Ports', 'ports'],
        'ib_planets'     => ['Planets', 'planets'],
        'ib_maintenance' => ['Maintenance', 'maintenance'],
        'ib_logs'        => ['Logs', 'logs'],
    ];

    const POST_ACTIONS = ['setup_pages', 'forge', 'reset_universe', 'export', 'save_settings', 'player_update',
                          'player_delete', 'team_disband', 'ports_restock', 'run_maintenance'];

    public static function init() {
        add_action('admin_menu', [__CLASS__, 'menu']);
        add_action('admin_enqueue_scripts', [__CLASS__, 'assets']);
        foreach (self::POST_ACTIONS as $action) {
            add_action('admin_post_ib_' . $action, function () use ($action) {
                IB_Admin::handle($action);
            });
        }
    }

    public static function menu() {
        add_menu_page('Imperial Barons Online', 'Imperial Barons Online', self::CAP, 'ib_dashboard', [__CLASS__, 'render'], 'dashicons-star-filled', 58);
        foreach (self::SCREENS as $slug => $def) {
            add_submenu_page('ib_dashboard', 'Imperial Barons Online ' . $def[0], $def[0], self::CAP, $slug, [__CLASS__, 'render']);
        }
    }

    public static function assets($hook) {
        if (strpos($hook, 'ib_') === false) return;
        wp_add_inline_style('common', '
            .ib-admin .ib-cards{display:grid;grid-template-columns:repeat(auto-fill,minmax(160px,1fr));gap:12px;margin:16px 0}
            .ib-admin .ib-card{background:#fff;border:1px solid #c3c4c7;padding:12px 16px}
            .ib-admin .ib-card strong{display:block;font-size:24px;line-height:1.3}
            .ib-admin .ib-danger{border-left:4px solid #d63638;background:#fff;padding:12px 16px;margin:16px 0}
            .ib-admin .ib-ok{color:#008a20}.ib-admin .ib-warning{color:#996800}.ib-admin .ib-error{color:#d63638}
            .ib-admin .ib-box{background:#fff;border:1px solid #c3c4c7;padding:12px 16px;margin:16px 0}
            .ib-admin input.small-text{width:90px}
        ');
    }

    public static function render() {
        if (!current_user_can(self::CAP)) wp_die('Not allowed.');
        $page = isset($_GET['page']) ? sanitize_key($_GET['page']) : 'ib_dashboard';
        $view = isset(self::SCREENS[$page]) ? self::SCREENS[$page][1] : 'dashboard';
        echo '<div class="wrap ib-admin"><h1>Imperial Barons Online &mdash; ' . esc_html(self::SCREENS[$page][0] ?? 'Dashboard') . '</h1>';
        self::render_notices();
        include IB_PATH . 'admin/views/' . $view . '.php';
        echo '</div>';
    }

    /** Opens an admin-post form for $action with nonce. */
    public static function form_open($action, $attrs = '') {
        return '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" ' . $attrs . '>'
            . '<input type="hidden" name="action" value="ib_' . esc_attr($action) . '">'
            . wp_nonce_field('ib_admin_' . $action, '_ibnonce', true, false);
    }

    public static function notice($type, $message) {
        $key = 'ib_admin_notice_' . get_current_user_id();
        $list = get_transient($key) ?: [];
        $list[] = [$type, $message];
        set_transient($key, $list, 5 * MINUTE_IN_SECONDS);
    }

    private static function render_notices() {
        $key = 'ib_admin_notice_' . get_current_user_id();
        $list = get_transient($key);
        if (!$list) return;
        delete_transient($key);
        foreach ($list as $n) {
            echo '<div class="notice notice-' . esc_attr($n[0]) . ' is-dismissible"><p>' . esc_html($n[1]) . '</p></div>';
        }
    }

    public static function handle($action) {
        if (!current_user_can(self::CAP)) wp_die('Not allowed.', 403);
        check_admin_referer('ib_admin_' . $action, '_ibnonce');
        $back = wp_get_referer() ?: admin_url('admin.php?page=ib_dashboard');
        try {
            $method = 'do_' . $action;
            self::$method();
        } catch (IB_Game_Exception $e) {
            self::notice('error', $e->getMessage());
        }
        wp_safe_redirect($back);
        exit;
    }

    private static function post($name, $default = '') {
        return isset($_POST[$name]) ? wp_unslash($_POST[$name]) : $default;
    }

    // ----- Handlers -----

    private static function do_setup_pages() {
        $ids = get_option('ib_page_ids', []);
        $created = 0;
        $renamed = 0;
        foreach (IB_UI::PAGES as $key => $def) {
            list($title, $slug, $shortcode) = $def;
            $existing = get_page_by_path($slug);
            if ($existing && $existing->post_status !== 'trash') {
                $ids[$key] = $existing->ID;
                // Bring pages from earlier versions up to the current "IBO - ..." titles so the
                // game's pages are easy to pick out in the Pages list. Slugs are left alone.
                if ($existing->post_title !== $title) {
                    wp_update_post(['ID' => $existing->ID, 'post_title' => $title]);
                    $renamed++;
                }
                continue;
            }
            $ids[$key] = wp_insert_post([
                'post_title' => $title, 'post_name' => $slug, 'post_content' => '[' . $shortcode . ']',
                'post_status' => 'publish', 'post_type' => 'page', 'comment_status' => 'closed', 'ping_status' => 'closed',
            ]);
            $created++;
        }
        update_option('ib_page_ids', $ids);

        // The site menu is a single link to the home page; the in-game navigation bar on every
        // game page covers the rest. Any other items in this menu (e.g. from an earlier version
        // that listed every page) are removed.
        $menu = wp_get_nav_menu_object('Imperial Barons Online');
        $menu_id = $menu ? $menu->term_id : wp_create_nav_menu('Imperial Barons Online');
        if (is_wp_error($menu_id)) throw new IB_Game_Exception('Could not create the menu: ' . $menu_id->get_error_message());
        $home_item = 0;
        foreach ((array) wp_get_nav_menu_items($menu_id) as $item) {
            if (!$item) continue;
            if (!$home_item && $item->object === 'page' && (int) $item->object_id === (int) $ids['dashboard']) {
                $home_item = (int) $item->db_id;
            } else {
                wp_delete_post($item->db_id, true);
            }
        }
        $item_id = wp_update_nav_menu_item($menu_id, $home_item, [
            'menu-item-title' => IB_Settings::get('game_name'), 'menu-item-object' => 'page',
            'menu-item-object-id' => $ids['dashboard'], 'menu-item-type' => 'post_type',
            'menu-item-status' => 'publish', 'menu-item-parent-id' => 0, 'menu-item-position' => 1,
        ]);
        if (is_wp_error($item_id)) throw new IB_Game_Exception('Could not add the menu item: ' . $item_id->get_error_message());

        $msg = sprintf('%d page(s) created, %d renamed to the "IBO - ..." titles; the "Imperial Barons Online" menu (a single home page link) is ready.', $created, $renamed);

        // Where the link should appear is the admin's choice: nowhere (the default), a block
        // theme's header, or one of the theme's classic menu locations.
        $location = sanitize_key(self::post('menu_location'));
        if ($location === 'block_header') {
            self::build_block_navigation($ids);
            $msg .= ' A block navigation menu was created for your theme\'s header.'
                . ' If the header still shows a different menu, choose "Imperial Barons Online" in its Navigation block in the Site Editor.';
        } elseif ($location) {
            $locations = get_theme_mod('nav_menu_locations', []);
            $locations[$location] = $menu_id;
            set_theme_mod('nav_menu_locations', $locations);
            $msg .= ' It has been assigned to the "' . $location . '" menu location.';
        } else {
            $msg .= ' It was not assigned to a theme location; add it wherever you like under Appearance.';
        }
        IB_Log::admin('setup', $msg);
        self::notice('success', $msg);
    }

    /**
     * Block themes ignore classic menus: their header Navigation block shows a wp_navigation
     * post, and when none is chosen explicitly it falls back to the most recently published one.
     * This creates (or refreshes) such a post holding a single link to the home page.
     */
    private static function build_block_navigation(array $ids) {
        $content = get_comment_delimited_block_content('core/navigation-link', [
            'label' => IB_Settings::get('game_name'), 'type' => 'page', 'id' => (int) $ids['dashboard'],
            'url' => get_permalink($ids['dashboard']), 'kind' => 'post-type',
        ], '');

        $post_id = (int) get_option('ib_nav_post_id');
        $post = [
            'post_type' => 'wp_navigation', 'post_status' => 'publish',
            'post_title' => 'Imperial Barons Online', 'post_content' => wp_slash($content),
        ];
        if ($post_id && get_post_type($post_id) === 'wp_navigation') {
            $post['ID'] = $post_id;
            $result = wp_update_post($post, true);
        } else {
            $result = wp_insert_post($post, true);
        }
        if (is_wp_error($result)) throw new IB_Game_Exception('Could not create the block navigation menu: ' . $result->get_error_message());
        update_option('ib_nav_post_id', (int) $result, false);
    }

    private static function do_forge() {
        if (!self::post('confirm_warning') || trim(self::post('confirm_text')) !== 'FORGE') {
            throw new IB_Game_Exception('Universe Forge cancelled: tick the warning box and type FORGE to confirm.');
        }
        $gen = new IB_Universe_Forge();
        $summary = $gen->generate([
            'sectors' => (int) self::post('sectors', 1000),
            'port_density' => (int) self::post('port_density', 40),
            'planet_density' => (int) self::post('planet_density', 5),
            'faction_strength' => (int) self::post('faction_strength', 100),
            'oneway_percent' => (float) self::post('oneway_percent', 4),
            'seed' => (int) self::post('seed', 0),
        ]);
        self::notice('success', sprintf(
            'The universe is born: %s sectors, %s warps (%d one-way), %d ports, %d planets, %d alien fleets. Drydock in sector %d. Took %ss.',
            IB_Game::fmt($summary['sectors']), IB_Game::fmt($summary['warps']), $summary['one_way'], $summary['ports'],
            $summary['planets'], $summary['fleets'], $summary['drydock'], $summary['seconds']
        ));
    }

    private static function do_reset_universe() {
        if (!self::post('confirm_warning') || trim(self::post('confirm_text')) !== 'RESET') {
            throw new IB_Game_Exception('Reset cancelled: tick the warning box and type RESET to confirm.');
        }
        IB_Universe_Forge::wipe();
        IB_Log::admin('reset', 'Universe and all player data erased.');
        self::notice('success', 'The universe has been erased. Forge the universe to start a new game.');
    }

    private static function do_export() {
        IB_Log::admin('export', 'Universe exported.');
        nocache_headers();
        header('Content-Type: application/json; charset=utf-8');
        header('Content-Disposition: attachment; filename="imperial-barons-online-universe-' . gmdate('Ymd-His') . '.json"');
        echo wp_json_encode(IB_Universe_Forge::export());
        exit;
    }

    private static function do_save_settings() {
        IB_Settings::update((array) self::post('ib', []));
        IB_Log::admin('settings', 'Settings updated.');
        self::notice('success', 'Settings saved.');
    }

    private static function do_player_update() {
        global $wpdb;
        $id = (int) self::post('player_id');
        $sector = (int) self::post('sector_id');
        if (!$wpdb->get_var($wpdb->prepare('SELECT id FROM ' . IB_DB::t('sectors') . ' WHERE id = %d', $sector))) {
            throw new IB_Game_Exception('That sector does not exist.');
        }
        $wpdb->update(IB_DB::t('players'), [
            'turns_remaining' => max(0, (int) self::post('turns_remaining')),
            'credits' => max(0, (int) self::post('credits')),
            'fighters' => max(0, (int) self::post('fighters')),
            'sector_id' => $sector, 'docked_port_id' => 0, 'landed_planet_id' => 0,
        ], ['id' => $id]);
        IB_Log::admin('player', sprintf('Edited player #%d (%s).', $id, IB_Player::name($id)));
        self::notice('success', 'Player updated.');
    }

    private static function do_player_delete() {
        global $wpdb;
        $p = IB_Player::get((int) self::post('player_id'));
        if (!$p) throw new IB_Game_Exception('Player not found.');
        if ((int) $p->team_id) IB_Teams::leave($p);
        $wpdb->update(IB_DB::t('planets'), ['owner_player_id' => 0, 'team_id' => 0], ['owner_player_id' => $p->id]);
        $wpdb->delete(IB_DB::t('fleets'), ['owner_player_id' => $p->id]);
        $wpdb->delete(IB_DB::t('explored'), ['player_id' => $p->id]);
        $wpdb->delete(IB_DB::t('messages'), ['recipient_player_id' => $p->id]);
        $wpdb->delete(IB_DB::t('players'), ['id' => $p->id]);
        IB_Log::admin('player', sprintf('Deleted player %s (#%d).', $p->alias_name, $p->id));
        self::notice('success', sprintf('Deleted %s. Their planets are now unclaimed.', $p->alias_name));
    }

    private static function do_team_disband() {
        $team = IB_Teams::get((int) self::post('team_id'));
        if (!$team) throw new IB_Game_Exception('Team not found.');
        IB_Teams::delete($team->id);
        IB_Log::admin('team', sprintf('Disbanded team %s.', $team->team_name));
        self::notice('success', 'Team disbanded.');
    }

    private static function do_ports_restock() {
        global $wpdb;
        $wpdb->query('UPDATE ' . IB_DB::t('ports') . ' SET ore_qty = ore_max, org_qty = org_max, equ_qty = equ_max');
        IB_Log::admin('ports', 'All ports fully restocked.');
        self::notice('success', 'All ports restocked to capacity.');
    }

    private static function do_run_maintenance() {
        $which = self::post('which') === 'daily' ? 'daily' : 'hourly';
        $result = $which === 'daily' ? IB_Maintenance::daily(true) : IB_Maintenance::hourly(true);
        IB_Log::admin('maintenance', 'Manual run: ' . $result);
        self::notice('success', $result);
    }
}
