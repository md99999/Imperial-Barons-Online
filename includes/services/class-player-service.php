<?php
if (!defined('ABSPATH')) exit;

class IB_Player {
    /** Integer columns that may be changed with add(). */
    const COUNTERS = ['fighters', 'shield_points', 'cargo_holds', 'ore', 'organics', 'equipment',
                      'isotopes', 'medicine', 'luxuries', 'colonists',
                      'worldseeds', 'survey_drones', 'vending_machines', 'smuggle_left', 'credits', 'experience', 'alignment', 'turns_remaining', 'kills', 'deaths'];

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
        if (!$fresh) return $p;
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

    /**
     * The Imperial Bounty: the Crown pays every pilot the same sum once a night, from daily maintenance.
     *
     * One UPDATE pays each pilot and stamps last_bounty with $day in the same row write, so a pilot
     * is paid at most once per day however many times maintenance runs (a rerun, "Run now", or WP-Cron
     * and a server cron racing each other). A pilot is paid only when their stamp is strictly older
     * than $day, so moving the site clock or timezone backwards cannot pay a day twice either. If the
     * statement fails nobody is stamped who was not paid, so running daily maintenance again that day
     * pays only those still owed. A pilot whose credits would overflow the column is skipped.
     *
     * @param string $day the site-local date being paid for, Y-m-d
     * @return array ['ok' => bool, 'amount' => int credits each, 'pilots' => int paid, 'credits' => int total]
     */
    public static function pay_imperial_bounty($day) {
        global $wpdb;
        $out = ['ok' => true, 'amount' => 0, 'pilots' => 0, 'credits' => 0];
        if (!is_string($day) || !preg_match('/^\d{4}-\d{2}-\d{2}$/D', $day)
            || !checkdate((int) substr($day, 5, 2), (int) substr($day, 8, 2), (int) substr($day, 0, 4))) {
            error_log('Imperial Barons Online: Imperial Bounty not paid, invalid day ' . var_export($day, true) . '.');
            $out['ok'] = false;
            return $out;
        }
        $amount = IB_Settings::imperial_bounty();
        $out['amount'] = $amount;
        if ($amount <= 0) return $out;
        $paid = $wpdb->query($wpdb->prepare(
            'UPDATE ' . IB_DB::t('players') . ' SET credits = credits + %d, last_bounty = %s
             WHERE (last_bounty IS NULL OR last_bounty < %s) AND credits <= %d',
            $amount, $day, $day, PHP_INT_MAX - $amount
        ));
        if ($paid === false) {
            // The database's own words go to the server's error log only, never to a page.
            error_log('Imperial Barons Online: Imperial Bounty payout failed: ' . $wpdb->last_error);
            IB_Log::admin('bounty', sprintf('Imperial Bounty for %s failed; pilots not yet paid will be paid if daily maintenance is run again today. Details are in the PHP error log.', $day));
            $out['ok'] = false;
            return $out;
        }
        $out['pilots'] = (int) $paid;
        $out['credits'] = $out['pilots'] * $amount;
        if ($out['pilots'] > 0) {
            IB_Log::news('bounty', sprintf('The Crown paid the Imperial Bounty of %s credits to %s %s.',
                IB_Game::fmt($amount), IB_Game::fmt($out['pilots']), $out['pilots'] === 1 ? 'pilot' : 'pilots'));
        }
        return $out;
    }

    public static function create($user_id, $alias, $ship_name) {
        global $wpdb;
        $alias = IB_Names::clean($alias);
        $ship_name = IB_Names::clean($ship_name);
        if (!IB_Settings::get('allow_new_players')) throw new IB_Game_Exception('New pilot registration is currently closed.');
        if (!IB_Game::universe_exists()) throw new IB_Game_Exception('The universe has not been created yet. Ask an administrator to forge the universe.');
        if (self::by_user($user_id)) throw new IB_Game_Exception('You already have a pilot.');
        if (IB_Names::length($alias) < 3 || IB_Names::length($alias) > 41) throw new IB_Game_Exception('Your alias must be 3 to 41 characters.');
        if (IB_Names::is_reserved($alias)) throw new IB_Game_Exception('That name belongs to the Imperium. Choose another alias.');
        if (IB_Names::length($ship_name) > 41) throw new IB_Game_Exception('Ship names can be up to 41 characters.');
        if ($wpdb->get_var($wpdb->prepare('SELECT id FROM ' . IB_DB::t('players') . ' WHERE alias_name = %s', $alias))) {
            throw new IB_Game_Exception('That alias is already taken.');
        }
        if ($ship_name === '') $ship_name = IB_Names::cut($alias . "'s Ship", 41);
        $s = IB_Settings::all();
        // The WordPress display name is deliberately not copied here. Other players only ever see
        // the alias, so the game has no use for it, and the less of it the game table holds the
        // less there is to leak. The real_name column stays for sites upgrading from older
        // versions; nothing reads it.
        $inserted = $wpdb->insert(IB_DB::t('players'), [
            'user_id' => $user_id, 'alias_name' => $alias,
            'ship_name' => $ship_name, 'ship_type' => IB_Ships::DEFAULT_TYPE,
            'sector_id' => 1, 'fighters' => $s['starting_fighters'], 'shield_points' => $s['starting_shields'],
            'cargo_holds' => $s['starting_holds'], 'credits' => $s['starting_credits'],
            'turns_remaining' => $s['turns_per_day'], 'last_turn_reset' => IB_Game::today(),
            'created_at' => current_time('mysql'), 'last_seen' => current_time('mysql'),
        ]);
        // The alias and user_id columns are UNIQUE, so a registration racing another one for the
        // same alias (or a second tab) fails here rather than creating a duplicate.
        if ($inserted === false && stripos((string) $wpdb->last_error, 'Duplicate') !== false) {
            throw new IB_Game_Exception(stripos((string) $wpdb->last_error, 'user_id') !== false
                ? 'You already have a pilot.' : 'That alias is already taken.');
        }
        IB_DB::check($inserted, 'player create');
        $id = (int) $wpdb->insert_id;
        if ($id <= 0) IB_DB::check(false, 'player create: no insert id');
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
        if ($p->turns_remaining === 0) {
            IB_Log::news('turns_spent', sprintf('%s %s has flown the last turn of the day.',
                IB_Game::rank_title($p->experience), $p->alias_name));
        }
    }

    /** Atomically deducts credits or throws. */
    public static function spend_credits($p, $amount) {
        // Guard the arithmetic before rounding it. ceil() returns a float, and casting a float
        // bigger than PHP_INT_MAX back to int gives zero or a negative - which would make the
        // purchase free. A price that large cannot be paid, so say so.
        if (!is_numeric($amount) || !is_finite((float) $amount)) {
            throw new IB_Game_Exception('That price is not a number.');
        }
        if ((float) $amount > (float) PHP_INT_MAX) {
            throw new IB_Game_Exception(sprintf('That costs more credits than exist; you have %s.', IB_Game::fmt($p->credits)));
        }
        $amount = (int) ceil((float) $amount);
        if ($amount <= 0) return;
        global $wpdb;
        $ok = $wpdb->query($wpdb->prepare(
            'UPDATE ' . IB_DB::t('players') . ' SET credits = credits - %d WHERE id = %d AND credits >= %d',
            $amount, $p->id, $amount
        ));
        if (!$ok) throw new IB_Game_Exception(sprintf('That costs %s credits; you only have %s.', IB_Game::fmt($amount), IB_Game::fmt($p->credits)));
        $p->credits -= $amount;
    }

    /**
     * Adds (or subtracts) values from integer columns, announcing any promotion that results.
     *
     * For gains, and for losses the pilot has no say in (combat damage, a tithe). A loss can never
     * take a column below zero: it saturates there, so two hits landing at once cannot leave a
     * negative count behind. Alignment is the exception, as it is meant to go negative. Anything a
     * pilot spends or hands over goes through spend() instead, which refuses rather than saturates.
     */
    public static function add($p, array $deltas) {
        global $wpdb;
        $rank_before = isset($deltas['experience']) ? IB_Game::rank_title($p->experience) : null;
        $sets = [];
        foreach ($deltas as $col => $delta) {
            if (!in_array($col, self::COUNTERS, true)) continue;
            $delta = (int) $delta;
            if ($col === 'alignment' || $delta >= 0) {
                $sets[] = $wpdb->prepare("$col = $col + %d", $delta);
                $p->$col = (int) $p->$col + $delta;
            } else {
                $sets[] = $wpdb->prepare("$col = GREATEST(0, $col + %d)", $delta);
                $p->$col = max(0, (int) $p->$col + $delta);
            }
        }
        if ($sets) {
            IB_DB::check($wpdb->query('UPDATE ' . IB_DB::t('players') . ' SET ' . implode(', ', $sets) . $wpdb->prepare(' WHERE id = %d', $p->id)), 'player add');
        }
        if ($rank_before !== null) self::announce_promotion($p, $rank_before);
    }

    /** SQL for "the holds this pilot uses", after optional changes to each cargo column. */
    private static function holds_used_sql(array $deltas) {
        global $wpdb;
        $parts = [];
        foreach (array_keys(IB_Game::COMMODITIES) as $key) {
            $parts[] = $wpdb->prepare("($key + %d)", (int) ($deltas[$key] ?? 0));
        }
        $parts[] = $wpdb->prepare('CEIL((colonists + %d) / %d)', (int) ($deltas['colonists'] ?? 0), self::colonists_per_hold());
        return '(' . implode(' + ', $parts) . ')';
    }

    /**
     * Takes $costs from the pilot and grants $gains, in ONE guarded UPDATE, or changes nothing and
     * throws. The guard sits in the WHERE clause, so it is checked against the row as it is at
     * that instant rather than against the copy this request loaded: two requests fired together
     * cannot both spend the same cargo, fighters or credits.
     *
     *  - every cost needs at least that much in the column;
     *  - a gain of cargo or colonists needs the room for it in the holds, measured after the change;
     *  - $opts['max'] = [col => ceiling] keeps a column at or below a ceiling after the change;
     *  - $opts['eq'] = [col => value] requires a column to still hold the value this request saw.
     *
     * @param string $message what to tell the pilot if the guard fails
     */
    public static function spend($p, array $costs, array $gains = [], array $opts = [], $message = null) {
        global $wpdb;
        $sets = [];
        $where = [$wpdb->prepare('id = %d', $p->id)];
        $deltas = [];
        foreach ($costs as $col => $n) {
            if (!in_array($col, self::COUNTERS, true)) throw new LogicException("spend(): $col is not a counter");
            $n = (int) $n;
            if ($n < 0) throw new LogicException('spend(): a cost cannot be negative');
            if ($n === 0) continue;
            $deltas[$col] = ($deltas[$col] ?? 0) - $n;
            $where[] = $wpdb->prepare("$col >= %d", $n);
        }
        foreach ($gains as $col => $n) {
            if (!in_array($col, self::COUNTERS, true)) throw new LogicException("spend(): $col is not a counter");
            $deltas[$col] = ($deltas[$col] ?? 0) + (int) $n;
        }
        foreach ($deltas as $col => $d) {
            if ($d !== 0) $sets[] = $wpdb->prepare("$col = $col + %d", $d);
        }
        if (!$sets) return;
        $cargo = array_merge(array_keys(IB_Game::COMMODITIES), ['colonists']);
        foreach ($cargo as $col) {
            if (($deltas[$col] ?? 0) > 0) {
                $where[] = $wpdb->prepare(self::holds_used_sql($deltas) . ' <= cargo_holds + %d', (int) ($deltas['cargo_holds'] ?? 0));
                break;
            }
        }
        foreach ((array) ($opts['max'] ?? []) as $col => $max) {
            if (!in_array($col, self::COUNTERS, true)) throw new LogicException("spend(): $col is not a counter");
            $where[] = $wpdb->prepare("$col + %d <= %d", (int) ($deltas[$col] ?? 0), (int) $max);
        }
        foreach ((array) ($opts['eq'] ?? []) as $col => $value) {
            if (!in_array($col, self::COUNTERS, true)) throw new LogicException("spend(): $col is not a counter");
            $where[] = $wpdb->prepare("$col = %d", (int) $value);
        }
        $rank_before = isset($deltas['experience']) ? IB_Game::rank_title($p->experience) : null;
        $ok = IB_DB::check($wpdb->query('UPDATE ' . IB_DB::t('players') . ' SET ' . implode(', ', $sets) . ' WHERE ' . implode(' AND ', $where)), 'player spend');
        if (!$ok) {
            self::refresh($p);
            throw new IB_Game_Exception($message ?: 'Your ship\'s manifest changed while that order was going through. Check what you have and try again.');
        }
        foreach ($deltas as $col => $d) $p->$col = (int) $p->$col + $d;
        if ($rank_before !== null) self::announce_promotion($p, $rank_before);
    }

    private static function announce_promotion($p, $rank_before) {
        $rank_after = IB_Game::rank_title($p->experience);
        if ($rank_after !== $rank_before) {
            IB_Log::news('promotion', sprintf('%s has been raised to %s.', $p->alias_name, $rank_after));
            IB_Messages::system($p->id, 'You have been promoted',
                sprintf('The Chancery has raised you from %s to %s. Congratulations, %s.', $rank_before, $rank_after, $rank_after));
        }
    }

    /**
     * Writes absolute values to the pilot's row, charging $cost credits, only if every column in
     * $expect still holds what this request saw. Used where a change replaces values outright (a
     * new hull) and a relative update cannot express it: without the check, a trade landing at the
     * same moment would be overwritten with the old cargo, duplicating it.
     */
    public static function set_if_unchanged($p, array $set, array $expect, $cost = 0, $message = null) {
        global $wpdb;
        $allowed = array_merge(self::COUNTERS, ['ship_type']);
        $sets = [];
        $where = [$wpdb->prepare('id = %d', $p->id)];
        foreach ($set as $col => $value) {
            if (!in_array($col, $allowed, true)) throw new LogicException("set_if_unchanged(): $col is not allowed");
            $sets[] = is_int($value) ? $wpdb->prepare("$col = %d", $value) : $wpdb->prepare("$col = %s", (string) $value);
        }
        foreach ($expect as $col => $value) {
            if (!in_array($col, $allowed, true)) throw new LogicException("set_if_unchanged(): $col is not allowed");
            $where[] = is_int($value) ? $wpdb->prepare("$col = %d", $value) : $wpdb->prepare("$col = %s", (string) $value);
        }
        $cost = (int) $cost;
        if ($cost > 0) {
            if (isset($set['credits'])) throw new LogicException('set_if_unchanged(): credits set and charged');
            $sets[] = $wpdb->prepare('credits = credits - %d', $cost);
            $where[] = $wpdb->prepare('credits >= %d', $cost);
        }
        $ok = IB_DB::check($wpdb->query('UPDATE ' . IB_DB::t('players') . ' SET ' . implode(', ', $sets) . ' WHERE ' . implode(' AND ', $where)), 'player set');
        if (!$ok) {
            self::refresh($p);
            throw new IB_Game_Exception($message ?: 'Your ship changed while that order was going through. Check what you have and try again.');
        }
        foreach ($set as $col => $value) $p->$col = $value;
        if ($cost > 0) $p->credits = (int) $p->credits - $cost;
    }

    public static function update($p, array $data) {
        global $wpdb;
        IB_DB::check($wpdb->update(IB_DB::t('players'), $data, ['id' => $p->id]), 'player update');
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
        if (!$first) return [];
        self::report_exploration($p);
        return IB_Discovery::first_visit($p);
    }

    /** Every 50th sector visited is worth a line in the Gazette. */
    private static function report_exploration($p) {
        global $wpdb;
        $visited = (int) $wpdb->get_var($wpdb->prepare(
            'SELECT COUNT(*) FROM ' . IB_DB::t('explored') . ' WHERE player_id = %d AND visited = 1', $p->id
        ));
        if ($visited > 0 && $visited % 50 === 0) {
            IB_Log::news('explore', sprintf('%s %s has now visited %s sectors of the galaxy.',
                IB_Game::rank_title($p->experience), $p->alias_name, IB_Game::fmt($visited)));
        }
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
        // Whatever guards the sector gets its say first: only a ship that survives finds the strongbox.
        if (empty($p->destroyed)) {
            $box = IB_Ports::claim_strongbox($p);
            if ($box) $msgs[] = ['success', $box];
        }
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

    /**
     * Autopilot to a sector and, if asked, dock or land on arrival, so a trade run or a visit to
     * one of your worlds is one click rather than plot, engage, then dock or land. Lands the
     * player on the page that matches where they ended up.
     *
     * @param  int $land_planet_id a planet to land on instead of docking, when it is still there
     * @return array [messages, page key to redirect to]
     */
    public static function fly($p, $target, $dock = false, $land_planet_id = 0) {
        $msgs = self::autopilot($p, $target);
        $arrived = (int) $p->sector_id === (int) $target;
        if ($land_planet_id) {
            if (!$arrived || !empty($p->destroyed)) return [$msgs, 'sector'];
            try {
                $planet = IB_Planets::land($p, (int) $land_planet_id);
                $msgs[] = ['success', sprintf('Landed on %s.', $planet->planet_name)];
                return [$msgs, 'planet'];
            } catch (IB_Game_Exception $e) {
                // Arriving is still worth something, so report why the landing failed and stop there.
                $msgs[] = ['warning', $e->getMessage()];
                return [$msgs, 'sector'];
            }
        }
        if (!$dock || !$arrived || !empty($p->destroyed)) {
            return [$msgs, 'sector'];
        }
        $port = IB_Ports::in_sector($p->sector_id);
        if (!$port) {
            $msgs[] = ['warning', 'There is no port in this sector to dock at.'];
            return [$msgs, 'sector'];
        }
        $cost = (int) IB_Settings::get('dock_turn_cost');
        if ((int) $p->docked_port_id !== (int) $port->id && (int) $p->turns_remaining < $cost) {
            $msgs[] = ['warning', sprintf('You arrived at %s but have no turn left to dock.', $port->port_name)];
            return [$msgs, 'sector'];
        }
        try {
            IB_Ports::dock($p);
        } catch (IB_Game_Exception $e) {
            $msgs[] = [$e->type ?? 'error', $e->getMessage()];    // customs at a smugglers' den
            return [$msgs, 'sector'];
        }
        $msgs[] = ['success', IB_Ports::docked_message($p, $port)];
        return [$msgs, 'port'];
    }

    public static function jettison($p, $commodity, $qty) {
        $jettisonable = array_merge(array_keys(IB_Game::COMMODITIES), ['colonists']);
        if (!in_array($commodity, $jettisonable, true)) throw new IB_Game_Exception('Invalid cargo.');
        $qty = min((int) $qty, (int) $p->$commodity);
        if ($qty <= 0) throw new IB_Game_Exception('Nothing to jettison.');
        if ($commodity === 'colonists') {
            self::spend($p, ['colonists' => $qty], ['alignment' => -$qty]);
            return sprintf('You released %s colonists into space. The Chancery will hear of this.', IB_Game::fmt($qty));
        }
        self::spend($p, [$commodity => $qty]);
        return sprintf('Jettisoned %s units of %s.', IB_Game::fmt($qty), IB_Game::label($commodity));
    }

    public static function rename_ship($p, $name) {
        $name = IB_Names::clean($name);
        if ($name === '' || IB_Names::length($name) > 41) throw new IB_Game_Exception('Ship names must be 1 to 41 characters.');
        self::update($p, ['ship_name' => $name]);
        return 'Your ship is now registered as the ' . $name . '.';
    }

    public static function ship($p) {
        return IB_Ships::get($p->ship_type);
    }

    /** Settlers travel in berths, so one cargo hold carries a group of them, not one person. */
    public static function colonists_per_hold() {
        return max(1, (int) IB_Settings::get('colonists_per_hold'));
    }

    /** The holds a given number of colonists occupies; a part-filled hold still counts as one. */
    public static function colonist_holds($colonists) {
        return (int) ceil(max(0, (int) $colonists) / self::colonists_per_hold());
    }

    /** How many more colonists will fit: the empty holds, plus any space left in a part-filled one. */
    public static function colonist_room($p) {
        $aboard = (int) $p->colonists;
        $slack = self::colonist_holds($aboard) * self::colonists_per_hold() - $aboard;
        return self::holds_free($p) * self::colonists_per_hold() + $slack;
    }

    public static function holds_used($p) {
        $used = self::colonist_holds($p->colonists);
        foreach (array_keys(IB_Game::COMMODITIES) as $key) $used += (int) $p->$key;
        return $used;
    }

    public static function holds_free($p) {
        return max(0, (int) $p->cargo_holds - self::holds_used($p));
    }

    public static function net_worth($p) {
        global $wpdb;
        $planets = $wpdb->get_row($wpdb->prepare(self::planet_worth_sql() . ' WHERE owner_player_id = %d', $p->id));
        return self::worth_of($p, $planets, IB_Settings::all());
    }

    /** One aggregate row per owner (or one row, filtered by the caller) of everything on their worlds. */
    private static function planet_worth_sql() {
        return 'SELECT owner_player_id, COALESCE(SUM(bastion_vault),0) cr, COALESCE(SUM(fighters),0) f, COALESCE(SUM(colonists),0) col,
                    COALESCE(SUM(ore),0) o, COALESCE(SUM(organics),0) g, COALESCE(SUM(equipment),0) e,
                    COALESCE(SUM(vending_machines),0) vm, COALESCE(SUM(vending_fund),0) vf
             FROM ' . IB_DB::t('planets');
    }

    /** Net worth from a pilot row and the aggregate of their worlds (null when they own none). */
    private static function worth_of($p, $planets, array $s) {
        $c = IB_Game::COMMODITIES;
        $ship = self::ship($p);
        $worth = (int) $p->credits
            + $p->fighters * $s['price_fighter'] + $p->shield_points * $s['price_shield']
            + (int) ($ship['price'] / 2) + $p->worldseeds * $s['price_worldseed']
            + (int) $p->vending_machines * $s['price_vending'];
        foreach (IB_Game::COMMODITIES as $key => $def) $worth += (int) $p->$key * $def['base'];
        if ($planets) {
            $worth += $planets->cr + $planets->f * $s['price_fighter'] + $planets->col * $s['price_colonist']
                + $planets->o * $c['ore']['base'] + $planets->g * $c['organics']['base'] + $planets->e * $c['equipment']['base']
                + $planets->vm * $s['price_vending'] + $planets->vf;
        }
        return $worth;
    }

    /**
     * Every pilot with their net worth in $pilot->worth, in two queries however many pilots there
     * are, rather than one query per pilot. The public home page reads this through a short cache
     * ($cached = true), so an anonymous visitor cannot make the database do work in proportion to
     * the number of pilots on every request.
     *
     * @return object[] pilot rows keyed by id, each with ->worth
     */
    public static function all_with_worth($cached = false) {
        global $wpdb;
        $key = 'ib_pilot_worth';
        if ($cached) {
            // Cached as arrays of scalars, never objects, so nothing an options read unserialises
            // can be more than plain data.
            $hit = get_transient($key);
            if (is_array($hit)) {
                $out = [];
                foreach ($hit as $id => $row) {
                    if (is_array($row)) $out[(int) $id] = (object) $row;
                }
                return $out;
            }
        }
        $s = IB_Settings::all();
        $planets = [];
        foreach ((array) $wpdb->get_results(self::planet_worth_sql() . ' WHERE owner_player_id > 0 GROUP BY owner_player_id') as $row) {
            $planets[(int) $row->owner_player_id] = $row;
        }
        $out = [];
        foreach ((array) $wpdb->get_results('SELECT id, alias_name, ship_type, team_id, experience, alignment, kills, deaths, credits, fighters, shield_points,
                worldseeds, vending_machines, ' . implode(', ', array_keys(IB_Game::COMMODITIES)) . ' FROM ' . IB_DB::t('players')) as $pl) {
            $pl->worth = self::worth_of($pl, $planets[(int) $pl->id] ?? null, $s);
            $out[(int) $pl->id] = $pl;
        }
        if ($cached) set_transient($key, array_map('get_object_vars', $out), 5 * MINUTE_IN_SECONDS);
        return $out;
    }

    /** Ship destroyed: the pilot ejects to Aurelia in a replacement Freetrader and is grounded for the rest of the day. */
    public static function destroy($p, $reason) {
        $s = IB_Settings::all();
        self::update($p, [
            'ship_type' => IB_Ships::DEFAULT_TYPE, 'cargo_holds' => $s['starting_holds'], 'fighters' => 0, 'shield_points' => 0,
            'ore' => 0, 'organics' => 0, 'equipment' => 0, 'isotopes' => 0, 'medicine' => 0, 'luxuries' => 0,
            'colonists' => 0, 'worldseeds' => 0, 'survey_drones' => 0, 'vending_machines' => 0,
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
