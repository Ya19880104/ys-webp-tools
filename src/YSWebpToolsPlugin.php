<?php
/**
 * 主外掛類別（Singleton）
 *
 * @package YangSheep\WebpTools
 * @since   1.0.0
 */

namespace YangSheep\WebpTools;

use YangSheep\WebpTools\Admin\YSWebpToolsAdmin;
use YangSheep\WebpTools\Admin\YSWebpToolsAjaxHandler;
use YangSheep\WebpTools\Database\YSWebpToolsTableMaker;
use YangSheep\WebpTools\Modules\YSImageResizer;
use YangSheep\WebpTools\Modules\YSWebpConverter;
use YangSheep\WebpTools\Modules\YSThumbnailManager;

defined( 'ABSPATH' ) || exit;

class YSWebpToolsPlugin {

    /** @var self|null 單一實例 */
    private static ?self $instance = null;

    /**
     * 取得單一實例
     */
    public static function instance(): self {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * 建構子 — 私有，防止外部 new
     */
    private function __construct() {
        $this->maybe_upgrade_schema();
        $this->init_components();
    }

    /**
     * 檢查 schema 版本，必要時升級資料表
     */
    private function maybe_upgrade_schema(): void {
        $current = get_option( 'ys_webp_tools_schema_version', '0' );
        if ( version_compare( (string) $current, YS_WEBP_TOOLS_VERSION, '<' ) ) {
            YSWebpToolsTableMaker::create_tables();
        }
    }

    /**
     * 初始化各元件
     *
     * 功能模組需在前後台皆載入（上傳可能來自媒體庫、文章編輯、前台或 REST）；
     * 後台 UI 元件僅在後台載入。
     */
    private function init_components(): void {
        // 功能模組（前後台皆載入）
        new YSImageResizer();
        new YSWebpConverter();
        new YSThumbnailManager();

        // 後台元件
        if ( is_admin() ) {
            new YSWebpToolsAdmin();
            new YSWebpToolsAjaxHandler();
        }
    }
}
