<?php
if (!defined('ABSPATH')) exit;

/**
 * Alien factions. Their fighters live in ib_fleets with owner_player_id = 0.
 *
 * behavior: 'static' fleets hold their sector, 'roam' fleets drift each hour.
 * mode:     'offensive' fleets attack ships entering their sector,
 *           'defensive' fleets only fight when attacked.
 */
class IB_Factions {
    const VRAXORI_SYNDICATE  = 'vraxori_syndicate';
    const THALORUUN_DOMINION = 'thaloruun_dominion';
    const GORVATH_CLANS      = 'gorvath_clans';
    const ZEPHRYL_CONTINUUM  = 'zephryl_continuum';
    const MYRRAK_SWARM       = 'myrrak_swarm';
    const REAVER_PIRATES     = 'reaver_pirates';

    const VRAXORI_MAX_FIGHTERS = 2000;

    public static function all() {
        return [
            self::VRAXORI_SYNDICATE => [
                'name' => 'Vraxori Syndicate', 'behavior' => 'static', 'mode' => 'offensive',
                'odds' => 1.2, 'bounty' => 12, 'alignment' => 1,
                'desc' => 'A militant syndicate entrenched in a remote cluster. Their fleets regenerate.',
            ],
            self::GORVATH_CLANS => [
                'name' => 'Gorvath Clans', 'behavior' => 'static', 'mode' => 'defensive',
                'odds' => 1.1, 'bounty' => 8, 'alignment' => 0,
                'desc' => 'Territorial warrior clans. They leave you alone unless provoked.',
            ],
            self::REAVER_PIRATES => [
                'name' => 'Reaver Pirates', 'behavior' => 'roam', 'mode' => 'offensive',
                'odds' => 1.0, 'bounty' => 10, 'alignment' => 2,
                'desc' => 'Pirates who prey on traders. Hunting them is honourable work.',
            ],
            self::MYRRAK_SWARM => [
                'name' => 'Myrrak Swarm', 'behavior' => 'roam', 'mode' => 'offensive',
                'odds' => 0.8, 'bounty' => 5, 'alignment' => 1,
                'desc' => 'Small insectoid swarms that attack anything that moves.',
            ],
            self::THALORUUN_DOMINION => [
                'name' => 'Thaloruun Dominion', 'behavior' => 'roam', 'mode' => 'defensive',
                'odds' => 1.1, 'bounty' => 0, 'alignment' => -3,
                'desc' => 'Peaceful patrols. Attacking them is a stain on your record.',
            ],
            self::ZEPHRYL_CONTINUUM => [
                'name' => 'Zephryl Continuum', 'behavior' => 'roam', 'mode' => 'defensive',
                'odds' => 1.0, 'bounty' => 0, 'alignment' => -3,
                'desc' => 'Enigmatic explorers. Attacking them is a stain on your record.',
            ],
        ];
    }

    public static function get($key) {
        $all = self::all();
        return isset($all[$key]) ? $all[$key] : null;
    }

    public static function name($key) {
        $f = self::get($key);
        return $f ? $f['name'] : 'Unknown';
    }

    /**
     * Seeds faction fleets into a freshly generated universe.
     *
     * @param int[] $sector_ids   non-Imperial Core sectors
     * @param int   $home_sector  Vraxori home sector (far from Aurelia)
     * @param array $adjacency    sector => int[] outgoing warps
     */
    public static function seed(array $sector_ids, $home_sector, array $adjacency, $strength_pct = 100) {
        global $wpdb;
        $scale = max(0.1, count($sector_ids) / 1000) * max(0, $strength_pct) / 100;
        $now = current_time('mysql');
        $rows = [];

        // Vraxori home cluster: the home sector and its neighbours.
        $cluster = array_merge([$home_sector], isset($adjacency[$home_sector]) ? $adjacency[$home_sector] : []);
        foreach (array_unique($cluster) as $sid) {
            $rows[] = [self::VRAXORI_SYNDICATE, $sid, mt_rand(1000, self::VRAXORI_MAX_FIGHTERS), 'offensive'];
        }

        $plan = [
            self::GORVATH_CLANS      => [8, 200, 600],
            self::REAVER_PIRATES     => [5, 100, 400],
            self::MYRRAK_SWARM       => [12, 30, 120],
            self::THALORUUN_DOMINION => [4, 150, 500],
            self::ZEPHRYL_CONTINUUM  => [4, 150, 500],
        ];
        foreach ($plan as $faction => $spec) {
            $count = max(1, (int) round($spec[0] * $scale));
            $def = self::get($faction);
            for ($i = 0; $i < $count; $i++) {
                $sid = $sector_ids[array_rand($sector_ids)];
                $rows[] = [$faction, $sid, mt_rand($spec[1], $spec[2]), $def['mode']];
            }
        }

        foreach ($rows as $r) {
            $wpdb->insert(IB_DB::t('fleets'), [
                'faction' => $r[0], 'owner_player_id' => 0, 'team_id' => 0,
                'sector_id' => $r[1], 'fighter_count' => $r[2], 'fleet_mode' => $r[3], 'created_at' => $now,
            ]);
        }
        return count($rows);
    }

    /** Hourly AI: regenerate Vraxori fleets and move roaming fleets to a neighbouring non-Core sector. */
    public static function tick() {
        global $wpdb;
        $fleets = IB_DB::t('fleets');
        $regen = (int) IB_Settings::get('vraxori_regeneration_rate');
        $wpdb->query($wpdb->prepare(
            "UPDATE $fleets SET fighter_count = LEAST(%d, fighter_count + %d) WHERE faction = %s",
            self::VRAXORI_MAX_FIGHTERS, $regen, self::VRAXORI_SYNDICATE
        ));

        $roamers = [];
        foreach (self::all() as $key => $def) {
            if ($def['behavior'] === 'roam') $roamers[] = $key;
        }
        $in = "'" . implode("','", array_map('esc_sql', $roamers)) . "'";
        $moving = $wpdb->get_results("SELECT id, sector_id FROM $fleets WHERE owner_player_id = 0 AND faction IN ($in)");
        $moved = 0;
        foreach ($moving as $fleet) {
            if (mt_rand(1, 100) > 60) continue; // not every fleet moves every hour
            $options = $wpdb->get_col($wpdb->prepare(
                'SELECT w.to_sector FROM ' . IB_DB::t('warps') . ' w JOIN ' . IB_DB::t('sectors') . ' s ON s.id = w.to_sector
                 WHERE w.from_sector = %d AND s.is_core = 0', $fleet->sector_id
            ));
            if (!$options) continue;
            $wpdb->update($fleets, ['sector_id' => (int) $options[array_rand($options)]], ['id' => $fleet->id]);
            $moved++;
        }
        return $moved;
    }
}
