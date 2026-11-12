<?php
/**
 * The game logo, as an SVG image so it looks the same on every device rather than depending on
 * each device's monospace font. The image is generated from the ANSI art in tools/logo-art.html
 * by tools/logo-to-svg.py (the same approach as Imperial Hackers).
 * Set $ib_logo_small before including it for the smaller version.
 */
if (!defined('ABSPATH')) exit;
?>
<div class="ib-logo-wrap<?php echo !empty($ib_logo_small) ? ' ib-logo-small' : ''; ?>">
<div class="ib-logo"><img src="<?php echo esc_url(IB_URL . 'assets/img/ib-logo.svg?ver=' . IB_VERSION); ?>" width="600" height="600" alt="<?php echo esc_attr(IB_GAME_NAME); ?>" decoding="async"></div>
</div>
