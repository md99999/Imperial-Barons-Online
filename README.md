# Imperial Barons Online

**A turn-based space trading, exploration and conquest game for WordPress.**

The Imperium has opened its frontier. From Aurelia, seat of the Imperial Throne, chartered
traders set out to buy low and sell high, chart unknown space, seed new worlds and fight their
way up the ranks of nobility, from humble Vagrant to Imperial Paragon.

Imperial Barons Online is played entirely in the web browser through ordinary WordPress pages.
There is nothing to download and no terminal emulator to configure: players sign in to your
WordPress site and fly.

---

## Inspiration and attribution

Imperial Barons Online draws its inspiration from **Trade Wars 2002**, the classic BBS "door"
game that, from the early 1990s, had players dialing in to bulletin board systems to trade
between ports, explore a sector-based galaxy, build planetary empires and battle rival traders
a few turns at a time each day.

Imperial Barons Online pays tribute to that style of play, but it is a **completely new game**:
- Original setting, lore, names, ships, factions, ranks and rules.
- A new, web-based way of playing built for WordPress, with pages, forms, an interactive galaxy
  map and a ship's computer in place of ANSI screens and keyboard menus.
- Its own gameplay systems, such as sensor sweeps, first-visit discoveries, survey drones and
  nebula charts, bastions and haggling.

It contains no code, text or artwork from Trade Wars 2002 and is not affiliated with or endorsed
by its creators or rights holders. *Trade Wars* is a trademark of its respective owner.

---

## The goal

You begin as a **Vagrant** with a single Freetrader and a few thousand credits. Your aim is to rise
through the ranks of the Imperial nobility to **Imperial Paragon** and become the most powerful
Baron in the galaxy.

### Objectives

- **Build a fortune:** trade between ports to grow your credits. **Net worth**, which counts your
  credits, cargo, ship, fighters and planets, is the main measure of success.
- **Earn your title:** gain experience by trading, haggling, exploring, colonizing and fighting,
  and climb the peerage: Vagrant, Freeholder, Yeoman, Squire, Knight, Baronet, Baron, Viscount, Earl,
  Marquess, Duke, Archduke, Prince, Lord Regent, and finally Imperial Paragon.
- **Explore the frontier:** chart the galaxy to find the best trade routes, unclaimed planets and
  hidden discoveries.
- **Build an empire:** claim or seed planets, settle them with colonists, and fortify them with
  bastions so they produce wealth and fighters for you.
- **Command the spacelanes:** upgrade your ship, deploy fighters to hold territory, and defeat
  pirates, alien fleets and rival Barons.
- **Rise with allies:** join or found a team to share planets and defenses and climb the team
  rankings together.

The **Rankings** page shows who leads the galaxy in net worth, experience and combat, and which
team is strongest. Each pilot receives only **10 turns a day** (configurable), so the best Barons
plan carefully and make every turn count.

---

## Requirements

- WordPress 5.8 or later
- PHP 7.4 or later
- MySQL 5.7+ / 8.x or MariaDB 10.3+ (the standard WordPress database)

Works with both classic and block themes (tested with Twenty Twenty-Five).

---

## Installation

1. **Install the plugin.** Copy the `imperial-barons-online` folder into your site's
   `wp-content/plugins/` directory, or zip the folder and upload it under
   **Plugins → Add New → Upload Plugin**.
2. **Activate it.** In **Plugins**, activate **Imperial Barons Online**. Activation creates the
   game's database tables (`wp_ib_*`) and schedules the hourly and daily maintenance jobs.
3. **Forge the universe.** Go to **Imperial Barons Online → Universe Management**. Review the
   options (the defaults of 500 sectors, 40% ports and 12% planets suit 10 turns a day), tick the
   warning box, type `FORGE`, and click **Forge the universe**. Then click **Validate universe**; every
   line should report OK.
   > Forging a universe erases any existing universe **and all player data**. Only do this to start
   > a new game.
4. **Create the game pages.** Go to **Imperial Barons Online → Dashboard** and click
   **Create pages & menu**. This creates the ten game pages below, each containing its shortcode,
   and a site menu with a single **Imperial Barons Online Home** link. On block themes, leave
   "Show this menu in the theme header" ticked so the header shows it. Players move between game
   pages using the in-game navigation bar on every game page.
5. **Let players in.** Only logged-in WordPress users can play, and each account gets one pilot.
   Other players see only the pilot's alias, never the WordPress username. Either create user
   accounts yourself, or enable **Settings → General → Anyone can register** (with the new-user role
   left as *Subscriber*). If registration is open, protect the registration form from bots with an
   anti-bot plugin such as *Simple Cloudflare Turnstile*. Players sign in, open
   **Imperial Barons Online Home**, and create a pilot.
6. **Set up cron** so the hourly and daily game jobs run on time. See
   [Scheduled maintenance (cron)](#scheduled-maintenance-cron) below.
7. **Optional: tune the game** under **Imperial Barons Online → Settings**: turns per day, turn
   costs, starting credits and ship, prices, port regeneration, discovery chance and more.

### Game pages

Pages are listed in the in-game navigation order.

| Page | Slug | Shortcode |
|---|---|---|
| Imperial Barons Online Home | `imperial-barons-online` | `[ib_dashboard]` |
| Sector | `imperial-barons-online-sector` | `[ib_sector]` |
| Port | `imperial-barons-online-port` | `[ib_port]` |
| Galaxy Map | `imperial-barons-online-map` | `[ib_map]` |
| Computer | `imperial-barons-online-computer` | `[ib_computer]` |
| Planet | `imperial-barons-online-planet` | `[ib_planet]` |
| Ship Status | `imperial-barons-online-ship` | `[ib_ship]` |
| Team | `imperial-barons-online-team` | `[ib_team]` |
| Messages | `imperial-barons-online-messages` | `[ib_messages]` |
| Rankings | `imperial-barons-online-rankings` | `[ib_rankings]` |

If you create pages by hand, keep these slugs; the plugin finds pages by slug.

### Scheduled maintenance (cron)

The game relies on two scheduled jobs:

| Job | When | Does |
|---|---|---|
| Hourly | every hour | ports restock, planets produce commodities and fighters, alien fleets regenerate and roam |
| Daily | midnight (site timezone) | turns reset, colonies grow, old news and read mail are purged |

#### Why a real cron job is needed

On activation the plugin schedules both jobs with **WP-Cron**, WordPress's built-in scheduler.
WP-Cron is not a real clock: it only runs when someone visits the site. On a quiet site, ports may
not restock and planets may not produce for hours at a time. For a live game, set up a **real cron
job** on your server. This is done in your hosting control panel or on the server itself, outside
WordPress.

Some things are safe regardless of your setup:
- **Turns** reset the first time each pilot visits on a new day, even if cron never runs.
- **Both jobs guard against double runs.** The hourly job skips itself if it ran in the last
  50 minutes, and the daily job runs at most once per day, so overlapping schedules won't double
  production.
- **You can run either job at any time** from **Imperial Barons Online → Maintenance**, which
  also shows each job's last and next run.

#### Choose one method

**Method A (recommended): trigger WordPress's scheduler every 5 minutes.** This runs *all* of your
site's scheduled tasks on time, including this game's jobs, WordPress updates checks and scheduled
posts. WordPress works out the site timezone itself, so the daily job runs at your local midnight.

1. Stop visitors from triggering WP-Cron. Add this line to `wp-config.php`, above the line that
   says *"That's all, stop editing!"*:
   ```php
   define('DISABLE_WP_CRON', true);
   ```
2. Add a cron job that runs every 5 minutes, using **one** of these commands (replace
   `https://example.com` with your site's address):
   ```bash
   wget -q -O - "https://example.com/wp-cron.php?doing_wp_cron" >/dev/null 2>&1
   ```
   ```bash
   curl -s "https://example.com/wp-cron.php?doing_wp_cron" >/dev/null 2>&1
   ```
   If WP-CLI is installed on your server, this command does the same without a web request:
   ```bash
   cd /path/to/wordpress && wp cron event run --due-now >/dev/null 2>&1
   ```

**Method B: run the game's own scripts directly.** Use this if your host blocks web requests
from cron, or you want the game jobs on their own schedule. The plugin includes two command-line
scripts that load WordPress and run one job each:

```bash
php /path/to/wordpress/wp-content/plugins/imperial-barons-online/maintenance/hourly_maintenance.php
```
```bash
php /path/to/wordpress/wp-content/plugins/imperial-barons-online/maintenance/daily_maintenance.php
```

Schedule the hourly script at minute 0 of every hour, and the daily script once a day at your
site's midnight. If you also use `DISABLE_WP_CRON`, still add a Method A job so WordPress's own
tasks keep running. The double-run guard makes it safe for both to be active.

> **Server time vs. site time:** cron schedules use the *server's* clock, which is often UTC,
> while the game uses the timezone in **Settings → General**. For Method B, set the daily job's
> hour to your site's midnight in server time. For example, a US Eastern site on a UTC server
> would run it at 05:00 (04:00 during daylight saving time). Method A handles this automatically.

#### Setting it up on common hosts

**cPanel** (most shared hosting):
1. Log in to cPanel and open **Advanced → Cron Jobs**.
2. Under **Add New Cron Job**, choose **Once Per Five Minutes** from *Common Settings*
   (for Method B's hourly script choose **Once Per Hour**, and for the daily script
   **Once Per Day** and then adjust the hour).
3. Paste the command into **Command** and click **Add New Cron Job**.
4. Paths in cPanel usually look like `/home/YOUR-CPANEL-USER/public_html/...`. For Method B,
   the PHP binary is usually `/usr/local/bin/php`. Your host's documentation or support can confirm both.

**Plesk:**
1. Open **Websites & Domains → Scheduled Tasks → Add Task**.
2. For Method A choose **Fetch a URL** and enter `https://example.com/wp-cron.php?doing_wp_cron`;
   for Method B choose **Run a PHP script** and select the script file.
3. Set the schedule (every 5 minutes, hourly or daily) and click **OK**.

**Linux server or VPS (crontab):**
1. Run `crontab -e` as the user that owns the WordPress files (often `www-data`:
   `sudo crontab -u www-data -e`).
2. Add the lines for your chosen method, then save:
   ```
   # Method A: every 5 minutes
   */5 * * * * wget -q -O - "https://example.com/wp-cron.php?doing_wp_cron" >/dev/null 2>&1

   # Method B: hourly at minute 0, daily at 05:00 server time
   0 * * * * /usr/bin/php /var/www/html/wp-content/plugins/imperial-barons-online/maintenance/hourly_maintenance.php >/dev/null 2>&1
   0 5 * * * /usr/bin/php /var/www/html/wp-content/plugins/imperial-barons-online/maintenance/daily_maintenance.php >/dev/null 2>&1
   ```
3. Find the PHP path with `which php` and adjust the WordPress path to your install.

**Windows server (Task Scheduler):**
1. Open **Task Scheduler → Create Basic Task**.
2. Set the trigger to **Daily**. In the task's properties, under **Triggers → Edit**, tick
   **Repeat task every** and choose 5 minutes (Method A) or 1 hour (Method B hourly).
3. For the action choose **Start a program**. For Method A, program `curl.exe` with
   arguments `-s "https://example.com/wp-cron.php?doing_wp_cron"`. For Method B, program
   `C:\path\to\php.exe` with the full path to the script as the argument.

**Managed WordPress hosts** (WP Engine, Kinsta, SiteGround and others) often already run a real
cron for WordPress, or offer a switch for it. Check your host's documentation before adding your own.

**Local development** (e.g. Local by Flywheel): no setup is needed. WP-Cron runs as you browse,
and you can use the **Run now** buttons on the Maintenance screen.

#### Checking that it works

Open **Imperial Barons Online → Maintenance**. After an hour or so, **Last run** for the hourly
job should keep advancing. When you run a Method B script by hand in a terminal, it prints what it
did, or *"skipped: it already ran…"* if the job ran recently.

### Updating and uninstalling

- **Updating:** replace the plugin folder with the new version. Database changes are applied
  automatically on the next page load.
- **Deactivating** stops the scheduled jobs but keeps all game data.
- **Deleting** the plugin from the Plugins screen removes all game tables and settings.

---

## How to play

- **Turns.** Each warp costs 1 turn and docking at a port costs 1 turn; trading while docked is free.
- **Trading.** A port's class is three letters showing whether it **B**uys or **S**ells Ferrium Ore,
  Biostock and Machinery, in that order. Buy where a port sells, and sell where another port buys.
  Well-stocked ports sell cheaply; ports with strong demand pay the most.
- **Haggling.** Offer a better price than the port lists. It may accept (bonus experience),
  counter-offer, or refuse to haggle with you for an hour if you push too hard.
- **Aurelia and the Imperial Drydock.** The Aurelian Armory in sector 1 sells holds, fighters,
  shields, survey drones and nebula charts. Aurelia Prime, the Crown World, supplies colonists. The
  Imperial Drydock also sells nine ship types, from the Freetrader to the Baronial Flagship, and
  Worldseeds for creating planets.
- **Exploring.** Sensors sweep every neighbouring sector as you arrive, revealing ports, planets and
  hostile fighters before you jump. First visits beyond the Imperial Core earn experience and may turn
  up salvage, a credit cache, abandoned fighters, an old survey beacon, or a Reaver Pirates ambush.
- **The Computer.** Plot the shortest course to any sector, engage the autopilot, use the Port finder
  to locate the nearest ports that buy or sell a commodity, and review every known port's prices. Pair
  two neighbouring ports with opposite classes (for example SBB and BSS) for a profitable run in
  both directions.
- **Planets.** Claim unowned worlds or grow new ones with Worldseeds. Colonists produce commodities and
  fighters every hour. Bastions (levels 1–6) add a vault, stronger defenses and a Lance Battery.
- **Danger.** The Imperial Core (sectors 1–10) is protected by the Crown's Peace. Beyond it roam the
  Vraxori Syndicate, Gorvath Clans, Reaver Pirates, Myrrak Swarm, Thaloruun Dominion and Zephryl
  Continuum, as well as rival Barons. If your ship is destroyed, you escape to Aurelia in a new
  Freetrader and are grounded until tomorrow.

The full guide is under **How to play** on the Imperial Barons Online Home page.

---

## Administration

**Imperial Barons Online** menu in wp-admin (administrators only):

Dashboard · Universe Management · Settings · Players · Teams · Ports · Planets · Maintenance · Logs

Destructive actions (forging or resetting the universe) require a warning checkbox, a typed
confirmation and administrator rights, and every admin action is recorded in the audit log.
See the `docs/` folder for details.

---

## License

Imperial Barons Online is released under the **GNU General Public License, version 2 or later**
(GPLv2+), the same license as WordPress itself. The full text is in [LICENSE](LICENSE).

You are free to use, modify and redistribute it, including commercially, provided derivative
works are distributed under the same license and keep the copyright notice. The software comes
with no warranty.

---

## Code layout

```
imperial-barons-online.php    plugin bootstrap and hooks
uninstall.php                 removes tables and options when the plugin is deleted
sql/install.sql               database schema (applied with dbDelta and the site's table prefix)
includes/class-ib-core.php    settings, table names, logging, ranks
includes/data/                ship catalogue
includes/services/            game rules: players, ports, planets, combat, discovery, teams,
                              messages, Universe Forge, pathfinder, factions, maintenance
includes/frontend/            shortcodes, form action dispatcher, UI helpers, page views
admin/                        wp-admin screens
assets/                       stylesheet and JavaScript (confirmations, galaxy map pan and zoom)
maintenance/                  optional CLI scripts for a system cron
docs/                         admin, database, page and Universe Forge reference
```
