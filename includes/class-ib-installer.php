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
        if (get_option('ib_db_version') !== IB_DB_VERSION) {
            self::install_schema();
        }
    }

    /** Runs sql/install.sql through dbDelta, substituting the table prefix. */
    public static function install_schema() {
        global $wpdb;
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        $sql = file_get_contents(IB_PATH . 'sql/install.sql');
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
