/**
 * YS WebP Tools — 後台 JS
 *
 * 頁籤切換 + Range 即時顯示 + AJAX 儲存（JSON 傳輸）+ 浮動通知
 *
 * @since 1.0.0
 */
( function ( $ ) {
    'use strict';

    /**
     * 顯示浮動通知
     *
     * @param {string} message 訊息文字
     * @param {string} type    'success' | 'error'
     */
    function showNotice( message, type ) {
        var $notice = $( '<div>' )
            .addClass( 'ys-webp-notice ys-webp-notice--' + type )
            .text( message )
            .appendTo( 'body' );

        setTimeout( function () {
            $notice.addClass( 'is-visible' );
        }, 10 );

        setTimeout( function () {
            $notice.removeClass( 'is-visible' );
            setTimeout( function () {
                $notice.remove();
            }, 300 );
        }, 3000 );
    }

    /**
     * 頁籤切換
     */
    $( document ).on( 'click', '.ys-webp-tab', function ( e ) {
        e.preventDefault();
        var tab = $( this ).data( 'tab' );

        $( '.ys-webp-tab' ).removeClass( 'is-active' );
        $( this ).addClass( 'is-active' );

        $( '.ys-webp-panel' ).removeClass( 'is-active' );
        $( '.ys-webp-panel[data-panel="' + tab + '"]' ).addClass( 'is-active' );

        // 說明文件頁不顯示儲存按鈕
        $( '#ys-webp-savebar' ).toggle( tab !== 'help' );
    } );

    /**
     * Range 即時顯示數值
     */
    $( document ).on( 'input', '#ys-webp-quality-range', function () {
        $( '#ys-webp-quality-val' ).text( $( this ).val() );
    } );

    /**
     * 收集所有設定值
     *
     * @return {Object}
     */
    function collectSettings() {
        var settings = {};

        // 單值欄位
        $( '[data-setting-key]' ).each( function () {
            var $el = $( this );
            var key = $el.data( 'setting-key' );
            var type = $el.data( 'setting-type' );
            var val;

            if ( $el.is( ':checkbox' ) ) {
                val = $el.is( ':checked' );
            } else {
                val = $el.val();
            }

            if ( 'int' === type ) {
                val = parseInt( val, 10 );
                if ( isNaN( val ) ) {
                    val = 0;
                }
            }

            settings[ key ] = val;
        } );

        // 陣列欄位（多個 checkbox 共用 data-setting-array）
        var arrays = {};
        $( '[data-setting-array]' ).each( function () {
            var $el = $( this );
            var key = $el.data( 'setting-array' );
            if ( ! arrays[ key ] ) {
                arrays[ key ] = [];
            }
            if ( $el.is( ':checked' ) ) {
                arrays[ key ].push( String( $el.val() ) );
            }
        } );
        $.each( arrays, function ( k, v ) {
            settings[ k ] = v;
        } );

        return settings;
    }

    /**
     * 儲存設定
     */
    $( document ).on( 'click', '#ys-webp-save-btn', function ( e ) {
        e.preventDefault();

        var $btn = $( this );
        var $label = $btn.find( '.ys-webp-btn-label' );
        var originalText = $label.text();

        $btn.prop( 'disabled', true );
        $label.text( ysWebpToolsAdmin.i18n.saving );

        $.ajax( {
            url: ysWebpToolsAdmin.ajax_url,
            type: 'POST',
            data: {
                action: 'ys_webp_tools_save_settings',
                nonce: ysWebpToolsAdmin.nonce,
                settings: JSON.stringify( collectSettings() )
            },
            success: function ( response ) {
                if ( response && response.success ) {
                    showNotice( ysWebpToolsAdmin.i18n.saved, 'success' );
                } else {
                    var msg = ( response && response.data && response.data.message )
                        ? response.data.message
                        : ysWebpToolsAdmin.i18n.error;
                    showNotice( msg, 'error' );
                }
            },
            error: function () {
                showNotice( ysWebpToolsAdmin.i18n.error, 'error' );
            },
            complete: function () {
                $btn.prop( 'disabled', false );
                $label.text( originalText );
            }
        } );
    } );

    /**
     * 縮圖尺寸：一鍵全部停用／啟用
     */
    $( document ).on( 'change', '#ys-webp-toggle-all-sizes', function () {
        var checked = $( this ).is( ':checked' );
        $( '[data-setting-array="disabled_sizes"]' ).prop( 'checked', checked );
    } );

    // 個別變更時同步「全部」開關狀態
    $( document ).on( 'change', '[data-setting-array="disabled_sizes"]', function () {
        var $items = $( '[data-setting-array="disabled_sizes"]' );
        var allChecked = $items.length > 0 && $items.filter( ':checked' ).length === $items.length;
        $( '#ys-webp-toggle-all-sizes' ).prop( 'checked', allChecked );
    } );

    /**
     * 批次重新產生既有圖片縮圖（offset 分頁輪詢）
     */
    $( document ).on( 'click', '#ys-webp-regen-btn', function ( e ) {
        e.preventDefault();
        if ( ! window.confirm( ysWebpToolsAdmin.i18n.regenConfirm ) ) {
            return;
        }

        var $btn = $( this );
        var $label = $btn.find( '.ys-webp-btn-label' );
        var origText = $label.text();
        var $progress = $( '#ys-webp-regen-progress' );
        var $fill = $( '#ys-webp-regen-fill' );
        var $text = $( '#ys-webp-regen-text' );

        var totalDeleted = 0;
        var totalCreated = 0;

        $btn.prop( 'disabled', true );
        $progress.show();
        $fill.css( 'width', '0%' );
        $text.text( '' );

        function finish() {
            $btn.prop( 'disabled', false );
            $label.text( origText );
        }

        function step( offset ) {
            $.ajax( {
                url: ysWebpToolsAdmin.ajax_url,
                type: 'POST',
                data: {
                    action: 'ys_webp_tools_regen_thumbs',
                    nonce: ysWebpToolsAdmin.nonce,
                    offset: offset
                },
                success: function ( response ) {
                    if ( ! response || ! response.success ) {
                        var msg = ( response && response.data && response.data.message ) ? response.data.message : ysWebpToolsAdmin.i18n.error;
                        showNotice( msg, 'error' );
                        finish();
                        return;
                    }
                    var d = response.data;
                    totalDeleted += ( d.deleted || 0 );
                    totalCreated += ( d.created || 0 );

                    if ( 0 === d.total ) {
                        $text.text( ysWebpToolsAdmin.i18n.regenEmpty );
                        showNotice( ysWebpToolsAdmin.i18n.regenEmpty, 'success' );
                        finish();
                        return;
                    }

                    var pct = Math.min( 100, Math.round( ( d.processed / d.total ) * 100 ) );
                    $fill.css( 'width', pct + '%' );
                    $text.text( ysWebpToolsAdmin.i18n.regenProg.replace( '%1$d', d.processed ).replace( '%2$d', d.total ) );

                    if ( d.done ) {
                        var doneMsg = ysWebpToolsAdmin.i18n.regenDone
                            .replace( '%1$d', d.processed )
                            .replace( '%2$d', totalDeleted )
                            .replace( '%3$d', totalCreated );
                        $text.text( doneMsg );
                        showNotice( doneMsg, 'success' );
                        finish();
                    } else {
                        step( d.next_offset );
                    }
                },
                error: function () {
                    showNotice( ysWebpToolsAdmin.i18n.error, 'error' );
                    finish();
                }
            } );
        }

        step( 0 );
    } );

} )( jQuery );
