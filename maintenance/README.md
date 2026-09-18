# Maintenance scripts

Command-line scripts for running the game's scheduled jobs from a real (system) cron job:

| Script | Schedule | Does |
|---|---|---|
| `hourly_maintenance.php` | every hour | ports restock, planets produce, alien fleets regenerate and roam |
| `daily_maintenance.php` | once a day at the site's midnight | turns reset, colonies grow, old news and read mail purged |

Each script finds and loads WordPress by itself, so it can be run from any directory:

```
php /path/to/wordpress/wp-content/plugins/imperial-barons-online/maintenance/hourly_maintenance.php
```

Both jobs skip themselves if they already ran recently, so it is safe to use these scripts
alongside WP-Cron.

For full cron setup instructions (cPanel, Plesk, crontab, Windows Task Scheduler, and the
recommended alternative of triggering `wp-cron.php`), see
**[Scheduled maintenance (cron)](../README.md#scheduled-maintenance-cron)** in the main README.
