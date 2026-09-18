# Maintenance

The plugin schedules both jobs with WP-Cron on activation, and they can be run
on demand from **Imperial Barons Online → Maintenance** in wp-admin.

| Script | Schedule | Does |
|---|---|---|
| `hourly_maintenance.php` | every hour | ports restock, planets produce, alien fleets regenerate and roam |
| `daily_maintenance.php` | midnight | turns reset, colonies grow, old news and read mail purged |

Turns also reset lazily the first time a pilot loads a page on a new day, so play
works correctly even if cron never fires.

WP-Cron only runs when the site gets traffic. For a quiet site, disable it
(`define('DISABLE_WP_CRON', true);` in wp-config.php) and use a system cron:

```
0 * * * * php /path/to/wp-content/plugins/imperial-barons-online/maintenance/hourly_maintenance.php
0 0 * * * php /path/to/wp-content/plugins/imperial-barons-online/maintenance/daily_maintenance.php
```
