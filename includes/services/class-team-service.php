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

    /** Shortest team password a captain may set. */
    const MIN_PASSWORD = 10;

    /** Longest team post, in characters (the form's own limit). */
    const MAX_POST = 2000;

    /** Password attempts allowed against one team per window, from every pilot together. */
    const JOIN_ATTEMPTS = 20;
    const JOIN_WINDOW = 15 * MINUTE_IN_SECONDS;

    private static function check_password_strength($password) {
        if (strlen($password) > 200) throw new IB_Game_Exception('Choose a team password of at most 200 characters.');
        if (mb_strlen($password, 'UTF-8') < self::MIN_PASSWORD) {
            throw new IB_Game_Exception(sprintf('Choose a team password of at least %d characters.', self::MIN_PASSWORD));
        }
    }

    public static function create($p, $name, $password) {
        global $wpdb;
        if ((int) $p->team_id) throw new IB_Game_Exception('Leave your current team first.');
        $name = IB_Names::clean($name);
        if (IB_Names::length($name) < 3 || IB_Names::length($name) > 41) throw new IB_Game_Exception('Team names must be 3 to 41 characters.');
        if (IB_Names::is_reserved($name)) throw new IB_Game_Exception('That name belongs to the Imperium. Choose another.');
        self::check_password_strength($password);
        if ($wpdb->get_var($wpdb->prepare('SELECT id FROM ' . IB_DB::t('teams') . ' WHERE team_name = %s', $name))) {
            throw new IB_Game_Exception('A team with that name already exists.');
        }
        $inserted = $wpdb->insert(IB_DB::t('teams'), [
            'team_name' => $name, 'password_hash' => wp_hash_password($password),
            'captain_player_id' => $p->id, 'created_at' => current_time('mysql'),
        ]);
        if ($inserted === false && stripos((string) $wpdb->last_error, 'Duplicate') !== false) {
            throw new IB_Game_Exception('A team with that name already exists.');
        }
        IB_DB::check($inserted, 'team create');
        $team_id = (int) $wpdb->insert_id;
        // Founding a team withdraws any request to join another.
        IB_DB::check($wpdb->update(IB_DB::t('players'), ['team_request_id' => 0, 'team_request_at' => null], ['id' => $p->id]), 'team request clear');
        $p->team_request_id = 0;
        self::set_player_team($p->id, $team_id);
        $p->team_id = $team_id;
        IB_Log::news('team', sprintf('%s founded the team "%s".', $p->alias_name, $name));
        return sprintf('Team "%s" founded. Pilots who know the password can ask to join, and you approve each one.', $name);
    }

    /**
     * Asks to join a team. The password only gets a request in front of the captain, who accepts
     * or declines it; knowing (or guessing) the password is never enough on its own to reach the
     * team's shared planets and Vaults.
     *
     * Guesses are rate limited per team, counted in one guarded UPDATE so parallel guesses cannot
     * all slip under the limit, and every wrong guess is written to the admin log. Those writes are
     * kept even though the request fails (the exception is thrown with commit set).
     */
    public static function join($p, $team_id, $password) {
        global $wpdb;
        if ((int) $p->team_id) throw new IB_Game_Exception('Leave your current team first.');
        $team = self::get($team_id);
        if (!$team) throw new IB_Game_Exception('That team no longer exists.');
        if ((int) $p->team_request_id) {
            throw new IB_Game_Exception('You have already asked to join a team. Withdraw that request first.');
        }
        if (strlen($password) > 200) throw new IB_Game_Exception('Incorrect team password.');
        if (!IB_DB::rate_limit('teams', $team->id, 'join_attempts', 'join_window', self::JOIN_ATTEMPTS, self::JOIN_WINDOW)) {
            // Logged once per team per window, so the throttle cannot be used to flood the log.
            if (!get_transient('ib_join_limit_' . (int) $team->id)) {
                set_transient('ib_join_limit_' . (int) $team->id, 1, self::JOIN_WINDOW);
                IB_Log::admin('security', sprintf('Team join attempts for "%s" (#%d) hit the limit; pilot %s (#%d) was turned away.',
                    $team->team_name, $team->id, $p->alias_name, $p->id));
            }
            throw new IB_Game_Exception('That team has had too many password attempts. Try again in a quarter of an hour.', 'error', true);
        }
        if (!wp_check_password($password, $team->password_hash)) {
            IB_Log::admin('security', sprintf('Wrong team password for "%s" (#%d) from pilot %s (#%d, user #%d).',
                $team->team_name, $team->id, $p->alias_name, $p->id, get_current_user_id()));
            throw new IB_Game_Exception('Incorrect team password.', 'error', true);
        }
        $max = (int) IB_Settings::get('team_max_members');
        if ($max && count(self::members($team->id)) >= $max) throw new IB_Game_Exception('That team is full.');
        IB_DB::check($wpdb->update(IB_DB::t('players'), ['team_request_id' => $team->id, 'team_request_at' => current_time('mysql')], ['id' => $p->id]), 'team request');
        $p->team_request_id = $team->id;
        IB_Messages::system($team->captain_player_id, 'Request to join your team', sprintf(
            '%s has asked to join %s. Accept or decline on the Team page.', $p->alias_name, $team->team_name));
        return sprintf('Your request to join %s has gone to its captain. You will hear back by sub-space message.', $team->team_name);
    }

    /** Pilots waiting for the captain's answer. */
    public static function requests($team_id) {
        global $wpdb;
        return $wpdb->get_results($wpdb->prepare(
            'SELECT id, alias_name, experience, team_request_at FROM ' . IB_DB::t('players') . ' WHERE team_request_id = %d AND team_id = 0 ORDER BY team_request_at',
            $team_id
        ));
    }

    public static function accept($p, $member_id) {
        global $wpdb;
        $team = self::require_captain($p);
        // Lock the team row for the rest of the action, so two acceptances cannot both see a free
        // place and take the team over its size limit.
        $wpdb->get_var($wpdb->prepare('SELECT id FROM ' . IB_DB::t('teams') . ' WHERE id = %d FOR UPDATE', $team->id));
        $member = IB_Player::get($member_id);
        if (!$member || (int) $member->team_request_id !== (int) $team->id || (int) $member->team_id) {
            throw new IB_Game_Exception('That pilot has no request waiting for this team.');
        }
        $max = (int) IB_Settings::get('team_max_members');
        if ($max && count(self::members($team->id)) >= $max) throw new IB_Game_Exception('Your team is full.');
        $ok = IB_DB::check($wpdb->query($wpdb->prepare(
            'UPDATE ' . IB_DB::t('players') . ' SET team_id = %d, team_request_id = 0, team_request_at = NULL WHERE id = %d AND team_id = 0 AND team_request_id = %d',
            $team->id, $member->id, $team->id
        )), 'team accept');
        if (!$ok) throw new IB_Game_Exception('That pilot has no request waiting for this team.');
        self::set_player_team($member->id, $team->id);
        IB_Messages::system($member->id, 'Welcome aboard', sprintf('%s accepted you into %s.', $p->alias_name, $team->team_name));
        self::broadcast($team->id, 0, 'New member', sprintf('%s has joined the team.', $member->alias_name));
        IB_Log::news('team', sprintf('%s has joined the house of %s.', $member->alias_name, $team->team_name));
        return sprintf('%s is now on the team.', $member->alias_name);
    }

    public static function decline($p, $member_id) {
        global $wpdb;
        $team = self::require_captain($p);
        $member = IB_Player::get($member_id);
        $ok = $member ? IB_DB::check($wpdb->query($wpdb->prepare(
            'UPDATE ' . IB_DB::t('players') . ' SET team_request_id = 0, team_request_at = NULL WHERE id = %d AND team_request_id = %d',
            $member->id, $team->id
        )), 'team decline') : 0;
        if (!$ok) throw new IB_Game_Exception('That pilot has no request waiting for this team.');
        IB_Messages::system($member->id, 'Request declined', sprintf('The captain of %s declined your request to join.', $team->team_name));
        return sprintf('Declined %s.', $member->alias_name);
    }

    public static function cancel_request($p) {
        global $wpdb;
        if (!(int) $p->team_request_id) throw new IB_Game_Exception('You have no request waiting.');
        IB_DB::check($wpdb->update(IB_DB::t('players'), ['team_request_id' => 0, 'team_request_at' => null], ['id' => $p->id]), 'team request cancel');
        $p->team_request_id = 0;
        return 'Request withdrawn.';
    }

    /**
     * The captain sets a new password, for instance after removing a member. Pending requests made
     * with the old password stay in front of the captain to accept or decline.
     */
    public static function change_password($p, $password) {
        global $wpdb;
        $team = self::require_captain($p);
        self::check_password_strength($password);
        IB_DB::check($wpdb->update(IB_DB::t('teams'), ['password_hash' => wp_hash_password($password)], ['id' => $team->id]), 'team password');
        IB_Log::admin('team', sprintf('Captain %s (#%d) changed the password of "%s" (#%d).', $p->alias_name, $p->id, $team->team_name, $team->id));
        self::broadcast($team->id, 0, 'Password changed', sprintf('%s changed the team password.', $p->alias_name));
        return 'Team password changed. Share it only with pilots you trust.';
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
        $wpdb->query($wpdb->prepare('UPDATE ' . IB_DB::t('players') . ' SET team_request_id = 0, team_request_at = NULL WHERE team_request_id = %d', $team_id));
        $wpdb->delete(IB_DB::t('messages'), ['team_id' => $team_id]);
        $wpdb->delete(IB_DB::t('teams'), ['id' => $team_id]);
    }

    public static function broadcast($team_id, $sender_id, $subject, $text) {
        global $wpdb;
        IB_DB::check($wpdb->insert(IB_DB::t('messages'), [
            'sender_player_id' => $sender_id, 'recipient_player_id' => 0, 'team_id' => $team_id,
            'subject' => IB_Names::cut($subject, 120), 'message_text' => $text, 'created_at' => current_time('mysql'),
        ]), 'team broadcast');
    }

    public static function post($p, $text) {
        if (!(int) $p->team_id) throw new IB_Game_Exception('You are not on a team.');
        $text = trim(sanitize_textarea_field($text));
        if ($text === '') throw new IB_Game_Exception('Write a message first.');
        if (mb_strlen($text, 'UTF-8') > self::MAX_POST) throw new IB_Game_Exception(sprintf('Team posts can be up to %s characters.', IB_Game::fmt(self::MAX_POST)));
        IB_Messages::rate_limit($p);
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

    /**
     * Team net worth is the sum of its members' net worth. Pass the result of
     * IB_Player::all_with_worth() when totalling several teams, so the worth of each pilot is
     * worked out once rather than once per team.
     */
    public static function net_worth($team_id, $pilots = null) {
        if ($pilots === null) {
            $total = 0;
            foreach (self::members($team_id) as $m) $total += IB_Player::net_worth($m);
            return $total;
        }
        $total = 0;
        foreach ($pilots as $pl) {
            if ((int) $pl->team_id === (int) $team_id) $total += $pl->worth;
        }
        return $total;
    }
}
