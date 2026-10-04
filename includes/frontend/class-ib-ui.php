<?php
if (!defined('ABSPATH')) exit;

/**
 * Front-end helpers: page URLs, navigation, flash messages and form scaffolding.
 */
class IB_UI {
    /**
     * key => [page title, slug, shortcode, nav label]
     * Order is the in-game navigation order, following the flow of play: move and trade,
     * then navigation, then your holdings, then the social pages.
     */
    const PAGES = [
        'dashboard' => ['Imperial Barons', 'imperial-barons-online', 'ib_dashboard', 'Home'],
        'sector'    => ['Imperial Barons - Sector', 'imperial-barons-online-sector', 'ib_sector', 'Sector'],
        'port'      => ['Imperial Barons - Port', 'imperial-barons-online-port', 'ib_port', 'Port'],
        'map'       => ['Imperial Barons - Galaxy Map', 'imperial-barons-online-map', 'ib_map', 'Map'],
        'computer'  => ['Imperial Barons - Computer', 'imperial-barons-online-computer', 'ib_computer', 'Computer'],
        'planet'    => ['Imperial Barons - Planet', 'imperial-barons-online-planet', 'ib_planet', 'Planet'],
        'ship'      => ['Imperial Barons - Ship Status', 'imperial-barons-online-ship', 'ib_ship', 'Ship'],
        'team'      => ['Imperial Barons - Team', 'imperial-barons-online-team', 'ib_team', 'Team'],
        'messages'  => ['Imperial Barons - Messages', 'imperial-barons-online-messages', 'ib_messages', 'Messages'],
        'rankings'  => ['Imperial Barons - Rankings', 'imperial-barons-online-rankings', 'ib_rankings', 'Rankings'],
        'gazette'   => ['Imperial Barons - Gazette', 'imperial-barons-online-gazette', 'ib_gazette', 'Gazette'],
        'howto'     => ['Imperial Barons - How to Play', 'imperial-barons-online-how-to-play', 'ib_howto', 'How to Play'],
    ];

    public static function url($key, $args = []) {
        static $cache = [];
        if (!isset($cache[$key])) {
            $ids = get_option('ib_page_ids', []);
            $url = '';
            if (!empty($ids[$key]) && get_post_status($ids[$key]) === 'publish') $url = get_permalink($ids[$key]);
            if (!$url && isset(self::PAGES[$key])) {
                $page = get_page_by_path(self::PAGES[$key][1]);
                $url = $page ? get_permalink($page) : home_url('/' . self::PAGES[$key][1] . '/');
            }
            $cache[$key] = $url;
        }
        return $args ? add_query_arg($args, $cache[$key]) : $cache[$key];
    }

    public static function enqueue_assets() {
        wp_register_style('imperial-barons-online', IB_URL . 'assets/css/imperial-barons-online.css', [], IB_VERSION);
        wp_register_script('imperial-barons-online', IB_URL . 'assets/js/imperial-barons-online.js', [], IB_VERSION, true);
        global $post;
        if (!is_singular() || !$post) return;
        foreach (self::PAGES as $def) {
            if (has_shortcode($post->post_content, $def[2])) {
                wp_enqueue_style('imperial-barons-online');
                wp_enqueue_script('imperial-barons-online');
                return;
            }
        }
    }

    public static function flash($type, $message) {
        $key = 'ib_flash_' . get_current_user_id();
        $list = get_transient($key);
        if (!is_array($list)) $list = [];
        $list[] = [$type, $message];
        set_transient($key, $list, 5 * MINUTE_IN_SECONDS);
    }

    public static function render_flashes() {
        $key = 'ib_flash_' . get_current_user_id();
        $list = get_transient($key);
        if (!$list) return '';
        delete_transient($key);
        $out = '';
        foreach ($list as $f) {
            $out .= '<div class="ib-flash ib-flash-' . esc_attr($f[0]) . '">' . nl2br(esc_html($f[1])) . '</div>';
        }
        return $out;
    }

    /**
     * Opens a POST form for a game action; close it with </form>.
     * The form carries the page it was submitted from, so actions that stay put return here.
     * (wp_get_referer() cannot be used for that: it returns false when the referring page is
     * the same URL as the request, which is exactly the case for these self-posting forms.)
     */
    public static function form_open($action, $class = '') {
        // sanitize_key() lowercases the action on the way back in, so a name with a capital in it
        // would never match its handler. Catch that while developing rather than in play.
        if (defined('WP_DEBUG') && WP_DEBUG && $action !== sanitize_key($action)) {
            trigger_error('Imperial Barons: action name "' . esc_html($action) . '" must be lowercase.', E_USER_WARNING);
        }
        return '<form method="post" class="ib-form ' . esc_attr($class) . '">'
            . '<input type="hidden" name="ib_action" value="' . esc_attr($action) . '">'
            . '<input type="hidden" name="ib_return" value="' . esc_url(self::current_url()) . '">'
            . wp_nonce_field('ib_action', 'ib_nonce', true, false);
    }

    /** The URL of the page being viewed, including its query string. */
    public static function current_url() {
        $url = home_url(add_query_arg([]));
        return remove_query_arg(['ib_action', 'ib_nonce', '_wp_http_referer'], $url);
    }

    /** Opens a GET form targeting a game page, preserving query args such as ?page_id= on plain permalinks. */
    public static function get_form_open($key, $class = 'ib-inline') {
        $url = self::url($key);
        $html = '<form method="get" class="' . esc_attr($class) . '" action="' . esc_url(strtok($url, '?')) . '">';
        $query = wp_parse_url($url, PHP_URL_QUERY);
        if ($query) {
            parse_str($query, $args);
            foreach ($args as $name => $value) {
                $html .= '<input type="hidden" name="' . esc_attr($name) . '" value="' . esc_attr($value) . '">';
            }
        }
        return $html;
    }

    /** A one-button form, optionally with hidden fields and a confirmation prompt. */
    public static function button($action, $label, $fields = [], $class = '', $confirm = '') {
        $html = self::form_open($action, 'ib-inline');
        foreach ($fields as $name => $value) {
            $html .= '<input type="hidden" name="' . esc_attr($name) . '" value="' . esc_attr($value) . '">';
        }
        $attr = $confirm ? ' data-confirm="' . esc_attr($confirm) . '"' : '';
        return $html . '<button type="submit" class="ib-btn ' . esc_attr($class) . '"' . $attr . '>' . esc_html($label) . '</button></form>';
    }

    /**
     * An action that looks like a link rather than a button: used for the sector numbers beside
     * ports, which fly you there and dock. It stays a POST with its nonce, because clicking it
     * spends turns, and a link that changes the game on a GET would be both wrong and unsafe.
     */
    public static function link_button($action, $label, $fields = [], $title = '', $confirm = '') {
        $html = self::form_open($action, 'ib-inline');
        foreach ($fields as $name => $value) {
            $html .= '<input type="hidden" name="' . esc_attr($name) . '" value="' . esc_attr($value) . '">';
        }
        $attr = ($title ? ' title="' . esc_attr($title) . '"' : '')
            . ($confirm ? ' data-confirm="' . esc_attr($confirm) . '"' : '');
        return $html . '<button type="submit" class="ib-linkish"' . $attr . '>' . esc_html($label) . '</button></form>';
    }

    /**
     * A sector number that flies you there and docks, when that is possible: there is a port, you
     * are not already in it, and you have the turns. Otherwise it is plain text, with the reason.
     *
     * @param object      $p
     * @param int         $sector
     * @param int|null    $turns  the turns the trip costs, when already worked out
     * @param string|null $label  what to show; the sector number by default
     * @param bool|null   $has_port whether a port is there, when the caller already knows; saves
     *                              a query per row in the port tables, which run to sixty rows
     */
    public static function fly_to($p, $sector, $turns = null, $label = null, $has_port = null) {
        $sector = (int) $sector;
        $label = $label === null ? (string) $sector : $label;
        if ($sector === (int) $p->sector_id) {
            return '<span title="You are here">' . esc_html($label) . '</span>';
        }
        if (!($has_port === null ? IB_Ports::in_sector($sector) : $has_port)) {
            return '<span title="No port in this sector">' . esc_html($label) . '</span>';
        }
        if ($turns !== null && (int) $p->turns_remaining < (int) $turns) {
            return '<span class="ib-dim" title="' . esc_attr(sprintf('%d turns needed, you have %d', (int) $turns, (int) $p->turns_remaining))
                . '">' . esc_html($label) . '</span>';
        }
        $title = $turns !== null
            ? sprintf('Fly to sector %d and dock (%d turns)', $sector, (int) $turns)
            : sprintf('Fly to sector %d and dock', $sector);
        return self::link_button('fly', $label, ['target' => $sector, 'dock' => 1], $title);
    }

    /**
     * A planet's sector number that flies you there and lands, when you can reach it. Landing
     * itself is free, so the cost is purely the warps.
     *
     * @param object      $p
     * @param object      $planet
     * @param int|null    $turns the turns the trip costs, when already worked out
     * @param string|null $label what to show; the sector number by default
     */
    public static function fly_to_planet($p, $planet, $turns = null, $label = null) {
        $sector = (int) $planet->sector_id;
        $label = $label === null ? (string) $sector : $label;
        if ($sector === (int) $p->sector_id) {
            return '<span title="You are here">' . esc_html($label) . '</span>';
        }
        if ($turns !== null && (int) $p->turns_remaining < (int) $turns) {
            return '<span class="ib-dim" title="' . esc_attr(sprintf('%d turns needed, you have %d', (int) $turns, (int) $p->turns_remaining))
                . '">' . esc_html($label) . '</span>';
        }
        return self::link_button('fly', $label, ['target' => $sector, 'planet' => (int) $planet->id],
            $turns !== null
                ? sprintf('Fly to %s and land (%d turns)', $planet->planet_name, (int) $turns)
                : sprintf('Fly to %s and land', $planet->planet_name));
    }

    /**
     * A route's risk as pips and a word: how the Planet page shows that a course looks dangerous
     * without saying what is waiting on it.
     */
    public static function risk_badge($risk) {
        $level = max(0, min(4, (int) $risk['level']));
        $class = $level >= 3 ? 'ib-bad' : ($level === 2 ? 'ib-special' : 'ib-good');
        $pips = str_repeat('&#9679;', $level) . str_repeat('&#9675;', 4 - $level);
        return '<span class="ib-risk ' . $class . '" title="' . esc_attr(sprintf('Route risk: %s', $risk['label'])) . '">'
            . $pips . ' <span class="ib-small">' . esc_html($risk['label']) . '</span></span>';
    }

    public static function status_bar($p) {
        $ship = IB_Player::ship($p);
        $items = [
            'Pilot' => esc_html($p->alias_name),
            'Sector' => '<a href="' . esc_url(self::url('sector')) . '">' . (int) $p->sector_id . '</a>',
            'Turns' => '<span class="' . ((int) $p->turns_remaining ? 'ib-good' : 'ib-bad') . '">' . (int) $p->turns_remaining
                . '</span> of ' . (int) IB_Settings::get('turns_per_day'),
            'Credits' => IB_Game::fmt($p->credits),
            'Holds' => IB_Player::holds_used($p) . '/' . (int) $p->cargo_holds,
            'Fighters' => IB_Game::fmt($p->fighters),
            'Shields' => IB_Game::fmt($p->shield_points),
            'Ship' => esc_html($ship['name']),
        ];
        $html = '<div class="ib-status">';
        foreach ($items as $label => $value) {
            $html .= '<span class="ib-stat"><span class="ib-label">' . esc_html($label) . '</span> ' . $value . '</span>';
        }
        return $html . '</div>';
    }

    /**
     * A one-line reminder of what a turn is: what each action costs, what is free,
     * and when turns come back. Shown on every game page under the status bar.
     */
    public static function turn_bar($p) {
        $s = IB_Settings::all();
        $left = (int) $p->turns_remaining;
        $reset = new DateTime('tomorrow', wp_timezone());
        $in = human_time_diff(current_time('timestamp'), $reset->getTimestamp());

        $costs = [
            sprintf('warp to another sector %s', self::turns($s['move_turn_cost'])),
            sprintf('dock at a port %s', self::turns($s['dock_turn_cost'])),
            sprintf('attack %s', self::turns($s['attack_turn_cost'])),
        ];
        $html = '<div class="ib-turnbar' . ($left ? '' : ' ib-turnbar-empty') . '">';
        $html .= $left
            ? sprintf('<strong>%d of %d turns left today.</strong>', $left, (int) $s['turns_per_day'])
            : '<strong>No turns left today.</strong> You can still trade while docked, manage planets, and use the Computer, Map and Messages.';
        $html .= ' <span class="ib-dim">Costs a turn:</span> ' . implode(', ', $costs) . '.';
        $html .= ' <span class="ib-dim">Free:</span> buying and selling while docked, haggling, undocking, landing and planet transfers,'
            . ' launching survey drones, plotting courses, messages and rankings.';
        if ((int) $p->docked_port_id) {
            $html .= ' <span class="ib-good">You are docked: trade as often as you like, for no turns, until you warp away.</span>';
        }
        $html .= sprintf(' <span class="ib-dim">Turns reset in %s (midnight site time); unused turns do not carry over.</span>', esc_html($in));
        return $html . '</div>';
    }

    private static function turns($n) {
        $n = (int) $n;
        return $n === 1 ? '1 turn' : $n . ' turns';
    }

    public static function nav($current, $p) {
        $unread = $p ? IB_Messages::unread_count($p->id) : 0;
        $html = '<nav class="ib-nav" aria-label="Imperial Barons Online">';
        foreach (self::PAGES as $key => $def) {
            $label = esc_html($def[3]);
            if ($key === 'messages' && $unread) $label .= ' <span class="ib-badge">' . $unread . '</span>';
            $html .= '<a href="' . esc_url(self::url($key)) . '"' . ($key === $current ? ' class="ib-active" aria-current="page"' : '') . '>' . $label . '</a>';
        }
        return $html . '</nav>';
    }

    /** Footer status bar: a quick recap of the pilot's standing, plus the site credit. */
    public static function footer_bar($p) {
        $items = [];
        if ($p) {
            $items['Pilot'] = esc_html(IB_Game::rank_title($p->experience) . ' ' . $p->alias_name);
            $items['Sector'] = (int) $p->sector_id;
            $items['Turns'] = (int) $p->turns_remaining . ' of ' . (int) IB_Settings::get('turns_per_day');
            $items['Credits'] = IB_Game::fmt($p->credits);
            $items['Net worth'] = IB_Game::fmt(IB_Player::net_worth($p));
        }
        $html = '<div class="ib-footer"><span class="ib-footer-stats">';
        foreach ($items as $label => $value) {
            $html .= '<span class="ib-stat"><span class="ib-label">' . esc_html($label) . '</span> ' . $value . '</span> ';
        }
        $html .= '</span><span class="ib-stat">' . esc_html(IB_GAME_NAME) . ' v' . esc_html(IB_VERSION)
            . ' &middot; <a href="' . esc_url(IB_SOURCE_URL) . '" target="_blank" rel="noopener">source on GitHub</a></span>';
        return $html . '</div>';
    }

    public static function port_badge($port) {
        if (!$port) return '';
        $code = IB_Ports::class_code($port->port_class);
        $cls = in_array((int) $port->port_class, [IB_Ports::ARMORY, IB_Ports::SHIPYARD], true) ? 'ib-special' : 'ib-port-code';
        return '<span class="' . $cls . '">' . esc_html($code) . '</span>';
    }

    /**
     * Renders "B S B" style trading pattern with colour per letter, followed by a chip for the
     * port's specialist good if it has one.
     *
     * The three letters are the port's class, and say what it does with the three staples in
     * order. Specialist goods came later and are not part of the class, so they hang off the end
     * as a marked chip rather than a fourth letter, which would mean a different commodity at
     * every port.
     */
    public static function pattern($port) {
        if (!IB_Ports::is_trading_port($port)) return self::port_badge($port);
        $out = '';
        foreach (str_split(IB_Ports::CLASSES[(int) $port->port_class]) as $ch) {
            $out .= '<span class="' . ($ch === 'B' ? 'ib-buy' : 'ib-sell') . '">' . $ch . '</span>';
        }
        return '<span class="ib-port-code">' . $out . '</span>' . self::specialist_chip($port);
    }

    /** Initials for the specialist goods, for the chip on a port badge. */
    const SPECIALIST_INITIALS = ['isotopes' => 'I', 'medicine' => 'M', 'luxuries' => 'L'];

    /**
     * "&#9670;I" after a port's class code: the specialist good it deals in, coloured like the
     * class letters, green where the port buys and cyan where it sells.
     */
    public static function specialist_chip($port) {
        $key = IB_Ports::specialty($port);
        if (!$key) return '';
        $buying = IB_Ports::mode($port, $key) === 'buying';
        $initial = isset(self::SPECIALIST_INITIALS[$key]) ? self::SPECIALIST_INITIALS[$key] : strtoupper(substr($key, 0, 1));
        return ' <span class="ib-spec-chip ' . ($buying ? 'ib-buy' : 'ib-sell') . '" title="'
            . esc_attr(sprintf('%s %s', $buying ? 'Buys' : 'Sells', IB_Game::label($key)))
            . '">&#9670;' . esc_html($initial) . '</span>';
    }

    public static function time_ago($mysql) {
        $ts = strtotime($mysql);
        return $ts ? human_time_diff($ts, current_time('timestamp')) . ' ago' : '';
    }
}
