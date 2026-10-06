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

## Disclaimer: install and run at your own risk

**You install and run this plugin entirely at your own risk. The author accepts no responsibility
or liability for any loss, damage or compromise that results from using it.**

Every effort has been made to write it safely: player actions are checked on the server, forms are
protected against cross-site request forgery, database queries are prepared, output is escaped, and
administrative functions require WordPress administrator rights. Even so, new vulnerabilities are
discovered in software of every kind every day, and no website can be guaranteed secure. This
software is provided **as is, without warranty of any kind**, as set out in the
[GNU General Public License v2](LICENSE), under which it is released.

Before installing it on a site you care about:

- **Test it first** on a staging or local site rather than a live one.
- **Back up your database and files**, and keep doing so. The Universe Forge and the Reset tool
  both erase game data permanently, and that cannot be undone.
- **Keep WordPress, PHP, your theme and every plugin up to date**, and serve the site over HTTPS.
- **Protect player accounts.** The game relies on WordPress for registration, login and passwords,
  so secure those as you would on any site, for example with an anti-bot plugin on the registration form.
- **Read the code.** It is open source precisely so you can audit it, and change it, before trusting it.

If you find a security problem, please report it privately by email to
**sysop@maddogproductions.online** rather than opening a public issue. See [SECURITY.md](SECURITY.md)
for what to include and what happens next.

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

> Standing up a whole board rather than adding one game to an existing site?
> [Setting up WordPress for a BBS Experience](docs/setup-bbs-on-wordpress.md) covers it end to end:
> securing the site before registration opens, a sysop mailbox, membership model, login hardening,
> the other BBS-door plugins, the menu, and what to test before you open the doors.

1. **Install the plugin.** Copy the `imperial-barons-online` folder into your site's
   `wp-content/plugins/` directory, or zip the folder and upload it under
   **Plugins → Add New → Upload Plugin**. Installing straight from a clone of this repository
   works too, but read [Installing from the repository](#installing-from-the-repository) first:
   a clone carries a `.git` directory that does not belong on a web server.
2. **Activate it.** In **Plugins**, activate **Imperial Barons Online**. Activation creates the
   game's database tables (`wp_ib_*`) and schedules the hourly and daily maintenance jobs.
3. **Forge the universe.** Go to **Imperial Barons Online → Universe Management**. Review the
   options (the defaults of 500 sectors, 40% ports and 12% planets suit 10 turns a day), tick the
   warning box, type `FORGE`, and click **Forge the universe**. Then click **Validate universe**; every
   line should report OK.
   > Forging a universe erases any existing universe **and all player data**. Only do this to start
   > a new game.
4. **Create the game pages.** Go to **Imperial Barons Online → Dashboard** and click
   **Create pages & menu**. This creates the game pages below, each containing its shortcode,
   and a site menu holding a single **Imperial Barons Online** link to the home page. Players move
   between game pages with the in-game navigation bar, so the menu never lists the other pages.
   **Assign menu to theme location** decides where that link appears: *Don't assign* (the default),
   one of your theme's menu locations, or, on block themes such as Twenty Twenty-Five, *Theme header*,
   which creates a matching block navigation menu.
5. **Let players in.** The home page is public: visitors who are not signed in see an introduction,
   the full How to play guide (the goal, objectives and rules) and the current standings, with
   buttons to sign in or create an account. The **How to Play** page is public too, and its
   shortcode `[ib_howto]` can be dropped into any post or page of your own, as can the Gazette's
   `[ib_gazette]`. The other game pages
   stay private.
6. **Accounts.** Only logged-in WordPress users can play, and each account gets one pilot.
   Other players see only the pilot's alias, never the WordPress username. Either create user
   accounts yourself, or enable **Settings → General → Anyone can register** (with the new-user role
   left as *Subscriber*). If registration is open, protect the registration form from bots with an
   anti-bot plugin such as *Simple Cloudflare Turnstile*. Players sign in, open
   the **Imperial Barons** page, and create a pilot.
7. **Set up cron** so the hourly and daily game jobs run on time. See
   [Scheduled maintenance (cron)](#scheduled-maintenance-cron) below.
8. **Optional: tune the game** under **Imperial Barons Online → Settings**: turns per day, turn
   costs, starting credits and ship, prices, port regeneration, discovery chance and more.

### Game pages

Pages are listed in the in-game navigation order. Titles carry an "Imperial Barons - " prefix so the game's
pages stand out in the WordPress Pages list; running *Create pages & menu* again renames pages
created by earlier versions.

| Page | Slug | Shortcode |
|---|---|---|
| Imperial Barons | `imperial-barons-online` | `[ib_dashboard]` |
| Imperial Barons - Sector | `imperial-barons-online-sector` | `[ib_sector]` |
| Imperial Barons - Port | `imperial-barons-online-port` | `[ib_port]` |
| Imperial Barons - Galaxy Map | `imperial-barons-online-map` | `[ib_map]` |
| Imperial Barons - Computer | `imperial-barons-online-computer` | `[ib_computer]` |
| Imperial Barons - Planet | `imperial-barons-online-planet` | `[ib_planet]` |
| Imperial Barons - Ship Status | `imperial-barons-online-ship` | `[ib_ship]` |
| Imperial Barons - Team | `imperial-barons-online-team` | `[ib_team]` |
| Imperial Barons - Messages | `imperial-barons-online-messages` | `[ib_messages]` |
| Imperial Barons - Rankings | `imperial-barons-online-rankings` | `[ib_rankings]` |
| Imperial Barons - Gazette | `imperial-barons-online-gazette` | `[ib_gazette]` |
| Imperial Barons - How to Play | `imperial-barons-online-how-to-play` | `[ib_howto]` |

If you create pages by hand, keep these slugs; the plugin finds pages by slug.

### Widgets: putting the Gazette in a sidebar

Every shortcode above also works in a **widget**, a **post** or any page of your own, which makes
the Gazette a good advertisement for the game: visitors see a galaxy in motion, and it needs no
account to read.

**Block themes** (Twenty Twenty-Five and similar): open **Appearance → Editor → Patterns → template
parts**, or edit the template you want, add a **Shortcode** block to the sidebar or footer area, and
paste:

```
[ib_gazette limit="10" compact="1"]
```

**Classic themes** (Hello Elementor and similar): open **Appearance → Widgets**, add a **Shortcode**
block (or a Custom HTML widget) to the sidebar, and paste the same line. Page builders such as
Elementor have their own Shortcode element that works the same way.

The Gazette takes two attributes:

| Attribute | Default | Does |
|---|---|---|
| `limit` | 40 on a page, 10 when compact | how many dispatches to show, up to 200 |
| `compact` | off | renders a plain list with no table, sized for a narrow column |

In compact mode the output is the news panel alone: the masthead, the dispatches and a link to the
full Gazette page. There is no game title, status bar, navigation or footer, whether or not the
reader is signed in, and it stays inside its column rather than widening as the game pages do.

The other shortcodes work in widgets too, though most only make sense for a signed-in pilot.
`[ib_howto]` is the other useful public one, for a "how to play" post or landing page.

### Scheduled maintenance (cron)

The game relies on two scheduled jobs:

| Job | When | Does |
|---|---|---|
| Hourly | every hour | ports restock, planets produce commodities and fighters, alien fleets regenerate and roam |
| Daily | midnight (site timezone) | turns reset, colonies grow, old news and read mail are purged |

#### Why a real cron job is needed

On activation the plugin schedules both jobs with **WP-Cron**, WordPress's built-in scheduler.
WP-Cron is not a real clock: it only runs when someone visits the site. On a quiet site, ports may
not restock and planets may not produce for hours at a time. For a live game, add a **real cron job**
on your server, from your hosting control panel or the server itself.

**Running WP-Cron and a real cron job together is safe.** Each job takes a database lock before it
does anything, so only one run of a kind happens at a time no matter what started it, and a run that
arrives while another is working stands down rather than repeating it. Each job also refuses to run
twice in the same period. You do not need to set `DISABLE_WP_CRON`, which many shared hosts do not
allow anyway.

Two more safeguards:
- **Turns** reset the first time each pilot visits on a new day, even if cron never runs.
- **You can run either job at any time** from **Imperial Barons Online → Maintenance**, which also
  shows each job's last and next run.

> **Tip:** the Maintenance screen prints all of the commands below with your site's real URL and
> paths filled in, ready to copy into cPanel or a crontab.

#### Recommended: trigger WordPress's scheduler every 5 minutes

This runs *all* of your site's scheduled tasks on time, including this game's jobs, update checks and
scheduled posts, and WordPress works out the site timezone itself. Schedule this every 5 minutes,
replacing `https://example.com` with your site's address:

```bash
curl -s "https://example.com/wp-cron.php?doing_wp_cron" > /dev/null 2>&1
```

Use `wget -q -O - "https://example.com/wp-cron.php?doing_wp_cron" > /dev/null 2>&1` if the host has no
`curl`, or, where WP-CLI is installed, `cd /path/to/wordpress && wp cron event run --due-now`.

#### Alternative: run the game's scripts directly

Use this if your host blocks cron from making web requests, or you want the game's jobs on their own
schedule. Run the first hourly and the second once a day at your site's midnight:

```bash
php /path/to/wordpress/wp-content/plugins/imperial-barons-online/maintenance/hourly_maintenance.php
```
```bash
php /path/to/wordpress/wp-content/plugins/imperial-barons-online/maintenance/daily_maintenance.php
```

These run only the game's jobs, so keep the 5-minute job as well where you can, to keep WordPress's
own tasks running.

> **Server time vs. site time:** cron schedules use the *server's* clock, which is often UTC, while
> the game uses the timezone in **Settings → General**. For the daily script, set the hour that matches
> your site's midnight: a US Eastern site on a UTC server would use 05:00 (04:00 in daylight saving
> time). The 5-minute job needs no such adjustment.

#### Setting it up on common hosts

**cPanel** (most shared hosting):
1. Log in to cPanel and open **Advanced → Cron Jobs**.
2. Under **Add New Cron Job**, choose **Once Per Five Minutes** from *Common Settings*
   (for the game's own scripts choose **Once Per Hour** and **Once Per Day** instead).
3. Paste the command into **Command** and click **Add New Cron Job**.
4. Paths in cPanel usually look like `/home/YOUR-CPANEL-USER/public_html/...`, and command-line PHP
   is usually `/usr/local/bin/php`. The Maintenance screen prints both filled in for your site.

**Plesk:**
1. Open **Websites & Domains → Scheduled Tasks → Add Task**.
2. Choose **Fetch a URL** and enter `https://example.com/wp-cron.php?doing_wp_cron`, or choose
   **Run a PHP script** and select one of the game's maintenance scripts.
3. Set the schedule (every 5 minutes, hourly or daily) and click **OK**.

**Linux server or VPS (crontab):**
1. Run `crontab -e` as the user that owns the WordPress files (often `www-data`:
   `sudo crontab -u www-data -e`).
2. Add the lines you need, then save:
   ```
   # every 5 minutes: runs everything WordPress has scheduled
   */5 * * * * curl -s "https://example.com/wp-cron.php?doing_wp_cron" >/dev/null 2>&1

   # optional: the game's own jobs, hourly at minute 0 and daily at 05:00 server time
   0 * * * * /usr/bin/php /var/www/html/wp-content/plugins/imperial-barons-online/maintenance/hourly_maintenance.php >/dev/null 2>&1
   0 5 * * * /usr/bin/php /var/www/html/wp-content/plugins/imperial-barons-online/maintenance/daily_maintenance.php >/dev/null 2>&1
   ```
3. Find the PHP path with `which php` and adjust the WordPress path to your install.

**Windows server (Task Scheduler):**
1. Open **Task Scheduler → Create Basic Task**.
2. Set the trigger to **Daily**. In the task's properties, under **Triggers → Edit**, tick
   **Repeat task every** and choose 5 minutes (or 1 hour for the game's hourly script).
3. For the action choose **Start a program**: either `curl.exe` with arguments
   `-s "https://example.com/wp-cron.php?doing_wp_cron"`, or `C:\path\to\php.exe` with the full path
   to a maintenance script as the argument.

**Managed WordPress hosts** (WP Engine, Kinsta, SiteGround and others) often already run a real
cron for WordPress, or offer a switch for it. Check your host's documentation before adding your own.

**Local development** (e.g. Local by Flywheel): no setup is needed. WP-Cron runs as you browse,
and you can use the **Run now** buttons on the Maintenance screen.

#### Checking that it works

Open **Imperial Barons Online → Maintenance**. After an hour or so, **Last run** for the hourly
job should keep advancing. Running a maintenance script by hand in a terminal prints what it did,
or *"skipped: it already ran…"* if the job already ran this period, or *"already running elsewhere"*
if another run currently holds the lock.

The same screen keeps a **Cron Maintenance Log**: one line per job per day for the last ten days,
showing the time the job ran, what started it (WP-Cron, *Run now by* whoever pressed the button, or
Server cron), what it did, and how many times it was started that day. Starts that found the work
already done are counted but never replace the run that did it, so a busy 5-minute cron shows a high
count beside the one run that mattered. The log is kept in the `ib_cron_log` option and is removed
when the plugin is deleted.

### Installing from the repository

The released zip and the repository hold the same plugin, so a clone or a GitHub **Download ZIP**
runs the game perfectly well. Two things are worth knowing before you put one on a live site.

**The folder gets a different name.** GitHub's zip unpacks as `Imperial-Barons-Online-main`, and
WordPress installs the plugin under that name. Nothing in the game depends on the folder name, so it
works — but WordPress treats `Imperial-Barons-Online-main/imperial-barons-online.php` and
`imperial-barons-online/imperial-barons-online.php` as two different plugins, and installing the
other one later gives you two copies, two sets of scheduled jobs and one shared set of tables.
Rename the folder to `imperial-barons-online` before you activate it, and stay with that name.

**A clone carries files a web server should not serve.** Chiefly `.git`, which holds the project's
entire history: on a public site anyone who knows the path can walk it. (GitHub's **Download ZIP**
is an export rather than a clone, so it has no `.git` in it; this applies to a working copy you
cloned or copied from your own machine.) The plugin ships an
`.htaccess` that refuses `.git`, `*.sql`, `*.md`, logs and editor leftovers, and every directory has
an `index.php` so nothing can be listed — but `.htaccess` is read by Apache only. On nginx, put this
in the server block:

```nginx
location ~ /wp-content/plugins/.*/\.(git|svn)(/|$) { deny all; }
location ~ /wp-content/plugins/.*\.(sql|md|log|ya?ml|lock)$ { deny all; }
```

The surest fix is not to deploy `.git` at all: build a zip from the repository as below, or run
`git archive` straight onto the server. The plugin also checks itself: **Imperial Barons Online →
Dashboard** has an *Install health* panel, and an administrator sees a notice on the Plugins screen,
if the folder is misnamed, if a `.git` directory is present (it tests whether your server actually
serves it), if a second copy of the plugin is installed, or if any file or directory is present that
this version does not ship. That last check works from a manifest of what a release contains rather
than a list of known rubbish, so it catches whatever is actually there: the rest of the `.git`
family, `.github`, `node_modules`, a `.bak` someone made in place, a file left behind by an older
version that the new one no longer has, or a script that has no business being there at all. The
build script compares the manifest against what it packs and refuses to build if the two have
drifted, so the list cannot quietly go stale. Nothing else in the tree is sensitive — the PHP files all
refuse to run unless WordPress loaded them, the scripts in `maintenance/` refuse to run over the web
at all, and the table schema lives in a PHP file for the same reason, so it cannot be fetched either.

### Building a release zip

There is no build step: no compiler, no bundler, no dependencies to install. The plugin is the
source, and there are three ways to package it.

#### Building it with git

To produce the same zip that is published for a release, from a clone of the repository:

```bash
git archive --format=zip --prefix=imperial-barons-online/ -o imperial-barons-online.zip HEAD
```

That gives a zip whose single top-level folder is `imperial-barons-online`, which is what
**Plugins → Add New → Upload Plugin** expects. It takes the files from the last commit, not the
working tree, so uncommitted edits are left out, and `.gitattributes` keeps development-only files
(`.gitignore`, `.gitattributes`, `.github`) out of the archive. On Windows, the same command works in
Git Bash or PowerShell wherever `git` is on the path.

#### Building it with PHP, without git

If git is not installed, or you are on Windows without a `zip` command, `tools/build-zip.php` does
the same job with nothing but PHP:

```bash
php tools/build-zip.php
```

That writes `imperial-barons-online-<version>.zip` next to the plugin folder, taking the version
from the plugin header, and prints the path, the file count and the size. Pass a path to put it
somewhere else:

```bash
php tools/build-zip.php /path/to/imperial-barons-online.zip
```

It needs PHP's `zip` extension, which is standard on hosting but is sometimes switched off in a
command-line PHP on Windows; the script says so plainly and stops if it is missing. It packs the
**working tree**, uncommitted edits included, which is the difference from `git archive` — useful
while testing a change, and worth remembering when cutting a release. It skips `.git`, `.github`,
`.gitignore`, `.gitattributes`, `tools/`, `node_modules`, `vendor`, editor leftovers and any zips
or logs lying about, and it produces the same file list as the `git archive` command above.

The script refuses to run over the web, as do the maintenance scripts, and `tools/` is left out of
both builds, so it never reaches an installed site.

#### Zipping the folder by hand

```bash
cd .. && zip -r imperial-barons-online.zip imperial-barons-online -x '*/.git/*'
```

Whichever you use, the zip should contain one top-level folder named `imperial-barons-online` with
`imperial-barons-online.php` directly inside it, and no `.git` directory.

### Updating and uninstalling

- **Updating:** replace the plugin folder with the new version. Database changes are applied
  automatically on the next page load.
- **Deactivating** stops the scheduled jobs but keeps all game data.
- **Deleting** the plugin from the Plugins screen removes all game tables and settings.

---

## How to play

- **Turns.** Only three actions cost turns: warping to another sector (1), docking at a port (1, charged
  once, after which trading and haggling are free until you leave) and attacking (1). Landing, cargo
  transfers, bastions, drones, the Computer, the Map and messages are all free. Turns reset at midnight
  (site time) and do not carry over; every game page shows a bar with the turns you have left.
- **Trading.** A port's class is three letters showing whether it **B**uys or **S**ells the three
  staples, Ferrium Ore, Biostock and Machinery, in that order. Buy where a port sells, and sell where
  another port buys. Well-stocked ports sell cheaply; ports with strong demand pay the most.
- **Specialist goods.** Some ports also deal in one of three specialist goods: Rare Isotopes,
  Medicine or Luxuries. They are worth several times a staple per hold and swing further in price,
  but stocks are small and such ports are scarce near Aurelia and more common out on the frontier.
  Planets do not produce them. Because the three letters are the port's class and cover only the
  staples, a specialist appears as a chip after them — `BBS ◆I`, `◆M` or `◆L` for Isotopes,
  Medicine or Luxuries — coloured green where the port buys and cyan where it sells, exactly like the
  B and S letters, and hovering it says which way round it is. The chip shows everywhere a port badge
  does: the sensor sweep, the Port page's suggestions, the port finder and the known-port report.
- **Haggling.** Offer a better price than the port lists. It may accept (bonus experience),
  counter-offer, or refuse to haggle with you for an hour if you push too hard.
- **Aurelia and the Imperial Drydock.** The Aurelian Armory in sector 1 sells holds, fighters,
  shields, survey drones and nebula charts, and recruits colonists while you are docked (you can also
  land on Aurelia Prime, the Crown World in the same sector, and recruit them there). The
  Imperial Drydock also sells nine ship types, from the Freetrader to the Baronial Flagship, and
  Worldseeds for creating planets.
- **Exploring.** Sensors sweep every neighbouring sector as you arrive, revealing ports, planets and
  hostile fighters before you jump, and you can warp straight there by tapping a sector in the sweep.
  First visits beyond the Imperial Core earn experience and may turn up salvage, a credit cache,
  abandoned fighters or an old survey beacon, or cost you: a meteoroid swarm takes cargo, a revenue
  cutter collects the Crown's tithe, and a false distress call can be a Reaver Pirates ambush.
  Everything that befalls a pilot out there is reported in the Gazette.
- **Running goods without leaving the Port page.** Under the trading table, **Where to take this
  cargo** lists the charted ports that will buy what is in your holds — best payout first, spread
  across the commodities you carry, with the price, how many units that port can take and the turns
  the trip costs. **Fly & dock** on any row plots the course, flies it and docks when you arrive, so
  a trade run is one click rather than a trip to the Computer and back. The sector number itself
  does the same thing wherever a port is listed — in that panel, in the Port finder, in the known-port
  table and on the plotted course — and in the Sector page's sensor sweep a neighbour's class code
  (BBS, SBB and so on) warps and docks in one go. A number you cannot reach today is shown plain,
  with a tooltip saying how many turns it would need. With empty holds the panel
  turns into *What to pick up next*. Below it sits a compact **ship's computer**: fly straight to a
  sector number, or search for a port that buys or sells one commodity.
- **The Computer.** The full version of the same tools, and the place to plan rather than react: plot
  the shortest course to any sector and see the route before you commit, engage the autopilot or fly
  and dock in one go, use the Port finder, review every known port's prices, and check where your
  fighters are deployed. Pair two neighbouring ports with opposite classes (for example SBB and BSS)
  for a profitable run in both directions.
- **Planets.** Claim unowned worlds or grow new ones with Worldseeds. Colonists produce commodities and
  fighters every hour, and a colony grows 5% a day by itself up to the planet's capacity. Output is
  quoted per 1,000 colonists a day and scaled by the **Planet output** setting, 300% by default, with
  a further 10% for every bastion level — so a fortified world repays the building, not just the
  defending. A Verdant's base 30 ore / 50 biostock / 20 machinery / 10 fighters becomes 90/150/60 + 30
  at the default, and 144/240/96 + 48 behind a level 6 Sovereign Spire. A colony of a thousand
  settlers makes 300 units of cargo a day, four loads for a 75-hold Freetrader, and planet stock has
  no ceiling, so what you cannot carry today keeps until you come back.
- **Visiting your worlds.** The Planet page lists every planet you own with the warps and turns to
  reach it and a **Fly & land** button, which flies the course and sets you down in one click; the
  sector number does the same. Landing is free, so the turns are purely the warps. Under it, a
  **Nearest ports** table gives the closest charted ports with their class, distance and the same
  one-click **Fly & dock**, for when a colony has just filled your holds.
- **Route risk.** Both tables carry a **Risk** reading of the course ahead, from Quiet to Severe. It
  is built from how much of the route runs outside the Crown's Peace, how much of it you have never
  charted, and what is deployed along the way — but it never says what is waiting, whose it is or
  how much of it there is. Sweep a sector or send a survey drone if you want to know before you fly. The Planet page shows the rate
  for the world you are standing on, what it yields at its current size, and the base rate behind it. Settlers travel in berths rather than one to a
  hold — 50 to a hold by default, so a 75-hold Freetrader carries 3,750 and a Pilgrim Ark 12,500 —
  which is what makes founding a colony a trip or two rather than a fortnight of shuttling. Set
  **Colonists per cargo hold** in Settings to tune it. Bastions (levels 1–6) add a vault, stronger
  defenses and a Lance Battery.
- **Danger.** The Imperial Core (sectors 1–10) is protected by the Crown's Peace. Beyond it roam the
  Vraxori Syndicate, Gorvath Clans, Reaver Pirates, Myrrak Swarm, Thaloruun Dominion and Zephryl
  Continuum, as well as rival Barons. If your ship is destroyed, you escape to Aurelia in a new
  Freetrader and are grounded until tomorrow.
- **The Gazette.** A public news page, readable without an account, carrying the galaxy's
  highlights: pilots joining, flying their last turn of the day and passing every 50th sector
  visited; worlds claimed, seeded, fortified and stormed; trades of 10,000 credits or more; ships
  bought and lost; duels between pilots; alien fleets broken and sectors garrisoned; promotions
  through the peerage; houses founded and joined; and what befalls pilots on the frontier. News is
  kept for the number of days set in Settings. Its masthead, "The Imperial Barons Gazette", is the
  `IB_GAZETTE_NAME` constant in the plugin's main file.

  The feed can go anywhere with its shortcode, which takes two attributes. In compact mode the
  output is the Gazette panel alone: no game title, status bar, navigation or footer, whether or
  not the reader is signed in.

  ```
  [ib_gazette]                         the full page: 40 dispatches in a table
  [ib_gazette limit="10" compact="1"]  a plain list for a sidebar widget; 200 dispatches at most
  ```

The full guide is under **How to play** on the Imperial Barons page.

---

## Administration

**Imperial Barons Online** menu in wp-admin (administrators only):

Dashboard · Universe Management · Settings · Players · Teams · Ports · Planets · Maintenance · Logs

Destructive actions (forging or resetting the universe) require a warning checkbox, a typed
confirmation and administrator rights, and every admin action is recorded in the audit log.
See the `docs/` folder for details.

---

## License

Copyright (C) 2026 Bill Mantz, <https://maddogproductions.online/>

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
.htaccess                     Apache: refuses .git, the schema, docs and logs over the web
index.php                     one per directory, so nothing can be listed or opened directly
SECURITY.md                   how to report a vulnerability, and what is in scope
sql/install-schema.php        database schema (applied with dbDelta and the site's table prefix)
includes/class-ib-core.php    settings, table names, logging, ranks
includes/class-ib-health.php  warns if the install came from a clone or a branch-named zip
includes/data/                ship catalogue
includes/services/            game rules: players, ports, planets, combat, discovery, teams,
                              messages, Universe Forge, pathfinder, factions, maintenance
includes/frontend/            shortcodes, form action dispatcher, UI helpers, page views
admin/                        wp-admin screens
assets/                       stylesheet and JavaScript (confirmations, galaxy map pan and zoom)
maintenance/                  optional CLI scripts for a system cron
tools/build-zip.php           builds an installable zip with PHP alone (not shipped in releases)
docs/                         admin, database, page and Universe Forge reference, and
                              setup-bbs-on-wordpress.md, running a site as a BBS
```
