<?php
if (!defined('ABSPATH')) exit;

/**
 * Planets: Aurelia Prime (colonist source), Worldseed-grown and pre-seeded worlds,
 * colonist production and bastions.
 */
class IB_Planets {
    const CROWN_WORLD = 'C';
    const MAX_PER_SECTOR = 3;
    const TRANSFERABLE = ['colonists', 'ore', 'organics', 'equipment', 'fighters', 'vending_machines'];

    /**
     * Production is per 1,000 colonists per day, at the base rate. What a planet actually makes is
     * this scaled by output_multiplier(): the site's Planet output setting, plus 10% a bastion level.
     */
    const CLASSES = [
        'V' => ['name' => 'Verdant',     'ore' => 30, 'organics' => 50, 'equipment' => 20, 'fighters' => 10, 'max_colonists' => 30000],
        'D' => ['name' => 'Dune',        'ore' => 50, 'organics' => 10, 'equipment' => 20, 'fighters' => 10, 'max_colonists' => 20000],
        'R' => ['name' => 'Highland',    'ore' => 60, 'organics' => 20, 'equipment' => 30, 'fighters' => 5,  'max_colonists' => 25000],
        'P' => ['name' => 'Pelagic',     'ore' => 10, 'organics' => 70, 'equipment' => 10, 'fighters' => 5,  'max_colonists' => 40000],
        'E' => ['name' => 'Ember',       'ore' => 80, 'organics' => 0,  'equipment' => 30, 'fighters' => 10, 'max_colonists' => 10000],
        'G' => ['name' => 'Gas Giant',   'ore' => 20, 'organics' => 20, 'equipment' => 60, 'fighters' => 15, 'max_colonists' => 15000],
        'I' => ['name' => 'Rimeworld',   'ore' => 20, 'organics' => 10, 'equipment' => 20, 'fighters' => 20, 'max_colonists' => 12000],
        'C' => ['name' => 'Crown World', 'ore' => 0,  'organics' => 0,  'equipment' => 0,  'fighters' => 0,  'max_colonists' => 0],
    ];

    /** Cost of building each bastion level (credits from the pilot, the rest from planet stock). */
    const BASTION = [
        1 => ['name' => 'Bastion & Vault',  'credits' => 5000,   'colonists' => 100,  'ore' => 100,  'organics' => 100,  'equipment' => 50],
        2 => ['name' => 'War Council',      'credits' => 10000,  'colonists' => 300,  'ore' => 200,  'organics' => 200,  'equipment' => 150],
        3 => ['name' => 'Lance Battery',    'credits' => 20000,  'colonists' => 800,  'ore' => 400,  'organics' => 300,  'equipment' => 300],
        4 => ['name' => 'Deep Bunkers',     'credits' => 40000,  'colonists' => 1500, 'ore' => 800,  'organics' => 600,  'equipment' => 600],
        5 => ['name' => 'Aegis Dome',       'credits' => 80000,  'colonists' => 3000, 'ore' => 1500, 'organics' => 1000, 'equipment' => 1200],
        6 => ['name' => 'Sovereign Spire',  'credits' => 150000, 'colonists' => 6000, 'ore' => 3000, 'organics' => 2000, 'equipment' => 2500],
    ];

    public static function class_name($class) {
        return isset(self::CLASSES[$class]) ? self::CLASSES[$class]['name'] : 'Unknown';
    }

    /** Fighters defending a planet fight at better odds for each bastion level. */
    public static function defense_odds($planet) {
        return 1 + 0.25 * (int) $planet->bastion_level;
    }

    public static function random_class() {
        $pool = ['V', 'V', 'D', 'R', 'R', 'P', 'P', 'E', 'G', 'I'];
        return $pool[array_rand($pool)];
    }

    public static function get($id) {
        global $wpdb;
        return $wpdb->get_row($wpdb->prepare('SELECT * FROM ' . IB_DB::t('planets') . ' WHERE id = %d', $id));
    }

    /** Aurelia Prime, where settlers are recruited. */
    public static function crown_world() {
        global $wpdb;
        return $wpdb->get_row($wpdb->prepare(
            'SELECT * FROM ' . IB_DB::t('planets') . ' WHERE planet_class = %s LIMIT 1', self::CROWN_WORLD
        ));
    }

    public static function in_sector($sector_id) {
        global $wpdb;
        return $wpdb->get_results($wpdb->prepare('SELECT * FROM ' . IB_DB::t('planets') . ' WHERE sector_id = %d ORDER BY id', $sector_id));
    }

    public static function owned_by($player_id) {
        global $wpdb;
        return $wpdb->get_results($wpdb->prepare('SELECT * FROM ' . IB_DB::t('planets') . ' WHERE owner_player_id = %d ORDER BY sector_id', $player_id));
    }

    public static function team_planets($team_id) {
        global $wpdb;
        return $wpdb->get_results($wpdb->prepare('SELECT * FROM ' . IB_DB::t('planets') . ' WHERE team_id = %d ORDER BY sector_id', $team_id));
    }

    public static function is_friendly($p, $planet) {
        if ((int) $planet->owner_player_id === (int) $p->id) return true;
        return (int) $p->team_id > 0 && (int) $planet->team_id === (int) $p->team_id;
    }

    public static function is_hostile($p, $planet) {
        return $planet->planet_class !== self::CROWN_WORLD && (int) $planet->owner_player_id > 0 && !self::is_friendly($p, $planet);
    }

    public static function owner_label($planet) {
        if ($planet->planet_class === self::CROWN_WORLD) return 'The Imperial Crown';
        if (!(int) $planet->owner_player_id) return 'Unclaimed';
        return IB_Player::name($planet->owner_player_id);
    }

    public static function land($p, $planet_id) {
        $planet = self::get($planet_id);
        if (!$planet || (int) $planet->sector_id !== (int) $p->sector_id) throw new IB_Game_Exception('That planet is not in this sector.');
        if (self::is_hostile($p, $planet) && (int) $planet->fighters > 0) {
            throw new IB_Game_Exception(sprintf('Planetary defenses (%s fighters) repel your landing. Destroy them first.', IB_Game::fmt($planet->fighters)));
        }
        IB_Player::update($p, ['landed_planet_id' => $planet->id, 'docked_port_id' => 0]);
        return $planet;
    }

    public static function require_landed($p) {
        $planet = (int) $p->landed_planet_id ? self::get($p->landed_planet_id) : null;
        if (!$planet || (int) $planet->sector_id !== (int) $p->sector_id) throw new IB_Game_Exception('You are not landed on a planet.');
        return $planet;
    }

    public static function require_friendly($p) {
        $planet = self::require_landed($p);
        if (!self::is_friendly($p, $planet)) throw new IB_Game_Exception('This planet does not belong to you or your team.');
        return $planet;
    }

    public static function leave($p) {
        IB_Player::update($p, ['landed_planet_id' => 0]);
    }

    public static function claim($p) {
        $planet = self::require_landed($p);
        if ($planet->planet_class === self::CROWN_WORLD) throw new IB_Game_Exception('Aurelia Prime belongs to the Crown.');
        if (self::is_friendly($p, $planet)) throw new IB_Game_Exception('This planet is already yours.');
        if ((int) $planet->fighters > 0) throw new IB_Game_Exception('The planet is still defended.');
        $previous = (int) $planet->owner_player_id;
        self::set_owner($planet, $p);
        IB_Player::add($p, ['experience' => 5]);
        if (!$previous) {
            IB_Log::news('planet', sprintf('%s claimed the %s planet %s in sector %d.',
                $p->alias_name, self::class_name($planet->planet_class), $planet->planet_name, $planet->sector_id));
        }
        if ($previous) {
            IB_Messages::system($previous, 'Planet lost', sprintf('%s has seized your planet %s in sector %d.', $p->alias_name, $planet->planet_name, $planet->sector_id));
            IB_Log::news('planet', sprintf('%s captured the planet %s in sector %d.', $p->alias_name, $planet->planet_name, $planet->sector_id));
        }
        return sprintf('You have claimed %s!', $planet->planet_name);
    }

    public static function set_owner($planet, $p) {
        global $wpdb;
        $wpdb->update(IB_DB::t('planets'), ['owner_player_id' => $p->id, 'team_id' => (int) $p->team_id], ['id' => $planet->id]);
        // Anyone else landed there is bumped back into space.
        $wpdb->query($wpdb->prepare(
            'UPDATE ' . IB_DB::t('players') . ' SET landed_planet_id = 0 WHERE landed_planet_id = %d AND id <> %d', $planet->id, $p->id
        ));
    }

    /** How many more of $what the ship can take on. Vending machines are crated and stowed, not held. */
    public static function ship_room($p, $what) {
        if ($what === 'fighters') return (int) IB_Player::ship($p)['max_fighters'] - (int) $p->fighters;
        if ($what === 'colonists') return IB_Player::colonist_room($p);
        if ($what === 'vending_machines') return max(0, IB_Ports::MAX_VENDING_CARRIED - (int) $p->vending_machines);
        return IB_Player::holds_free($p);
    }

    /** Move colonists, commodities, fighters or vending machines between ship and planet. $dir is 'take' or 'leave'. */
    public static function transfer($p, $what, $dir, $qty) {
        global $wpdb;
        if (!in_array($what, self::TRANSFERABLE, true)) throw new IB_Game_Exception('Invalid cargo type.');
        $planet = self::require_friendly($p);
        $qty = (int) $qty;
        if ($qty <= 0) throw new IB_Game_Exception('Enter a quantity greater than zero.');
        $ship_col = $what === 'fighters' ? 'fighters' : $what;
        $t = IB_DB::t('planets');

        if ($dir === 'take') {
            $room = self::ship_room($p, $what);
            if ($qty > $room) throw new IB_Game_Exception(sprintf('Your ship only has room for %s more.', IB_Game::fmt(max(0, $room))));
            $ok = $wpdb->query($wpdb->prepare("UPDATE $t SET $what = $what - %d WHERE id = %d AND $what >= %d", $qty, $planet->id, $qty));
            if (!$ok) throw new IB_Game_Exception('The planet does not have that many.');
            IB_Player::add($p, [$ship_col => $qty]);
            return sprintf('Loaded %s %s onto your ship.', IB_Game::fmt($qty), IB_Game::label($what));
        }

        if ($qty > (int) $p->$ship_col) throw new IB_Game_Exception(sprintf('You only carry %s %s.', IB_Game::fmt($p->$ship_col), IB_Game::label($what)));
        if ($what === 'colonists') {
            $max = self::CLASSES[$planet->planet_class]['max_colonists'];
            if ($planet->colonists + $qty > $max) throw new IB_Game_Exception(sprintf('This planet can only support %s colonists.', IB_Game::fmt($max)));
        }
        IB_Player::add($p, [$ship_col => -$qty]);
        $wpdb->query($wpdb->prepare("UPDATE $t SET $what = $what + %d WHERE id = %d", $qty, $planet->id));
        return sprintf('Unloaded %s %s onto %s.', IB_Game::fmt($qty), IB_Game::label($what), $planet->planet_name);
    }

    public static function build_bastion($p) {
        global $wpdb;
        $planet = self::require_friendly($p);
        $next = (int) $planet->bastion_level + 1;
        if (!isset(self::BASTION[$next])) throw new IB_Game_Exception('The bastion is already at its maximum level.');
        $req = self::BASTION[$next];
        foreach (['colonists', 'ore', 'organics', 'equipment'] as $k) {
            if ((int) $planet->$k < $req[$k]) {
                throw new IB_Game_Exception(sprintf('Level %d needs %s %s on the planet (it has %s).', $next, IB_Game::fmt($req[$k]), IB_Game::label($k), IB_Game::fmt($planet->$k)));
            }
        }
        IB_Player::spend_credits($p, $req['credits']);
        $wpdb->query($wpdb->prepare(
            'UPDATE ' . IB_DB::t('planets') . ' SET bastion_level = %d, ore = ore - %d, organics = organics - %d, equipment = equipment - %d WHERE id = %d',
            $next, $req['ore'], $req['organics'], $req['equipment'], $planet->id
        ));
        IB_Player::add($p, ['experience' => 10 * $next]);
        IB_Log::news('bastion', sprintf('%s completed a level %d bastion (%s) on %s in sector %d.',
            $p->alias_name, $next, $req['name'], $planet->planet_name, $planet->sector_id));
        return sprintf('Construction complete: %s now has a level %d bastion (%s).', $planet->planet_name, $next, $req['name']);
    }

    /** $dir is 'deposit' or 'withdraw'. */
    public static function vault($p, $dir, $amount) {
        global $wpdb;
        $planet = self::require_friendly($p);
        if ((int) $planet->bastion_level < 1) throw new IB_Game_Exception('Build a bastion to get a Vault.');
        if (!is_numeric($amount) || (float) $amount > (float) PHP_INT_MAX) {
            throw new IB_Game_Exception('Enter an amount you actually have.');
        }
        $amount = (int) $amount;
        if ($amount <= 0) throw new IB_Game_Exception('Enter an amount greater than zero.');
        $t = IB_DB::t('planets');
        if ($dir === 'deposit') {
            // Checked here as well as in spend_credits, so the Vault can never be credited with
            // money that was not taken from the pilot.
            if ($amount > (int) $p->credits) {
                throw new IB_Game_Exception(sprintf('You only have %s credits.', IB_Game::fmt($p->credits)));
            }
            IB_Player::spend_credits($p, $amount);
            $wpdb->query($wpdb->prepare("UPDATE $t SET bastion_vault = bastion_vault + %d WHERE id = %d", $amount, $planet->id));
            return sprintf('Deposited %s credits in the Vault.', IB_Game::fmt($amount));
        }
        $ok = $wpdb->query($wpdb->prepare("UPDATE $t SET bastion_vault = bastion_vault - %d WHERE id = %d AND bastion_vault >= %d", $amount, $planet->id, $amount));
        if (!$ok) throw new IB_Game_Exception('The Vault does not hold that much.');
        IB_Player::add($p, ['credits' => $amount]);
        return sprintf('Withdrew %s credits from the Vault.', IB_Game::fmt($amount));
    }

    public static function rename($p, $name) {
        global $wpdb;
        $planet = self::require_landed($p);
        if ((int) $planet->owner_player_id !== (int) $p->id) throw new IB_Game_Exception('Only the owner can rename a planet.');
        $name = trim(sanitize_text_field($name));
        if ($name === '' || strlen($name) > 100) throw new IB_Game_Exception('Enter a name of up to 100 characters.');
        $wpdb->update(IB_DB::t('planets'), ['planet_name' => $name], ['id' => $planet->id]);
        return 'Planet renamed to ' . $name . '.';
    }

    /**
     * Recruit colonists: either landed on the Crown World, or docked at the Aurelian Armory,
     * whose recruiting office is where most traders pick them up.
     */
    public static function buy_colonists($p, $qty) {
        $planet = (int) $p->landed_planet_id ? self::get($p->landed_planet_id) : null;
        $on_crown_world = $planet && $planet->planet_class === self::CROWN_WORLD && (int) $planet->sector_id === (int) $p->sector_id;
        if (!$on_crown_world) {
            $port = IB_Ports::in_sector($p->sector_id);
            $at_armory = $port && (int) $port->port_class === IB_Ports::ARMORY && (int) $p->docked_port_id === (int) $port->id;
            if (!$at_armory) {
                throw new IB_Game_Exception('Colonists are recruited on Aurelia Prime, or at the Aurelian Armory while docked.');
            }
        }
        $qty = (int) $qty;
        if ($qty <= 0) throw new IB_Game_Exception('Enter a quantity greater than zero.');
        $room = IB_Player::colonist_room($p);
        if ($qty > $room) {
            throw new IB_Game_Exception(sprintf('Your ship has berths for %s more colonists (%d empty holds at %d apiece).',
                IB_Game::fmt($room), IB_Player::holds_free($p), IB_Player::colonists_per_hold()));
        }
        $cost = $qty * (int) IB_Settings::get('price_colonist');
        IB_Player::spend_credits($p, $cost);
        IB_Player::add($p, ['colonists' => $qty]);
        return sprintf('%s colonists board your ship, filling %d holds (%s credits in transport fees).',
            IB_Game::fmt($qty), IB_Player::colonist_holds($p->colonists), IB_Game::fmt($cost));
    }

    public static function launch_worldseed($p, $name) {
        global $wpdb;
        if ((int) $p->worldseeds < 1) throw new IB_Game_Exception('You have no Worldseeds.');
        if (IB_Game::is_core($p->sector_id)) throw new IB_Game_Exception('Imperial law forbids Worldseeds in the Imperial Core.');
        if (count(self::in_sector($p->sector_id)) >= self::MAX_PER_SECTOR) throw new IB_Game_Exception('This sector cannot support another planet.');
        $name = trim(sanitize_text_field($name));
        if ($name === '') $name = $p->alias_name . "'s World";
        $class = self::random_class();
        IB_Player::add($p, ['worldseeds' => -1, 'experience' => 10]);
        $wpdb->insert(IB_DB::t('planets'), [
            'sector_id' => $p->sector_id, 'planet_name' => substr($name, 0, 100), 'planet_class' => $class,
            'owner_player_id' => $p->id, 'team_id' => (int) $p->team_id, 'created_at' => current_time('mysql'),
        ]);
        IB_Log::news('worldseed', sprintf('%s created a new %s planet, %s, in sector %d.', $p->alias_name, self::class_name($class), $name, $p->sector_id));
        return sprintf('The Worldseed takes root... a new %s planet, %s, forms before your eyes!', self::class_name($class), $name);
    }

    /**
     * How much a planet makes against the base rates: the site's Planet output setting (300% by
     * default, so colonists are three times as productive as the class tables alone suggest), and a
     * further 10% for each level of bastion, which gives fortifying a world a return beyond
     * defending it.
     */
    public static function output_multiplier($planet) {
        $pct = max(0, (int) IB_Settings::get('planet_output_percent'));
        $level = isset($planet->bastion_level) ? (int) $planet->bastion_level : 0;
        return $pct / 100 * (1 + 0.10 * $level);
    }

    /** This planet's daily output per 1,000 colonists, after the multiplier. */
    public static function output_rates($planet) {
        $def = isset(self::CLASSES[$planet->planet_class]) ? self::CLASSES[$planet->planet_class] : self::CLASSES['V'];
        $multiplier = self::output_multiplier($planet);
        $rates = [];
        foreach (['ore', 'organics', 'equipment', 'fighters'] as $k) {
            $rates[$k] = $def[$k] * $multiplier;
        }
        return $rates;
    }

    /** Rounds fractional production up or down at random so small colonies still produce over time. */
    private static function random_round($x) {
        $whole = (int) floor($x);
        return $whole + ((mt_rand() / mt_getrandmax()) < ($x - $whole) ? 1 : 0);
    }

    /** Hourly: colonists produce commodities and fighters. */
    public static function produce() {
        global $wpdb;
        $t = IB_DB::t('planets');
        $per = max(0, (int) IB_Settings::get('vending_per_1000'));
        $planets = $wpdb->get_results("SELECT id, planet_class, colonists, bastion_level, owner_player_id, fighters, vending_machines, vending_short_days FROM $t WHERE colonists > 0 AND planet_class <> 'C'");
        foreach ($planets as $pl) {
            $rates = self::output_rates($pl);
            $gain = [];
            foreach (['ore', 'organics', 'equipment', 'fighters'] as $k) {
                $gain[$k] = self::random_round($pl->colonists / 1000 * $rates[$k] / 24);
            }
            // Vending machines: an owned world's garrison only grows as far as its machines can feed,
            // and a world already short of stocked machines raises no new fighters at all.
            if ($per && (int) $pl->owner_player_id > 0) {
                $fed = (int) $pl->vending_short_days > 0 ? 0 : (int) floor((int) $pl->vending_machines * 1000 / $per);
                $gain['fighters'] = max(0, min($gain['fighters'], $fed - (int) $pl->fighters));
            }
            if (array_sum($gain) === 0) continue;
            $wpdb->query($wpdb->prepare(
                "UPDATE $t SET ore = ore + %d, organics = organics + %d, equipment = equipment + %d, fighters = fighters + %d WHERE id = %d",
                $gain['ore'], $gain['organics'], $gain['equipment'], $gain['fighters'], $pl->id
            ));
        }
        return count($planets);
    }

    /**
     * Daily: the Crown's factors buy the staples off every colony that has a Vault to pay into.
     *
     * This is what makes a world you never visit worth owning. They pay below base price, set by
     * "Auto-sale price", so carrying cargo to a port by hand still earns more; and they only deal
     * with a planet that has a bastion, because the money goes into its Vault. Specialist goods
     * are not grown on planets, so only ferrium ore, biostock and machinery are sold.
     *
     * @return array ['planets' => int, 'units' => int, 'credits' => int]
     */
    public static function sell_stock() {
        global $wpdb;
        $share = max(0, min(100, (int) IB_Settings::get('planet_autosell_percent')));
        $rate = max(0, (int) IB_Settings::get('planet_autosell_price_percent'));
        $out = ['planets' => 0, 'units' => 0, 'credits' => 0];
        if (!$share || !$rate) return $out;

        $t = IB_DB::t('planets');
        $staples = IB_Game::commodities();
        // Only worlds that belong to someone: the factors pay an owner's Vault, and an unclaimed
        // world has nobody to pay. Without this, a world released by an administrator deleting its
        // owner would go on banking credits for nobody until somebody happened to claim it.
        $rows = $wpdb->get_results("SELECT id, planet_name, sector_id, owner_player_id, ore, organics, equipment
            FROM $t WHERE bastion_level >= 1 AND owner_player_id > 0
             AND (ore > 0 OR organics > 0 OR equipment > 0)");
        foreach ($rows as $planet) {
            $sold = [];
            $paid = 0;
            foreach ($staples as $key) {
                $stock = (int) $planet->$key;
                if ($stock < 1) continue;
                $units = (int) floor($stock * $share / 100);
                if ($units < 1) continue;
                $price = (int) floor(IB_Game::COMMODITIES[$key]['base'] * $rate / 100);
                $sold[$key] = $units;
                $paid += $units * $price;
            }
            if (!$sold || $paid < 1) continue;
            $wpdb->query($wpdb->prepare(
                "UPDATE $t SET ore = ore - %d, organics = organics - %d, equipment = equipment - %d,
                 bastion_vault = bastion_vault + %d WHERE id = %d",
                isset($sold['ore']) ? $sold['ore'] : 0,
                isset($sold['organics']) ? $sold['organics'] : 0,
                isset($sold['equipment']) ? $sold['equipment'] : 0,
                $paid, $planet->id
            ));
            $out['planets']++;
            $out['units'] += array_sum($sold);
            $out['credits'] += $paid;
        }
        if ($out['credits'] > 0) {
            IB_Log::news('autosell', sprintf(
                "The Crown's factors paid %s credits into the vaults of %d world%s for %s units of cargo.",
                IB_Game::fmt($out['credits']), $out['planets'], $out['planets'] === 1 ? '' : 's', IB_Game::fmt($out['units'])));
        }
        return $out;
    }

    /** What the factors pay for one unit, after the price setting. */
    public static function autosell_price($commodity) {
        if (!isset(IB_Game::COMMODITIES[$commodity])) return 0;
        $rate = max(0, (int) IB_Settings::get('planet_autosell_price_percent'));
        return (int) floor(IB_Game::COMMODITIES[$commodity]['base'] * $rate / 100);
    }

    /** The pilot's own worlds that hold a Vault, for the remote panel. */
    public static function vaults_of($player_id) {
        global $wpdb;
        return $wpdb->get_results($wpdb->prepare(
            'SELECT * FROM ' . IB_DB::t('planets') . ' WHERE owner_player_id = %d AND bastion_level >= 1 ORDER BY sector_id',
            $player_id
        ));
    }

    /**
     * Draws credits from one of the pilot's Vaults from anywhere in the galaxy. The Vault pays out
     * the full amount; the pilot receives it less the transfer fee, which covers the Chancery's
     * couriers. Free of turns, like every other transfer.
     */
    public static function draw($p, $planet_id, $amount) {
        global $wpdb;
        $planet = self::get($planet_id);
        if (!$planet || (int) $planet->owner_player_id !== (int) $p->id) {
            throw new IB_Game_Exception('That is not one of your worlds.');
        }
        if ((int) $planet->bastion_level < 1) throw new IB_Game_Exception('That world has no Vault.');
        if (!is_numeric($amount) || (float) $amount > (float) PHP_INT_MAX) {
            throw new IB_Game_Exception('Enter an amount the Vault actually holds.');
        }
        $amount = (int) $amount;
        if ($amount <= 0) throw new IB_Game_Exception('Enter an amount greater than zero.');

        $t = IB_DB::t('planets');
        $ok = $wpdb->query($wpdb->prepare(
            "UPDATE $t SET bastion_vault = bastion_vault - %d WHERE id = %d AND bastion_vault >= %d",
            $amount, $planet->id, $amount
        ));
        if (!$ok) throw new IB_Game_Exception('The Vault does not hold that much.');

        $fee = (int) ceil($amount * max(0, (int) IB_Settings::get('vault_transfer_fee_percent')) / 100);
        $fee = min($fee, $amount);
        IB_Player::add($p, ['credits' => $amount - $fee]);
        return $fee > 0
            ? sprintf('Drew %s credits from %s; %s went to the couriers, %s reached your account.',
                IB_Game::fmt($amount), $planet->planet_name, IB_Game::fmt($fee), IB_Game::fmt($amount - $fee))
            : sprintf('Drew %s credits from %s.', IB_Game::fmt($amount), $planet->planet_name);
    }

    /** Daily: colonies grow 5% up to the planet's capacity. */
    public static function grow() {
        global $wpdb;
        $t = IB_DB::t('planets');
        foreach (self::CLASSES as $class => $def) {
            if (!$def['max_colonists']) continue;
            $wpdb->query($wpdb->prepare(
                "UPDATE $t SET colonists = LEAST(%d, colonists + CEIL(colonists * 0.05)) WHERE planet_class = %s AND colonists > 0",
                $def['max_colonists'], $class
            ));
        }
    }

    /**
     * Vending machines. A garrison needs feeding: an owned world needs vending_per_1000 machines (1 by
     * default) for every 1,000 fighters stationed on it, each restocked every day for vending_restock
     * credits (100), plus vending_bastion_percent (10%) per bastion level. Only planet garrisons eat:
     * fighters aboard ships and in deployed fleets are fed by the Crown.
     */
    public static function vending_needed($planet) {
        $per = max(0, (int) IB_Settings::get('vending_per_1000'));
        if (!$per || (int) $planet->fighters <= 0) return 0;
        return (int) ceil((int) $planet->fighters / 1000 * $per);
    }

    /** Restock for one machine on this world: the base rate, plus a share per bastion level. */
    public static function vending_unit_cost($planet) {
        $base = max(0, (int) IB_Settings::get('vending_restock'));
        return (int) round($base * (1 + max(0, (int) IB_Settings::get('vending_bastion_percent')) / 100 * (int) $planet->bastion_level));
    }

    /** Daily restock bill: each machine in use, up to what the garrison needs. */
    public static function vending_cost($planet) {
        return min((int) $planet->vending_machines, self::vending_needed($planet)) * self::vending_unit_cost($planet);
    }

    /** Whole days of restocking the supply fund covers; null when nothing needs restocking. */
    public static function vending_days_left($planet) {
        $cost = self::vending_cost($planet);
        return $cost > 0 ? (int) floor((int) $planet->vending_fund / $cost) : null;
    }

    /**
     * Puts your own credits into a world's vending supply fund. Open to the owner and teammates, from
     * the planet itself or, with $planet_id, from anywhere (the Your planets list).
     */
    public static function vending_contribute($p, $amount, $planet_id = 0) {
        global $wpdb;
        if ($planet_id) {
            $planet = self::get($planet_id);
            if (!$planet || !(int) $planet->owner_player_id || !self::is_friendly($p, $planet)) {
                throw new IB_Game_Exception('That planet does not belong to you or your team.');
            }
        } else {
            $planet = self::require_friendly($p);
        }
        $amount = (int) $amount;
        if ($amount <= 0) throw new IB_Game_Exception('Enter an amount greater than zero.');
        IB_Player::spend_credits($p, $amount);
        $wpdb->query($wpdb->prepare('UPDATE ' . IB_DB::t('planets') . ' SET vending_fund = vending_fund + %d WHERE id = %d', $amount, $planet->id));
        $cost = self::vending_cost($planet);
        $fund = (int) $planet->vending_fund + $amount;
        return sprintf('Added %s credits to the vending supply fund on %s (%s in the fund%s).', IB_Game::fmt($amount), $planet->planet_name,
            IB_Game::fmt($fund), $cost ? sprintf(', about %s days of restocking', IB_Game::fmt(floor($fund / $cost))) : '');
    }

    /**
     * Daily: restock every garrison's vending machines, paying from the world's supply fund and then,
     * if the setting allows, its Vault. Fighters without a stocked machine leave the world short.
     * After vending_grace_days short days in a row, vending_desert_percent of those fighters desert
     * each day until it is fixed. A short world raises no new fighters. Owners are told either way.
     *
     * @return array ['planets' => worlds restocked, 'deserted' => fighters lost across the galaxy]
     */
    public static function vending_daily() {
        global $wpdb;
        $out = ['planets' => 0, 'deserted' => 0];
        $per = max(0, (int) IB_Settings::get('vending_per_1000'));
        if (!$per) return $out;
        $vault_pays = (int) IB_Settings::get('vending_vault_pays') === 1;
        $warn_days = max(0, (int) IB_Settings::get('vending_warn_days'));
        $grace = max(0, (int) IB_Settings::get('vending_grace_days'));
        $desert = max(0, min(50, (int) IB_Settings::get('vending_desert_percent'))) / 100;
        $t = IB_DB::t('planets');
        // Worlds that were short but have since lost their whole garrison are no longer short.
        $wpdb->query("UPDATE $t SET vending_short_days = 0 WHERE fighters <= 0 AND vending_short_days > 0");
        foreach ($wpdb->get_results("SELECT * FROM $t WHERE owner_player_id > 0 AND fighters > 0 AND planet_class <> 'C'") as $pl) {
            $needed = self::vending_needed($pl);
            $using = min((int) $pl->vending_machines, $needed);
            $restock = self::vending_unit_cost($pl);
            $bill = $using * $restock;
            // Only whole machines are restocked, and only those are paid for: a fund that covers two
            // and a half machines stocks two and keeps the change.
            $fund = max(0, (int) $pl->vending_fund);
            $available = $fund + ($vault_pays ? max(0, (int) $pl->bastion_vault) : 0);
            $stocked = $restock > 0 ? min($using, (int) floor($available / $restock)) : $using;
            $charge = $stocked * $restock;
            $from_fund = min($fund, $charge);
            $from_vault = $charge - $from_fund;
            $served = (int) floor($stocked * 1000 / $per);
            $unserved = max(0, (int) $pl->fighters - $served);
            $short = $unserved > 0 ? (int) $pl->vending_short_days + 1 : 0;
            $walked = 0;
            if ($unserved > 0 && $short > $grace && $desert > 0) {
                $walked = min($unserved, (int) ceil($unserved * $desert));
            }
            // Relative updates, so fighters lost or added since this row was read are not overwritten.
            $wpdb->query($wpdb->prepare(
                "UPDATE $t SET vending_fund = vending_fund - %d, bastion_vault = bastion_vault - %d, vending_short_days = %d,
                        fighters = GREATEST(0, fighters - %d) WHERE id = %d",
                $from_fund, $from_vault, min(255, $short), $walked, $pl->id
            ));
            if ($stocked > 0) $out['planets']++;
            $out['deserted'] += $walked;
            if ($unserved > 0) {
                $why = (int) $pl->vending_machines < $needed
                    ? sprintf('it has %s vending machines and its %s fighters need %s', IB_Game::fmt($pl->vending_machines), IB_Game::fmt($pl->fighters), IB_Game::fmt($needed))
                    : ($vault_pays ? 'its supply fund and Vault could not pay the restock' : 'its supply fund ran out');
                $when = $short >= $grace ? 'tomorrow' : sprintf('in %d days', $grace - $short + 1);
                if ($walked) {
                    IB_Messages::system((int) $pl->owner_player_id, 'Fighters deserted', sprintf(
                        "%s fighters deserted %s in sector %d because %s.\n\nAssign more vending machines or add to its supply fund to stop it. A world with no fighters left can be claimed by anyone who lands.",
                        IB_Game::fmt($walked), $pl->planet_name, $pl->sector_id, $why));
                    IB_Log::news('vending', sprintf('%s hungry fighters deserted the garrison of %s in sector %d.',
                        IB_Game::fmt($walked), $pl->planet_name, $pl->sector_id));
                } else {
                    IB_Messages::system((int) $pl->owner_player_id, 'Garrison going hungry', sprintf(
                        "%s is short of stocked vending machines because %s. %s fighters are going hungry and will start to desert %s, and the world raises no new fighters until it is fixed.",
                        $pl->planet_name, $why, IB_Game::fmt($unserved), $when));
                }
            }
            // Low-fund warning, so the supply fund is a number worth watching. Sent once, the day it dips.
            $fund_left = (int) $pl->vending_fund - $from_fund;
            if (!$unserved && $warn_days && $bill > 0 && $fund_left < $bill * $warn_days && (int) $pl->vending_fund >= $bill * $warn_days) {
                $days = (int) floor($fund_left / $bill);
                IB_Messages::system((int) $pl->owner_player_id, 'Vending supply fund running low', sprintf(
                    "%s's vending supply fund covers %s of restocking (%s credits a day)%s. Add to it on the Planet page.",
                    $pl->planet_name, $days === 1 ? '1 day' : $days . ' days', IB_Game::fmt($bill),
                    $vault_pays ? '; after that the Vault pays' : '; after that its fighters go hungry'));
            }
        }
        return $out;
    }
}
