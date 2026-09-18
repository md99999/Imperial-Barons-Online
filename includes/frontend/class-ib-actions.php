<?php
if (!defined('ABSPATH')) exit;

/**
 * Handles every game form (POST -> redirect -> GET). Services throw IB_Game_Exception
 * on failure; the message becomes a flash notice on the next page.
 */
class IB_Actions {

    public static function handle() {
        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST' || empty($_POST['ib_action']) || !is_user_logged_in()) return;

        $redirect = wp_get_referer() ?: IB_UI::url('dashboard');
        if (!isset($_POST['ib_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['ib_nonce'])), 'ib_action')) {
            IB_UI::flash('error', 'Your session expired. Please try again.');
            wp_safe_redirect($redirect);
            exit;
        }

        $action = sanitize_key(wp_unslash($_POST['ib_action']));
        $p = IB_Player::current();
        try {
            if (!$p && $action !== 'register') throw new IB_Game_Exception('Create a pilot first.');
            list($messages, $goto) = self::dispatch($action, $p);
            foreach ((array) $messages as $m) {
                if (is_array($m)) IB_UI::flash($m[0], $m[1]); else IB_UI::flash('success', $m);
            }
            if ($goto) $redirect = IB_UI::url($goto);
        } catch (IB_Game_Exception $e) {
            IB_UI::flash($e->type, $e->getMessage());
        }
        wp_safe_redirect($redirect);
        exit;
    }

    private static function field($name, $default = '') {
        return isset($_POST[$name]) ? wp_unslash($_POST[$name]) : $default;
    }

    private static function int($name) {
        return (int) self::field($name, 0);
    }

    private static function text($name) {
        return sanitize_text_field(self::field($name));
    }

    /** @return array [messages, page key to redirect to or null for "back"] */
    private static function dispatch($action, $p) {
        switch ($action) {
            case 'register':
                IB_Player::create(get_current_user_id(), self::text('alias'), self::text('ship_name'));
                return [['Your pilot is registered. Welcome aboard!'], 'sector'];

            // Navigation
            case 'move':
                $events = IB_Player::move($p, self::int('to'));
                return [$events ?: [sprintf('Warped to sector %d.', $p->sector_id)], 'sector'];
            case 'autopilot':
                return [IB_Player::autopilot($p, self::int('target')), 'sector'];
            case 'launch_drone':
                return [[IB_Discovery::launch_drone($p)], null];

            // Ports
            case 'dock':
                $port = IB_Ports::dock($p);
                return [[sprintf('Docked at %s.', $port->port_name)], 'port'];
            case 'undock':
                IB_Player::update($p, ['docked_port_id' => 0]);
                return [['You undock and return to open space.'], 'sector'];
            case 'trade':
                return [[IB_Ports::trade($p, sanitize_key(self::field('commodity')), self::int('qty'), self::int('offer'))], null];
            case 'buy_hardware':
                return [[IB_Ports::buy_hardware($p, sanitize_key(self::field('item')), self::int('qty'))], null];
            case 'buy_ship':
                return [[IB_Ports::buy_ship($p, sanitize_key(self::field('ship_type')))], null];
            case 'buy_chart':
                return [[IB_Discovery::buy_nebula_chart($p, self::text('nebula'))], null];

            // Planets
            case 'land':
                $planet = IB_Planets::land($p, self::int('planet_id'));
                return [[sprintf('You land on %s.', $planet->planet_name)], 'planet'];
            case 'leave_planet':
                IB_Planets::leave($p);
                return [['You lift off into orbit.'], 'sector'];
            case 'claim_planet':
                return [[IB_Planets::claim($p)], null];
            case 'planet_transfer':
                return [[IB_Planets::transfer($p, sanitize_key(self::field('what')), sanitize_key(self::field('dir')), self::int('qty'))], null];
            case 'build_bastion':
                return [[IB_Planets::build_bastion($p)], null];
            case 'Vault':
                return [[IB_Planets::Vault($p, sanitize_key(self::field('dir')), self::int('amount'))], null];
            case 'rename_planet':
                return [[IB_Planets::rename($p, self::text('name'))], null];
            case 'buy_colonists':
                return [[IB_Planets::buy_colonists($p, self::int('qty'))], null];
            case 'launch_worldseed':
                return [[IB_Planets::launch_worldseed($p, self::text('name'))], 'planet'];

            // Combat
            case 'attack_player':
                return [[['warning', IB_Combat::attack_player($p, self::int('target_id'), self::int('fighters'))]], 'sector'];
            case 'attack_fleet':
                return [[['warning', IB_Combat::attack_fleet($p, self::int('fleet_id'), self::int('fighters'))]], 'sector'];
            case 'attack_planet':
                return [[['warning', IB_Combat::attack_planet($p, self::int('planet_id'), self::int('fighters'))]], null];
            case 'deploy':
                return [[IB_Combat::deploy($p, self::int('qty'), sanitize_key(self::field('mode')))], 'sector'];
            case 'recall':
                return [[IB_Combat::recall($p, self::int('fleet_id'), self::int('qty'))], 'sector'];

            // Ship
            case 'rename_ship':
                return [[IB_Player::rename_ship($p, self::text('name'))], null];
            case 'jettison':
                return [[IB_Player::jettison($p, sanitize_key(self::field('commodity')), self::int('qty'))], null];

            // Teams
            case 'team_create':
                return [[IB_Teams::create($p, self::text('team_name'), (string) self::field('password'))], null];
            case 'team_join':
                return [[IB_Teams::join($p, self::int('team_id'), (string) self::field('password'))], null];
            case 'team_leave':
                return [[IB_Teams::leave($p)], null];
            case 'team_kick':
                return [[IB_Teams::kick($p, self::int('member_id'))], null];
            case 'team_captain':
                return [[IB_Teams::make_captain($p, self::int('member_id'))], null];
            case 'team_disband':
                return [[IB_Teams::disband($p)], null];
            case 'team_post':
                return [[IB_Teams::post($p, self::field('message'))], null];

            // Messages
            case 'send_message':
                return [[IB_Messages::send($p, self::int('recipient_id'), self::text('subject'), self::field('message'))], null];
            case 'delete_message':
                return [[IB_Messages::delete($p, self::int('message_id'))], null];
            case 'delete_read':
                return [[IB_Messages::delete_read($p)], null];
        }
        throw new IB_Game_Exception('Unknown command.');
    }
}
