<?php
if (!defined('ABSPATH')) exit;

class IB_Installer {

    public static function activate() {
        self::install_schema();
        if (get_option(IB_Settings::OPTION) === false) {
            update_option(IB_Settings::OPTION, IB_Settings::defaults());
        }
        IB_Maintenance::schedule();
    }

    public static function deactivate() {
        IB_Maintenance::unschedule();
    }

    public static function maybe_upgrade() {
        $from = (int) get_option('ib_db_version');
        if (get_option('ib_db_version') !== IB_DB_VERSION) {
            self::install_schema();
            self::migrate($from);
        }
    }

    /** One-off data changes that go with a schema version. $from is the version being upgraded from. */
    private static function migrate($from) {
        global $wpdb;
        // v5 brings vending machines for planet garrisons. Worlds that already hold fighters are given
        // the machines they need, and a supply fund for a few days, once, so no garrison deserts on
        // the day of the update and owners have time to find the new panel.
        if ($from > 0 && $from < 5) {
            $per = max(0, (int) IB_Settings::get('vending_per_1000'));
            if ($per) {
                $t = IB_DB::t('planets');
                $wpdb->query($wpdb->prepare("UPDATE $t SET vending_machines = CEIL(fighters / 1000 * %d) WHERE owner_player_id > 0 AND fighters > 0 AND vending_machines = 0", $per));
                $days = max(3, (int) IB_Settings::get('vending_warn_days'));
                foreach ((array) $wpdb->get_results("SELECT * FROM $t WHERE owner_player_id > 0 AND fighters > 0 AND vending_fund = 0") as $pl) {
                    $wpdb->update($t, ['vending_fund' => IB_Planets::vending_cost($pl) * $days], ['id' => $pl->id]);
                }
            }
        }
        // v7 brings smugglers' dens. A universe forged before them gets its share of dens now,
        // once; a new forge marks its own.
        if ($from > 0 && $from < 7 && get_option('ib_universe')) {
            $any = (int) $wpdb->get_var('SELECT COUNT(*) FROM ' . IB_DB::t('ports') . ' WHERE is_smuggler = 1');
            if (!$any) {
                $dens = IB_Ports::mark_smugglers();
                if ($dens) {
                    $u = (array) get_option('ib_universe');
                    $u['smuggler_ports'] = $dens;
                    update_option('ib_universe', $u, false);
                    IB_Log::news('smuggle', sprintf('Whispers on the frontier: %d ports have turned to smuggling. Their goods come cheap, if Imperial customs looks the other way.', $dens));
                }
            }
        }
    }

    /** Runs the schema in sql/install-schema.php through dbDelta, substituting the table prefix. */
    public static function install_schema() {
        global $wpdb;
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        $sql = require IB_PATH . 'sql/install-schema.php';
        $sql = str_replace(
            ['{prefix}', '{charset_collate}'],
            [$wpdb->prefix, $wpdb->get_charset_collate()],
            $sql
        );
        dbDelta($sql);
        update_option('ib_db_version', IB_DB_VERSION);
    }

    public static function drop_schema() {
        global $wpdb;
        foreach (IB_DB::TABLES as $table) {
            $wpdb->query('DROP TABLE IF EXISTS ' . IB_DB::t($table));
        }
    }
}
