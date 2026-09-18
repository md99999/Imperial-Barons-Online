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
5. **Let players in.** Enable **Settings → General → Anyone can register**, or create user
   accounts yourself. Players sign in, open **Imperial Barons Online Home**, and create a pilot.
6. **Optional: tune the game** under **Imperial Barons Online → Settings**: turns per day, turn
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

### Maintenance and cron

The plugin uses WP-Cron for two jobs, which you can also run on demand from
**Imperial Barons Online → Maintenance**:

| Job | When | Does |
|---|---|---|
| Hourly | every hour | ports restock, planets produce, alien fleets regenerate and roam |
| Daily | midnight (site timezone) | turns reset, colonies grow, old news and read mail purged |

Turns also reset automatically the first time a pilot visits on a new day, so play works even if
cron runs late. WP-Cron only fires when the site gets visits; for exact timing on a quiet site, see
`maintenance/README.md` to use a real system cron instead.

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
