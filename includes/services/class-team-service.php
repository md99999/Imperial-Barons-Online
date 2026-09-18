<?php
if (!defined('ABSPATH')) exit;

/**
 * Teams (corporations). Teammates share planets and sector fighters and cannot attack each other.
 */
class IB_Teams {

    public static function get($id) {
        global $wpdb;
        return $wpdb->get_row($wpdb->prepare('SELECT * FROM ' . IB_DB::t('teams') . ' WHERE id = %d', $id));
    }

    public static function all() {
        global $wpdb;
        return $wpdb->get_results(
            'SELECT t.*, COUNT(p.id) AS members FROM ' . IB_DB::t('teams') . ' t LEFT JOIN ' . IB_DB::t('players') . ' p ON p.team_id = t.id
             GROUP BY t.id ORDER BY t.team_name'
        );
    }

    public static function members($team_id) {
        global $wpdb;
        return $wpdb->get_results($wpdb->prepare('SELECT * FROM ' . IB_DB::t('players') . ' WHERE team_id = %d ORDER BY alias_name', $team_id));
    }

    public static function is_captain($p) {
        if (!(int) $p->team_id) return false;
        $team = self::get($p->team_id);
        return $team && (int) $team->captain_player_id === (int) $p->id;
    }

    /** Keeps a player's planets and deployed fighters in step with their team membership. */
    private static function set_player_team($player_id, $team_id) {
        global $wpdb;
        $wpdb->update(IB_DB::t('players'), ['team_id' => $team_id], ['id' => $player_id]);
        $wpdb->update(IB_DB::t('planets'), ['team_id' => $team_id], ['owner_player_id' => $player_id]);
        $wpdb->update(IB_DB::t('fleets'), ['team_id' => $team_id], ['owner_player_id' => $player_id]);
    }

    public static function create($p, $name, $password) {
        global $wpdb;
        if ((int) $p->team_id) throw new IB_Game_Exception('Leave your current team first.');
        $name = trim(sanitize_text_field($name));
        if (strlen($name) < 3 || strlen($name) > 41) throw new IB_Game_Exception('Team names must be 3 to 41 characters.');
        if (strlen($password) < 4) throw new IB_Game_Exception('Choose a team password of at least 4 characters.');
        if ($wpdb->get_var($wpdb->prepare('SELECT id FROM ' . IB_DB::t('teams') . ' WHERE team_name = %s', $name))) {
            throw new IB_Game_Exception('A team with that name already exists.');
        }
        $wpdb->insert(IB_DB::t('teams'), [
            'team_name' => $name, 'password_hash' => wp_hash_password($password),
            'captain_player_id' => $p->id, 'created_at' => current_time('mysql'),
        ]);
        $team_id = (int) $wpdb->insert_id;
        self::set_player_team($p->id, $team_id);
        $p->team_id = $team_id;
        IB_Log::news('team', sprintf('%s founded the team "%s".', $p->alias_name, $name));
        return sprintf('Team "%s" founded. Share the password with pilots you trust.', $name);
    }

    public static function join($p, $team_id, $password) {
        if ((int) $p->team_id) throw new IB_Game_Exception('Leave your current team first.');
        $team = self::get($team_id);
        if (!$team) throw new IB_Game_Exception('That team no longer exists.');
        if (!wp_check_password($password, $team->password_hash)) throw new IB_Game_Exception('Incorrect team password.');
        $max = (int) IB_Settings::get('team_max_members');
        if ($max && count(self::members($team->id)) >= $max) throw new IB_Game_Exception('That team is full.');
        self::set_player_team($p->id, $team->id);
        $p->team_id = $team->id;
        self::broadcast($team->id, 0, 'New member', sprintf('%s has joined the team.', $p->alias_name));
        return sprintf('Welcome to %s!', $team->team_name);
    }

    public static function leave($p) {
        global $wpdb;
        $team = (int) $p->team_id ? self::get($p->team_id) : null;
        if (!$team) throw new IB_Game_Exception('You are not on a team.');
        self::set_player_team($p->id, 0);
        $p->team_id = 0;
        $remaining = self::members($team->id);
        if (!$remaining) {
            self::delete($team->id);
            return sprintf('You left %s. With no members left, the team has been disbanded.', $team->team_name);
        }
        if ((int) $team->captain_player_id === (int) $p->id) {
            $wpdb->update(IB_DB::t('teams'), ['captain_player_id' => $remaining[0]->id], ['id' => $team->id]);
            self::broadcast($team->id, 0, 'New captain', sprintf('%s left the team. %s is the new captain.', $p->alias_name, $remaining[0]->alias_name));
        } else {
            self::broadcast($team->id, 0, 'Member left', sprintf('%s left the team.', $p->alias_name));
        }
        return sprintf('You left %s.', $team->team_name);
    }

    private static function require_captain($p) {
        if (!self::is_captain($p)) throw new IB_Game_Exception('Only the team captain can do that.');
        return self::get($p->team_id);
    }

    public static function kick($p, $member_id) {
        $team = self::require_captain($p);
        $member = IB_Player::get($member_id);
        if (!$member || (int) $member->team_id !== (int) $team->id || (int) $member->id === (int) $p->id) {
            throw new IB_Game_Exception('That pilot is not a member you can remove.');
        }
        self::set_player_team($member->id, 0);
        IB_Messages::system($member->id, 'Removed from team', sprintf('You were removed from %s by the captain.', $team->team_name));
        return sprintf('%s has been removed from the team.', $member->alias_name);
    }

    public static function make_captain($p, $member_id) {
        global $wpdb;
        $team = self::require_captain($p);
        $member = IB_Player::get($member_id);
        if (!$member || (int) $member->team_id !== (int) $team->id) throw new IB_Game_Exception('That pilot is not on your team.');
        $wpdb->update(IB_DB::t('teams'), ['captain_player_id' => $member->id], ['id' => $team->id]);
        self::broadcast($team->id, 0, 'New captain', sprintf('%s is now the team captain.', $member->alias_name));
        return sprintf('%s is now captain.', $member->alias_name);
    }

    public static function disband($p) {
        $team = self::require_captain($p);
        foreach (self::members($team->id) as $m) {
            if ((int) $m->id !== (int) $p->id) IB_Messages::system($m->id, 'Team disbanded', sprintf('%s has been disbanded by its captain.', $team->team_name));
        }
        self::delete($team->id);
        $p->team_id = 0;
        IB_Log::news('team', sprintf('The team "%s" has been disbanded.', $team->team_name));
        return 'Team disbanded.';
    }

    public static function delete($team_id) {
        global $wpdb;
        foreach (['players', 'planets', 'fleets'] as $table) {
            $wpdb->update(IB_DB::t($table), ['team_id' => 0], ['team_id' => $team_id]);
        }
        $wpdb->delete(IB_DB::t('messages'), ['team_id' => $team_id]);
        $wpdb->delete(IB_DB::t('teams'), ['id' => $team_id]);
    }

    public static function broadcast($team_id, $sender_id, $subject, $text) {
        global $wpdb;
        $wpdb->insert(IB_DB::t('messages'), [
            'sender_player_id' => $sender_id, 'recipient_player_id' => 0, 'team_id' => $team_id,
            'subject' => substr($subject, 0, 120), 'message_text' => $text, 'created_at' => current_time('mysql'),
        ]);
    }

    public static function post($p, $text) {
        if (!(int) $p->team_id) throw new IB_Game_Exception('You are not on a team.');
        $text = trim(sanitize_textarea_field($text));
        if ($text === '') throw new IB_Game_Exception('Write a message first.');
        self::broadcast($p->team_id, $p->id, 'Team chat', $text);
        return 'Message posted to your team.';
    }

    public static function board($team_id, $limit = 30) {
        global $wpdb;
        return $wpdb->get_results($wpdb->prepare(
            'SELECT * FROM ' . IB_DB::t('messages') . ' WHERE team_id = %d ORDER BY id DESC LIMIT %d', $team_id, $limit
        ));
    }

    public static function award_medal($team_id) {
        global $wpdb;
        $wpdb->query($wpdb->prepare('UPDATE ' . IB_DB::t('teams') . ' SET combat_medals = combat_medals + 1 WHERE id = %d', $team_id));
    }

    /** Team net worth is the sum of its members' net worth. */
    public static function net_worth($team_id) {
        $total = 0;
        foreach (self::members($team_id) as $m) $total += IB_Player::net_worth($m);
        return $total;
    }
}
