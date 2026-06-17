<?php
/**
 * 後台設定頁面模板
 *
 * @package YangSheep\WebpTools
 * @since   1.0.0
 */

use YangSheep\WebpTools\Settings\YSSettingKeys;
use YangSheep\WebpTools\Modules\YSThumbnailManager;

defined( 'ABSPATH' ) || exit;

$s        = YSSettingKeys::all();
$sizes    = YSThumbnailManager::detect_sizes();
$formats  = (array) ( $s[ YSSettingKeys::WEBP_FORMATS ] ?? [] );
$disabled = (array) ( $s[ YSSettingKeys::DISABLED_SIZES ] ?? [] );

$master       = YSSettingKeys::to_bool( $s[ YSSettingKeys::MASTER_ENABLED ] ?? true );
$webp_on      = YSSettingKeys::to_bool( $s[ YSSettingKeys::WEBP_ENABLED ] ?? false );
$keep_orig    = YSSettingKeys::to_bool( $s[ YSSettingKeys::WEBP_KEEP_ORIGINAL ] ?? false );
$resize_on    = YSSettingKeys::to_bool( $s[ YSSettingKeys::RESIZE_ENABLED ] ?? false );
$quality      = (int) ( $s[ YSSettingKeys::WEBP_QUALITY ] ?? 82 );
$max_width    = (int) ( $s[ YSSettingKeys::RESIZE_MAX_WIDTH ] ?? 2560 );
$max_height   = (int) ( $s[ YSSettingKeys::RESIZE_MAX_HEIGHT ] ?? 0 );
?>
<!-- Hero Header（在 .wrap 外面，避免 WP notice 注入） -->
<div class="ys-webp-hero">
    <div class="ys-webp-hero-content">
        <div class="ys-webp-hero-title">
            <span class="dashicons dashicons-images-alt2"></span>
            <?php echo esc_html__( 'YS WebP Tools', 'ys-webp-tools' ); ?>
        </div>
        <div class="ys-webp-hero-subtitle">
            <?php echo esc_html__( '圖片優化 — WebP 轉換 / 自動縮圖 / 縮圖管理', 'ys-webp-tools' ); ?>
        </div>
    </div>
    <span class="ys-webp-version-badge">v<?php echo esc_html( YS_WEBP_TOOLS_VERSION ); ?></span>
</div>

<!-- WP notice 錨點 -->
<div class="wrap"><h2 style="display:none;"></h2></div>

<div class="ys-webp-wrap">

    <!-- 頁籤導覽 -->
    <nav class="ys-webp-tabs">
        <a href="#" class="ys-webp-tab is-active" data-tab="general"><span class="dashicons dashicons-admin-generic"></span> <?php echo esc_html__( '一般設定', 'ys-webp-tools' ); ?></a>
        <a href="#" class="ys-webp-tab" data-tab="webp"><span class="dashicons dashicons-format-image"></span> <?php echo esc_html__( 'WebP 轉換', 'ys-webp-tools' ); ?></a>
        <a href="#" class="ys-webp-tab" data-tab="resize"><span class="dashicons dashicons-editor-expand"></span> <?php echo esc_html__( '自動縮圖', 'ys-webp-tools' ); ?></a>
        <a href="#" class="ys-webp-tab" data-tab="sizes"><span class="dashicons dashicons-screenoptions"></span> <?php echo esc_html__( '縮圖尺寸管理', 'ys-webp-tools' ); ?></a>
        <a href="#" class="ys-webp-tab" data-tab="help"><span class="dashicons dashicons-book"></span> <?php echo esc_html__( '說明文件', 'ys-webp-tools' ); ?></a>
    </nav>

    <!-- 一般設定 -->
    <div class="ys-webp-panel is-active" data-panel="general">
        <div class="ys-webp-card">
            <h2><span class="dashicons dashicons-admin-settings"></span> <?php echo esc_html__( '一般設定', 'ys-webp-tools' ); ?></h2>
            <div class="ys-webp-row">
                <div class="ys-webp-row-main">
                    <span class="ys-webp-row-label"><?php echo esc_html__( '啟用 YS WebP Tools', 'ys-webp-tools' ); ?></span>
                    <p class="ys-webp-row-desc"><?php echo esc_html__( '總開關。關閉後，以下所有功能（WebP 轉換、自動縮圖、縮圖管理）皆停用。', 'ys-webp-tools' ); ?></p>
                </div>
                <div class="ys-webp-row-control">
                    <label class="ys-webp-toggle">
                        <input type="checkbox" data-setting-key="<?php echo esc_attr( YSSettingKeys::MASTER_ENABLED ); ?>" data-setting-type="bool" value="1" <?php checked( $master ); ?>>
                        <span class="ys-webp-toggle-slider"></span>
                    </label>
                </div>
            </div>
            <div class="ys-webp-note">
                <span class="dashicons dashicons-shield-alt"></span>
                <?php echo esc_html__( '安全提醒：WebP 轉換與自動縮圖預設為關閉，需於對應頁籤明確開啟後才會生效。', 'ys-webp-tools' ); ?>
            </div>
        </div>
    </div>

    <!-- WebP 轉換 -->
    <div class="ys-webp-panel" data-panel="webp">
        <div class="ys-webp-card">
            <h2><span class="dashicons dashicons-format-image"></span> <?php echo esc_html__( 'WebP 轉換', 'ys-webp-tools' ); ?></h2>

            <div class="ys-webp-row">
                <div class="ys-webp-row-main">
                    <span class="ys-webp-row-label"><?php echo esc_html__( '上傳時自動轉成 WebP', 'ys-webp-tools' ); ?></span>
                    <p class="ys-webp-row-desc"><?php echo esc_html__( '啟用後，新上傳的圖片會自動轉成 WebP 格式。', 'ys-webp-tools' ); ?></p>
                </div>
                <div class="ys-webp-row-control">
                    <label class="ys-webp-toggle">
                        <input type="checkbox" data-setting-key="<?php echo esc_attr( YSSettingKeys::WEBP_ENABLED ); ?>" data-setting-type="bool" value="1" <?php checked( $webp_on ); ?>>
                        <span class="ys-webp-toggle-slider"></span>
                    </label>
                </div>
            </div>

            <div class="ys-webp-row">
                <div class="ys-webp-row-main">
                    <span class="ys-webp-row-label"><?php echo esc_html__( 'WebP 品質', 'ys-webp-tools' ); ?></span>
                    <p class="ys-webp-row-desc"><?php echo esc_html__( '1–100，數值越高品質越好、檔案越大。建議 80–85。', 'ys-webp-tools' ); ?></p>
                </div>
                <div class="ys-webp-row-control ys-webp-range-wrap">
                    <input type="range" class="ys-webp-range" id="ys-webp-quality-range" min="1" max="100" step="1" value="<?php echo esc_attr( $quality ); ?>" data-setting-key="<?php echo esc_attr( YSSettingKeys::WEBP_QUALITY ); ?>" data-setting-type="int">
                    <span class="ys-webp-range-val" id="ys-webp-quality-val"><?php echo esc_html( $quality ); ?></span>
                </div>
            </div>

            <div class="ys-webp-row">
                <div class="ys-webp-row-main">
                    <span class="ys-webp-row-label"><?php echo esc_html__( '要轉換的來源格式', 'ys-webp-tools' ); ?></span>
                    <p class="ys-webp-row-desc"><?php echo esc_html__( 'GIF 動圖轉 WebP 會失去動畫，預設不勾選。', 'ys-webp-tools' ); ?></p>
                </div>
                <div class="ys-webp-row-control">
                    <div class="ys-webp-checks">
                        <label class="ys-webp-check"><input type="checkbox" data-setting-array="<?php echo esc_attr( YSSettingKeys::WEBP_FORMATS ); ?>" value="jpeg" <?php checked( in_array( 'jpeg', $formats, true ) ); ?>> JPEG</label>
                        <label class="ys-webp-check"><input type="checkbox" data-setting-array="<?php echo esc_attr( YSSettingKeys::WEBP_FORMATS ); ?>" value="png" <?php checked( in_array( 'png', $formats, true ) ); ?>> PNG</label>
                        <label class="ys-webp-check"><input type="checkbox" data-setting-array="<?php echo esc_attr( YSSettingKeys::WEBP_FORMATS ); ?>" value="gif" <?php checked( in_array( 'gif', $formats, true ) ); ?>> GIF</label>
                    </div>
                </div>
            </div>

            <div class="ys-webp-row">
                <div class="ys-webp-row-main">
                    <span class="ys-webp-row-label"><?php echo esc_html__( '保留原始檔', 'ys-webp-tools' ); ?></span>
                    <p class="ys-webp-row-desc"><?php echo esc_html__( '預設關閉＝轉換後刪除原 JPG/PNG（最省空間）。開啟＝原檔與 WebP 並存。', 'ys-webp-tools' ); ?></p>
                </div>
                <div class="ys-webp-row-control">
                    <label class="ys-webp-toggle">
                        <input type="checkbox" data-setting-key="<?php echo esc_attr( YSSettingKeys::WEBP_KEEP_ORIGINAL ); ?>" data-setting-type="bool" value="1" <?php checked( $keep_orig ); ?>>
                        <span class="ys-webp-toggle-slider"></span>
                    </label>
                </div>
            </div>
        </div>
    </div>

    <!-- 自動縮圖 -->
    <div class="ys-webp-panel" data-panel="resize">
        <div class="ys-webp-card">
            <h2><span class="dashicons dashicons-editor-expand"></span> <?php echo esc_html__( '自動縮圖', 'ys-webp-tools' ); ?></h2>

            <div class="ys-webp-row">
                <div class="ys-webp-row-main">
                    <span class="ys-webp-row-label"><?php echo esc_html__( '上傳時自動縮圖', 'ys-webp-tools' ); ?></span>
                    <p class="ys-webp-row-desc"><?php echo esc_html__( '啟用後，超過下方尺寸上限的圖片會在上傳時等比例縮小並覆寫原圖。', 'ys-webp-tools' ); ?></p>
                </div>
                <div class="ys-webp-row-control">
                    <label class="ys-webp-toggle">
                        <input type="checkbox" data-setting-key="<?php echo esc_attr( YSSettingKeys::RESIZE_ENABLED ); ?>" data-setting-type="bool" value="1" <?php checked( $resize_on ); ?>>
                        <span class="ys-webp-toggle-slider"></span>
                    </label>
                </div>
            </div>

            <div class="ys-webp-row">
                <div class="ys-webp-row-main">
                    <span class="ys-webp-row-label"><?php echo esc_html__( '最大寬度（px）', 'ys-webp-tools' ); ?></span>
                    <p class="ys-webp-row-desc"><?php echo esc_html__( '0 = 不限制寬度。', 'ys-webp-tools' ); ?></p>
                </div>
                <div class="ys-webp-row-control">
                    <input type="number" class="ys-webp-input" min="0" step="1" value="<?php echo esc_attr( $max_width ); ?>" data-setting-key="<?php echo esc_attr( YSSettingKeys::RESIZE_MAX_WIDTH ); ?>" data-setting-type="int">
                </div>
            </div>

            <div class="ys-webp-row">
                <div class="ys-webp-row-main">
                    <span class="ys-webp-row-label"><?php echo esc_html__( '最大高度（px）', 'ys-webp-tools' ); ?></span>
                    <p class="ys-webp-row-desc"><?php echo esc_html__( '0 = 不限制高度。', 'ys-webp-tools' ); ?></p>
                </div>
                <div class="ys-webp-row-control">
                    <input type="number" class="ys-webp-input" min="0" step="1" value="<?php echo esc_attr( $max_height ); ?>" data-setting-key="<?php echo esc_attr( YSSettingKeys::RESIZE_MAX_HEIGHT ); ?>" data-setting-type="int">
                </div>
            </div>
        </div>
    </div>

    <!-- 縮圖尺寸管理 -->
    <div class="ys-webp-panel" data-panel="sizes">
        <div class="ys-webp-card">
            <h2><span class="dashicons dashicons-screenoptions"></span> <?php echo esc_html__( '縮圖尺寸管理', 'ys-webp-tools' ); ?></h2>
            <p class="ys-webp-card-desc"><?php echo esc_html__( '以下為本站偵測到的縮圖尺寸。勾選「停用」後，未來上傳的圖片將不再產生該尺寸的縮圖，節省磁碟空間。', 'ys-webp-tools' ); ?></p>

            <table class="ys-webp-size-table">
                <thead>
                    <tr>
                        <th><?php echo esc_html__( '尺寸名稱', 'ys-webp-tools' ); ?></th>
                        <th><?php echo esc_html__( '尺寸（寬 × 高）', 'ys-webp-tools' ); ?></th>
                        <th><?php echo esc_html__( '裁切', 'ys-webp-tools' ); ?></th>
                        <th><?php echo esc_html__( '來源', 'ys-webp-tools' ); ?></th>
                        <th class="ys-webp-col-toggle"><?php echo esc_html__( '停用', 'ys-webp-tools' ); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ( $sizes as $name => $info ) : ?>
                        <tr>
                            <td><code><?php echo esc_html( $info['label'] ); ?></code></td>
                            <td><?php echo esc_html( ( $info['width'] ?: '∞' ) . ' × ' . ( $info['height'] ?: '∞' ) ); ?></td>
                            <td><?php echo $info['crop'] ? '✓' : '—'; ?></td>
                            <td><span class="ys-webp-source"><?php echo esc_html( $info['source'] ); ?></span></td>
                            <td class="ys-webp-col-toggle">
                                <label class="ys-webp-toggle ys-webp-toggle-sm">
                                    <input type="checkbox" data-setting-array="<?php echo esc_attr( YSSettingKeys::DISABLED_SIZES ); ?>" value="<?php echo esc_attr( $name ); ?>" <?php checked( in_array( $name, $disabled, true ) ); ?>>
                                    <span class="ys-webp-toggle-slider"></span>
                                </label>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- 說明文件 -->
    <div class="ys-webp-panel" data-panel="help">
        <div class="ys-webp-card ys-webp-help">
            <h2><span class="dashicons dashicons-book"></span> <?php echo esc_html__( '說明文件', 'ys-webp-tools' ); ?></h2>
            <h3><?php echo esc_html__( '功能說明', 'ys-webp-tools' ); ?></h3>
            <ul>
                <li><strong><?php echo esc_html__( 'WebP 轉換', 'ys-webp-tools' ); ?></strong>：<?php echo esc_html__( '上傳時自動把 JPG/PNG 轉成體積更小的 WebP，預設取代原檔。', 'ys-webp-tools' ); ?></li>
                <li><strong><?php echo esc_html__( '自動縮圖', 'ys-webp-tools' ); ?></strong>：<?php echo esc_html__( '上傳超大圖時自動等比例縮小到設定上限，避免巨檔拖慢網站。', 'ys-webp-tools' ); ?></li>
                <li><strong><?php echo esc_html__( '縮圖尺寸管理', 'ys-webp-tools' ); ?></strong>：<?php echo esc_html__( '停用用不到的縮圖尺寸，減少每次上傳產生的檔案數量。', 'ys-webp-tools' ); ?></li>
            </ul>
            <h3><?php echo esc_html__( '注意事項', 'ys-webp-tools' ); ?></h3>
            <ul>
                <li><?php echo esc_html__( '本外掛只處理「啟用後新上傳」的圖片，不會變動既有媒體庫。', 'ys-webp-tools' ); ?></li>
                <li><?php echo esc_html__( '「取代原檔」會刪除原始 JPG/PNG，無法復原；若需保留請開啟「保留原始檔」。', 'ys-webp-tools' ); ?></li>
                <li><?php echo esc_html__( 'WebP 轉換需要伺服器支援 GD 或 Imagick 的 WebP 功能。', 'ys-webp-tools' ); ?></li>
            </ul>
        </div>
    </div>

    <!-- 儲存按鈕 -->
    <div class="ys-webp-savebar" id="ys-webp-savebar">
        <button type="button" id="ys-webp-save-btn" class="ys-webp-btn ys-webp-btn-primary">
            <span class="dashicons dashicons-saved"></span>
            <span class="ys-webp-btn-label"><?php echo esc_html__( '儲存設定', 'ys-webp-tools' ); ?></span>
        </button>
    </div>

</div>
