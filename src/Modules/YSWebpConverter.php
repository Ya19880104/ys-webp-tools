<?php
/**
 * WebP 轉換模組（需求 1）
 *
 * 上傳圖片時自動轉成 WebP。預設「取代原檔」（刪除原 JPG/PNG），
 * 可透過「保留原始檔」開關保留原檔並存。
 *
 * Hook priority 20 —— 晚於自動縮圖（priority 10），對縮小後的圖轉檔。
 *
 * 核心轉換邏輯參考 plugins-reference/image-sizes/app/Controllers/Common/Convert_Webp.php
 *
 * @package YangSheep\WebpTools\Modules
 * @since   1.0.0
 */

namespace YangSheep\WebpTools\Modules;

use YangSheep\WebpTools\Settings\YSSettingKeys;

defined( 'ABSPATH' ) || exit;

class YSWebpConverter {

    /** 格式短碼 → MIME 對應 */
    private const FORMAT_MIME_MAP = [
        'jpeg' => 'image/jpeg',
        'png'  => 'image/png',
        'gif'  => 'image/gif',
    ];

    public function __construct() {
        add_filter( 'wp_handle_upload', [ $this, 'convert_on_upload' ], 20 );
    }

    /**
     * 上傳時轉成 WebP（取代或保留原檔）
     *
     * @param mixed $upload wp_handle_upload 結果（file/url/type）
     * @return mixed
     */
    public function convert_on_upload( $upload ) {
        if ( ! is_array( $upload ) || isset( $upload['error'] ) ) {
            return $upload;
        }
        if ( ! YSSettingKeys::is_on( YSSettingKeys::MASTER_ENABLED ) ) {
            return $upload;
        }
        if ( ! YSSettingKeys::is_on( YSSettingKeys::WEBP_ENABLED ) ) {
            return $upload;
        }

        $type = $upload['type'] ?? '';
        if ( ! $this->is_convertible_mime( $type ) ) {
            return $upload;
        }

        $source = $upload['file'] ?? '';
        if ( ! $source || ! file_exists( $source ) ) {
            return $upload;
        }

        $webp_path = $this->convert_to_webp( $source );
        if ( ! $webp_path ) {
            return $upload;
        }

        // 取代或保留原檔（安全閥）
        if ( ! YSSettingKeys::is_on( YSSettingKeys::WEBP_KEEP_ORIGINAL ) ) {
            wp_delete_file( $source );
        }

        return [
            'file' => $webp_path,
            'url'  => $this->path_to_url( $webp_path ),
            'type' => 'image/webp',
        ];
    }

    /**
     * 檢查 MIME 是否在使用者勾選的轉換格式白名單內
     *
     * 永不轉換 WebP 自身、SVG 或白名單外格式。
     *
     * @param string $mime 待檢查的 MIME
     */
    private function is_convertible_mime( string $mime ): bool {
        $formats = (array) YSSettingKeys::get( YSSettingKeys::WEBP_FORMATS );
        $allowed = [];
        foreach ( $formats as $f ) {
            $f = strtolower( (string) $f );
            if ( isset( self::FORMAT_MIME_MAP[ $f ] ) ) {
                $allowed[] = self::FORMAT_MIME_MAP[ $f ];
            }
        }
        return in_array( $mime, $allowed, true );
    }

    /**
     * 將來源圖轉為 WebP
     *
     * @param string $source 來源檔路徑
     * @return string|false 轉換後的 webp 路徑，失敗回 false
     */
    private function convert_to_webp( string $source ) {
        $info = pathinfo( $source );
        $ext  = strtolower( $info['extension'] ?? '' );
        if ( 'webp' === $ext || '' === $ext ) {
            return false;
        }

        $dir       = $info['dirname'];
        $name      = $info['filename'];
        $webp_path = $dir . '/' . $name . '.webp';

        // 避免不同來源產生同一個 webp（foo.jpg + foo.png → foo.webp）
        if ( file_exists( $webp_path ) ) {
            $unique    = wp_unique_filename( $dir, $name . '.webp' );
            $webp_path = $dir . '/' . $unique;
        }

        wp_raise_memory_limit( 'image' );

        // 記憶體保護（防大圖 OOM）
        $dims = @getimagesize( $source );
        if ( is_array( $dims ) ) {
            $pixels       = (int) $dims[0] * (int) $dims[1];
            $needed_bytes = $pixels * 4 * 2; // 解碼緩衝 + 工作副本
            $memory_limit = wp_convert_hr_to_bytes( ini_get( 'memory_limit' ) );
            $memory_usage = memory_get_usage( true );
            if ( $memory_limit > 0 && ( $memory_usage + $needed_bytes ) > $memory_limit ) {
                return false;
            }
        }

        $editor = wp_get_image_editor( $source );
        if ( is_wp_error( $editor ) ) {
            return false;
        }

        $quality = (int) YSSettingKeys::get( YSSettingKeys::WEBP_QUALITY );
        $quality = max( 1, min( 100, $quality ) );
        $editor->set_quality( $quality );

        $result = $editor->save( $webp_path, 'image/webp' );
        if ( is_wp_error( $result ) ) {
            return false;
        }

        $saved_path = $result['path'] ?? $webp_path;
        $saved_mime = $result['mime-type'] ?? '';

        // 環境不支援 webp 時，editor 可能存成其他格式 → 視為失敗並清理
        if ( $saved_mime && 'image/webp' !== $saved_mime ) {
            if ( file_exists( $saved_path ) ) {
                wp_delete_file( $saved_path );
            }
            return false;
        }

        return $saved_path;
    }

    /**
     * 檔案路徑轉 URL（優先以 uploads 目錄對應）
     *
     * @param string $path 檔案絕對路徑
     */
    private function path_to_url( string $path ): string {
        $uploads = wp_get_upload_dir();
        if ( ! empty( $uploads['basedir'] ) && ! empty( $uploads['baseurl'] )
            && 0 === strpos( $path, $uploads['basedir'] ) ) {
            return str_replace( $uploads['basedir'], $uploads['baseurl'], $path );
        }
        // 後備：以 ABSPATH 對應 home_url
        return str_replace( ABSPATH, trailingslashit( home_url( '/' ) ), $path );
    }
}
