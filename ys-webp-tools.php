<?php
/**
 * Plugin Name: YS WebP Tools
 * Plugin URI:  https://yangsheep.com.tw
 * Description: 圖片優化工具 — 上傳自動轉 WebP、自動縮圖、關閉不需要的縮圖尺寸。
 * Version:     1.0.0
 * Author:      YANGSHEEP DESIGN
 * Author URI:  https://yangsheep.com.tw
 * License:     GPL-2.0-or-later
 * Text Domain: ys-webp-tools
 * Domain Path: /languages
 * Requires at least: 6.0
 * Requires PHP: 8.0
 *
 * @package YangSheep\WebpTools
 */

defined( 'ABSPATH' ) || exit;

/* ──────────────────────────────────────────────
 * 常數定義
 * ────────────────────────────────────────────── */
define( 'YS_WEBP_TOOLS_VERSION', '1.0.0' );
define( 'YS_WEBP_TOOLS_PLUGIN_FILE', __FILE__ );
define( 'YS_WEBP_TOOLS_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'YS_WEBP_TOOLS_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'YS_WEBP_TOOLS_BASENAME', plugin_basename( __FILE__ ) );

/* ──────────────────────────────────────────────
 * Vendor autoload（Hub Client）
 * ────────────────────────────────────────────── */
if ( file_exists( YS_WEBP_TOOLS_PLUGIN_DIR . 'vendor/autoload.php' ) ) {
    require_once YS_WEBP_TOOLS_PLUGIN_DIR . 'vendor/autoload.php';
}

/* ──────────────────────────────────────────────
 * Fallback PSR-4 Autoloader
 * 永遠註冊自身 namespace，不放 else 分支
 * ────────────────────────────────────────────── */
spl_autoload_register( function ( $class ) {
    $prefix   = 'YangSheep\\WebpTools\\';
    $base_dir = YS_WEBP_TOOLS_PLUGIN_DIR . 'src/';
    $len      = strlen( $prefix );

    if ( 0 !== strncmp( $prefix, $class, $len ) ) {
        return;
    }

    $relative = substr( $class, $len );
    $file     = $base_dir . str_replace( '\\', '/', $relative ) . '.php';

    if ( file_exists( $file ) ) {
        require_once $file;
    }
} );

/* ──────────────────────────────────────────────
 * HPOS 相容宣告（通用圖片工具，WooCommerce 非必要）
 * ────────────────────────────────────────────── */
add_action( 'before_woocommerce_init', function () {
    if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility(
            'custom_order_tables',
            __FILE__,
            true
        );
    }
} );

/* ──────────────────────────────────────────────
 * Hub Client 註冊（priority 5，比其他 hook 早）
 * ────────────────────────────────────────────── */
add_action( 'plugins_loaded', function () {
    if ( class_exists( '\YangSheep\PluginHubClient\YSPluginHubClient' ) ) {
        \YangSheep\PluginHubClient\YSPluginHubClient::register( array(
            'slug'        => 'ys-webp-tools',
            'version'     => YS_WEBP_TOOLS_VERSION,
            'plugin_file' => __FILE__,
            'name'        => 'YS WebP Tools',
        ) );
    }
}, 5 );

/* ──────────────────────────────────────────────
 * Activation — 建立資料表
 * ────────────────────────────────────────────── */
register_activation_hook( __FILE__, function () {
    \YangSheep\WebpTools\Database\YSWebpToolsTableMaker::create_tables();
} );

/* ──────────────────────────────────────────────
 * 主外掛初始化（priority 11，在 Hub Client 之後）
 * ────────────────────────────────────────────── */
add_action( 'plugins_loaded', function () {
    \YangSheep\WebpTools\YSWebpToolsPlugin::instance();
}, 11 );

/* ──────────────────────────────────────────────
 * 外掛動作連結（設定頁快捷）
 * ────────────────────────────────────────────── */
add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), function ( array $links ): array {
    $url = admin_url( 'admin.php?page=ys-webp-tools' );
    array_unshift(
        $links,
        '<a href="' . esc_url( $url ) . '">' . esc_html__( '設定', 'ys-webp-tools' ) . '</a>'
    );
    return $links;
} );
