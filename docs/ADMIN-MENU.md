Imperial Barons Online (wp-admin menu, requires `manage_options`)
- Dashboard: universe stats, page status, install health (folder name, duplicate copies, a stray `.git`), **Create pages & menu**
- Universe Management: current universe summary, Validate, Export (JSON), Universe Forge, Reset
- Settings: turns per day, turn costs, starting ship, prices, port regeneration, Imperial Core size
- Players: edit sector, turns, credits and fighters; delete a pilot
- Teams: view and disband
- Ports: browse by class, restock all
- Planets: browse owned and unowned planets
- Maintenance: run hourly/daily jobs now, see last and next run
- Logs: admin audit log and the Imperial Gazette

Every admin action posts to `admin-post.php` with a nonce and a capability check, and is recorded in the audit log.

**Disclaimer:** you run this plugin at your own risk. Every effort has been made to write it safely,
but no website can be guaranteed secure, and the author accepts no responsibility or liability for
loss or damage arising from its use. See the Disclaimer section in the main README.
