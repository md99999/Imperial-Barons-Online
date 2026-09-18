<?php
/**
 * Optional: run from a system cron instead of relying on WP-Cron.
 *   0 0 * * * php /path/to/wp-content/plugins/imperial-barons-online/maintenance/daily_maintenance.php
 */
if (PHP_SAPI !== 'cli') exit("CLI only.\n");
require_once __DIR__ . '/bootstrap.php';
echo IB_Maintenance::daily() . "\n";
