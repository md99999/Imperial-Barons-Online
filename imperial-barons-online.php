<?php
/*
Plugin Name: Imperial Barons Online
Plugin URI: https://maddogproductions.online/
Author: Bill Mantz
Author URI: https://maddogproductions.online/
Description: Imperial Barons Online: a turn-based space trading and conquest game. Trade, colonize and fight your way up the ranks of the Imperium, played through WordPress pages using shortcodes.
Version: 1.1.0
Requires PHP: 7.4
Requires at least: 5.8
Text Domain: imperial-barons-online
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Copyright (C) 2026 Bill Mantz

Imperial Barons Online is free software: you can redistribute it and/or modify it under the
terms of the GNU General Public License as published by the Free Software Foundation, either
version 2 of the License, or (at your option) any later version.

This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY;
without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
See the GNU General Public License for more details. A copy is included in LICENSE.
*/
if (!defined('ABSPATH')) exit;

define('IB_VERSION', '1.1.0');
define('IB_DB_VERSION', '3');
define('IB_FILE', __FILE__);
define('IB_PATH', plugin_dir_path(__FILE__));
define('IB_URL', plugin_dir_url(__FILE__));

require_once IB_PATH . 'includes/class-ib-core.php';
require_once IB_PATH . 'includes/class-ib-installer.php';
require_once IB_PATH . 'includes/data/class-ib-ships.php';
require_once IB_PATH . 'includes/services/class-factions.php';
require_once IB_PATH . 'includes/services/class-pathfinder.php';
require_once IB_PATH . 'includes/services/class-player-service.php';
require_once IB_PATH . 'includes/services/class-port-service.php';
require_once IB_PATH . 'includes/services/class-planet-service.php';
require_once IB_PATH . 'includes/services/class-combat-service.php';
require_once IB_PATH . 'includes/services/class-team-service.php';
require_once IB_PATH . 'includes/services/class-message-service.php';
require_once IB_PATH . 'includes/services/class-universe-forge.php';
require_once IB_PATH . 'includes/services/class-discovery-service.php';
require_once IB_PATH . 'includes/services/class-maintenance-service.php';
require_once IB_PATH . 'includes/importers/class-legacy-importer.php';
require_once IB_PATH . 'includes/frontend/class-ib-ui.php';
require_once IB_PATH . 'includes/frontend/class-ib-actions.php';
require_once IB_PATH . 'includes/frontend/class-ib-shortcodes.php';

register_activation_hook(__FILE__, ['IB_Installer', 'activate']);
register_deactivation_hook(__FILE__, ['IB_Installer', 'deactivate']);

add_action('plugins_loaded', ['IB_Installer', 'maybe_upgrade']);
add_action('init', ['IB_Shortcodes', 'register']);
add_action('template_redirect', ['IB_Actions', 'handle']);
add_action('wp_enqueue_scripts', ['IB_UI', 'enqueue_assets']);
add_action(IB_Maintenance::HOURLY_HOOK, ['IB_Maintenance', 'hourly']);
add_action(IB_Maintenance::DAILY_HOOK, ['IB_Maintenance', 'daily']);

if (is_admin()) {
    require_once IB_PATH . 'admin/class-ib-admin.php';
    IB_Admin::init();
}
