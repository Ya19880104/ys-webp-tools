<?php
/**
 * 解除安裝 — 移除資料表與 schema option
 *
 * @package YangSheep\WebpTools
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

global $wpdb;

// 移除設定資料表
$table = $wpdb->prefix . 'ys_webp_tools_settings';
// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
$wpdb->query( "DROP TABLE IF EXISTS {$table}" );

// 移除 schema 版本 option
delete_option( 'ys_webp_tools_schema_version' );
