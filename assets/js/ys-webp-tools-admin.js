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

} )( jQuery );
