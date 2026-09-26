<?php
if (!defined('ABSPATH')) exit;

/**
 * Planets: Aurelia Prime (colonist source), Worldseed-grown and pre-seeded worlds,
 * colonist production and bastions.
 */
class IB_Planets {
    const CROWN_WORLD = 'C';
    const MAX_PER_SECTOR = 3;
    const TRANSFERABLE = ['colonists', 'ore', 'organics', 'equipment', 'fighters'];

    /** Production is per 1,000 colonists per day. */
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

    /** Move colonists, commodities or fighters between ship and planet. $dir is 'take' or 'leave'. */
    public static function transfer($p, $what, $dir, $qty) {
        global $wpdb;
        if (!in_array($what, self::TRANSFERABLE, true)) throw new IB_Game_Exception('Invalid cargo type.');
        $planet = self::require_friendly($p);
        $qty = (int) $qty;
        if ($qty <= 0) throw new IB_Game_Exception('Enter a quantity greater than zero.');
        $ship_col = $what === 'fighters' ? 'fighters' : $what;
        $t = IB_DB::t('planets');

        if ($dir === 'take') {
            $room = $what === 'fighters'
                ? IB_Player::ship($p)['max_fighters'] - $p->fighters
                : IB_Player::holds_free($p);
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
        return sprintf('Construction complete: %s now has a level %d bastion (%s).', $planet->planet_name, $next, $req['name']);
    }

    /** $dir is 'deposit' or 'withdraw'. */
    public static function Vault($p, $dir, $amount) {
        global $wpdb;
        $planet = self::require_friendly($p);
        if ((int) $planet->bastion_level < 1) throw new IB_Game_Exception('Build a bastion to get a Vault.');
        $amount = (int) $amount;
        if ($amount <= 0) throw new IB_Game_Exception('Enter an amount greater than zero.');
        $t = IB_DB::t('planets');
        if ($dir === 'deposit') {
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
        if ($qty > IB_Player::holds_free($p)) throw new IB_Game_Exception(sprintf('You only have %d empty holds.', IB_Player::holds_free($p)));
        $cost = $qty * (int) IB_Settings::get('price_colonist');
        IB_Player::spend_credits($p, $cost);
        IB_Player::add($p, ['colonists' => $qty]);
        return sprintf('%s colonists board your ship (%s credits in transport fees).', IB_Game::fmt($qty), IB_Game::fmt($cost));
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

    /** Rounds fractional production up or down at random so small colonies still produce over time. */
    private static function random_round($x) {
        $whole = (int) floor($x);
        return $whole + ((mt_rand() / mt_getrandmax()) < ($x - $whole) ? 1 : 0);
    }

    /** Hourly: colonists produce commodities and fighters. */
    public static function produce() {
        global $wpdb;
        $t = IB_DB::t('planets');
        $planets = $wpdb->get_results("SELECT id, planet_class, colonists FROM $t WHERE colonists > 0 AND planet_class <> 'C'");
        foreach ($planets as $pl) {
            $rates = self::CLASSES[$pl->planet_class] ?? self::CLASSES['V'];
            $gain = [];
            foreach (['ore', 'organics', 'equipment', 'fighters'] as $k) {
                $gain[$k] = self::random_round($pl->colonists / 1000 * $rates[$k] / 24);
            }
            if (array_sum($gain) === 0) continue;
            $wpdb->query($wpdb->prepare(
                "UPDATE $t SET ore = ore + %d, organics = organics + %d, equipment = equipment + %d, fighters = fighters + %d WHERE id = %d",
                $gain['ore'], $gain['organics'], $gain['equipment'], $gain['fighters'], $pl->id
            ));
        }
        return count($planets);
    }

    /** Daily: colonies grow 2% up to the planet's capacity. */
    public static function grow() {
        global $wpdb;
        $t = IB_DB::t('planets');
        foreach (self::CLASSES as $class => $def) {
            if (!$def['max_colonists']) continue;
            $wpdb->query($wpdb->prepare(
                "UPDATE $t SET colonists = LEAST(%d, colonists + CEIL(colonists * 0.02)) WHERE planet_class = %s AND colonists > 0",
                $def['max_colonists'], $class
            ));
        }
    }
}
