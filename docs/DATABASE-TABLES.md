All tables use the site's table prefix (shown here as `wp_`). The schema lives in `sql/install.sql`.

| Table | Contents |
|---|---|
| wp_ib_players | one row per pilot, linked to a WordPress user: location, ship, cargo, credits, turns, experience, alignment |
| wp_ib_sectors | sector id, map x/y, nebula name, Imperial Core flag (`is_core`), beacon |
| wp_ib_warps | directed warp lanes (from_sector, to_sector) |
| wp_ib_ports | port per sector: class 0-9, per-commodity quantity and maximum |
| wp_ib_planets | planets: class, owner, team, colonists, stock, fighters, bastion level, vault |
| wp_ib_teams | teams: name, password hash, captain, combat medals |
| wp_ib_messages | private messages (recipient) and team channel posts (team_id) |
| wp_ib_fleets | deployed fighters: player-owned, or alien factions (owner 0) |
| wp_ib_explored | which sectors each pilot has visited (drives the map and computer) |
| wp_ib_news | Imperial Gazette feed |
| wp_ib_admin_log | admin audit log |

Commodity columns keep short internal names: `ore` = Ferrium Ore, `organics` = Biostock, `equipment` = Machinery.

Game settings are stored in the `ib_settings` option rather than a table.
Other options: `ib_universe` (last Universe Forge summary), `ib_page_ids`, `ib_db_version`, `ib_last_hourly`, `ib_last_daily`.
