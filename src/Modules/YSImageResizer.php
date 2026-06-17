<?php
/**
 * 自動縮圖模組（需求 2）
 *
 * 上傳圖片若超過設定的最大寬/高，於上傳當下等比例縮小並覆寫原檔。
 * 與參考外掛 image-sizes「拒絕超尺寸上傳」不同，此處為「自動縮小」。
 *
 * Hook priority 10 —— 須早於 WebP 轉換（priority 20），先縮小再轉檔。
 *
 * @package YangSheep\WebpTools\Modules
 * @since   1.0.0
 */

namespace YangSheep\WebpTools\Modules;

use YangSheep\WebpTools\Settings\YSSettingKeys;

defined( 'ABSPATH' ) || exit;

class YSImageResizer {

    /** 支援縮放的 MIME（排除 GIF，避免破壞動畫） */
    private const SUPPORTED_MIMES = [ 'image/jpeg', 'image/png', 'image/webp' ];

    public function __construct() {
        add_filter( 'wp_handle_upload', [ $this, 'maybe_resize_on_upload' ], 10 );
    }

    /**
     * 上傳時若超過尺寸上限，等比例縮小並覆寫原檔
     *
     * @param mixed $upload wp_handle_upload 結果（file/url/type）
     * @return mixed
     */
    public function maybe_resize_on_upload( $upload ) {
        if ( ! is_array( $upload ) || isset( $upload['error'] ) ) {
            return $upload;
        }
        if ( ! YSSettingKeys::is_on( YSSettingKeys::MASTER_ENABLED ) ) {
            return $upload;
        }
        if ( ! YSSettingKeys::is_on( YSSettingKeys::RESIZE_ENABLED ) ) {
            return $upload;
        }

        $type = $upload['type'] ?? '';
        if ( ! in_array( $type, self::SUPPORTED_MIMES, true ) ) {
            return $upload;
        }

        $file = $upload['file'] ?? '';
        if ( ! $file || ! file_exists( $file ) ) {
            return $upload;
        }

        $max_w = (int) YSSettingKeys::get( YSSettingKeys::RESIZE_MAX_WIDTH );
        $max_h = (int) YSSettingKeys::get( YSSettingKeys::RESIZE_MAX_HEIGHT );

        // 兩軸皆未設定上限 → 不處理
        if ( $max_w <= 0 && $max_h <= 0 ) {
            return $upload;
        }

        $dims = @getimagesize( $file );
        if ( ! is_array( $dims ) ) {
            return $upload;
        }
        $width  = (int) $dims[0];
        $height = (int) $dims[1];

        // 未超過上限 → 不處理
        $exceeds_w = ( $max_w > 0 && $width > $max_w );
        $exceeds_h = ( $max_h > 0 && $height > $max_h );
        if ( ! $exceeds_w && ! $exceeds_h ) {
            return $upload;
        }

        // 記憶體保護（防大圖 OOM）
        if ( ! $this->has_enough_memory( $width, $height ) ) {
            return $upload;
        }

        wp_raise_memory_limit( 'image' );

        $editor = wp_get_image_editor( $file );
        if ( is_wp_error( $editor ) ) {
            return $upload;
        }

        // 等比例縮小（false = 不裁切）；某軸為 0 時傳 null 表示該軸不限
        $resized = $editor->resize( $max_w > 0 ? $max_w : null, $max_h > 0 ? $max_h : null, false );
        if ( is_wp_error( $resized ) ) {
            return $upload;
        }

        // 覆寫原檔（格式不變）
        $saved = $editor->save( $file );
        if ( is_wp_error( $saved ) ) {
            return $upload;
        }

        return $upload;
    }

    /**
     * 記憶體保護：估算解碼緩衝是否會超過 memory_limit
     *
     * @param int $width  影像寬
     * @param int $height 影像高
     */
    private function has_enough_memory( int $width, int $height ): bool {
        $pixels = $width * $height;
        if ( $pixels <= 0 ) {
            return true;
        }
        $needed_bytes = $pixels * 4 * 2; // 解碼緩衝 + 工作副本
        $memory_limit = wp_convert_hr_to_bytes( ini_get( 'memory_limit' ) );
        if ( $memory_limit <= 0 ) {
            return true; // 無限制
        }
        $memory_usage = memory_get_usage( true );
        return ( $memory_usage + $needed_bytes ) <= $memory_limit;
    }
}
