<?php
if (!defined('ABSPATH')) exit;

/**
 * Ports, trading, haggling and the hardware/ship dealers.
 *
 * A trading port's class (1-8) is written as three letters giving its stance on
 * Ferrium Ore, Biostock and Machinery in that order: B = port buys, S = port sells.
 * Class 0 is the Aurelian Armory; class 9 is the Imperial Drydock.
 *
 * For a commodity the port SELLS, *_qty is stock on hand.
 * For a commodity the port BUYS, *_qty is how many units it still wants.
 * Both regenerate toward *_max every hour.
 */
class IB_Ports {
    const CLASSES = [1 => 'BBS', 2 => 'BSB', 3 => 'SBB', 4 => 'SSB', 5 => 'SBS', 6 => 'BSS', 7 => 'SSS', 8 => 'BBB'];
    const ARMORY = 0;
    const SHIPYARD = 9;
    const MAX_WORLDSEEDS_CARRIED = 5;
    /** Vending machines travel crated in the ship's stores, not its holds, up to this many. */
    const MAX_VENDING_CARRIED = 1000;

    /** A single trade worth at least this many credits is reported in the Gazette. */
    const NEWSWORTHY_TRADE = 10000;

    public static function class_code($class) {
        $class = (int) $class;
        if ($class === self::ARMORY) return 'Armory';
        if ($class === self::SHIPYARD) return 'Drydock';
        return isset(self::CLASSES[$class]) ? self::CLASSES[$class] : '???';
    }

    public static function in_sector($sector_id) {
        global $wpdb;
        return $wpdb->get_row($wpdb->prepare('SELECT * FROM ' . IB_DB::t('ports') . ' WHERE sector_id = %d', $sector_id));
    }

    public static function get($id) {
        global $wpdb;
        return $wpdb->get_row($wpdb->prepare('SELECT * FROM ' . IB_DB::t('ports') . ' WHERE id = %d', $id));
    }

    /**
     * The Crown's own ports, keyed 'armory' and 'drydock'. These are the only places hardware,
     * charts, Worldseeds and ships are sold, which the "Where do I buy...?" table lists.
     *
     * @return array [key => port row], missing keys for whatever a universe lacks
     */
    public static function special_venues() {
        global $wpdb;
        $out = [];
        $rows = $wpdb->get_results($wpdb->prepare(
            'SELECT * FROM ' . IB_DB::t('ports') . ' WHERE port_class IN (%d, %d) ORDER BY sector_id',
            self::ARMORY, self::SHIPYARD
        ));
        foreach ($rows as $port) {
            $key = (int) $port->port_class === self::ARMORY ? 'armory' : 'drydock';
            if (!isset($out[$key])) $out[$key] = $port;    // the nearest-numbered one, if ever duplicated
        }
        return $out;
    }

    public static function is_trading_port($port) {
        return $port && (int) $port->port_class >= 1 && (int) $port->port_class <= 8;
    }

    /**
     * A smugglers' den: a frontier trading port where whatever it sells can be stolen off the
     * docks for a fraction of the market price, if customs doesn't catch you docking.
     */
    public static function is_smuggler($port) {
        return self::is_trading_port($port) && !empty($port->is_smuggler);
    }

    /** How far under market a den's goods go, in percent. Never less than 50. */
    public static function smuggler_discount() {
        return max(50, min(90, (int) IB_Settings::get('smuggler_discount_percent')));
    }

    /** Units a pilot may carry off from one den visit: a share of their holds. */
    public static function smuggler_haul($p) {
        $pct = max(1, min(100, (int) IB_Settings::get('smuggler_haul_percent')));
        return max(1, (int) floor((int) $p->cargo_holds * $pct / 100));
    }

    /**
     * Records a docking at a den and returns how many this pilot has made there in the last
     * 24 hours, this one included. Kept in a transient per pilot and den.
     */
    public static function record_visit($p, $port) {
        $key = 'ib_den_' . (int) $p->id . '_' . (int) $port->id;
        $now = time();
        $list = array_values(array_filter((array) get_transient($key), function ($t) use ($now) {
            return is_int($t) && $t > $now - DAY_IN_SECONDS;
        }));
        $list[] = $now;
        set_transient($key, $list, DAY_IN_SECONDS);
        return count($list);
    }

    /** Dockings at this den in the last 24 hours, for the page. */
    public static function recent_visits($p, $port) {
        $now = time();
        return count(array_filter((array) get_transient('ib_den_' . (int) $p->id . '_' . (int) $port->id), function ($t) use ($now) {
            return is_int($t) && $t > $now - DAY_IN_SECONDS;
        }));
    }

    /** Percent chance customs catches this pilot on their next docking at the den. */
    public static function catch_chance($visits_before) {
        $over = max(0, (int) $visits_before + 1 - (int) IB_Settings::get('smuggler_free_visits'));
        $chance = (int) IB_Settings::get('smuggler_catch_percent') + $over * max(0, (int) IB_Settings::get('smuggler_repeat_step'));
        return max(0, min(80, $chance));
    }

    /** The specialist good this port deals in, or '' for none. */
    public static function specialty($port) {
        $key = ($port && isset($port->spec_commodity)) ? (string) $port->spec_commodity : '';
        return IB_Game::is_specialist($key) ? $key : '';
    }

    /** Goods this port trades: the three staples, plus its specialist good if it has one. */
    public static function goods($port) {
        if (!self::is_trading_port($port)) return [];
        $goods = IB_Game::commodities();
        $special = self::specialty($port);
        if ($special) $goods[] = $special;
        return $goods;
    }

    /** @return 'selling'|'buying'|null from the port's perspective */
    public static function mode($port, $commodity) {
        if (!self::is_trading_port($port)) return null;
        if (IB_Game::is_specialist($commodity)) {
            if (self::specialty($port) !== $commodity) return null;
            return $port->spec_mode === 'S' ? 'selling' : 'buying';
        }
        $idx = array_search($commodity, IB_Game::commodities(), true);
        if ($idx === false) return null;
        return self::CLASSES[(int) $port->port_class][$idx] === 'S' ? 'selling' : 'buying';
    }

    /** Column prefix: the staple's own prefix, or 'spec' for the port's specialist good. */
    private static function col($commodity) {
        return IB_Game::is_specialist($commodity) ? 'spec' : IB_Game::COMMODITIES[$commodity]['col'];
    }

    public static function qty($port, $commodity) {
        return (int) $port->{self::col($commodity) . '_qty'};
    }

    public static function max($port, $commodity) {
        return (int) $port->{self::col($commodity) . '_max'};
    }

    /**
     * Listed price per unit. Plentiful stock is cheap; strong demand pays well.
     * Specialist goods carry a higher 'vol', so their prices swing further either way.
     */
    public static function price($port, $commodity) {
        // At a den, what it "sells" is stolen: the cost effort is well under market. Rounded
        // down, so it is never less of a discount than the setting promises.
        if (self::mode($port, $commodity) === 'selling' && self::is_smuggler($port)) {
            return max(1, (int) floor(self::market_price($port, $commodity) * (100 - self::smuggler_discount()) / 100));
        }
        return self::market_price($port, $commodity);
    }

    /** The price an honest port with the same stock would ask or pay. */
    public static function market_price($port, $commodity) {
        $c = IB_Game::COMMODITIES[$commodity];
        $max = self::max($port, $commodity);
        $ratio = $max > 0 ? self::qty($port, $commodity) / $max : 0;
        $vol = isset($c['vol']) ? (float) $c['vol'] : 1.0;
        $selling = self::mode($port, $commodity) === 'selling';
        $deviation = $selling ? 0.25 - 0.35 * $ratio : -0.25 + 0.45 * $ratio;
        $price = $c['base'] * (1 + $vol * $deviation);
        return max(1, (int) round($price));
    }

    public static function dock($p) {
        $port = self::in_sector($p->sector_id);
        if (!$port) throw new IB_Game_Exception('There is no port in this sector.');
        if ((int) $p->docked_port_id === (int) $port->id) return $port;
        IB_Player::spend_turns($p, (int) IB_Settings::get('dock_turn_cost'));
        if (self::is_smuggler($port)) self::customs($p, $port);
        $data = ['docked_port_id' => $port->id, 'landed_planet_id' => 0];
        if (self::is_smuggler($port)) $data['smuggle_left'] = self::smuggler_haul($p);
        IB_Player::update($p, $data);
        return $port;
    }

    const DEN_KINDS = ["Smugglers' Den", 'Black Market', 'Pirate Haven', "Thieves' Wharf", 'Shadow Bazaar'];

    /**
     * Turns a share of the frontier's trading ports into smugglers' dens and renames them to
     * match. Only ports outside the Core that sell at least one good qualify. Uses mt_rand, so a
     * seeded Universe Forge run marks the same dens every time.
     *
     * @return int dens marked
     */
    public static function mark_smugglers() {
        global $wpdb;
        $pct = max(0, min(50, (int) IB_Settings::get('smuggler_ports_percent')));
        if ($pct <= 0) return 0;
        $ports = IB_DB::t('ports');
        $rows = $wpdb->get_results(
            "SELECT p.id, p.port_name, p.port_class, p.spec_mode FROM $ports p JOIN " . IB_DB::t('sectors') . ' s ON s.id = p.sector_id' .
            ' WHERE s.is_core = 0 AND p.port_class BETWEEN 1 AND 8 AND p.is_smuggler = 0 ORDER BY p.id'
        );
        $candidates = array_values(array_filter((array) $rows, function ($r) {
            return (int) $r->port_class !== 8 || $r->spec_mode === 'S';    // a den must have something to steal
        }));
        $count = (int) round(count($candidates) * $pct / 100);
        if ($count <= 0) return 0;
        for ($i = count($candidates) - 1; $i > 0; $i--) {
            $j = mt_rand(0, $i);
            $tmp = $candidates[$i]; $candidates[$i] = $candidates[$j]; $candidates[$j] = $tmp;
        }
        foreach (array_slice($candidates, 0, $count) as $r) {
            $place = strtok((string) $r->port_name, ' ');
            $name = ($place !== false ? $place . ' ' : '') . self::DEN_KINDS[mt_rand(0, count(self::DEN_KINDS) - 1)];
            $wpdb->update($ports, ['is_smuggler' => 1, 'port_name' => $name], ['id' => $r->id]);
        }
        return $count;
    }

    const FINES_FUND = 'ib_fines_fund';

    /** Credits in the Crown's Fines Fund: every customs fine paid, waiting to be won. */
    public static function fines_fund() {
        global $wpdb;
        // Read straight from the table: the fund is bumped with SQL, so a cached copy can be stale.
        return max(0, (int) $wpdb->get_var($wpdb->prepare("SELECT option_value FROM {$wpdb->options} WHERE option_name = %s", self::FINES_FUND)));
    }

    /** Adds to the fund in one UPDATE, so two fines at the same moment both count. */
    public static function add_to_fines_fund($credits) {
        global $wpdb;
        $credits = (int) $credits;
        if ($credits <= 0) return;
        add_option(self::FINES_FUND, '0', '', 'no');    // no-op once it exists
        $wpdb->query($wpdb->prepare(
            "UPDATE {$wpdb->options} SET option_value = CAST(option_value AS SIGNED) + %d WHERE option_name = %s", $credits, self::FINES_FUND
        ));
        wp_cache_delete(self::FINES_FUND, 'options');
    }

    /** What a pilot is told on docking; at a den, that they got past customs and how much they can carry off. */
    public static function docked_message($p, $port) {
        if (!self::is_smuggler($port)) return sprintf('Docked at %s.', $port->port_name);
        return sprintf('You slip past Imperial customs and tie up at %s. You can carry off up to %s units this visit.',
            $port->port_name, IB_Game::fmt((int) $p->smuggle_left));
    }

    /**
     * Imperial customs watches the dens. Each docking risks being caught: the pilot is fined a
     * share of the credits they carry and turned away without docking. Every docking past the
     * free ones in a day makes it likelier. Throws when caught.
     */
    private static function customs($p, $port) {
        $chance = self::catch_chance(self::recent_visits($p, $port));
        self::record_visit($p, $port);
        if (mt_rand(1, 100) > $chance) return;

        $credits = max(0, (int) $p->credits);
        $fine = min($credits, max(25, (int) floor($credits * max(0, (int) IB_Settings::get('smuggler_fine_percent')) / 100)));
        if ($fine > 0) {
            try {
                IB_Player::spend_credits($p, $fine);
            } catch (IB_Game_Exception $e) {
                $fine = 0;    // spent elsewhere a moment ago; the warning still stands
            }
        }
        self::add_to_fines_fund($fine);
        IB_Player::add($p, ['alignment' => -5]);
        if ($fine >= 1000) {
            IB_Log::news('smuggle', sprintf('Imperial customs caught %s %s sneaking into %s and fined them %s credits.',
                IB_Game::rank_title($p->experience), $p->alias_name, $port->port_name, IB_Game::fmt($fine)));
        }
        throw new IB_Game_Exception(sprintf(
            'Imperial customs caught you slipping into %s! You were fined %s credits, paid into the Crown\'s Fines Fund, and turned away. (Catch chance was %d%%.)',
            $port->port_name, IB_Game::fmt($fine), $chance
        ), 'warning');
    }

    public static function require_docked($p) {
        $port = self::in_sector($p->sector_id);
        if (!$port || (int) $p->docked_port_id !== (int) $port->id) {
            throw new IB_Game_Exception('You must dock at the port first.');
        }
        return $port;
    }

    /**
     * Buy from or sell to the docked port.
     * $offer is an optional haggled price per unit; 0 accepts the listed price.
     */
    public static function trade($p, $commodity, $qty, $offer = 0) {
        global $wpdb;
        if (!isset(IB_Game::COMMODITIES[$commodity])) throw new IB_Game_Exception('Unknown commodity.');
        $port = self::require_docked($p);
        $mode = self::mode($port, $commodity);
        if (!$mode) throw new IB_Game_Exception('This port does not trade in that commodity.');
        $qty = (int) $qty;
        if ($qty <= 0) throw new IB_Game_Exception('Enter a quantity greater than zero.');

        $label = IB_Game::COMMODITIES[$commodity]['label'];
        $col = self::col($commodity) . '_qty';
        $stock = self::qty($port, $commodity);
        $list = self::price($port, $commodity);

        $den = $mode === 'selling' && self::is_smuggler($port);
        if ($den) {
            $left = max(0, (int) $p->smuggle_left);
            if ($left <= 0) throw new IB_Game_Exception('You have stolen all you dare this visit. Undock and come back later.', 'warning');
            if ($qty > $left) throw new IB_Game_Exception(sprintf('You can only slip %s more units past the dock guards this visit.', IB_Game::fmt($left)));
        }
        if ($mode === 'selling') {
            if ($qty > $stock) throw new IB_Game_Exception(sprintf('The port only has %s units of %s for sale.', IB_Game::fmt($stock), $label));
            if ($qty > IB_Player::holds_free($p)) throw new IB_Game_Exception(sprintf('You only have %d empty holds.', IB_Player::holds_free($p)));
        } else {
            if ($qty > (int) $p->$commodity) throw new IB_Game_Exception(sprintf('You only carry %d units of %s.', $p->$commodity, $label));
            if ($qty > $stock) throw new IB_Game_Exception(sprintf('The port will only buy %s more units of %s.', IB_Game::fmt($stock), $label));
        }

        // Nobody haggles over stolen goods.
        list($unit, $exp) = $den ? [$list, 0] : self::haggle($p, $port, $commodity, $mode, $list, (int) $offer);
        $total = (int) round($unit * $qty);

        // Claim the port's stock/demand first so two traders can't take the same units.
        $claimed = $wpdb->query($wpdb->prepare(
            'UPDATE ' . IB_DB::t('ports') . " SET $col = $col - %d WHERE id = %d AND $col >= %d", $qty, $port->id, $qty
        ));
        if (!$claimed) throw new IB_Game_Exception('Another trader just beat you to it. Please try again.');

        // At a den, also claim the haul allowance, guarded so two quick clicks can't overrun it.
        if ($den) {
            $ok = $wpdb->query($wpdb->prepare(
                'UPDATE ' . IB_DB::t('players') . ' SET smuggle_left = smuggle_left - %d WHERE id = %d AND smuggle_left >= %d', $qty, $p->id, $qty
            ));
            if (!$ok) {
                $wpdb->query($wpdb->prepare('UPDATE ' . IB_DB::t('ports') . " SET $col = $col + %d WHERE id = %d", $qty, $port->id));
                throw new IB_Game_Exception('You have stolen all you dare this visit.', 'warning');
            }
            $p->smuggle_left = (int) $p->smuggle_left - $qty;
        }

        if ($mode === 'selling') {
            try {
                IB_Player::spend_credits($p, $total);
            } catch (IB_Game_Exception $e) {
                $wpdb->query($wpdb->prepare('UPDATE ' . IB_DB::t('ports') . " SET $col = $col + %d WHERE id = %d", $qty, $port->id));
                if ($den) {
                    $wpdb->query($wpdb->prepare('UPDATE ' . IB_DB::t('players') . ' SET smuggle_left = smuggle_left + %d WHERE id = %d', $qty, $p->id));
                    $p->smuggle_left = (int) $p->smuggle_left + $qty;
                }
                throw $e;
            }
            if ($den) {
                IB_Player::add($p, [$commodity => $qty, 'experience' => 1, 'alignment' => -1]);
                $msg = sprintf('You stole %s %s at a cost effort of %s cr each (%s credits in bribes and gear). %s more units can slip out this visit.',
                    IB_Game::fmt($qty), $label, IB_Game::fmt($unit), IB_Game::fmt($total), IB_Game::fmt(max(0, (int) $p->smuggle_left)));
                return $msg;
            }
            IB_Player::add($p, [$commodity => $qty, 'experience' => 1 + $exp]);
            $msg = sprintf('You bought %s %s at %s cr each for %s credits.', IB_Game::fmt($qty), $label, IB_Game::fmt($unit), IB_Game::fmt($total));
        } else {
            IB_Player::add($p, [$commodity => -$qty, 'credits' => $total, 'experience' => 1 + $exp]);
            $msg = sprintf('You sold %s %s at %s cr each for %s credits.', IB_Game::fmt($qty), $label, IB_Game::fmt($unit), IB_Game::fmt($total));
        }
        if ($exp > 1) $msg .= ' "You drive a hard bargain, trader." (+' . $exp . ' experience)';
        if ($total >= self::NEWSWORTHY_TRADE) {
            IB_Log::news('trade', sprintf('%s %s %s %s units of %s at %s for %s credits.',
                IB_Game::rank_title($p->experience), $p->alias_name, $mode === 'selling' ? 'bought' : 'sold',
                IB_Game::fmt($qty), $label, $port->port_name, IB_Game::fmt($total)));
        }
        return $msg;
    }

    private static function haggle_key($p, $port, $commodity) {
        return 'ib_hag_' . $p->id . '_' . $port->id . '_' . $commodity;
    }

    /** The port's last counter-offer, used to pre-fill the trade form. */
    public static function counter_offer($p, $port, $commodity) {
        $state = get_transient(self::haggle_key($p, $port, $commodity));
        return ($state && !empty($state['counter'])) ? (int) $state['counter'] : 0;
    }

    /**
     * The port privately decides how far it will bend (3-10%). Offers inside that
     * margin are accepted; greedier offers get a counter-offer until attempts run out.
     *
     * @return array [unit price, bonus experience]
     */
    private static function haggle($p, $port, $commodity, $mode, $list, $offer) {
        $better = $mode === 'selling' ? ($offer > 0 && $offer < $list) : ($offer > $list);
        if (!$better) return [$list, 0];

        $key = self::haggle_key($p, $port, $commodity);
        if (get_transient($key . '_lock')) {
            throw new IB_Game_Exception('The port master refuses to haggle with you again this hour. Trade at the listed price.', 'warning');
        }
        $state = get_transient($key);
        if (!$state) $state = ['tol' => mt_rand(3, 10) / 100, 'attempts' => 0, 'counter' => 0];

        $ratio = abs($offer - $list) / $list;
        if ($ratio <= $state['tol']) {
            delete_transient($key);
            return [$offer, $ratio >= $state['tol'] * 0.75 ? 3 : 1];
        }

        $state['attempts']++;
        $max = max(1, (int) IB_Settings::get('max_haggle_attempts'));
        if ($state['attempts'] >= $max) {
            delete_transient($key);
            set_transient($key . '_lock', 1, HOUR_IN_SECONDS);
            throw new IB_Game_Exception('"You insult me! Get out of my port!" The port master will not haggle with you again this hour.', 'warning');
        }
        $shift = $list * $state['tol'] * mt_rand(40, 80) / 100;
        $state['counter'] = (int) round($mode === 'selling' ? $list - $shift : $list + $shift);
        set_transient($key, $state, HOUR_IN_SECONDS);
        throw new IB_Game_Exception(sprintf(
            '"%s per unit? Ridiculous! I could do %s." (%d haggle attempt(s) left)',
            IB_Game::fmt($offer), IB_Game::fmt($state['counter']), $max - $state['attempts']
        ), 'warning');
    }

    /** Price of buying $n more holds when the ship already has $current. Each hold costs a little more. */
    public static function hold_cost($current, $n) {
        $base = (int) IB_Settings::get('price_hold_base');
        return (int) ($n * $base + 5 * ($n * $current + $n * ($n - 1) / 2));
    }

    /** @return array item => [label, unit price or null, max purchasable] */
    public static function hardware_catalog($p, $port) {
        $s = IB_Settings::all();
        $ship = IB_Player::ship($p);
        $items = [
            'holds'    => ['Cargo holds', null, max(0, $ship['max_holds'] - $p->cargo_holds)],
            'fighters' => ['Fighters', $s['price_fighter'], max(0, $ship['max_fighters'] - $p->fighters)],
            'shields'  => ['Shield points', $s['price_shield'], max(0, $ship['max_shields'] - $p->shield_points)],
            'drones'   => ['Survey drones', $s['price_survey_drone'], max(0, IB_Discovery::MAX_DRONES - $p->survey_drones)],
        ];
        // Vending machines for planet garrisons, sold at both Crown ports while the rule is switched on.
        if ((int) $s['vending_per_1000'] > 0) {
            $items['vending'] = ['Vending machines', $s['price_vending'], max(0, self::MAX_VENDING_CARRIED - (int) $p->vending_machines)];
        }
        if ((int) $port->port_class === self::SHIPYARD) {
            $items['worldseed'] = ['Worldseeds', $s['price_worldseed'], max(0, self::MAX_WORLDSEEDS_CARRIED - $p->worldseeds)];
        }
        return $items;
    }

    public static function buy_hardware($p, $item, $qty) {
        $port = self::require_docked($p);
        if (!in_array((int) $port->port_class, [self::ARMORY, self::SHIPYARD], true)) throw new IB_Game_Exception('This port does not sell hardware.');
        $catalog = self::hardware_catalog($p, $port);
        if (!isset($catalog[$item])) throw new IB_Game_Exception('That item is not sold here.');
        $qty = (int) $qty;
        list($label, $unit, $max) = $catalog[$item];
        if ($qty <= 0) throw new IB_Game_Exception('Enter a quantity greater than zero.');
        if ($qty > $max) throw new IB_Game_Exception(sprintf('Your ship can only take %s more %s.', IB_Game::fmt($max), strtolower($label)));

        $cost = $item === 'holds' ? self::hold_cost($p->cargo_holds, $qty) : $unit * $qty;
        IB_Player::spend_credits($p, $cost);
        $col = ['holds' => 'cargo_holds', 'fighters' => 'fighters', 'shields' => 'shield_points', 'worldseed' => 'worldseeds', 'drones' => 'survey_drones', 'vending' => 'vending_machines'][$item];
        IB_Player::add($p, [$col => $qty]);
        $msg = sprintf('Purchased %s %s for %s credits.', IB_Game::fmt($qty), strtolower($label), IB_Game::fmt($cost));
        if ($item === 'vending') {
            $msg .= ' Land on one of your worlds and leave them there with the cargo transfer; each one feeds '
                . IB_Game::fmt(1000 / max(1, (int) IB_Settings::get('vending_per_1000'))) . ' fighters of its garrison.';
        } elseif ($item === 'worldseed' && (int) IB_Settings::get('vending_per_1000') > 0) {
            $msg .= sprintf(' Remember the vending machines: every 1,000 fighters garrisoned on a world need %d, stocked with rations and ale. They are sold right here, %s credits each.',
                (int) IB_Settings::get('vending_per_1000'), IB_Game::fmt(IB_Settings::get('price_vending')));
        }
        return $msg;
    }

    public static function trade_in_value($p) {
        $ship = IB_Player::ship($p);
        return (int) floor($ship['price'] / 2);
    }

    public static function buy_ship($p, $type) {
        $port = self::require_docked($p);
        if ((int) $port->port_class !== self::SHIPYARD) throw new IB_Game_Exception('Ships are only sold at the Imperial Drydock.');
        $all = IB_Ships::all();
        if (!isset($all[$type])) throw new IB_Game_Exception('Unknown ship type.');
        if ($type === $p->ship_type) throw new IB_Game_Exception('You already fly that model.');
        $new = $all[$type];
        if (!empty($new['captain_only']) && !IB_Teams::is_captain($p)) throw new IB_Game_Exception('Only team captains may purchase a ' . $new['name'] . '.');

        $cost = max(0, $new['price'] - self::trade_in_value($p));
        IB_Player::spend_credits($p, $cost);

        $holds = max($new['base_holds'], min((int) $p->cargo_holds, $new['max_holds']));
        // Anything that will not fit the new hull is left on the dock. Colonists are counted in
        // berths, the same way holds_used() counts them, and are the last thing put ashore.
        $cargo = [];
        foreach (array_keys(IB_Game::COMMODITIES) as $k) $cargo[$k] = (int) $p->$k;
        $cargo['colonists'] = (int) $p->colonists;
        $used = function ($cargo) {
            $n = IB_Player::colonist_holds($cargo['colonists']);
            foreach (IB_Game::COMMODITIES as $k => $def) $n += $cargo[$k];
            return $n;
        };
        foreach (array_keys(IB_Game::COMMODITIES) as $k) {
            $excess = $used($cargo) - $holds;
            if ($excess <= 0) break;
            $cargo[$k] -= min($cargo[$k], $excess);
        }
        if ($used($cargo) > $holds) {
            $berths = max(0, $holds - ($used($cargo) - IB_Player::colonist_holds($cargo['colonists'])));
            $cargo['colonists'] = min($cargo['colonists'], $berths * IB_Player::colonists_per_hold());
        }
        IB_Player::update($p, array_merge($cargo, [
            'ship_type' => $type, 'cargo_holds' => $holds,
            'fighters' => min((int) $p->fighters, $new['max_fighters']),
            'shield_points' => min((int) $p->shield_points, $new['max_shields']),
        ]));
        IB_Log::news('ship', sprintf('%s took delivery of a new %s.', $p->alias_name, $new['name']));
        return sprintf('Congratulations on your new %s! After trade-in it cost %s credits.', $new['name'], IB_Game::fmt($cost));
    }

    /** Hourly: stock and demand recover toward their maximums. */
    /**
     * Every port in charted space, with how many warps away it is, nearest first.
     * Charted means visited, swept by sensors or surveyed: what the pilot could know about.
     *
     * @return array list of [port, hops]
     */
    public static function charted($p) {
        global $wpdb;
        $charted = array_flip(IB_Player::charted_ids($p->id));
        $dist = IB_Pathfinder::distances(null, (int) $p->sector_id);
        $out = [];
        foreach ($wpdb->get_results('SELECT * FROM ' . IB_DB::t('ports')) as $port) {
            $sector = (int) $port->sector_id;
            if (!isset($charted[$sector], $dist[$sector])) continue;
            $out[] = [$port, (int) $dist[$sector]];
        }
        usort($out, function ($a, $b) { return $a[1] <=> $b[1]; });
        return $out;
    }

    /**
     * Charted ports that sell ($want 'buy') or buy ($want 'sell') one commodity, nearest first.
     * Used by the computer panel on the Port page and by the Computer page's port finder.
     *
     * @return array rows of ['port', 'price', 'units', 'hops', 'turns', 'reachable']
     */
    public static function search($p, $commodity, $want = 'buy', $limit = 10) {
        if (!isset(IB_Game::COMMODITIES[$commodity])) return [];
        $mode_wanted = $want === 'sell' ? 'buying' : 'selling';
        $move = (int) IB_Settings::get('move_turn_cost');
        $dock = (int) IB_Settings::get('dock_turn_cost');
        $rows = [];
        foreach (self::charted($p) as $entry) {
            list($port, $hops) = $entry;
            if (!self::is_trading_port($port)) continue;
            if (!in_array($commodity, self::goods($port), true)) continue;
            if (self::mode($port, $commodity) !== $mode_wanted) continue;
            $turns = $hops * $move + ($hops ? $dock : 0);
            $rows[] = [
                'port'      => $port,
                'price'     => self::price($port, $commodity),
                'units'     => self::qty($port, $commodity),
                'hops'      => $hops,
                'turns'     => $turns,
                'reachable' => (int) $p->turns_remaining >= $turns,
            ];
            if (count($rows) >= max(1, (int) $limit)) break;
        }
        return $rows;
    }

    /**
     * Where to go next: the charted ports that will buy what is in the holds, and, for empty
     * holds, the ones selling nearby. This is the question a trader asks after every sale, and
     * answering it on the Port page is what keeps the loop off the Computer page.
     *
     * @param  object $p
     * @param  int    $limit rows to return
     * @return array  rows of ['port', 'commodity', 'mode', 'price', 'units', 'value', 'hops', 'turns', 'reachable']
     */
    public static function runs_for($p, $limit = 8) {
        $move = (int) IB_Settings::get('move_turn_cost');
        $dock = (int) IB_Settings::get('dock_turn_cost');
        $turns = (int) $p->turns_remaining;
        $here = (int) $p->sector_id;

        $carrying = [];
        foreach (array_keys(IB_Game::COMMODITIES) as $key) {
            if ((int) $p->$key > 0) $carrying[$key] = (int) $p->$key;
        }
        $selling = !empty($carrying);
        $rows = [];

        foreach (self::charted($p) as $entry) {
            list($port, $hops) = $entry;
            if ((int) $port->sector_id === $here || !self::is_trading_port($port)) continue;

            foreach (self::goods($port) as $key) {
                $mode = self::mode($port, $key);
                if ($selling) {
                    // Somewhere to sell what is aboard.
                    if ($mode !== 'buying' || empty($carrying[$key])) continue;
                    $units = min($carrying[$key], self::qty($port, $key));
                } else {
                    // Nothing aboard: somewhere to buy, sized to holds and credits.
                    if ($mode !== 'selling') continue;
                    $price = self::price($port, $key);
                    $units = min(self::qty($port, $key), IB_Player::holds_free($p), (int) floor($p->credits / max(1, $price)));
                }
                if ($units < 1) continue;
                $price = self::price($port, $key);
                $cost = $hops * $move + $dock;
                $rows[] = [
                    'port'      => $port,
                    'commodity' => $key,
                    'mode'      => $mode,
                    'price'     => $price,
                    'units'     => $units,
                    'value'     => $units * $price,
                    'hops'      => $hops,
                    'turns'     => $cost,
                    'reachable' => $turns >= $cost,
                ];
            }
        }

        // Best first: what the run is worth, then how far away it is. Selling ranks on the money
        // on offer; buying ranks on the cheapest goods, since the profit comes later.
        usort($rows, function ($a, $b) use ($selling) {
            if ($selling) {
                if ($a['reachable'] !== $b['reachable']) return $a['reachable'] ? -1 : 1;
                $cmp = $b['value'] <=> $a['value'];
            } else {
                if ($a['reachable'] !== $b['reachable']) return $a['reachable'] ? -1 : 1;
                $cmp = $a['price'] <=> $b['price'];
            }
            return $cmp ?: $a['hops'] <=> $b['hops'];
        });

        // Spread the list across what is aboard, rather than filling it with the one commodity
        // that happens to pay best: a hold of ore and a hold of biostock each need a buyer.
        $by_commodity = [];
        foreach ($rows as $row) $by_commodity[$row['commodity']][] = $row;
        $out = [];
        $limit = max(1, (int) $limit);
        for ($round = 0; count($out) < $limit; $round++) {
            $added = false;
            foreach ($by_commodity as $list) {
                if (!isset($list[$round])) continue;
                $out[] = $list[$round];
                $added = true;
                if (count($out) >= $limit) break;
            }
            if (!$added) break;
        }
        return $out;
    }

    public static function regenerate() {
        global $wpdb;
        $pct = max(0, (int) IB_Settings::get('port_regen_percent')) / 100;
        $t = IB_DB::t('ports');
        return $wpdb->query($wpdb->prepare(
            "UPDATE $t SET
                ore_qty = LEAST(ore_max, ore_qty + CEIL(ore_max * %f)),
                org_qty = LEAST(org_max, org_qty + CEIL(org_max * %f)),
                equ_qty = LEAST(equ_max, equ_qty + CEIL(equ_max * %f)),
                spec_qty = LEAST(spec_max, spec_qty + CEIL(spec_max * %f))
             WHERE port_class BETWEEN 1 AND 8",
            $pct, $pct, $pct, $pct
        ));
    }
}
