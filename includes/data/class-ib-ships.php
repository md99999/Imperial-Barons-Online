<?php
if (!defined('ABSPATH')) exit;

/**
 * Ship catalogue, sold by the shipwrights of the Imperial Drydock.
 * off/def are combat odds multipliers applied to fighters.
 */
class IB_Ships {
    const DEFAULT_TYPE = 'freetrader';

    public static function all() {
        return [
            'freetrader' => [
                'name' => 'Freetrader', 'base_holds' => 20, 'max_holds' => 75,
                'max_fighters' => 2500, 'max_shields' => 400, 'price' => 41300,
                'off' => 1.0, 'def' => 1.0,
                'desc' => 'The dependable workhorse the Chancery grants every newly chartered trader.',
            ],
            'outrider_skiff' => [
                'name' => 'Outrider Skiff', 'base_holds' => 10, 'max_holds' => 25,
                'max_fighters' => 250, 'max_shields' => 100, 'price' => 15950,
                'off' => 1.1, 'def' => 0.9,
                'desc' => 'A cheap, nimble scout favoured by frontier surveyors. Poor cargo space.',
            ],
            'guild_hauler' => [
                'name' => 'Guild Hauler', 'base_holds' => 30, 'max_holds' => 65,
                'max_fighters' => 300, 'max_shields' => 500, 'price' => 33400,
                'off' => 0.8, 'def' => 1.0,
                'desc' => 'Built to Merchant Guild pattern: roomy holds, light armament.',
            ],
            'bulwark_freighter' => [
                'name' => 'Bulwark Freighter', 'base_holds' => 50, 'max_holds' => 125,
                'max_fighters' => 400, 'max_shields' => 1000, 'price' => 51950,
                'off' => 0.6, 'def' => 1.2,
                'desc' => 'A heavily shielded bulk carrier for serious trade routes.',
            ],
            'pilgrim_ark' => [
                'name' => 'Pilgrim Ark', 'base_holds' => 50, 'max_holds' => 250,
                'max_fighters' => 200, 'max_shields' => 500, 'price' => 63600,
                'off' => 0.5, 'def' => 1.0,
                'desc' => 'Cavernous holds for carrying settlers from the Crown World to new colonies.',
            ],
            'warden_gunship' => [
                'name' => 'Warden Gunship', 'base_holds' => 12, 'max_holds' => 50,
                'max_fighters' => 10000, 'max_shields' => 3000, 'price' => 79000,
                'off' => 1.3, 'def' => 1.3,
                'desc' => 'A compact patrol warship with excellent combat odds.',
            ],
            'lancer_frigate' => [
                'name' => 'Lancer Frigate', 'base_holds' => 12, 'max_holds' => 60,
                'max_fighters' => 5000, 'max_shields' => 400, 'price' => 100000,
                'off' => 1.5, 'def' => 1.1,
                'desc' => 'Strikes hard and fast, but its shields are thin.',
            ],
            'sovereign_dreadnought' => [
                'name' => 'Sovereign Dreadnought', 'base_holds' => 16, 'max_holds' => 80,
                'max_fighters' => 10000, 'max_shields' => 750, 'price' => 110000,
                'off' => 1.4, 'def' => 1.4,
                'desc' => 'The backbone of every baronial war fleet.',
            ],
            'baronial_flagship' => [
                'name' => 'Baronial Flagship', 'base_holds' => 20, 'max_holds' => 85,
                'max_fighters' => 20000, 'max_shields' => 1500, 'price' => 163500,
                'off' => 1.3, 'def' => 1.5, 'captain_only' => true,
                'desc' => 'A floating court. Available only to team captains.',
            ],
        ];
    }

    public static function get($type) {
        $all = self::all();
        return isset($all[$type]) ? $all[$type] : $all[self::DEFAULT_TYPE];
    }
}
