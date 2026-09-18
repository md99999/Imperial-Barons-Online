<?php
if (!defined('ABSPATH')) exit;

class IB_Player {
    /** Integer columns that may be changed with add(). */
    const COUNTERS = ['fighters', 'shield_points', 'cargo_holds', 'ore', 'organics', 'equipment', 'colonists',
                      'worldseeds', 'survey_drones', 'credits', 'experience', 'alignment', 'turns_remaining', 'kills', 'deaths'];

    private static $current = false;

    /** The logged-in user's pilot, or null. Applies the daily turn reset. */
    public static function current() {
        if (self::$current === false) {
            self::$current = is_user_logged_in() ? self::by_user(get_current_user_id()) : null;
            if (self::$current) {
                global $wpdb;
                $wpdb->update(IB_DB::t('players'), ['last_seen' => current_time('mysql')], ['id' => self::$current->id]);
            }
        }
        return self::$current;
    }

    public static function by_user($user_id) {
        global $wpdb;
        $row = $wpdb->get_row($wpdb->prepare('SELECT * FROM ' . IB_DB::t('players') . ' WHERE user_id = %d', $user_id));
        return $row ? self::apply_turn_reset($row) : null;
    }

    public static function get($id) {
        global $wpdb;
        $row = $wpdb->get_row($wpdb->prepare('SELECT * FROM ' . IB_DB::t('players') . ' WHERE id = %d', $id));
        return $row ? self::apply_turn_reset($row) : null;
    }

    public static function refresh($p) {
        $fresh = self::get($p->id);
        foreach (get_object_vars($fresh) as $k => $v) $p->$k = $v;
        return $p;
    }

    public static function name($id) {
        global $wpdb;
        $name = $wpdb->get_var($wpdb->prepare('SELECT alias_name FROM ' . IB_DB::t('players') . ' WHERE id = %d', $id));
        return $name ? $name : 'Unknown';
    }

    /** Turns reset once per calendar day (site timezone). Unused turns do not carry over. */
    private static function apply_turn_reset($p) {
        $today = IB_Game::today();
        if ($p->last_turn_reset !== $today) {
            global $wpdb;
            $turns = (int) IB_Settings::get('turns_per_day');
            $wpdb->query($wpdb->prepare(
                'UPDATE ' . IB_DB::t('players') . ' SET turns_remaining = %d, last_turn_reset = %s
                 WHERE id = %d AND (last_turn_reset IS NULL OR last_turn_reset <> %s)',
                $turns, $today, $p->id, $today
            ));
            $p->turns_remaining = $turns;
            $p->last_turn_reset = $today;
        }
        return $p;
    }

    public static function create($user_id, $alias, $ship_name) {
        global $wpdb;
        $alias = trim(sanitize_text_field($alias));
        $ship_name = trim(sanitize_text_field($ship_name));
        if (!IB_Settings::get('allow_new_players')) throw new IB_Game_Exception('New pilot registration is currently closed.');
        if (!IB_Game::universe_exists()) throw new IB_Game_Exception('The universe has not been created yet. Ask an administrator to forge the universe.');
        if (self::by_user($user_id)) throw new IB_Game_Exception('You already have a pilot.');
        if (strlen($alias) < 3 || strlen($alias) > 41) throw new IB_Game_Exception('Your alias must be 3 to 41 characters.');
        if ($wpdb->get_var($wpdb->prepare('SELECT id FROM ' . IB_DB::t('players') . ' WHERE alias_name = %s', $alias))) {
            throw new IB_Game_Exception('That alias is already taken.');
        }
        if ($ship_name === '') $ship_name = $alias . "'s Ship";
        $user = get_userdata($user_id);
        $s = IB_Settings::all();
        $wpdb->insert(IB_DB::t('players'), [
            'user_id' => $user_id, 'alias_name' => $alias,
            'real_name' => $user ? substr($user->display_name, 0, 41) : '',
            'ship_name' => substr($ship_name, 0, 41), 'ship_type' => IB_Ships::DEFAULT_TYPE,
            'sector_id' => 1, 'fighters' => $s['starting_fighters'], 'shield_points' => $s['starting_shields'],
            'cargo_holds' => $s['starting_holds'], 'credits' => $s['starting_credits'],
            'turns_remaining' => $s['turns_per_day'], 'last_turn_reset' => IB_Game::today(),
            'created_at' => current_time('mysql'), 'last_seen' => current_time('mysql'),
        ]);
        $id = (int) $wpdb->insert_id;
        self::mark_visited($id, 1);
        self::chart($id, self::warps_from(1));
        IB_Log::news('new_player', sprintf('%s has joined the galaxy as a new trader.', $alias));
        IB_Messages::system($id, 'Welcome to the galaxy',
            "Welcome, $alias!\n\nYou begin in Aurelia (sector 1), the seat of the Imperial Throne. Buy low, sell high: " .
            "find a port that sells a commodity cheaply and a nearby port that buys it. Use the Computer to plot courses " .
            "and the Map to see what you have charted.\n\nYour sensors sweep neighbouring sectors whenever you arrive, and first visits beyond " .
            "the Imperial Core often turn up salvage, caches or survey data.\n\nYou receive " . $s['turns_per_day'] . " turns each day. Moving between sectors " .
            "and docking at ports cost turns. Good luck, trader.");
        self::$current = false;
        return $id;
    }

    /** Atomically deducts turns or throws. */
    public static function spend_turns($p, $n) {
        if ($n <= 0) return;
        global $wpdb;
        $ok = $wpdb->query($wpdb->prepare(
            'UPDATE ' . IB_DB::t('players') . ' SET turns_remaining = turns_remaining - %d WHERE id = %d AND turns_remaining >= %d',
            $n, $p->id, $n
        ));
        if (!$ok) {
            throw new IB_Game_Exception(sprintf('You need %d turn(s) and have %d left. Turns reset daily.', $n, $p->turns_remaining));
        }
        $p->turns_remaining -= $n;
    }

    /** Atomically deducts credits or throws. */
    public static function spend_credits($p, $amount) {
        $amount = (int) ceil($amount);
        if ($amount <= 0) return;
        global $wpdb;
        $ok = $wpdb->query($wpdb->prepare(
            'UPDATE ' . IB_DB::t('players') . ' SET credits = credits - %d WHERE id = %d AND credits >= %d',
            $amount, $p->id, $amount
        ));
        if (!$ok) throw new IB_Game_Exception(sprintf('That costs %s credits; you only have %s.', IB_Game::fmt($amount), IB_Game::fmt($p->credits)));
        $p->credits -= $amount;
    }

    /** Adds (or subtracts) values from integer columns. */
    public static function add($p, array $deltas) {
        global $wpdb;
        $sets = [];
        foreach ($deltas as $col => $delta) {
            if (!in_array($col, self::COUNTERS, true)) continue;
            $sets[] = $wpdb->prepare("$col = $col + %d", (int) $delta);
            $p->$col += (int) $delta;
        }
        if ($sets) $wpdb->query('UPDATE ' . IB_DB::t('players') . ' SET ' . implode(', ', $sets) . $wpdb->prepare(' WHERE id = %d', $p->id));
    }

    public static function update($p, array $data) {
        global $wpdb;
        $wpdb->update(IB_DB::t('players'), $data, ['id' => $p->id]);
        foreach ($data as $k => $v) $p->$k = $v;
    }

    /*
     * Map knowledge lives in the explored table. A row means the pilot has CHARTED the sector
     * (knows its port and planets, e.g. from a sensor sweep, beacon, drone or chart);
     * visited = 1 means they have actually flown there.
     */

    /**
     * Records a visit.
     * @return bool true if this is the pilot's first visit to the sector
     */
    public static function mark_visited($player_id, $sector_id) {
        global $wpdb;
        $t = IB_DB::t('explored');
        $before = $wpdb->get_var($wpdb->prepare("SELECT visited FROM $t WHERE player_id = %d AND sector_id = %d", $player_id, $sector_id));
        $now = current_time('mysql');
        $wpdb->query($wpdb->prepare(
            "INSERT INTO $t (player_id, sector_id, visited, visited_at) VALUES (%d, %d, 1, %s)
             ON DUPLICATE KEY UPDATE visited = 1, visited_at = COALESCE(visited_at, %s)",
            $player_id, $sector_id, $now, $now
        ));
        return $before === null || (int) $before === 0;
    }

    /**
     * Adds sectors to the pilot's charts without visiting them.
     * @return int how many sectors were newly charted
     */
    public static function chart($player_id, array $sector_ids) {
        global $wpdb;
        $sector_ids = array_unique(array_map('intval', $sector_ids));
        $added = 0;
        foreach (array_chunk($sector_ids, 500) as $chunk) {
            $values = [];
            foreach ($chunk as $sid) $values[] = $wpdb->prepare('(%d, %d, 0)', $player_id, $sid);
            $added += (int) $wpdb->query('INSERT IGNORE INTO ' . IB_DB::t('explored') . ' (player_id, sector_id, visited) VALUES ' . implode(',', $values));
        }
        return $added;
    }

    /** Sectors the pilot has flown to. */
    public static function visited_ids($player_id) {
        global $wpdb;
        return array_map('intval', $wpdb->get_col($wpdb->prepare(
            'SELECT sector_id FROM ' . IB_DB::t('explored') . ' WHERE player_id = %d AND visited = 1', $player_id
        )));
    }

    /** Sectors whose contents the pilot knows: visited or charted. */
    public static function charted_ids($player_id) {
        global $wpdb;
        return array_map('intval', $wpdb->get_col($wpdb->prepare(
            'SELECT sector_id FROM ' . IB_DB::t('explored') . ' WHERE player_id = %d', $player_id
        )));
    }

    /** Arrival routine: log the visit, sweep neighbouring sectors, and roll for a discovery on a first visit. */
    public static function arrive($p) {
        $first = self::mark_visited($p->id, $p->sector_id);
        self::chart($p->id, self::warps_from($p->sector_id));
        return $first ? IB_Discovery::first_visit($p) : [];
    }

    public static function warps_from($sector_id) {
        global $wpdb;
        return array_map('intval', $wpdb->get_col($wpdb->prepare(
            'SELECT to_sector FROM ' . IB_DB::t('warps') . ' WHERE from_sector = %d ORDER BY to_sector', $sector_id
        )));
    }

    /**
     * Warps the ship to an adjacent sector.
     * @return array list of [type, message]: discoveries are 'success', combat is 'warning'.
     *               $p->hostile_contact is set when anything attacked the ship.
     */
    public static function move($p, $to) {
        $to = (int) $to;
        if (!in_array($to, self::warps_from($p->sector_id), true)) {
            throw new IB_Game_Exception(sprintf('There is no warp lane from sector %d to sector %d.', $p->sector_id, $to));
        }
        self::spend_turns($p, (int) IB_Settings::get('move_turn_cost'));
        self::update($p, ['sector_id' => $to, 'prev_sector_id' => $p->sector_id, 'docked_port_id' => 0, 'landed_planet_id' => 0]);
        $msgs = array_map(function ($m) { return ['success', $m]; }, self::arrive($p));
        $combat = IB_Combat::sector_entry($p);
        $p->hostile_contact = (bool) $combat;
        foreach ($combat as $m) $msgs[] = ['warning', $m];
        return $msgs;
    }

    /**
     * Follows the shortest course toward $target one warp at a time. Discoveries along the
     * way are reported; the autopilot stops early when turns run out or the ship is attacked.
     * @return array list of [type, message]
     */
    public static function autopilot($p, $target) {
        $target = (int) $target;
        $path = IB_Pathfinder::shortestPath(null, (int) $p->sector_id, $target);
        if (!$path) throw new IB_Game_Exception(sprintf('No known course to sector %d.', $target));
        if (count($path) === 1) throw new IB_Game_Exception('You are already there.');
        $cost = (int) IB_Settings::get('move_turn_cost');
        $msgs = [];
        foreach (array_slice($path, 1) as $next) {
            if ($p->turns_remaining < $cost) {
                $msgs[] = ['warning', sprintf('Autopilot disengaged in sector %d: out of turns.', $p->sector_id)];
                break;
            }
            $msgs = array_merge($msgs, self::move($p, $next));
            if (!empty($p->hostile_contact)) {
                if (empty($p->destroyed)) $msgs[] = ['warning', sprintf('Autopilot disengaged in sector %d: hostile contact.', $p->sector_id)];
                break;
            }
        }
        if ((int) $p->sector_id === $target) $msgs[] = ['success', sprintf('Arrived at sector %d.', $target)];
        return $msgs;
    }

    public static function jettison($p, $commodity, $qty) {
        if (!in_array($commodity, ['ore', 'organics', 'equipment', 'colonists'], true)) throw new IB_Game_Exception('Invalid cargo.');
        $qty = min((int) $qty, (int) $p->$commodity);
        if ($qty <= 0) throw new IB_Game_Exception('Nothing to jettison.');
        if ($commodity === 'colonists') {
            self::add($p, ['colonists' => -$qty, 'alignment' => -$qty]);
            return sprintf('You released %s colonists into space. The Chancery will hear of this.', IB_Game::fmt($qty));
        }
        self::add($p, [$commodity => -$qty]);
        return sprintf('Jettisoned %s units of %s.', IB_Game::fmt($qty), IB_Game::label($commodity));
    }

    public static function rename_ship($p, $name) {
        $name = trim(sanitize_text_field($name));
        if ($name === '' || strlen($name) > 41) throw new IB_Game_Exception('Ship names must be 1 to 41 characters.');
        self::update($p, ['ship_name' => $name]);
        return 'Your ship is now registered as the ' . $name . '.';
    }

    public static function ship($p) {
        return IB_Ships::get($p->ship_type);
    }

    public static function holds_used($p) {
        return (int) $p->ore + (int) $p->organics + (int) $p->equipment + (int) $p->colonists;
    }

    public static function holds_free($p) {
        return max(0, (int) $p->cargo_holds - self::holds_used($p));
    }

    public static function net_worth($p) {
        global $wpdb;
        $s = IB_Settings::all();
        $c = IB_Game::COMMODITIES;
        $ship = self::ship($p);
        $worth = (int) $p->credits
            + $p->ore * $c['ore']['base'] + $p->organics * $c['organics']['base'] + $p->equipment * $c['equipment']['base']
            + $p->fighters * $s['price_fighter'] + $p->shield_points * $s['price_shield']
            + (int) ($ship['price'] / 2) + $p->worldseeds * $s['price_worldseed'];
        $planets = $wpdb->get_row($wpdb->prepare(
            'SELECT COALESCE(SUM(bastion_vault),0) cr, COALESCE(SUM(fighters),0) f, COALESCE(SUM(colonists),0) col,
                    COALESCE(SUM(ore),0) o, COALESCE(SUM(organics),0) g, COALESCE(SUM(equipment),0) e
             FROM ' . IB_DB::t('planets') . ' WHERE owner_player_id = %d', $p->id
        ));
        if ($planets) {
            $worth += $planets->cr + $planets->f * $s['price_fighter'] + $planets->col * $s['price_colonist']
                + $planets->o * $c['ore']['base'] + $planets->g * $c['organics']['base'] + $planets->e * $c['equipment']['base'];
        }
        return $worth;
    }

    /** Ship destroyed: the pilot ejects to Aurelia in a replacement Freetrader and is grounded for the rest of the day. */
    public static function destroy($p, $reason) {
        $s = IB_Settings::all();
        self::update($p, [
            'ship_type' => IB_Ships::DEFAULT_TYPE, 'cargo_holds' => $s['starting_holds'], 'fighters' => 0, 'shield_points' => 0,
            'ore' => 0, 'organics' => 0, 'equipment' => 0, 'colonists' => 0, 'worldseeds' => 0, 'survey_drones' => 0,
            'sector_id' => 1, 'prev_sector_id' => 0, 'docked_port_id' => 0, 'landed_planet_id' => 0,
            'turns_remaining' => 0, 'last_turn_reset' => IB_Game::today(),
        ]);
        self::add($p, ['deaths' => 1]);
        IB_Messages::system($p->id, 'Your ship was destroyed',
            "$reason\n\nYour escape pod carried you back to Aurelia, where the Chancery issued you a replacement Freetrader. " .
            "Your cargo and fighters were lost. You may fly again tomorrow.");
        IB_Log::news('destroyed', sprintf('%s was destroyed. %s', $p->alias_name, $reason));
    }

    public static function all_active($exclude_id = 0) {
        global $wpdb;
        return $wpdb->get_results($wpdb->prepare(
            'SELECT id, alias_name FROM ' . IB_DB::t('players') . ' WHERE id <> %d ORDER BY alias_name', $exclude_id
        ));
    }

    /** Other players currently in a sector (not landed on a planet). */
    public static function in_sector($sector_id, $exclude_id = 0) {
        global $wpdb;
        return $wpdb->get_results($wpdb->prepare(
            'SELECT * FROM ' . IB_DB::t('players') . ' WHERE sector_id = %d AND id <> %d AND landed_planet_id = 0 ORDER BY alias_name',
            $sector_id, $exclude_id
        ));
    }
}
