# Universe Forge Administration

Universe Management menu
- Forge Universe
- Validate Universe: checks every sector has warps, no sector exceeds 6 warps, all warps lead
  to real sectors, every sector is reachable from Aurelia and can return to it, and the Aurelian
  Armory, Imperial Drydock and Aurelia Prime all exist
- Export Universe: downloads sectors, warps, ports, planets and fleets as JSON
- Reset Universe: erases everything without forging a new universe

Destructive actions require:
1. Warning checkbox
2. Typing `FORGE` (or `RESET`)
3. `manage_options` capability and a valid nonce
4. An entry in the audit log (Imperial Barons Online → Logs)

## Forging steps
1. Scatter N sectors (100-5,000) on a jittered 1000x1000 grid. The sectors nearest the centre become the Imperial Core (1-10), with Aurelia as sector 1.
2. Link each sector to 1-5 of its nearest neighbours, bidirectionally, capped at 6 warps.
3. Join any disconnected clusters through their closest pair of sectors.
4. Make a percentage of lanes outside the Imperial Core one-way, keeping each change only if every sector can still reach Aurelia and be reached from it.
5. Divide the rest of space into named nebulae (for example the Marches of Veyl or the Ashen Palatinate), each the region around a random seed sector.
6. Place the Aurelian Armory (class 0) and the Crown World Aurelia Prime in sector 1, the Imperial Drydock (class 9) 4-8 warps from Aurelia, and trading ports of classes 1-8 at the chosen density, each with a generated name such as "Kestenholt Freeport".
7. Scatter unclaimed planets and seed alien fleets. The Vraxori home cluster is the sector farthest from Aurelia.

A seed can be supplied to reproduce the same universe.
