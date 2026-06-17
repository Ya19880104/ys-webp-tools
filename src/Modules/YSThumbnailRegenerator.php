<?php
/**
 * 批次重新產生縮圖（既有圖片）
 *
 * 兩種模式：
 * - cleanup：只刪除「停用尺寸」的舊縮圖檔並從 metadata 移除，保留啟用尺寸的既有檔不動（最安全、最快）。
 * - rebuild：刪除全部縮圖後依目前啟用尺寸重建（會補產生缺失的尺寸）。
 *
 * 由後台手動觸發、AJAX 分批執行（不依賴 Action Scheduler）。
 *
 * @package YangSheep\WebpTools\Modules
 * @since   1.1.0
 */

namespace YangSheep\WebpTools\Modules;

use YangSheep\WebpTools\Settings\YSSettingKeys;

defined( 'ABSPATH' ) || exit;

class YSThumbnailRegenerator {

    /** 每批處理張數 */
    public const BATCH_SIZE = 10;

    /**
     * 站台圖片附件總數
     */
    public static function count_images(): int {
        global $wpdb;
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        return (int) $wpdb->get_var(
            "SELECT COUNT(ID) FROM {$wpdb->posts}
             WHERE post_type = 'attachment'
             AND post_mime_type LIKE 'image/%'
             AND post_status != 'trash'"
        );
    }

    /**
     * 取一批圖片附件 ID（依 ID 升序，offset 分頁）
     *
     * @param int $offset 起始位移
     * @param int $limit  數量
     * @return int[]
     */
    public static function get_batch_ids( int $offset, int $limit ): array {
        global $wpdb;
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $rows = $wpdb->get_col(
            $wpdb->prepare(
                "SELECT ID FROM {$wpdb->posts}
                 WHERE post_type = 'attachment'
                 AND post_mime_type LIKE 'image/%%'
                 AND post_status != 'trash'
                 ORDER BY ID ASC
                 LIMIT %d OFFSET %d",
                $limit,
                $offset
            )
        );
        return array_map( 'intval', (array) $rows );
    }

    /**
     * 重新產生單一附件的縮圖
     *
     * @param int    $id   附件 ID
     * @param string $mode 'cleanup' | 'rebuild'
     * @return array{skipped:bool, deleted:int, created:int}
     */
    public static function regenerate_one( int $id, string $mode = 'rebuild' ): array {
        require_once ABSPATH . 'wp-admin/includes/image.php';

        $result = [ 'skipped' => false, 'deleted' => 0, 'created' => 0 ];

        $main = get_attached_file( $id );
        if ( ! $main || ! file_exists( $main ) ) {
            $result['skipped'] = true;
            return $result;
        }

        $meta = wp_get_attachment_metadata( $id );
        $dir  = trailingslashit( dirname( $main ) );

        if ( 'cleanup' === $mode ) {
            // 只刪停用尺寸：保留啟用尺寸的既有檔不動 → 啟用尺寸 URL 絕不改變
            $disabled = (array) YSSettingKeys::get( YSSettingKeys::DISABLED_SIZES );
            if ( empty( $disabled ) || empty( $meta['sizes'] ) || ! is_array( $meta['sizes'] ) ) {
                return $result;
            }
            $changed = false;
            foreach ( $meta['sizes'] as $name => $size ) {
                if ( ! in_array( $name, $disabled, true ) ) {
                    continue;
                }
                if ( isset( $size['mime-type'] ) && 'image/svg+xml' === $size['mime-type'] ) {
                    continue;
                }
                if ( ! empty( $size['file'] ) ) {
                    $path = $dir . $size['file'];
                    if ( file_exists( $path ) && $path !== $main ) {
                        wp_delete_file( $path );
                        ++$result['deleted'];
                    }
                }
                unset( $meta['sizes'][ $name ] );
                $changed = true;
            }
            if ( $changed ) {
                wp_update_attachment_metadata( $id, $meta );
            }
            return $result;
        }

        // rebuild：刪除全部舊縮圖（保留主檔、跳過 SVG）後重建
        if ( ! empty( $meta['sizes'] ) && is_array( $meta['sizes'] ) ) {
            foreach ( $meta['sizes'] as $size ) {
                if ( empty( $size['file'] ) ) {
                    continue;
                }
                if ( isset( $size['mime-type'] ) && 'image/svg+xml' === $size['mime-type'] ) {
                    continue;
                }
                $path = $dir . $size['file'];
                if ( file_exists( $path ) && $path !== $main ) {
                    wp_delete_file( $path );
                    ++$result['deleted'];
                }
            }
        }

        $new = wp_generate_attachment_metadata( $id, $main );
        if ( is_array( $new ) ) {
            wp_update_attachment_metadata( $id, $new );
            $result['created'] = is_array( $new['sizes'] ?? null ) ? count( $new['sizes'] ) : 0;
        }

        return $result;
    }
}
