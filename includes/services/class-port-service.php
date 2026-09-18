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

    public static function is_trading_port($port) {
        return $port && (int) $port->port_class >= 1 && (int) $port->port_class <= 8;
    }

    /** @return 'selling'|'buying'|null from the port's perspective */
    public static function mode($port, $commodity) {
        if (!self::is_trading_port($port)) return null;
        $idx = array_search($commodity, array_keys(IB_Game::COMMODITIES), true);
        if ($idx === false) return null;
        return self::CLASSES[(int) $port->port_class][$idx] === 'S' ? 'selling' : 'buying';
    }

    public static function qty($port, $commodity) {
        $col = IB_Game::COMMODITIES[$commodity]['col'];
        return (int) $port->{$col . '_qty'};
    }

    public static function max($port, $commodity) {
        $col = IB_Game::COMMODITIES[$commodity]['col'];
        return (int) $port->{$col . '_max'};
    }

    /** Listed price per unit. Plentiful stock is cheap; strong demand pays well. */
    public static function price($port, $commodity) {
        $base = IB_Game::COMMODITIES[$commodity]['base'];
        $max = self::max($port, $commodity);
        $ratio = $max > 0 ? self::qty($port, $commodity) / $max : 0;
        $price = self::mode($port, $commodity) === 'selling'
            ? $base * (1.25 - 0.35 * $ratio)
            : $base * (0.75 + 0.45 * $ratio);
        return max(1, (int) round($price));
    }

    public static function dock($p) {
        $port = self::in_sector($p->sector_id);
        if (!$port) throw new IB_Game_Exception('There is no port in this sector.');
        if ((int) $p->docked_port_id === (int) $port->id) return $port;
        IB_Player::spend_turns($p, (int) IB_Settings::get('dock_turn_cost'));
        IB_Player::update($p, ['docked_port_id' => $port->id, 'landed_planet_id' => 0]);
        return $port;
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
        $col = IB_Game::COMMODITIES[$commodity]['col'] . '_qty';
        $stock = self::qty($port, $commodity);
        $list = self::price($port, $commodity);

        if ($mode === 'selling') {
            if ($qty > $stock) throw new IB_Game_Exception(sprintf('The port only has %s units of %s for sale.', IB_Game::fmt($stock), $label));
            if ($qty > IB_Player::holds_free($p)) throw new IB_Game_Exception(sprintf('You only have %d empty holds.', IB_Player::holds_free($p)));
        } else {
            if ($qty > (int) $p->$commodity) throw new IB_Game_Exception(sprintf('You only carry %d units of %s.', $p->$commodity, $label));
            if ($qty > $stock) throw new IB_Game_Exception(sprintf('The port will only buy %s more units of %s.', IB_Game::fmt($stock), $label));
        }

        list($unit, $exp) = self::haggle($p, $port, $commodity, $mode, $list, (int) $offer);
        $total = (int) round($unit * $qty);

        // Claim the port's stock/demand first so two traders can't take the same units.
        $claimed = $wpdb->query($wpdb->prepare(
            'UPDATE ' . IB_DB::t('ports') . " SET $col = $col - %d WHERE id = %d AND $col >= %d", $qty, $port->id, $qty
        ));
        if (!$claimed) throw new IB_Game_Exception('Another trader just beat you to it. Please try again.');

        if ($mode === 'selling') {
            try {
                IB_Player::spend_credits($p, $total);
            } catch (IB_Game_Exception $e) {
                $wpdb->query($wpdb->prepare('UPDATE ' . IB_DB::t('ports') . " SET $col = $col + %d WHERE id = %d", $qty, $port->id));
                throw $e;
            }
            IB_Player::add($p, [$commodity => $qty, 'experience' => 1 + $exp]);
            $msg = sprintf('You bought %s %s at %s cr each for %s credits.', IB_Game::fmt($qty), $label, IB_Game::fmt($unit), IB_Game::fmt($total));
        } else {
            IB_Player::add($p, [$commodity => -$qty, 'credits' => $total, 'experience' => 1 + $exp]);
            $msg = sprintf('You sold %s %s at %s cr each for %s credits.', IB_Game::fmt($qty), $label, IB_Game::fmt($unit), IB_Game::fmt($total));
        }
        if ($exp > 1) $msg .= ' "You drive a hard bargain, trader." (+' . $exp . ' experience)';
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
        $col = ['holds' => 'cargo_holds', 'fighters' => 'fighters', 'shields' => 'shield_points', 'worldseed' => 'worldseeds', 'drones' => 'survey_drones'][$item];
        IB_Player::add($p, [$col => $qty]);
        return sprintf('Purchased %s %s for %s credits.', IB_Game::fmt($qty), strtolower($label), IB_Game::fmt($cost));
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
        $cargo = ['colonists' => (int) $p->colonists, 'ore' => (int) $p->ore, 'organics' => (int) $p->organics, 'equipment' => (int) $p->equipment];
        $excess = array_sum($cargo) - $holds;
        foreach ($cargo as $k => $v) {
            if ($excess <= 0) break;
            $drop = min($v, $excess);
            $cargo[$k] -= $drop;
            $excess -= $drop;
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
    public static function regenerate() {
        global $wpdb;
        $pct = max(0, (int) IB_Settings::get('port_regen_percent')) / 100;
        $t = IB_DB::t('ports');
        return $wpdb->query($wpdb->prepare(
            "UPDATE $t SET
                ore_qty = LEAST(ore_max, ore_qty + CEIL(ore_max * %f)),
                org_qty = LEAST(org_max, org_qty + CEIL(org_max * %f)),
                equ_qty = LEAST(equ_max, equ_qty + CEIL(equ_max * %f))
             WHERE port_class BETWEEN 1 AND 8",
            $pct, $pct, $pct
        ));
    }
}
