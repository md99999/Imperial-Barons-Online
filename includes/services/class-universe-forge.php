<?php
if (!defined('ABSPATH')) exit;

/**
 * Generates a random universe.
 *
 * Sectors are scattered across a 1000x1000 plane (which gives the Galaxy Map its layout)
 * and linked to their nearest neighbours, 1-6 warps each, the classic way. The graph is
 * made fully connected, then a few lanes are made one-way where that keeps every sector
 * reachable from, and able to return to, Aurelia.
 */
class IB_Universe_Forge {
    const SECTORS = 500;
    const MAX_WARPS = 6;
    const MIN_SECTORS = 100;
    const MAX_SECTORS = 5000;

    const NEBULAE = ['The Marches of Veyl', "Emperor's Reach", 'The Ashen Palatinate', 'Drowned Crown Nebula', 'The Gilded Shoals',
        'Vestry Drift', 'The Oathbreaker Rift', 'Cindermere', 'The Tollgate Expanse', 'Serewyn Deep', 'The Hollow Throne', 'Kestrian Verge',
        'The Pale Duchy', 'Ironvale Cloud', "Regent's Folly", 'The Sable Wastes', 'Marrowgate', 'The Amber Concord', 'Duskmantle',
        'The Shattered Fief', 'Orrenhold Nebula', 'The Weeping Veil', 'Castellan Reach', 'Thornwick Cluster', 'The Silent Crownlands',
        'Varrow Straits', 'The Mourning Belt', 'Highspire Drift', 'The Last Tithe', 'Brannoch Expanse', 'The Heretic Shelf',
        'Ysolde Nebula', 'The Forsaken Marches', 'Goldwater Basin', 'The Iron Covenant', 'Wyrmtail Shoal', 'Coldharrow',
        'The Exiled Barony', 'Starfall Demesne', 'The Outer Dominions'];

    public static function defaults() {
        return ['sectors' => self::SECTORS, 'port_density' => 40, 'planet_density' => 12, 'faction_strength' => 100, 'oneway_percent' => 4, 'seed' => 0];
    }

    /** @return array summary of what was created */
    public function generate($opts = []) {
        $o = wp_parse_args($opts, self::defaults());
        $n = max(self::MIN_SECTORS, min(self::MAX_SECTORS, (int) $o['sectors']));
        $fed = max(1, min((int) IB_Settings::get('core_sectors'), (int) ($n / 10)));
        $seed = (int) $o['seed'] ?: mt_rand(1, 999999999);
        mt_srand($seed);
        if (function_exists('set_time_limit')) @set_time_limit(600);
        $started = microtime(true);

        self::wipe();

        $sectors = $this->place_sectors($n, $fed);
        $links = $this->link_neighbours($sectors);
        $this->connect_components($sectors, $links);
        $graph = [];
        foreach ($links as $id => $set) $graph[$id] = array_keys($set);
        $one_way = $this->make_one_way($graph, $fed, (float) $o['oneway_percent']);
        $nebula = $this->assign_nebulae($sectors, $fed);

        $this->insert_sectors($sectors, $nebula, $fed);
        $warp_count = $this->insert_warps($graph);

        $dist = IB_Pathfinder::distances($graph, 1);
        $open = array_values(array_filter(array_keys($sectors), function ($id) use ($fed) { return $id > $fed; }));
        $drydock = $this->pick_drydock($dist, $fed);
        $ports = $this->create_ports($sectors, $drydock, (int) $o['port_density']);
        $planets = $this->create_planets($open, (int) $o['planet_density']);

        $home = 0;
        foreach ($dist as $id => $d) {
            if ($id > $fed && ($home === 0 || $d > $dist[$home])) $home = $id;
        }
        $fleets = IB_Factions::seed($open, $home, $graph, (int) $o['faction_strength']);

        IB_Pathfinder::flush();
        $summary = [
            'seed' => $seed, 'sectors' => $n, 'core' => $fed, 'warps' => $warp_count, 'one_way' => $one_way,
            'ports' => $ports, 'planets' => $planets, 'fleets' => $fleets, 'drydock' => $drydock,
            'vraxori_home' => $home, 'generated_at' => current_time('mysql'),
            'seconds' => round(microtime(true) - $started, 1),
        ];
        update_option('ib_universe', $summary, false);
        IB_Log::news('forge', sprintf('The Universe Forge has spoken! A new universe of %s sectors has been born. The Imperial Drydock lies in sector %d.', IB_Game::fmt($n), $drydock));
        IB_Log::admin('forge', sprintf('Universe generated: %d sectors, %d warps, %d ports, %d planets, %d fleets (seed %d).', $n, $warp_count, $ports, $planets, $fleets, $seed));
        return $summary;
    }

    /** Empties every game table (keeps the admin log). */
    public static function wipe() {
        global $wpdb;
        foreach (IB_DB::TABLES as $table) {
            if ($table === 'admin_log') continue;
            if ($wpdb->query('TRUNCATE TABLE ' . IB_DB::t($table)) === false) {
                $wpdb->query('DELETE FROM ' . IB_DB::t($table));
            }
        }
        delete_option('ib_universe');
        IB_Pathfinder::flush();
    }

    /**
     * Jittered grid placement: one sector per grid cell, randomly offset.
     * The Imperial Core takes the sectors nearest the centre; the rest get shuffled numbers.
     */
    private function place_sectors($n, $fed) {
        $cols = (int) ceil(sqrt($n));
        $rows = (int) ceil($n / $cols);
        $cw = 1000 / $cols;
        $ch = 1000 / $rows;
        $slots = range(0, $cols * $rows - 1);
        shuffle($slots);
        $points = [];
        foreach (array_slice($slots, 0, $n) as $slot) {
            $c = $slot % $cols;
            $r = intdiv($slot, $cols);
            $points[] = [
                'x' => round(($c + mt_rand(15, 85) / 100) * $cw, 1),
                'y' => round(($r + mt_rand(15, 85) / 100) * $ch, 1),
                'c' => $c, 'r' => $r,
            ];
        }
        usort($points, function ($a, $b) {
            return (($a['x'] - 500) ** 2 + ($a['y'] - 500) ** 2) <=> (($b['x'] - 500) ** 2 + ($b['y'] - 500) ** 2);
        });
        $ids = range($fed + 1, $n);
        shuffle($ids);
        $sectors = [];
        foreach ($points as $i => $pt) {
            $sectors[$i < $fed ? $i + 1 : $ids[$i - $fed]] = $pt;
        }
        ksort($sectors);
        return $sectors;
    }

    private static function dist2($a, $b) {
        return ($a['x'] - $b['x']) ** 2 + ($a['y'] - $b['y']) ** 2;
    }

    /** Links each sector to some of its nearest neighbours (bidirectional). */
    private function link_neighbours($sectors) {
        $grid = [];
        foreach ($sectors as $id => $s) $grid[$s['c']][$s['r']] = $id;
        $links = array_fill_keys(array_keys($sectors), []);
        $order = array_keys($sectors);
        shuffle($order);
        $weights = [1 => 5, 2 => 25, 3 => 35, 4 => 25, 5 => 10];

        foreach ($order as $id) {
            $want = $this->weighted($weights);
            if (count($links[$id]) >= $want) continue;
            $s = $sectors[$id];
            $cands = [];
            for ($dc = -2; $dc <= 2; $dc++) {
                for ($dr = -2; $dr <= 2; $dr++) {
                    $other = $grid[$s['c'] + $dc][$s['r'] + $dr] ?? null;
                    if ($other !== null && $other !== $id) $cands[$other] = self::dist2($s, $sectors[$other]);
                }
            }
            asort($cands);
            foreach ($cands as $other => $d) {
                if (count($links[$id]) >= $want) break;
                if (isset($links[$id][$other]) || count($links[$other]) >= self::MAX_WARPS) continue;
                $links[$id][$other] = true;
                $links[$other][$id] = true;
            }
        }
        return $links;
    }

    private function weighted(array $weights) {
        $roll = mt_rand(1, array_sum($weights));
        foreach ($weights as $value => $w) {
            if (($roll -= $w) <= 0) return $value;
        }
        return key($weights);
    }

    /** Joins any isolated clusters to the main body through their closest pair of sectors. */
    private function connect_components($sectors, &$links) {
        while (true) {
            $seen = [];
            $components = [];
            foreach (array_keys($sectors) as $start) {
                if (isset($seen[$start])) continue;
                $comp = [];
                $stack = [$start];
                $seen[$start] = true;
                while ($stack) {
                    $node = array_pop($stack);
                    $comp[] = $node;
                    foreach (array_keys($links[$node]) as $next) {
                        if (!isset($seen[$next])) { $seen[$next] = true; $stack[] = $next; }
                    }
                }
                $components[] = $comp;
            }
            if (count($components) <= 1) return;

            // Join the smallest component to its nearest outside sector, preferring sectors with free warp slots.
            usort($components, function ($a, $b) { return count($a) <=> count($b); });
            $small = array_flip($components[0]);
            $best = null;
            foreach ($components[0] as $a) {
                foreach ($sectors as $b => $sb) {
                    if (isset($small[$b])) continue;
                    $full = (count($links[$a]) >= self::MAX_WARPS || count($links[$b]) >= self::MAX_WARPS) ? 1e9 : 0;
                    $d = self::dist2($sectors[$a], $sb) + $full;
                    if ($best === null || $d < $best[2]) $best = [$a, $b, $d];
                }
            }
            $links[$best[0]][$best[1]] = true;
            $links[$best[1]][$best[0]] = true;
        }
    }

    private static function strongly_connected($graph, $count) {
        return count(IB_Pathfinder::distances($graph, 1)) === $count
            && count(IB_Pathfinder::distances(IB_Pathfinder::reverse($graph), 1)) === $count;
    }

    /** Converts a few two-way lanes outside the Imperial Core into one-way lanes. */
    private function make_one_way(&$graph, $fed, $percent) {
        $pairs = [];
        foreach ($graph as $a => $tos) {
            foreach ($tos as $b) {
                if ($a < $b && $a > $fed && $b > $fed) $pairs[] = [$a, $b];
            }
        }
        shuffle($pairs);
        $target = min(300, (int) round(count($pairs) * $percent / 100));
        $count = count($graph);
        $made = 0;
        foreach ($pairs as $pair) {
            if ($made >= $target) break;
            if (mt_rand(0, 1)) $pair = [$pair[1], $pair[0]];
            list($from, $to) = $pair; // remove from -> to, leaving only to -> from
            if (count($graph[$from]) < 2) continue;
            $before = $graph[$from];
            $graph[$from] = array_values(array_diff($graph[$from], [$to]));
            if (self::strongly_connected($graph, $count)) {
                $made++;
            } else {
                $graph[$from] = $before;
            }
        }
        return $made;
    }

    private function assign_nebulae($sectors, $fed) {
        $open = array_filter(array_keys($sectors), function ($id) use ($fed) { return $id > $fed; });
        $seed_count = max(4, min(count(self::NEBULAE), (int) round(count($sectors) / 80)));
        $seed_ids = (array) array_rand(array_flip($open), min($seed_count, count($open)));
        $names = self::NEBULAE;
        shuffle($names);
        $nebula = [];
        foreach ($sectors as $id => $s) {
            if ($id <= $fed) { $nebula[$id] = 'The Imperial Core'; continue; }
            $best = null;
            foreach ($seed_ids as $i => $sid) {
                $d = self::dist2($s, $sectors[$sid]);
                if ($best === null || $d < $best[1]) $best = [$i, $d];
            }
            $nebula[$id] = $names[$best[0] % count($names)];
        }
        return $nebula;
    }

    private function insert_rows($table, $columns, $placeholders, $rows) {
        global $wpdb;
        foreach (array_chunk($rows, 500) as $chunk) {
            $values = [];
            foreach ($chunk as $row) $values[] = $wpdb->prepare('(' . $placeholders . ')', $row);
            $wpdb->query('INSERT INTO ' . IB_DB::t($table) . ' (' . $columns . ') VALUES ' . implode(',', $values));
        }
    }

    private function insert_sectors($sectors, $nebula, $fed) {
        $rows = [];
        foreach ($sectors as $id => $s) {
            $beacon = $id === 1 ? 'Aurelia - Seat of the Imperial Throne' : ($id <= $fed ? "Imperial Core - the Crown's Peace is enforced" : '');
            $rows[] = [$id, $s['x'], $s['y'], $nebula[$id], $id <= $fed ? 1 : 0, $beacon];
        }
        $this->insert_rows('sectors', 'id, x, y, nebula, is_core, beacon', '%d, %f, %f, %s, %d, %s', $rows);
    }

    private function insert_warps($graph) {
        $rows = [];
        foreach ($graph as $from => $tos) {
            foreach ($tos as $to) $rows[] = [$from, $to];
        }
        $this->insert_rows('warps', 'from_sector, to_sector', '%d, %d', $rows);
        return count($rows);
    }

    /** The Imperial Drydock sits a moderate distance from Aurelia: far enough to be a journey, close enough to reach. */
    private function pick_drydock($dist, $fed) {
        $cands = [];
        foreach ($dist as $id => $d) {
            if ($id > $fed && $d >= 4 && $d <= 8) $cands[] = $id;
        }
        if (!$cands) {
            foreach ($dist as $id => $d) if ($id > $fed) $cands[] = $id;
        }
        return $cands[array_rand($cands)];
    }

    private static function port_name() {
        $pre = ['Ash', 'Brann', 'Cael', 'Dun', 'Esk', 'Fal', 'Grim', 'Hal', 'Istr', 'Kest', 'Lor', 'Marr', 'Nor', 'Orr', 'Pell', 'Rav', 'Sev', 'Thal', 'Vor', 'Wess', 'Yar', 'Zan'];
        $mid = ['a', 'e', 'i', 'o', 'en', 'or', 'an', 'ev', ''];
        $suf = ['wick', 'mere', 'holt', 'gard', 'ford', 'mont', 'haven', 'ley', 'stead', 'more', 'burg', 'wyn'];
        $kind = ['Freeport', 'Guildhall', 'Toll Station', 'Exchange', 'Bourse', 'Entrepot', 'Staple Market', 'Customs House', 'Wharf', 'Charter Post'];
        return ucfirst($pre[array_rand($pre)] . $mid[array_rand($mid)] . $suf[array_rand($suf)]) . ' ' . $kind[array_rand($kind)];
    }

    private function create_ports($sectors, $drydock, $density) {
        $rows = [[1, 'The Aurelian Armory', IB_Ports::ARMORY, 0, 0, 0, 0, 0, 0], [$drydock, 'The Imperial Drydock', IB_Ports::SHIPYARD, 0, 0, 0, 0, 0, 0]];
        $class_weights = [1 => 15, 2 => 15, 3 => 15, 4 => 12, 5 => 12, 6 => 12, 7 => 9, 8 => 10];
        foreach (array_keys($sectors) as $id) {
            if ($id === 1 || $id === $drydock || mt_rand(1, 100) > $density) continue;
            $row = [$id, self::port_name(), $this->weighted($class_weights)];
            for ($i = 0; $i < 3; $i++) {
                $max = mt_rand(100, 350) * 10;
                $row[] = (int) ($max * mt_rand(50, 100) / 100);
                $row[] = $max;
            }
            $rows[] = $row;
        }
        $this->insert_rows('ports', 'sector_id, port_name, port_class, ore_qty, ore_max, org_qty, org_max, equ_qty, equ_max',
            '%d, %s, %d, %d, %d, %d, %d, %d, %d', $rows);
        return count($rows);
    }

    private static function planet_name() {
        $a = ['Ael', 'Bry', 'Cor', 'Dav', 'Ery', 'Gal', 'Hes', 'Isol', 'Jor', 'Kal', 'Lys', 'Mael', 'Nym', 'Ost', 'Perr', 'Quel', 'Syl', 'Tor', 'Vael', 'Wyr', 'Xer'];
        $b = ['a', 'ine', 'on', 'eth', 'aris', 'ion', 'ia', 'ane', 'ora', 'ith'];
        $c = ['', '', "'s Rest", ' Secundus', ' Tertius', "'s Crown", ' Minor', ' Regalis'];
        return $a[array_rand($a)] . $b[array_rand($b)] . $c[array_rand($c)];
    }

    private function create_planets($open, $density) {
        $now = current_time('mysql');
        $rows = [[1, 'Aurelia Prime', IB_Planets::CROWN_WORLD, 0, 0, 0, 0, 0, $now]];
        $count = min(count($open), (int) round(count($open) * $density / 100));
        if ($count > 0) {
            foreach ((array) array_rand(array_flip($open), $count) as $sid) {
                $rows[] = [$sid, self::planet_name(), IB_Planets::random_class(), 0, mt_rand(0, 200), mt_rand(0, 200), mt_rand(0, 200), 0, $now];
            }
        }
        $this->insert_rows('planets', 'sector_id, planet_name, planet_class, owner_player_id, ore, organics, equipment, fighters, created_at',
            '%d, %s, %s, %d, %d, %d, %d, %d, %s', $rows);
        return count($rows);
    }

    /** @return array[] list of [level, message] where level is ok|warning|error */
    public static function validate() {
        global $wpdb;
        $out = [];
        $sectors = array_map('intval', $wpdb->get_col('SELECT id FROM ' . IB_DB::t('sectors')));
        $n = count($sectors);
        if (!$n) return [['error', 'No universe exists. Forge the universe.']];
        $out[] = ['ok', sprintf('%s sectors present.', IB_Game::fmt($n))];

        $graph = IB_Pathfinder::graph();
        $known = array_flip($sectors);
        $dead = 0; $over = 0; $bad = 0;
        foreach ($sectors as $id) {
            $w = isset($graph[$id]) ? $graph[$id] : [];
            if (!$w) $dead++;
            if (count($w) > self::MAX_WARPS) $over++;
            foreach ($w as $to) if (!isset($known[$to])) $bad++;
        }
        $out[] = $dead ? ['error', "$dead sectors have no outgoing warps."] : ['ok', 'Every sector has at least one outgoing warp.'];
        $out[] = $over ? ['warning', "$over sectors have more than " . self::MAX_WARPS . ' warps.'] : ['ok', 'No sector exceeds ' . self::MAX_WARPS . ' warps.'];
        $out[] = $bad ? ['error', "$bad warps point to missing sectors."] : ['ok', 'All warps lead to real sectors.'];

        $fwd = count(IB_Pathfinder::distances($graph, 1));
        $back = count(IB_Pathfinder::distances(IB_Pathfinder::reverse($graph), 1));
        $out[] = $fwd === $n ? ['ok', 'Every sector is reachable from Aurelia.'] : ['error', ($n - $fwd) . ' sectors cannot be reached from Aurelia.'];
        $out[] = $back === $n ? ['ok', 'Every sector can find its way back to Aurelia.'] : ['error', ($n - $back) . ' sectors are one-way traps with no route back to Aurelia.'];

        $classes = $wpdb->get_results('SELECT port_class, COUNT(*) c FROM ' . IB_DB::t('ports') . ' GROUP BY port_class ORDER BY port_class');
        $parts = [];
        $has = [];
        foreach ($classes as $c) { $parts[] = IB_Ports::class_code($c->port_class) . ': ' . $c->c; $has[(int) $c->port_class] = true; }
        $out[] = ['ok', 'Ports by class - ' . implode(', ', $parts)];
        if (empty($has[IB_Ports::ARMORY])) $out[] = ['error', 'The Aurelian Armory (class 0) is missing.'];
        if (empty($has[IB_Ports::SHIPYARD])) $out[] = ['error', 'The Imperial Drydock (class 9) is missing.'];
        if (!$wpdb->get_var('SELECT id FROM ' . IB_DB::t('planets') . " WHERE planet_class = 'C'")) $out[] = ['error', 'The Crown World Aurelia Prime is missing.'];
        $orphans = (int) $wpdb->get_var('SELECT COUNT(*) FROM ' . IB_DB::t('players') . ' p LEFT JOIN ' . IB_DB::t('sectors') . ' s ON s.id = p.sector_id WHERE s.id IS NULL');
        if ($orphans) $out[] = ['error', "$orphans players are in sectors that do not exist."];
        return $out;
    }

    public static function export() {
        global $wpdb;
        $warps = [];
        foreach (IB_Pathfinder::graph() as $from => $tos) $warps[$from] = $tos;
        return [
            'exported_at' => current_time('mysql'),
            'universe' => get_option('ib_universe'),
            'sectors' => $wpdb->get_results('SELECT * FROM ' . IB_DB::t('sectors') . ' ORDER BY id', ARRAY_A),
            'warps' => $warps,
            'ports' => $wpdb->get_results('SELECT * FROM ' . IB_DB::t('ports') . ' ORDER BY sector_id', ARRAY_A),
            'planets' => $wpdb->get_results('SELECT * FROM ' . IB_DB::t('planets') . ' ORDER BY sector_id', ARRAY_A),
            'fleets' => $wpdb->get_results('SELECT * FROM ' . IB_DB::t('fleets') . ' ORDER BY sector_id', ARRAY_A),
        ];
    }
}
