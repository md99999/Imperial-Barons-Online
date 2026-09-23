<?php
if (!defined('ABSPATH')) exit;

/**
 * Registers the ten game shortcodes. Each renders a shared frame (status bar,
 * navigation, notices) around a view from includes/frontend/views.
 */
class IB_Shortcodes {

    public static function register() {
        foreach (IB_UI::PAGES as $key => $def) {
            add_shortcode($def[2], function () use ($key) {
                return IB_Shortcodes::render($key);
            });
        }
    }

    public static function render($key) {
        // Shortcodes can run in admin/REST contexts (block editor previews); keep those cheap.
        if (is_admin() || (defined('REST_REQUEST') && REST_REQUEST)) {
            return '<p>[Imperial Barons Online: ' . esc_html(IB_UI::PAGES[$key][0]) . ']</p>';
        }
        wp_enqueue_style('imperial-barons-online');
        wp_enqueue_script('imperial-barons-online');

        ob_start();
        echo '<div class="ib-game ib-page-' . esc_attr($key) . '">';
        echo '<div class="ib-title">' . esc_html(IB_Settings::get('game_name')) . '</div>';

        if (!is_user_logged_in()) {
            echo '<div class="ib-panel"><p>Pilots must sign in to fly.</p><p><a class="ib-btn" href="' . esc_url(wp_login_url(get_permalink())) . '">Sign in</a>';
            if (get_option('users_can_register')) echo ' <a class="ib-btn ib-btn-alt" href="' . esc_url(wp_registration_url()) . '">Register</a>';
            echo '</p></div>';
        } elseif (!IB_Game::universe_exists()) {
            echo '<div class="ib-panel"><p>The universe has not been created yet.</p>';
            if (current_user_can('manage_options')) {
                echo '<p><a class="ib-btn" href="' . esc_url(admin_url('admin.php?page=ib_universe')) . '">Forge the universe</a></p>';
            }
            echo '</div>';
        } else {
            $p = IB_Player::current();
            if ($p) {
                echo IB_UI::status_bar($p);
                echo IB_UI::turn_bar($p);
                echo IB_UI::nav($key, $p);
            }
            echo IB_UI::render_flashes();
            if (!$p && $key !== 'dashboard') {
                echo '<div class="ib-panel"><p>You need a pilot before you can fly.</p><p><a class="ib-btn" href="' . esc_url(IB_UI::url('dashboard')) . '">Create a pilot</a></p></div>';
            } else {
                include IB_PATH . 'includes/frontend/views/' . $key . '.php';
            }
        }
        echo '</div>';
        return ob_get_clean();
    }
}
