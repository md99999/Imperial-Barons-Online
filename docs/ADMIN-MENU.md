Imperial Barons Online (wp-admin menu, requires `manage_options`)
- Dashboard: universe stats, page status, **Create pages & menu**
- Universe Management: current universe summary, Validate, Export (JSON), Universe Forge, Reset
- Settings: turns per day, turn costs, starting ship, prices, port regeneration, Imperial Core size
- Players: edit sector, turns, credits and fighters; delete a pilot
- Teams: view and disband
- Ports: browse by class, restock all
- Planets: browse owned and unowned planets
- Maintenance: run hourly/daily jobs now, see last and next run
- Logs: admin audit log and the Imperial Gazette

Every admin action posts to `admin-post.php` with a nonce and a capability check, and is recorded in the audit log.
