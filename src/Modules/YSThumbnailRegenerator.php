<?php
/**
 * 批次重新產生縮圖（既有圖片）
 *
 * 套用「縮圖尺寸管理」的停用設定到既有媒體庫：刪除停用尺寸已產生的舊縮圖檔，
 * 並依目前啟用的尺寸重建。由後台手動觸發、AJAX 分批執行（不依賴 Action Scheduler）。
 *
 * 核心邏輯參考 plugins-reference/image-sizes/app/Controllers/Common/Thumbnails.php
 *
 * @package YangSheep\WebpTools\Modules
 * @since   1.1.0
 */

namespace YangSheep\WebpTools\Modules;

defined( 'ABSPATH' ) || exit;

class YSThumbnailRegenerator {

    /** 每批處理張數（重建較重，批次小以免逾時） */
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
     * 先刪除舊縮圖檔（保留主檔與 SVG），再以 wp_generate_attachment_metadata 重建。
     * 重建時會套用 intermediate_image_sizes_advanced filter（即停用設定），
     * 故停用的尺寸不會再被產生。
     *
     * @param int $id 附件 ID
     * @return array{skipped:bool, deleted:int, created:int}
     */
    public static function regenerate_one( int $id ): array {
        require_once ABSPATH . 'wp-admin/includes/image.php';

        $result = [ 'skipped' => false, 'deleted' => 0, 'created' => 0 ];

        $main = get_attached_file( $id );
        if ( ! $main || ! file_exists( $main ) ) {
            $result['skipped'] = true;
            return $result;
        }

        $old = wp_get_attachment_metadata( $id );
        $dir = trailingslashit( dirname( $main ) );

        // 刪除舊縮圖檔（保留主檔；跳過 SVG）
        if ( ! empty( $old['sizes'] ) && is_array( $old['sizes'] ) ) {
            foreach ( $old['sizes'] as $size ) {
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

        // 重建（依目前啟用的尺寸）
        $new = wp_generate_attachment_metadata( $id, $main );
        if ( is_array( $new ) ) {
            wp_update_attachment_metadata( $id, $new );
            $result['created'] = is_array( $new['sizes'] ?? null ) ? count( $new['sizes'] ) : 0;
        }

        return $result;
    }
}
