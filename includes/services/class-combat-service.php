<?php
if (!defined('ABSPATH')) exit;

/**
 * Fighter combat, sector defenses and sector-entry encounters.
 */
class IB_Combat {

    /**
     * Resolves one engagement. Attacking fighters first burn through shields,
     * then trade blows with defending fighters. Each side's fighters are weighted
     * by its odds and a +/-15% luck roll.
     *
     * @return array att_lost, def_fighters_lost, def_shields_lost, destroyed
     */
    public static function resolve($att, $att_odds, $def_fighters, $def_shields, $def_odds) {
        $att = max(0, (int) $att);
        $def_fighters = max(0, (int) $def_fighters);
        $def_shields = max(0, (int) $def_shields);
        $a_unit = $att_odds * mt_rand(85, 115) / 100;
        $d_unit = $def_odds * mt_rand(85, 115) / 100;

        $strength = $att * $a_unit;
        $shield_hit = min($def_shields, $strength);
        $strength -= $shield_hit;
        $defense = $def_fighters * $d_unit;

        if ($strength > $defense) {
            return [
                'att_lost' => min($att, (int) ceil(($shield_hit + $defense) / $a_unit)),
                'def_fighters_lost' => $def_fighters,
                'def_shields_lost' => $def_shields,
                'destroyed' => true,
            ];
        }
        return [
            'att_lost' => $att,
            'def_fighters_lost' => min($def_fighters, (int) floor($strength / $d_unit)),
            'def_shields_lost' => (int) floor($shield_hit),
            'destroyed' => false,
        ];
    }

    private static function prepare_attack($p, $fighters) {
        if (IB_Game::is_core($p->sector_id)) throw new IB_Game_Exception('Combat is forbidden in the Imperial Core.');
        $fighters = (int) $fighters;
        if ($fighters <= 0) throw new IB_Game_Exception('Commit at least one fighter.');
        if ($fighters > (int) $p->fighters) throw new IB_Game_Exception(sprintf('You only have %s fighters.', IB_Game::fmt($p->fighters)));
        IB_Player::spend_turns($p, (int) IB_Settings::get('attack_turn_cost'));
        return $fighters;
    }

    public static function attack_player($p, $target_id, $fighters) {
        $target = IB_Player::get($target_id);
        if (!$target || (int) $target->id === (int) $p->id || (int) $target->sector_id !== (int) $p->sector_id || (int) $target->landed_planet_id) {
            throw new IB_Game_Exception('That ship is not in this sector.');
        }
        if ((int) $p->team_id && (int) $p->team_id === (int) $target->team_id) throw new IB_Game_Exception('You cannot attack a teammate.');
        $fighters = self::prepare_attack($p, $fighters);

        $r = self::resolve($fighters, IB_Player::ship($p)['off'], $target->fighters, $target->shield_points, IB_Player::ship($target)['def']);
        IB_Player::add($p, ['fighters' => -$r['att_lost'], 'experience' => (int) ceil($r['def_fighters_lost'] / 10)]);
        IB_Player::add($target, ['fighters' => -$r['def_fighters_lost'], 'shield_points' => -$r['def_shields_lost']]);

        $summary = sprintf('You lost %s fighters. %s lost %s fighters and %s shield points.',
            IB_Game::fmt($r['att_lost']), $target->alias_name, IB_Game::fmt($r['def_fighters_lost']), IB_Game::fmt($r['def_shields_lost']));

        if ($r['destroyed']) {
            IB_Player::add($p, [
                'kills' => 1,
                'experience' => 50 + (int) ($target->experience / 20),
                'alignment' => (int) $target->alignment >= 0 ? -25 : 25,
            ]);
            IB_Player::destroy($target, sprintf('Destroyed by %s in sector %d.', $p->alias_name, $p->sector_id));
            if ((int) $p->team_id) IB_Teams::award_medal($p->team_id);
            return $summary . sprintf(' %s\'s ship explodes in a brilliant fireball!', $target->alias_name);
        }
        IB_Messages::system($target->id, 'You were attacked', sprintf(
            '%s attacked you in sector %d. You lost %s fighters and %s shield points; they lost %s fighters.',
            $p->alias_name, $p->sector_id, IB_Game::fmt($r['def_fighters_lost']), IB_Game::fmt($r['def_shields_lost']), IB_Game::fmt($r['att_lost'])
        ));
        return $summary . ' Their ship survived.';
    }

    public static function fleets_in_sector($sector_id) {
        global $wpdb;
        return $wpdb->get_results($wpdb->prepare('SELECT * FROM ' . IB_DB::t('fleets') . ' WHERE sector_id = %d AND fighter_count > 0 ORDER BY id', $sector_id));
    }

    public static function fleet_is_friendly($p, $fleet) {
        if ((int) $fleet->owner_player_id === 0) return false;
        if ((int) $fleet->owner_player_id === (int) $p->id) return true;
        return (int) $p->team_id > 0 && (int) $fleet->team_id === (int) $p->team_id;
    }

    public static function fleet_owner_label($fleet) {
        return (int) $fleet->owner_player_id ? IB_Player::name($fleet->owner_player_id) : IB_Factions::name($fleet->faction);
    }

    private static function fleet_odds($fleet) {
        if ((int) $fleet->owner_player_id) return 1.0;
        $f = IB_Factions::get($fleet->faction);
        return $f ? $f['odds'] : 1.0;
    }

    private static function reduce_fleet($fleet, $lost) {
        global $wpdb;
        $left = max(0, (int) $fleet->fighter_count - (int) $lost);
        if ($left === 0) {
            $wpdb->delete(IB_DB::t('fleets'), ['id' => $fleet->id]);
        } else {
            $wpdb->update(IB_DB::t('fleets'), ['fighter_count' => $left], ['id' => $fleet->id]);
        }
        return $left;
    }

    public static function attack_fleet($p, $fleet_id, $fighters) {
        global $wpdb;
        $fleet = $wpdb->get_row($wpdb->prepare('SELECT * FROM ' . IB_DB::t('fleets') . ' WHERE id = %d', $fleet_id));
        if (!$fleet || (int) $fleet->sector_id !== (int) $p->sector_id) throw new IB_Game_Exception('Those fighters are not in this sector.');
        if (self::fleet_is_friendly($p, $fleet)) throw new IB_Game_Exception('Those are friendly fighters.');
        $fighters = self::prepare_attack($p, $fighters);

        $owner = self::fleet_owner_label($fleet);
        $r = self::resolve($fighters, IB_Player::ship($p)['off'], $fleet->fighter_count, 0, self::fleet_odds($fleet));
        $killed = $r['def_fighters_lost'];
        $left = self::reduce_fleet($fleet, $killed);
        $deltas = ['fighters' => -$r['att_lost'], 'experience' => (int) ceil($killed / 10)];
        $msg = sprintf('You lost %s fighters and destroyed %s %s fighters.', IB_Game::fmt($r['att_lost']), IB_Game::fmt($killed), $owner);

        if (!(int) $fleet->owner_player_id) {
            $faction = IB_Factions::get($fleet->faction);
            if ($faction && $killed > 0) {
                $bounty = $killed * $faction['bounty'];
                $deltas['credits'] = $bounty;
                $deltas['alignment'] = $faction['alignment'] * max(1, (int) round($killed / 50));
                if ($bounty) $msg .= sprintf(' The Imperial Chancery pays a bounty of %s credits.', IB_Game::fmt($bounty));
                if ($faction['alignment'] < 0) $msg .= ' The Chancery frowns on attacking peaceful patrols.';
            }
        } else {
            IB_Messages::system($fleet->owner_player_id, 'Sector fighters attacked', sprintf(
                '%s attacked your fighters in sector %d, destroying %s of them. %s remain.',
                $p->alias_name, $p->sector_id, IB_Game::fmt($killed), IB_Game::fmt($left)
            ));
        }
        IB_Player::add($p, $deltas);
        if ($left === 0) $msg .= ' The sector is clear.';
        return $msg;
    }

    public static function attack_planet($p, $planet_id, $fighters) {
        $planet = IB_Planets::get($planet_id);
        if (!$planet || (int) $planet->sector_id !== (int) $p->sector_id) throw new IB_Game_Exception('That planet is not in this sector.');
        if ($planet->planet_class === IB_Planets::CROWN_WORLD) throw new IB_Game_Exception('Attacking the Crown World would bring the entire Imperium down on you.');
        if (IB_Planets::is_friendly($p, $planet)) throw new IB_Game_Exception('That planet is yours.');
        if ((int) $planet->fighters === 0) throw new IB_Game_Exception('The planet has no fighters. Land on it and claim it.');
        $fighters = self::prepare_attack($p, $fighters);

        global $wpdb;
        $r = self::resolve($fighters, IB_Player::ship($p)['off'], $planet->fighters, 0, IB_Planets::defense_odds($planet));
        $left = max(0, (int) $planet->fighters - $r['def_fighters_lost']);
        $wpdb->update(IB_DB::t('planets'), ['fighters' => $left], ['id' => $planet->id]);
        IB_Player::add($p, ['fighters' => -$r['att_lost'], 'experience' => (int) ceil($r['def_fighters_lost'] / 10)]);

        $msg = sprintf('You lost %s fighters. The planet lost %s fighters (%s remain).',
            IB_Game::fmt($r['att_lost']), IB_Game::fmt($r['def_fighters_lost']), IB_Game::fmt($left));
        if ($left === 0) $msg .= ' Its defenses are down. Land and claim it!';
        if ((int) $planet->owner_player_id) {
            IB_Messages::system($planet->owner_player_id, 'Planet under attack', sprintf(
                '%s attacked %s in sector %d. %s defending fighters remain.', $p->alias_name, $planet->planet_name, $planet->sector_id, IB_Game::fmt($left)
            ));
        }
        return $msg;
    }

    /**
     * Called after a ship enters a sector: offensive fighters and lance batteries fire.
     * @return string[] messages; $p->destroyed is set when the ship did not survive.
     */
    public static function sector_entry($p) {
        $p->destroyed = false;
        if (IB_Game::is_core($p->sector_id)) return [];
        $msgs = [];
        $ship = IB_Player::ship($p);

        foreach (self::fleets_in_sector($p->sector_id) as $fleet) {
            if ($fleet->fleet_mode !== 'offensive' || self::fleet_is_friendly($p, $fleet)) continue;
            $owner = self::fleet_owner_label($fleet);
            $r = self::resolve($fleet->fighter_count, self::fleet_odds($fleet), $p->fighters, $p->shield_points, $ship['def']);
            self::reduce_fleet($fleet, $r['att_lost']);
            IB_Player::add($p, ['fighters' => -$r['def_fighters_lost'], 'shield_points' => -$r['def_shields_lost']]);
            $msgs[] = sprintf('%s %s fighters attack! You lose %s fighters and %s shield points, and destroy %s of them.',
                IB_Game::fmt($fleet->fighter_count), $owner, IB_Game::fmt($r['def_fighters_lost']), IB_Game::fmt($r['def_shields_lost']), IB_Game::fmt($r['att_lost']));
            if ((int) $fleet->owner_player_id) {
                IB_Messages::system($fleet->owner_player_id, 'Sector fighters engaged', sprintf(
                    'Your fighters in sector %d engaged %s. They destroyed %s of their fighters and lost %s.',
                    $p->sector_id, $p->alias_name, IB_Game::fmt($r['def_fighters_lost']), IB_Game::fmt($r['att_lost'])
                ));
            }
            if ($r['destroyed']) {
                IB_Player::destroy($p, sprintf('Destroyed by %s fighters in sector %d.', $owner, $p->sector_id));
                $msgs[] = 'Your ship has been destroyed! You eject in your escape pod...';
                $p->destroyed = true;
                return $msgs;
            }
        }

        foreach (IB_Planets::in_sector($p->sector_id) as $planet) {
            if ((int) $planet->bastion_level < 3 || !IB_Planets::is_hostile($p, $planet)) continue;
            $shot = min((int) $planet->ore, 100 * ((int) $planet->bastion_level - 2));
            if ($shot <= 0) continue;
            global $wpdb;
            $wpdb->query($wpdb->prepare('UPDATE ' . IB_DB::t('planets') . ' SET ore = ore - %d WHERE id = %d', $shot, $planet->id));
            $shields = min((int) $p->shield_points, $shot);
            $hull = min((int) $p->fighters, $shot - $shields);
            IB_Player::add($p, ['shield_points' => -$shields, 'fighters' => -$hull]);
            $msgs[] = sprintf('The Lance Battery on %s fires! You lose %s shield points and %s fighters.', $planet->planet_name, IB_Game::fmt($shields), IB_Game::fmt($hull));
            if ($shot - $shields - $hull > 0) {
                IB_Player::destroy($p, sprintf('Destroyed by the Lance Battery of %s in sector %d.', $planet->planet_name, $p->sector_id));
                $msgs[] = 'Your ship has been destroyed! You eject in your escape pod...';
                $p->destroyed = true;
                return $msgs;
            }
        }
        return $msgs;
    }

    public static function deploy($p, $qty, $mode) {
        global $wpdb;
        if (IB_Game::is_core($p->sector_id)) throw new IB_Game_Exception('Fighters may not be deployed in the Imperial Core.');
        $qty = (int) $qty;
        if (!in_array($mode, ['offensive', 'defensive'], true)) $mode = 'defensive';
        if ($qty <= 0) throw new IB_Game_Exception('Enter a quantity greater than zero.');
        if ($qty > (int) $p->fighters) throw new IB_Game_Exception(sprintf('You only have %s fighters.', IB_Game::fmt($p->fighters)));
        $existing = null;
        foreach (self::fleets_in_sector($p->sector_id) as $fleet) {
            if (!self::fleet_is_friendly($p, $fleet)) throw new IB_Game_Exception('Hostile fighters hold this sector. Destroy them first.');
            if ((int) $fleet->owner_player_id === (int) $p->id) $existing = $fleet;
        }
        IB_Player::add($p, ['fighters' => -$qty]);
        if ($existing) {
            $wpdb->query($wpdb->prepare(
                'UPDATE ' . IB_DB::t('fleets') . ' SET fighter_count = fighter_count + %d, fleet_mode = %s WHERE id = %d', $qty, $mode, $existing->id
            ));
        } else {
            $wpdb->insert(IB_DB::t('fleets'), [
                'faction' => '', 'owner_player_id' => $p->id, 'team_id' => (int) $p->team_id, 'sector_id' => $p->sector_id,
                'fighter_count' => $qty, 'fleet_mode' => $mode, 'created_at' => current_time('mysql'),
            ]);
        }
        return sprintf('Deployed %s %s fighters in sector %d.', IB_Game::fmt($qty), $mode, $p->sector_id);
    }

    public static function recall($p, $fleet_id, $qty) {
        global $wpdb;
        $fleet = $wpdb->get_row($wpdb->prepare('SELECT * FROM ' . IB_DB::t('fleets') . ' WHERE id = %d', $fleet_id));
        if (!$fleet || (int) $fleet->sector_id !== (int) $p->sector_id || !self::fleet_is_friendly($p, $fleet)) {
            throw new IB_Game_Exception('You have no fighters here to recall.');
        }
        $qty = min((int) $qty, (int) $fleet->fighter_count);
        $room = IB_Player::ship($p)['max_fighters'] - (int) $p->fighters;
        if ($qty <= 0) throw new IB_Game_Exception('Enter a quantity greater than zero.');
        if ($qty > $room) throw new IB_Game_Exception(sprintf('Your ship can only carry %s more fighters.', IB_Game::fmt(max(0, $room))));
        self::reduce_fleet($fleet, $qty);
        IB_Player::add($p, ['fighters' => $qty]);
        return sprintf('Recalled %s fighters to your ship.', IB_Game::fmt($qty));
    }

    /** All fighters a player has deployed across the galaxy. */
    public static function deployed_by($player_id) {
        global $wpdb;
        return $wpdb->get_results($wpdb->prepare('SELECT * FROM ' . IB_DB::t('fleets') . ' WHERE owner_player_id = %d ORDER BY sector_id', $player_id));
    }
}
