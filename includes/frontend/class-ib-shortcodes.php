<?php
if (!defined('ABSPATH')) exit;

/**
 * Registers the game shortcodes. Each renders a shared frame (status bar, navigation, notices)
 * around a view from includes/frontend/views. They work in posts and pages alike; the home page
 * and the How to Play guide also render for visitors who are not signed in.
 */
class IB_Shortcodes {
    /** Pages readable without signing in or having a pilot. */
    const PUBLIC_PAGES = ['dashboard', 'howto', 'gazette'];

    /** Attributes the current shortcode was called with; views read them through atts(). */
    private static $atts = [];

    public static function register() {
        foreach (IB_UI::PAGES as $key => $def) {
            add_shortcode($def[2], function ($atts) use ($key) {
                return IB_Shortcodes::render($key, is_array($atts) ? $atts : []);
            });
        }
    }

    /**
     * A shortcode attribute, for views that accept them.
     * The Gazette takes limit="20" and compact="1", which suits a sidebar widget.
     */
    public static function att($name, $default = '') {
        return isset(self::$atts[$name]) ? self::$atts[$name] : $default;
    }

    public static function render($key, $atts = []) {
        self::$atts = $atts;
        // Shortcodes can run in admin/REST contexts (block editor previews); keep those cheap.
        if (is_admin() || (defined('REST_REQUEST') && REST_REQUEST)) {
            return '<p>[Imperial Barons Online: ' . esc_html(IB_UI::PAGES[$key][0]) . ']</p>';
        }
        wp_enqueue_style('imperial-barons-online');
        wp_enqueue_script('imperial-barons-online');

        ob_start();

        // Compact is for a widget: the panel and nothing else. No game title, status bar, turn bar,
        // navigation, notices or footer, whether or not the reader is signed in.
        if (!empty($atts['compact'])) {
            echo '<div class="ib-game ib-page-' . esc_attr($key) . ' ib-compact">';
            $p = is_user_logged_in() ? IB_Player::current() : null;
            if (in_array($key, self::PUBLIC_PAGES, true) && IB_Game::universe_exists()) {
                include IB_PATH . 'includes/frontend/views/' . ($key === 'dashboard' ? '_welcome' : $key) . '.php';
            } else {
                echo '<div class="ib-panel"><p class="ib-small"><a href="' . esc_url(IB_UI::url('dashboard')) . '">'
                    . esc_html(IB_GAME_NAME) . '</a></p></div>';
            }
            echo '</div>';
            return ob_get_clean();
        }

        echo '<div class="ib-game ib-page-' . esc_attr($key) . '">';
        echo '<div class="ib-title">' . esc_html(IB_GAME_NAME) . '</div>';

        if (!is_user_logged_in()) {
            // The home page doubles as the shop window: the guide and the standings are public,
            // so visitors can see what the game is before they sign up.
            if (in_array($key, self::PUBLIC_PAGES, true)) {
                include IB_PATH . 'includes/frontend/views/' . ($key === 'dashboard' ? '_welcome' : $key) . '.php';
                echo IB_UI::footer_bar(null);
            } else {
                echo '<div class="ib-panel"><p>Pilots must sign in to fly.</p><p><a class="ib-btn" href="' . esc_url(wp_login_url(get_permalink())) . '">Sign in</a>';
                if (get_option('users_can_register')) echo ' <a class="ib-btn ib-btn-alt" href="' . esc_url(wp_registration_url()) . '">Create an account</a>';
                echo ' <a class="ib-btn ib-btn-alt" href="' . esc_url(IB_UI::url('dashboard')) . '">How to play</a></p></div>';
            }
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
            if (!$p && !in_array($key, self::PUBLIC_PAGES, true)) {
                echo '<div class="ib-panel"><p>You need a pilot before you can fly.</p><p><a class="ib-btn" href="' . esc_url(IB_UI::url('dashboard')) . '">Create a pilot</a></p></div>';
            } else {
                include IB_PATH . 'includes/frontend/views/' . $key . '.php';
            }
            echo IB_UI::footer_bar($p);
        }
        echo '</div>';
        return ob_get_clean();
    }
}
