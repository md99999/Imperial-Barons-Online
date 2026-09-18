<?php
if (!defined('ABSPATH')) exit;

/**
 * Exploration rewards: finds on a pilot's first visit to a sector, survey drones and nebula charts.
 */
class IB_Discovery {
    const DRONE_RADIUS = 3;
    const BEACON_RADIUS = 2;
    const MAX_DRONES = 5;

    /** Relative weights of each kind of find. */
    const FINDS = ['salvage' => 35, 'cache' => 25, 'beacon' => 20, 'fighters' => 15, 'ambush' => 5];

    /**
     * Called on a pilot's first visit to a sector outside the Imperial Core.
     * @return string[] messages
     */
    public static function first_visit($p) {
        if (IB_Game::is_core($p->sector_id)) return [];
        IB_Player::add($p, ['experience' => 1]);
        if (mt_rand(1, 100) > (int) IB_Settings::get('discovery_chance')) {
            return [sprintf('Sector %d entered in your charts. (+1 experience)', $p->sector_id)];
        }
        $roll = mt_rand(1, array_sum(self::FINDS));
        foreach (self::FINDS as $kind => $weight) {
            if (($roll -= $weight) <= 0) break;
        }
        return [call_user_func([__CLASS__, 'find_' . $kind], $p) . ' (+1 experience)'];
    }

    private static function wreck_name() {
        $kind = ['freighter', 'courier', 'ore barge', 'survey cutter', 'pilgrim ark', 'guild hauler', 'patrol skiff'];
        $name = ['Vesper', 'Obedience', 'Lady Ysolde', 'Tithe Collector', 'Marrow Star', 'Faithful Oath', 'Cinder Rose', 'Brannoch Pride', 'Last Ember', 'Kestrel'];
        return 'the derelict ' . $kind[array_rand($kind)] . ' ' . $name[array_rand($name)];
    }

    private static function credit_find($p, $min, $max) {
        $credits = mt_rand($min, $max);
        IB_Player::add($p, ['credits' => $credits]);
        return $credits;
    }

    private static function find_salvage($p) {
        $wreck = self::wreck_name();
        $commodity = array_rand(IB_Game::COMMODITIES);
        $qty = min(mt_rand(5, 25), IB_Player::holds_free($p));
        if ($qty <= 0) {
            $credits = self::credit_find($p, 50, 150);
            return sprintf('You find %s drifting here. Your holds are full, so you strip its credit chip instead: %s credits.', $wreck, IB_Game::fmt($credits));
        }
        IB_Player::add($p, [$commodity => $qty]);
        return sprintf('You find %s drifting here and salvage %d units of %s.', $wreck, $qty, IB_Game::label($commodity));
    }

    private static function find_cache($p) {
        $credits = self::credit_find($p, 100, 600);
        return sprintf('Your sensors pick up a smuggler\'s cache hidden in an asteroid: %s credits!', IB_Game::fmt($credits));
    }

    private static function find_fighters($p) {
        $room = IB_Player::ship($p)['max_fighters'] - (int) $p->fighters;
        $qty = min(mt_rand(5, 30), $room);
        if ($qty <= 0) {
            $credits = self::credit_find($p, 50, 150);
            return sprintf('You find abandoned fighters but have no room for them, so you sell their targeting cores for %s credits.', IB_Game::fmt($credits));
        }
        IB_Player::add($p, ['fighters' => $qty]);
        return sprintf('%d abandoned fighters answer your recall signal and dock with your ship.', $qty);
    }

    private static function find_beacon($p) {
        $sectors = array_keys(IB_Pathfinder::distances(null, (int) $p->sector_id, self::BEACON_RADIUS));
        $added = IB_Player::chart($p->id, $sectors);
        return $added
            ? sprintf('An old Imperial survey beacon uploads its charts: %d more sectors added to your map.', $added)
            : 'An old Imperial survey beacon hails you, but its charts hold nothing you don\'t already know.';
    }

    private static function find_ambush($p) {
        global $wpdb;
        $wpdb->insert(IB_DB::t('fleets'), [
            'faction' => IB_Factions::REAVER_PIRATES, 'owner_player_id' => 0, 'team_id' => 0,
            'sector_id' => $p->sector_id, 'fighter_count' => mt_rand(15, 50), 'fleet_mode' => 'offensive',
            'created_at' => current_time('mysql'),
        ]);
        return 'A distress call lures you in... it is a trap! Reaver Pirates drop out of hiding.';
    }

    public static function launch_drone($p) {
        if ((int) $p->survey_drones < 1) throw new IB_Game_Exception('You have no survey drones. Buy them at the Aurelian Armory or the Imperial Drydock.');
        $sectors = array_keys(IB_Pathfinder::distances(null, (int) $p->sector_id, self::DRONE_RADIUS));
        IB_Player::add($p, ['survey_drones' => -1]);
        $added = IB_Player::chart($p->id, $sectors);
        return sprintf('Your survey drone sweeps every sector within %d warps: %d new sectors charted.', self::DRONE_RADIUS, $added);
    }

    /** @return array nebula name => [sectors, charted by this pilot], excluding the Imperial Core */
    public static function nebulae($p) {
        global $wpdb;
        $rows = $wpdb->get_results($wpdb->prepare(
            'SELECT s.nebula, COUNT(*) total, SUM(e.sector_id IS NOT NULL) charted
             FROM ' . IB_DB::t('sectors') . ' s
             LEFT JOIN ' . IB_DB::t('explored') . ' e ON e.sector_id = s.id AND e.player_id = %d
             WHERE s.is_core = 0 GROUP BY s.nebula ORDER BY s.nebula', $p->id
        ));
        $out = [];
        foreach ($rows as $r) $out[$r->nebula] = [(int) $r->total, (int) $r->charted];
        return $out;
    }

    public static function buy_nebula_chart($p, $nebula) {
        global $wpdb;
        $port = IB_Ports::require_docked($p);
        if (!in_array((int) $port->port_class, [IB_Ports::ARMORY, IB_Ports::SHIPYARD], true)) {
            throw new IB_Game_Exception('Nebula charts are sold only at the Aurelian Armory and the Imperial Drydock.');
        }
        $sectors = array_map('intval', $wpdb->get_col($wpdb->prepare(
            'SELECT id FROM ' . IB_DB::t('sectors') . ' WHERE nebula = %s AND is_core = 0', $nebula
        )));
        if (!$sectors) throw new IB_Game_Exception('No such nebula is known to the Chancery cartographers.');
        IB_Player::spend_credits($p, (int) IB_Settings::get('price_nebula_chart'));
        $added = IB_Player::chart($p->id, $sectors);
        return sprintf('You purchase the Chancery chart of %s: %d new sectors added to your map.', $nebula, $added);
    }
}
