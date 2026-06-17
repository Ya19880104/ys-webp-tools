<?php
/**
 * 設定鍵常數與預設值集中管理
 *
 * 所有設定鍵名、預設值、型別判讀都在此定義，
 * 模組與後台一律透過此類別存取，避免行內硬編碼字串。
 *
 * @package YangSheep\WebpTools\Settings
 * @since   1.0.0
 */

namespace YangSheep\WebpTools\Settings;

use YangSheep\WebpTools\Database\YSWebpToolsSettingsRepo;

defined( 'ABSPATH' ) || exit;

final class YSSettingKeys {

    /* ── 設定鍵 ──────────────────────────────── */
    public const MASTER_ENABLED     = 'master_enabled';
    public const WEBP_ENABLED       = 'webp_enabled';
    public const WEBP_QUALITY       = 'webp_quality';
    public const WEBP_FORMATS       = 'webp_formats';
    public const WEBP_KEEP_ORIGINAL = 'webp_keep_original';
    public const RESIZE_ENABLED     = 'resize_enabled';
    public const RESIZE_MAX_WIDTH   = 'resize_max_width';
    public const RESIZE_MAX_HEIGHT  = 'resize_max_height';
    public const DISABLED_SIZES     = 'disabled_sizes';

    /**
     * 預設值（安全預設：破壞性功能一律關閉）
     *
     * @return array<string, mixed>
     */
    public static function defaults(): array {
        return [
            self::MASTER_ENABLED     => true,
            self::WEBP_ENABLED       => false,
            self::WEBP_QUALITY       => 82,
            self::WEBP_FORMATS       => [ 'jpeg', 'png' ],
            self::WEBP_KEEP_ORIGINAL => false,
            self::RESIZE_ENABLED     => false,
            self::RESIZE_MAX_WIDTH   => 2560,
            self::RESIZE_MAX_HEIGHT  => 0,
            self::DISABLED_SIZES     => [],
        ];
    }

    /**
     * 取得單一設定值（找不到回退預設）
     *
     * @param string $key 設定鍵名
     * @return mixed
     */
    public static function get( string $key ): mixed {
        $defaults = self::defaults();
        $default  = $defaults[ $key ] ?? null;
        return YSWebpToolsSettingsRepo::get( $key, $default );
    }

    /**
     * 取得全部設定（用於設定頁渲染）
     *
     * @return array<string, mixed>
     */
    public static function all(): array {
        $out = [];
        foreach ( self::defaults() as $key => $default ) {
            $out[ $key ] = YSWebpToolsSettingsRepo::get( $key, $default );
        }
        return $out;
    }

    /**
     * 布林判讀（相容 1/0、'1'/'0'、true/false、'yes'/'no'、'on'）
     *
     * @param string $key 設定鍵名
     */
    public static function is_on( string $key ): bool {
        return self::to_bool( self::get( $key ) );
    }

    /**
     * 將任意值轉為布林
     *
     * @param mixed $value 待判讀的值
     */
    public static function to_bool( mixed $value ): bool {
        if ( is_bool( $value ) ) {
            return $value;
        }
        if ( is_numeric( $value ) ) {
            return (int) $value === 1;
        }
        return in_array( strtolower( (string) $value ), [ '1', 'yes', 'true', 'on' ], true );
    }
}
