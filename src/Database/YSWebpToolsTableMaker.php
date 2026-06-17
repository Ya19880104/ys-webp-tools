<?php
/**
 * 資料表建立與升級
 *
 * @package YangSheep\WebpTools\Database
 * @since   1.0.0
 */

namespace YangSheep\WebpTools\Database;

defined( 'ABSPATH' ) || exit;

class YSWebpToolsTableMaker {

    /**
     * 建立（或升級）資料表
     *
     * schema_version 存在 wp_options（唯一例外）
     */
    public static function create_tables(): void {
        global $wpdb;

        $table_name      = self::table_name();
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE {$table_name} (
            id          BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            setting_key VARCHAR(191)        NOT NULL DEFAULT '',
            setting_val LONGTEXT            NOT NULL,
            created_at  DATETIME            NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at  DATETIME            NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY idx_setting_key (setting_key)
        ) {$charset_collate};";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta( $sql );

        update_option( 'ys_webp_tools_schema_version', YS_WEBP_TOOLS_VERSION );
    }

    /**
     * 取得設定資料表名稱
     */
    public static function table_name(): string {
        global $wpdb;
        return $wpdb->prefix . 'ys_webp_tools_settings';
    }
}
