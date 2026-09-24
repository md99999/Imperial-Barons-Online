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

Running these alongside WP-Cron is safe: each job takes a database lock before it does anything,
so only one run of a kind happens at a time whatever started it, a run arriving while another is
working stands down, and each job refuses to run twice in the same period. WP-Cron does not need
to be disabled.

**Imperial Barons Online → Maintenance** in wp-admin shows these commands with your site's real
paths, ready to copy into cPanel or a crontab.

For full cron setup instructions (cPanel, Plesk, crontab, Windows Task Scheduler, and the
recommended alternative of triggering `wp-cron.php`), see
**[Scheduled maintenance (cron)](../README.md#scheduled-maintenance-cron)** in the main README.
