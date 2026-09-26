<?php
/**
 * Core helpers shared by every part of the game: table names, settings,
 * game-level exceptions, logging and rank titles.
 */
if (!defined('ABSPATH')) exit;

/**
 * Thrown by services when a player action cannot be completed.
 * The message is shown to the player; $type controls the flash colour.
 */
class IB_Game_Exception extends Exception {
    public $type;
    public function __construct($message, $type = 'error') {
        parent::__construct($message);
        $this->type = $type;
    }
}

class IB_DB {
    const TABLES = ['players', 'sectors', 'warps', 'ports', 'planets', 'teams', 'messages', 'fleets', 'explored', 'news', 'admin_log'];

    public static function t($name) {
        global $wpdb;
        return $wpdb->prefix . 'ib_' . $name;
    }
}

class IB_Settings {
    const OPTION = 'ib_settings';

    public static function defaults() {
        return [
            'game_name'            => 'Imperial Barons Online',
            'turns_per_day'        => 10,
            'starting_credits'     => 5000,
            'starting_fighters'    => 30,
            'starting_shields'     => 0,
            'starting_holds'       => 20,
            'move_turn_cost'       => 1,
            'dock_turn_cost'       => 1,
            'attack_turn_cost'     => 1,
            'port_regen_percent'   => 5,
            'max_haggle_attempts'  => 3,
            'core_sectors'     => 10,
            'team_max_members'     => 6,
            'news_retention_days'  => 14,
            'allow_new_players'    => 1,
            'price_fighter'        => 50,
            'price_shield'         => 25,
            'price_hold_base'      => 200,
            'price_worldseed'        => 20000,
            'price_colonist'       => 5,
            'price_survey_drone'   => 400,
            'price_nebula_chart'   => 1500,
            'discovery_chance'     => 30,
            'vraxori_regeneration_rate' => 100,
        ];
    }

    /** Settings that are free text rather than integers. */
    public static function text_keys() {
        return ['game_name'];
    }

    public static function all() {
        $saved = get_option(self::OPTION, []);
        return wp_parse_args(is_array($saved) ? $saved : [], self::defaults());
    }

    public static function get($key) {
        $all = self::all();
        return isset($all[$key]) ? $all[$key] : null;
    }

    public static function update(array $values) {
        $current = self::all();
        foreach (self::defaults() as $key => $default) {
            if (!array_key_exists($key, $values)) continue;
            $current[$key] = in_array($key, self::text_keys(), true)
                ? sanitize_text_field($values[$key])
                : max(0, (int) $values[$key]);
        }
        $current['turns_per_day'] = max(1, $current['turns_per_day']);
        update_option(self::OPTION, $current);
        return $current;
    }
}

class IB_Log {
    /** Public "Imperial Gazette" item shown to all players. */
    public static function news($type, $message) {
        global $wpdb;
        $wpdb->insert(IB_DB::t('news'), [
            'event_type' => $type,
            'message'    => $message,
            'created_at' => current_time('mysql'),
        ]);
    }

    /** Administrative audit log. */
    public static function admin($type, $message) {
        global $wpdb;
        $wpdb->insert(IB_DB::t('admin_log'), [
            'event_type' => $type,
            'message'    => $message,
            'user_id'    => get_current_user_id(),
            'created_at' => current_time('mysql'),
        ]);
    }
}

class IB_Game {
    /**
     * Tradeable goods.
     *
     * Every trading port (classes 1-8) handles the three staples, and the port's class code is
     * its buy/sell stance on them. Specialist goods are rarer, dearer and swing further in price;
     * a port deals in at most one of them, and only some ports do at all. 'col' is the port's
     * column prefix for staples, while specialists live in the port's spec_* columns.
     * 'vol' scales how far the price moves from base as stock or demand changes.
     */
    const COMMODITIES = [
        'ore'       => ['label' => 'Ferrium Ore',   'base' => 30,  'col' => 'ore', 'vol' => 1.0, 'specialist' => false],
        'organics'  => ['label' => 'Biostock',      'base' => 50,  'col' => 'org', 'vol' => 1.0, 'specialist' => false],
        'equipment' => ['label' => 'Machinery',     'base' => 90,  'col' => 'equ', 'vol' => 1.0, 'specialist' => false],
        'isotopes'  => ['label' => 'Rare Isotopes', 'base' => 320, 'col' => '',    'vol' => 1.7, 'specialist' => true],
        'medicine'  => ['label' => 'Medicine',      'base' => 200, 'col' => '',    'vol' => 1.5, 'specialist' => true],
        'luxuries'  => ['label' => 'Luxuries',      'base' => 480, 'col' => '',    'vol' => 1.9, 'specialist' => true],
    ];

    /** @return string[] commodity keys: staples by default, specialists when $specialist is true. */
    public static function commodities($specialist = false) {
        $out = [];
        foreach (self::COMMODITIES as $key => $c) {
            if ((bool) $c['specialist'] === (bool) $specialist) $out[] = $key;
        }
        return $out;
    }

    public static function is_specialist($commodity) {
        return isset(self::COMMODITIES[$commodity]) && !empty(self::COMMODITIES[$commodity]['specialist']);
    }

    /** Experience thresholds and titles, lowest first. */
    const RANKS = [
        0 => 'Vagrant', 2 => 'Freeholder', 8 => 'Yeoman', 16 => 'Squire',
        32 => 'Knight-Errant', 64 => 'Knight', 128 => 'Knight Banneret',
        256 => 'Baronet', 512 => 'Baron', 1000 => 'High Baron',
        2000 => 'Viscount', 3500 => 'Earl', 5000 => 'Count Palatine',
        7500 => 'Marquess', 10000 => 'Margrave', 15000 => 'Duke',
        22000 => 'Grand Duke', 30000 => 'Archduke', 45000 => 'Prince',
        65000 => 'Prince-Elector', 90000 => 'Lord Regent', 130000 => 'Imperial Paragon',
    ];

    /** Display name for a cargo/stock column key. */
    public static function label($key) {
        if (isset(self::COMMODITIES[$key])) return self::COMMODITIES[$key]['label'];
        return ucfirst(str_replace('_', ' ', $key));
    }

    public static function rank_title($experience) {
        $title = 'Vagrant';
        foreach (self::RANKS as $threshold => $name) {
            if ($experience >= $threshold) $title = $name;
        }
        return $title;
    }

    public static function alignment_label($alignment) {
        if ($alignment >= 1000) return 'Saintly';
        if ($alignment >= 100)  return 'Good';
        if ($alignment > -100)  return 'Neutral';
        if ($alignment > -1000) return 'Evil';
        return 'Notorious';
    }

    public static function universe_exists() {
        global $wpdb;
        return (int) $wpdb->get_var('SELECT COUNT(*) FROM ' . IB_DB::t('sectors')) > 0;
    }

    public static function is_core($sector_id) {
        global $wpdb;
        return (bool) $wpdb->get_var($wpdb->prepare(
            'SELECT is_core FROM ' . IB_DB::t('sectors') . ' WHERE id = %d', $sector_id
        ));
    }

    public static function today() {
        return current_time('Y-m-d');
    }

    public static function fmt($n) {
        return number_format_i18n((float) $n);
    }
}
