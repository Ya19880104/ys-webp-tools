<?php
/**
 * 縮圖尺寸管理模組（需求 3）
 *
 * 自動偵測站台所有已註冊的縮圖尺寸，並關閉使用者勾選停用的尺寸，
 * 避免每次上傳產生大量用不到的縮圖檔案。
 *
 * 核心邏輯參考 plugins-reference/image-sizes/app/Controllers/Common/Thumbnails.php
 *
 * @package YangSheep\WebpTools\Modules
 * @since   1.0.0
 */

namespace YangSheep\WebpTools\Modules;

use YangSheep\WebpTools\Settings\YSSettingKeys;

defined( 'ABSPATH' ) || exit;

class YSThumbnailManager {

    /** WP 內建尺寸名稱 */
    private const BUILTIN_SIZES = [ 'thumbnail', 'medium', 'medium_large', 'large' ];

    /** WP 大圖門檻特殊識別（停用即關閉 -scaled 機制） */
    public const SCALED_KEY = 'scaled';

    /** WP 大圖門檻預設值（px） */
    private const SCALED_DEFAULT = 2560;

    public function __construct() {
        add_filter( 'intermediate_image_sizes_advanced', [ $this, 'filter_sizes' ] );
        add_filter( 'big_image_size_threshold', [ $this, 'filter_big_image_threshold' ] );
    }

    /**
     * 移除被停用的縮圖尺寸
     *
     * @param mixed $sizes 即將產生的尺寸陣列
     * @return mixed
     */
    public function filter_sizes( $sizes ) {
        if ( ! is_array( $sizes ) ) {
            return $sizes;
        }
        if ( ! YSSettingKeys::is_on( YSSettingKeys::MASTER_ENABLED ) ) {
            return $sizes;
        }

        $disabled = (array) YSSettingKeys::get( YSSettingKeys::DISABLED_SIZES );
        if ( empty( $disabled ) ) {
            return $sizes;
        }

        foreach ( $disabled as $name ) {
            unset( $sizes[ $name ] );
        }

        return $sizes;
    }

    /**
     * 若停用 scaled，則關閉 WP 大圖 -scaled 機制
     *
     * @param mixed $threshold 原門檻值
     * @return mixed
     */
    public function filter_big_image_threshold( $threshold ) {
        if ( ! YSSettingKeys::is_on( YSSettingKeys::MASTER_ENABLED ) ) {
            return $threshold;
        }

        $disabled = (array) YSSettingKeys::get( YSSettingKeys::DISABLED_SIZES );
        return in_array( self::SCALED_KEY, $disabled, true ) ? false : $threshold;
    }

    /**
     * 自動偵測所有已註冊的縮圖尺寸（供設定頁列出）
     *
     * @return array<string, array{label:string, width:int, height:int, crop:bool, source:string}>
     */
    public static function detect_sizes(): array {
        global $_wp_additional_image_sizes;

        $result = [];

        $names = get_intermediate_image_sizes();
        if ( ! is_array( $names ) ) {
            $names = [];
        }

        foreach ( $names as $name ) {
            if ( in_array( $name, self::BUILTIN_SIZES, true ) ) {
                $width  = (int) get_option( "{$name}_size_w" );
                $height = (int) get_option( "{$name}_size_h" );
                $crop   = (bool) get_option( "{$name}_crop" );
                $source = __( 'WordPress 內建', 'ys-webp-tools' );
            } elseif ( isset( $_wp_additional_image_sizes[ $name ] ) ) {
                $width  = (int) ( $_wp_additional_image_sizes[ $name ]['width'] ?? 0 );
                $height = (int) ( $_wp_additional_image_sizes[ $name ]['height'] ?? 0 );
                $crop   = (bool) ( $_wp_additional_image_sizes[ $name ]['crop'] ?? false );
                $source = __( '主題／外掛', 'ys-webp-tools' );
            } else {
                $width  = 0;
                $height = 0;
                $crop   = false;
                $source = __( '主題／外掛', 'ys-webp-tools' );
            }

            $result[ $name ] = [
                'label'  => $name,
                'width'  => $width,
                'height' => $height,
                'crop'   => $crop,
                'source' => $source,
            ];
        }

        // 特殊：WP 大圖 -scaled 門檻機制
        $result[ self::SCALED_KEY ] = [
            'label'  => self::SCALED_KEY,
            'width'  => self::SCALED_DEFAULT,
            'height' => self::SCALED_DEFAULT,
            'crop'   => false,
            'source' => __( 'WordPress 大圖門檻', 'ys-webp-tools' ),
        ];

        return $result;
    }
}
