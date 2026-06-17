<?php
/**
 * 後台管理頁面 — 選單（電商工具箱整合）、資源載入、頁面渲染
 *
 * @package YangSheep\WebpTools\Admin
 * @since   1.0.0
 */

namespace YangSheep\WebpTools\Admin;

defined( 'ABSPATH' ) || exit;

class YSWebpToolsAdmin {

    /** @var string 頁面 slug */
    private const PAGE_SLUG = 'ys-webp-tools';

    /** @var string 電商工具箱頂層 slug（與其他 YS 外掛共用） */
    private const TOOLBOX_SLUG = 'ys-toolbox';

    public function __construct() {
        add_filter( 'ys_toolbox_plugins', [ $this, 'register_toolbox_card' ] );
        add_action( 'admin_menu', [ $this, 'register_toolbox_menu' ], 25 );
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_assets' ] );
    }

    /**
     * 註冊本外掛的工具箱卡片資訊（供總覽頁列出）
     *
     * @param array $plugins 已註冊的外掛列表
     * @return array
     */
    public function register_toolbox_card( $plugins ) {
        $plugins[]   = [
            'name'    => 'YS WebP Tools',
            'version' => YS_WEBP_TOOLS_VERSION,
            'icon'    => 'dashicons-images-alt2',
            'desc'    => '圖片優化：上傳自動轉 WebP、自動縮圖、關閉多餘縮圖尺寸。',
            'url'     => admin_url( 'admin.php?page=' . self::PAGE_SLUG ),
        ];
        return $plugins;
    }

    /**
     * 註冊選單
     *
     * 偵測「電商工具箱」頂層是否已存在；不存在則建立（含總覽頁），
     * 再掛上本外掛的子選單。
     */
    public function register_toolbox_menu(): void {
        global $menu;

        $toolbox_exists = false;
        if ( is_array( $menu ) ) {
            foreach ( $menu as $item ) {
                if ( isset( $item[2] ) && self::TOOLBOX_SLUG === $item[2] ) {
                    $toolbox_exists = true;
                    break;
                }
            }
        }

        if ( ! $toolbox_exists ) {
            $welcome_callback = $this->get_toolbox_welcome_callback();

            add_menu_page(
                __( '電商工具箱', 'ys-webp-tools' ),
                __( '電商工具箱', 'ys-webp-tools' ),
                'manage_options',
                self::TOOLBOX_SLUG,
                $welcome_callback,
                'dashicons-store',
                56
            );

            add_submenu_page(
                self::TOOLBOX_SLUG,
                __( '電商工具箱', 'ys-webp-tools' ),
                __( '總覽', 'ys-webp-tools' ),
                'manage_options',
                self::TOOLBOX_SLUG,
                $welcome_callback
            );
        }

        add_submenu_page(
            self::TOOLBOX_SLUG,
            __( 'YS WebP Tools 設定', 'ys-webp-tools' ),
            __( 'WebP 工具', 'ys-webp-tools' ),
            'manage_options',
            self::PAGE_SLUG,
            [ $this, 'render_page' ]
        );
    }

    /**
     * 取得總覽頁渲染回調（fallback 鏈：優先用已載入的 YS 外掛）
     *
     * @return callable
     */
    private function get_toolbox_welcome_callback(): callable {
        $fallback_classes = [
            '\YangSheep\ShoplinePayment\Admin\YSAdminSettings',
            '\YangSheep\PayNow\Shipping\Settings\YSSettingsTab',
            '\YangSheep\CheckoutOptimizer\Admin\YSCheckoutSettings',
        ];

        foreach ( $fallback_classes as $class ) {
            if ( class_exists( $class ) && method_exists( $class, 'render_toolbox_welcome' ) ) {
                return [ $class, 'render_toolbox_welcome' ];
            }
        }

        return [ __CLASS__, 'render_toolbox_welcome' ];
    }

    /**
     * 渲染電商工具箱總覽頁（自動列出所有已啟用的 YS 外掛）
     */
    public static function render_toolbox_welcome(): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        $plugins = apply_filters( 'ys_toolbox_plugins', [] );
        ?>
        <div class="wrap"><h2 style="display:none;"></h2></div>
        <div class="ys-toolbox-welcome">
            <div class="ys-toolbox-header">
                <div class="ys-toolbox-header-content">
                    <div class="ys-toolbox-logo"><span class="dashicons dashicons-store"></span></div>
                    <h2><?php echo esc_html__( '電商工具箱', 'ys-webp-tools' ); ?></h2>
                    <p class="ys-toolbox-subtitle">
                        <?php echo esc_html__( 'WooCommerce 電商擴充套件，由 YANGSHEEP DESIGN 開發維護', 'ys-webp-tools' ); ?>
                    </p>
                </div>
            </div>

            <div class="ys-toolbox-cards">
                <?php if ( empty( $plugins ) ) : ?>
                    <div class="ys-toolbox-empty"><?php echo esc_html__( '尚未偵測到已啟用的 YS 外掛。', 'ys-webp-tools' ); ?></div>
                <?php else : ?>
                    <?php foreach ( $plugins as $plugin ) :
                        $plugin = wp_parse_args( $plugin, [
                            'name'    => __( '未知外掛', 'ys-webp-tools' ),
                            'version' => '0.0.0',
                            'icon'    => 'dashicons-admin-plugins',
                            'desc'    => '',
                            'url'     => '#',
                        ] );
                        ?>
                        <a href="<?php echo esc_url( $plugin['url'] ); ?>" class="ys-toolbox-card">
                            <div class="ys-toolbox-card-icon"><span class="dashicons <?php echo esc_attr( $plugin['icon'] ); ?>"></span></div>
                            <div class="ys-toolbox-card-body">
                                <h3><?php echo esc_html( $plugin['name'] ); ?> <span class="ys-toolbox-card-version">v<?php echo esc_html( $plugin['version'] ); ?></span></h3>
                                <p><?php echo esc_html( $plugin['desc'] ); ?></p>
                            </div>
                            <span class="ys-toolbox-card-arrow dashicons dashicons-arrow-right-alt2"></span>
                        </a>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <div class="ys-toolbox-footer">
                <span class="dashicons dashicons-heart"></span>
                <span><?php echo esc_html__( '由', 'ys-webp-tools' ); ?> <strong>YANGSHEEP DESIGN</strong> <?php echo esc_html__( '用心開發', 'ys-webp-tools' ); ?></span>
                <span class="ys-toolbox-sep">|</span>
                <a href="https://yangsheep.com.tw" target="_blank" rel="noopener noreferrer">yangsheep.com.tw</a>
            </div>
        </div>
        <style>
            .ys-toolbox-welcome { max-width: 860px; margin: 20px 0; }
            .ys-toolbox-header { background: linear-gradient(135deg, #3a4f63 0%, #2c3e50 100%); border-radius: 12px; padding: 40px; color: #fff; }
            .ys-toolbox-logo .dashicons { font-size: 40px; width: 40px; height: 40px; }
            .ys-toolbox-header h2 { color: #fff; margin: 12px 0 4px; font-size: 24px; }
            .ys-toolbox-subtitle { color: rgba(255,255,255,0.8); margin: 0; }
            .ys-toolbox-cards { display: flex; flex-direction: column; gap: 12px; margin: 20px 0; }
            .ys-toolbox-card { display: flex; align-items: center; gap: 16px; background: #fff; border: 1px solid #e0e0e0; border-radius: 10px; padding: 18px 20px; text-decoration: none; color: #2c3e50; transition: all .2s; }
            .ys-toolbox-card:hover { border-color: #8fa8b8; transform: translateY(-2px); box-shadow: 0 4px 16px rgba(107,138,154,0.15); }
            .ys-toolbox-card-icon { flex: 0 0 52px; width: 52px; height: 52px; display: flex; align-items: center; justify-content: center; background: #f0f4f7; border-radius: 12px; }
            .ys-toolbox-card-icon .dashicons { font-size: 26px; width: 26px; height: 26px; color: #6b8a9a; }
            .ys-toolbox-card-body { flex: 1; }
            .ys-toolbox-card-body h3 { margin: 0 0 4px; font-size: 15px; }
            .ys-toolbox-card-version { font-size: 11px; font-weight: 600; color: #6b8a9a; background: #eef3f6; padding: 2px 8px; border-radius: 20px; }
            .ys-toolbox-card-body p { margin: 0; font-size: 13px; color: #7b8a96; }
            .ys-toolbox-card-arrow { color: #b3c7d3; }
            .ys-toolbox-empty { text-align: center; padding: 40px; color: #7b8a96; background: #fff; border: 1px dashed #e0e0e0; border-radius: 10px; }
            .ys-toolbox-footer { text-align: center; color: #7b8a96; font-size: 13px; padding: 16px; }
            .ys-toolbox-footer .dashicons { color: #c08080; font-size: 16px; width: 16px; height: 16px; vertical-align: middle; }
            .ys-toolbox-footer a { color: #6b8a9a; text-decoration: none; }
            .ys-toolbox-sep { margin: 0 8px; color: #d0d8de; }
        </style>
        <?php
    }

    /**
     * 載入後台 CSS / JS（僅在本外掛設定頁）
     *
     * @param string $hook 當前頁面 hook
     */
    public function enqueue_assets( string $hook ): void {
        $screen = get_current_screen();
        if ( ! $screen || false === strpos( $screen->id, self::PAGE_SLUG ) ) {
            return;
        }

        wp_enqueue_style(
            'ys-webp-tools-admin',
            YS_WEBP_TOOLS_PLUGIN_URL . 'assets/css/ys-webp-tools-admin.css',
            [],
            YS_WEBP_TOOLS_VERSION
        );

        wp_enqueue_script(
            'ys-webp-tools-admin',
            YS_WEBP_TOOLS_PLUGIN_URL . 'assets/js/ys-webp-tools-admin.js',
            [ 'jquery' ],
            YS_WEBP_TOOLS_VERSION,
            true
        );

        wp_localize_script( 'ys-webp-tools-admin', 'ysWebpToolsAdmin', [
            'ajax_url' => admin_url( 'admin-ajax.php' ),
            'nonce'    => wp_create_nonce( 'ys_webp_tools_nonce' ),
            'i18n'     => [
                'saving' => __( '儲存中…', 'ys-webp-tools' ),
                'saved'  => __( '設定已儲存', 'ys-webp-tools' ),
                'error'  => __( '儲存失敗，請重試', 'ys-webp-tools' ),
            ],
        ] );
    }

    /**
     * 渲染本外掛設定頁面
     */
    public function render_page(): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( '你沒有足夠的權限。', 'ys-webp-tools' ) );
        }

        $template = YS_WEBP_TOOLS_PLUGIN_DIR . 'templates/admin/settings.php';
        if ( file_exists( $template ) ) {
            include $template;
        }
    }
}
