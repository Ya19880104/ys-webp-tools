<?php
/**
 * AJAX 處理器 — 儲存設定 + 批次重新產生縮圖 + 內容圖片網址修復
 *
 * 儲存以 JSON 傳輸（精確保留 bool/int/array 型別），後端依 schema 逐項白名單清理。
 * 批次作業以 offset 分頁、前端輪詢驅動（不依賴 Action Scheduler）。內容修復預設 dry-run。
 *
 * @package YangSheep\WebpTools\Admin
 * @since   1.0.0
 */

namespace YangSheep\WebpTools\Admin;

use YangSheep\WebpTools\Database\YSWebpToolsSettingsRepo;
use YangSheep\WebpTools\Modules\YSThumbnailManager;
use YangSheep\WebpTools\Modules\YSThumbnailRegenerator;
use YangSheep\WebpTools\Modules\YSContentUrlReplacer;
use YangSheep\WebpTools\Settings\YSSettingKeys;

defined( 'ABSPATH' ) || exit;

class YSWebpToolsAjaxHandler {

    public function __construct() {
        add_action( 'wp_ajax_ys_webp_tools_save_settings', [ $this, 'save_settings' ] );
        add_action( 'wp_ajax_ys_webp_tools_regen_thumbs', [ $this, 'regen_thumbs' ] );
        add_action( 'wp_ajax_ys_webp_tools_content_urls', [ $this, 'content_urls' ] );
    }

    /**
     * 共用前置驗證（nonce + 權限）；失敗直接結束回應
     */
    private function guard(): void {
        if ( ! check_ajax_referer( 'ys_webp_tools_nonce', 'nonce', false ) ) {
            wp_send_json_error( [ 'message' => __( '安全驗證失敗', 'ys-webp-tools' ) ], 403 );
        }
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => __( '權限不足', 'ys-webp-tools' ) ], 403 );
        }
    }

    /**
     * 儲存設定（AJAX）
     */
    public function save_settings(): void {
        $this->guard();

        $json = isset( $_POST['settings'] ) ? wp_unslash( $_POST['settings'] ) : '';
        $raw  = is_string( $json ) ? json_decode( $json, true ) : null;

        if ( ! is_array( $raw ) ) {
            wp_send_json_error( [ 'message' => __( '沒有收到設定資料', 'ys-webp-tools' ) ] );
        }

        $clean = $this->sanitize_settings( $raw );

        foreach ( $clean as $key => $value ) {
            YSWebpToolsSettingsRepo::set( $key, $value );
        }

        wp_send_json_success( [
            'message'  => __( '設定已儲存', 'ys-webp-tools' ),
            'settings' => $clean,
        ] );
    }

    /**
     * 批次重新產生縮圖（AJAX，offset 分頁，cleanup / rebuild 兩模式）
     */
    public function regen_thumbs(): void {
        $this->guard();

        $offset = isset( $_POST['offset'] ) ? absint( wp_unslash( $_POST['offset'] ) ) : 0;
        $mode   = isset( $_POST['mode'] ) ? sanitize_key( wp_unslash( $_POST['mode'] ) ) : 'rebuild';
        if ( ! in_array( $mode, [ 'cleanup', 'rebuild' ], true ) ) {
            $mode = 'rebuild';
        }

        $total = YSThumbnailRegenerator::count_images();
        if ( 0 === $total ) {
            wp_send_json_success( [
                'total'       => 0,
                'processed'   => 0,
                'deleted'     => 0,
                'created'     => 0,
                'done'        => true,
                'next_offset' => 0,
            ] );
        }

        $ids     = YSThumbnailRegenerator::get_batch_ids( $offset, YSThumbnailRegenerator::BATCH_SIZE );
        $deleted = 0;
        $created = 0;
        foreach ( $ids as $id ) {
            $r        = YSThumbnailRegenerator::regenerate_one( $id, $mode );
            $deleted += $r['deleted'];
            $created += $r['created'];
        }

        $processed = $offset + count( $ids );
        $done      = ( 0 === count( $ids ) ) || ( $processed >= $total );

        wp_send_json_success( [
            'total'       => $total,
            'processed'   => min( $processed, $total ),
            'deleted'     => $deleted,
            'created'     => $created,
            'done'        => $done,
            'next_offset' => $processed,
        ] );
    }

    /**
     * 內容圖片網址修復（AJAX，offset 分頁；預設 dry-run 預覽，dry=0 才實際替換）
     */
    public function content_urls(): void {
        $this->guard();

        $offset = isset( $_POST['offset'] ) ? absint( wp_unslash( $_POST['offset'] ) ) : 0;
        // 預設 dry-run；僅當明確傳 dry=0 才執行替換
        $dry = ! ( isset( $_POST['dry'] ) && '0' === (string) wp_unslash( $_POST['dry'] ) );

        $total = YSContentUrlReplacer::count_posts();
        if ( 0 === $total ) {
            wp_send_json_success( [
                'total'          => 0,
                'processed'      => 0,
                'affected_posts' => 0,
                'url_count'      => 0,
                'samples'        => [],
                'done'           => true,
                'next_offset'    => 0,
                'dry'            => $dry,
            ] );
        }

        $ids       = YSContentUrlReplacer::get_batch_ids( $offset, YSContentUrlReplacer::BATCH_SIZE );
        $affected  = 0;
        $url_count = 0;
        $samples   = [];
        foreach ( $ids as $id ) {
            if ( $dry ) {
                $plan = YSContentUrlReplacer::scan_post( $id );
                if ( ! empty( $plan ) ) {
                    ++$affected;
                    $url_count += count( $plan );
                    foreach ( $plan as $p ) {
                        if ( count( $samples ) >= 5 ) {
                            break;
                        }
                        $samples[] = [
                            'post' => $id,
                            'old'  => $p['old'],
                            'new'  => $p['new'],
                        ];
                    }
                }
            } else {
                $n = YSContentUrlReplacer::replace_post( $id );
                if ( $n > 0 ) {
                    ++$affected;
                    $url_count += $n;
                }
            }
        }

        $processed = $offset + count( $ids );
        $done      = ( 0 === count( $ids ) ) || ( $processed >= $total );

        wp_send_json_success( [
            'total'          => $total,
            'processed'      => min( $processed, $total ),
            'affected_posts' => $affected,
            'url_count'      => $url_count,
            'samples'        => $samples,
            'done'           => $done,
            'next_offset'    => $processed,
            'dry'            => $dry,
        ] );
    }

    /**
     * 依設定 schema 逐項清理（白名單）
     *
     * @param array $raw 解碼後的原始設定
     * @return array<string, mixed>
     */
    private function sanitize_settings( array $raw ): array {
        $out = [];

        // 布林欄位
        $bool_keys = [
            YSSettingKeys::MASTER_ENABLED,
            YSSettingKeys::WEBP_ENABLED,
            YSSettingKeys::WEBP_KEEP_ORIGINAL,
            YSSettingKeys::RESIZE_ENABLED,
        ];
        foreach ( $bool_keys as $k ) {
            if ( array_key_exists( $k, $raw ) ) {
                $out[ $k ] = YSSettingKeys::to_bool( $raw[ $k ] );
            }
        }

        // 整數：WebP 品質（夾限 1–100）
        if ( array_key_exists( YSSettingKeys::WEBP_QUALITY, $raw ) ) {
            $q = absint( $raw[ YSSettingKeys::WEBP_QUALITY ] );
            $out[ YSSettingKeys::WEBP_QUALITY ] = max( 1, min( 100, $q ) );
        }

        // 整數：最大寬/高（>= 0）
        foreach ( [ YSSettingKeys::RESIZE_MAX_WIDTH, YSSettingKeys::RESIZE_MAX_HEIGHT ] as $k ) {
            if ( array_key_exists( $k, $raw ) ) {
                $out[ $k ] = absint( $raw[ $k ] );
            }
        }

        // 陣列：WebP 來源格式（限定 jpeg/png/gif）
        if ( array_key_exists( YSSettingKeys::WEBP_FORMATS, $raw ) ) {
            $allowed = [ 'jpeg', 'png', 'gif' ];
            $vals    = array_map( 'strtolower', array_map( 'sanitize_text_field', (array) $raw[ YSSettingKeys::WEBP_FORMATS ] ) );
            $out[ YSSettingKeys::WEBP_FORMATS ] = array_values( array_intersect( $vals, $allowed ) );
        }

        // 陣列：停用的縮圖尺寸（白名單 = 實際偵測到的尺寸名）
        if ( array_key_exists( YSSettingKeys::DISABLED_SIZES, $raw ) ) {
            $valid = array_keys( YSThumbnailManager::detect_sizes() );
            $vals  = array_map( 'sanitize_text_field', (array) $raw[ YSSettingKeys::DISABLED_SIZES ] );
            $out[ YSSettingKeys::DISABLED_SIZES ] = array_values( array_intersect( $vals, $valid ) );
        }

        return $out;
    }
}
