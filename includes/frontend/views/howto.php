<?php
/**
 * The How to Play page: the guide on its own, readable by anyone.
 * @var object|null $p the pilot, when one is signed in
 */
if (!defined('ABSPATH')) exit;
$ib_help_open = true;
?>
<?php if (empty($p)) : ?>
    <div class="ib-panel">
        <h2>How to play <?php echo esc_html(IB_GAME_NAME); ?></h2>
        <p>Everything below explains the game. You need an account on this site to fly, and your pilot's alias is all
            other players ever see.</p>
        <p>
            <a class="ib-btn" href="<?php echo esc_url(wp_login_url(get_permalink())); ?>">Sign in</a>
            <?php if (get_option('users_can_register')) : ?>
                <a class="ib-btn ib-btn-alt" href="<?php echo esc_url(wp_registration_url()); ?>">Create an account</a>
            <?php endif; ?>
            <a class="ib-btn ib-btn-alt" href="<?php echo esc_url(IB_UI::url('dashboard')); ?>">Home &amp; standings</a>
        </p>
    </div>
<?php endif; ?>

<?php include IB_PATH . 'includes/frontend/views/_how-to-play.php'; ?>
