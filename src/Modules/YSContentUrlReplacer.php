<?php
/**
 * 內容圖片網址修復（文章／頁面）
 *
 * 掃描 post_content 中指向本站 uploads 但「實體檔已不存在」的圖片 URL，
 * 反查其附件，替換為目前有效的主檔（full）URL，修復破圖。
 *
 * 安全設計：只替換「實體檔失效」的 URL（不動有效的）；替換目標一定是存在的附件主檔 URL；
 * 提供 dry-run 預覽；僅更新 post_content；AJAX 分批 + 前端輪詢。
 *
 * @package YangSheep\WebpTools\Modules
 * @since   1.2.0
 */

namespace YangSheep\WebpTools\Modules;

defined( 'ABSPATH' ) || exit;

class YSContentUrlReplacer {

    /** 每批掃描／替換的文章數 */
    public const BATCH_SIZE = 20;

    /** 處理的文章類型與狀態 */
    private const POST_TYPES    = "'post','page'";
    private const POST_STATUSES = "'publish','draft','pending','private','future'";

    /**
     * 含 <img 的文章／頁面數
     */
    public static function count_posts(): int {
        global $wpdb;
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        return (int) $wpdb->get_var(
            "SELECT COUNT(ID) FROM {$wpdb->posts}
             WHERE post_type IN (" . self::POST_TYPES . ")
             AND post_status IN (" . self::POST_STATUSES . ")
             AND post_content LIKE '%<img%'"
        );
    }

    /**
     * 取一批文章 ID
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
                 WHERE post_type IN (" . self::POST_TYPES . ")
                 AND post_status IN (" . self::POST_STATUSES . ")
                 AND post_content LIKE '%<img%'
                 ORDER BY ID ASC
                 LIMIT %d OFFSET %d",
                $limit,
                $offset
            )
        );
        return array_map( 'intval', (array) $rows );
    }

    /**
     * 掃描單篇，回傳需替換的 (old → new) 清單（僅含失效 → 有效）
     *
     * @param int $post_id 文章 ID
     * @return array<int, array{old:string, new:string}>
     */
    public static function scan_post( int $post_id ): array {
        $post = get_post( $post_id );
        if ( ! $post || '' === $post->post_content ) {
            return [];
        }

        $uploads = wp_get_upload_dir();
        $baseurl = $uploads['baseurl'] ?? '';
        if ( '' === $baseurl ) {
            return [];
        }

        if ( ! preg_match_all( '#https?://[^\s"\'<>]+?\.(?:jpe?g|png|gif|webp)#i', $post->post_content, $m ) ) {
            return [];
        }

        $plan = [];
        $seen = [];
        foreach ( $m[0] as $url ) {
            if ( isset( $seen[ $url ] ) ) {
                continue;
            }
            $seen[ $url ] = true;
            if ( 0 !== strpos( $url, $baseurl ) ) {
                continue; // 只處理本站 uploads
            }
            $new = self::resolve_valid_url( $url, $uploads );
            if ( $new && $new !== $url ) {
                $plan[] = [ 'old' => $url, 'new' => $new ];
            }
        }
        return $plan;
    }

    /**
     * 失效 URL → 有效主檔 URL；檔案有效或無法解析 → null
     *
     * @param string $url     圖片 URL
     * @param array  $uploads wp_get_upload_dir() 結果
     */
    private static function resolve_valid_url( string $url, array $uploads ): ?string {
        $basedir = $uploads['basedir'] ?? '';
        $baseurl = $uploads['baseurl'] ?? '';
        if ( '' === $basedir || '' === $baseurl ) {
            return null;
        }

        // URL → 實體路徑（去掉可能的查詢字串）
        $clean_url = preg_replace( '#\?.*$#', '', $url );
        $path      = $basedir . substr( $clean_url, strlen( $baseurl ) );
        if ( file_exists( $path ) ) {
            return null; // 檔案存在，不需替換
        }

        $id = self::url_to_attachment_id( $clean_url );
        if ( ! $id ) {
            return null;
        }
        $full = wp_get_attachment_url( $id );
        return $full ?: null;
    }

    /**
     * 由圖片 URL 反查附件 ID（含去尺寸後綴與 basename fallback）
     *
     * @param string $url 圖片 URL
     */
    private static function url_to_attachment_id( string $url ): int {
        // 去尺寸後綴（foo-300x300.jpg → foo.jpg）
        $clean = preg_replace( '#-\d+x\d+(\.\w+)$#', '$1', $url );

        $id = attachment_url_to_postid( $clean );
        if ( $id ) {
            return (int) $id;
        }
        $id = attachment_url_to_postid( $url );
        if ( $id ) {
            return (int) $id;
        }

        // fallback：以檔名（不含尺寸後綴與副檔名）比對 _wp_attached_file（處理副檔名變更，如轉 WebP）
        global $wpdb;
        $base_noext = pathinfo( preg_replace( '#-\d+x\d+(\.\w+)$#', '$1', basename( $clean ) ), PATHINFO_FILENAME );
        if ( '' === $base_noext ) {
            return 0;
        }
        $like = '%/' . $wpdb->esc_like( $base_noext ) . '.%';
        $row  = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = '_wp_attached_file' AND meta_value LIKE %s LIMIT 1",
                $like
            )
        );
        return $row ? (int) $row : 0;
    }

    /**
     * 執行替換並更新 post_content，回傳替換的 URL 數
     *
     * @param int $post_id 文章 ID
     */
    public static function replace_post( int $post_id ): int {
        $plan = self::scan_post( $post_id );
        if ( empty( $plan ) ) {
            return 0;
        }
        $post    = get_post( $post_id );
        $content = $post->post_content;
        $count   = 0;
        foreach ( $plan as $pair ) {
            $replaced = str_replace( $pair['old'], $pair['new'], $content );
            if ( $replaced !== $content ) {
                ++$count;
                $content = $replaced;
            }
        }
        if ( $count > 0 ) {
            wp_update_post(
                [
                    'ID'           => $post_id,
                    'post_content' => $content,
                ]
            );
        }
        return $count;
    }
}
