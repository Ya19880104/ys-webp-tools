/**
 * YS WebP Tools — 後台 JS
 *
 * 頁籤切換 + Range 即時顯示 + AJAX 儲存（JSON）+ 一鍵全關
 * + 批次重生（cleanup/rebuild）+ 內容 URL 掃描/替換 + 浮動通知
 *
 * @since 1.0.0
 */
( function ( $ ) {
    'use strict';

    function showNotice( message, type ) {
        var $notice = $( '<div>' )
            .addClass( 'ys-webp-notice ys-webp-notice--' + type )
            .text( message )
            .appendTo( 'body' );
        setTimeout( function () { $notice.addClass( 'is-visible' ); }, 10 );
        setTimeout( function () {
            $notice.removeClass( 'is-visible' );
            setTimeout( function () { $notice.remove(); }, 300 );
        }, 3000 );
    }

    function escHtml( str ) {
        return $( '<div>' ).text( null == str ? '' : String( str ) ).html();
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
        // 「既有圖片工具」與「說明文件」頁不顯示儲存按鈕
        $( '#ys-webp-savebar' ).toggle( 'help' !== tab && 'tools' !== tab );
    } );

    /**
     * Range 即時顯示數值
     */
    $( document ).on( 'input', '#ys-webp-quality-range', function () {
        $( '#ys-webp-quality-val' ).text( $( this ).val() );
    } );

    /**
     * 收集所有設定值
     */
    function collectSettings() {
        var settings = {};
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
                if ( isNaN( val ) ) { val = 0; }
            }
            settings[ key ] = val;
        } );
        var arrays = {};
        $( '[data-setting-array]' ).each( function () {
            var $el = $( this );
            var key = $el.data( 'setting-array' );
            if ( ! arrays[ key ] ) { arrays[ key ] = []; }
            if ( $el.is( ':checked' ) ) { arrays[ key ].push( String( $el.val() ) ); }
        } );
        $.each( arrays, function ( k, v ) { settings[ k ] = v; } );
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
                        ? response.data.message : ysWebpToolsAdmin.i18n.error;
                    showNotice( msg, 'error' );
                }
            },
            error: function () { showNotice( ysWebpToolsAdmin.i18n.error, 'error' ); },
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
    $( document ).on( 'change', '[data-setting-array="disabled_sizes"]', function () {
        var $items = $( '[data-setting-array="disabled_sizes"]' );
        var allChecked = $items.length > 0 && $items.filter( ':checked' ).length === $items.length;
        $( '#ys-webp-toggle-all-sizes' ).prop( 'checked', allChecked );
    } );

    /**
     * 通用批次輪詢（offset 分頁）
     */
    function pollBatch( action, baseData, onProgress, onComplete ) {
        function step( offset ) {
            $.ajax( {
                url: ysWebpToolsAdmin.ajax_url,
                type: 'POST',
                data: $.extend( {}, baseData, { action: action, nonce: ysWebpToolsAdmin.nonce, offset: offset } ),
                success: function ( resp ) {
                    if ( ! resp || ! resp.success ) {
                        showNotice( ( resp && resp.data && resp.data.message ) || ysWebpToolsAdmin.i18n.error, 'error' );
                        onComplete( null );
                        return;
                    }
                    onProgress( resp.data );
                    if ( resp.data.done ) { onComplete( resp.data ); }
                    else { step( resp.data.next_offset ); }
                },
                error: function () { showNotice( ysWebpToolsAdmin.i18n.error, 'error' ); onComplete( null ); }
            } );
        }
        step( 0 );
    }

    /**
     * 批次重生縮圖（cleanup / rebuild）
     */
    $( document ).on( 'click', '#ys-webp-regen-cleanup, #ys-webp-regen-rebuild', function ( e ) {
        e.preventDefault();
        var mode = $( this ).data( 'mode' );
        var confirmMsg = ( 'rebuild' === mode ) ? ysWebpToolsAdmin.i18n.regenConfirmRebuild : ysWebpToolsAdmin.i18n.regenConfirmCleanup;
        if ( ! window.confirm( confirmMsg ) ) { return; }

        var $f = $( '#ys-webp-regen-fill' ), $t = $( '#ys-webp-regen-text' );
        var $btns = $( '#ys-webp-regen-cleanup, #ys-webp-regen-rebuild' );
        var totDel = 0, totCre = 0;
        $btns.prop( 'disabled', true );
        $( '#ys-webp-regen-progress' ).show();
        $f.css( 'width', '0%' ); $t.text( '' );

        pollBatch( 'ys_webp_tools_regen_thumbs', { mode: mode },
            function ( d ) {
                totDel += ( d.deleted || 0 ); totCre += ( d.created || 0 );
                if ( 0 === d.total ) { $t.text( ysWebpToolsAdmin.i18n.regenEmpty ); return; }
                var pct = Math.min( 100, Math.round( d.processed / d.total * 100 ) );
                $f.css( 'width', pct + '%' );
                $t.text( ysWebpToolsAdmin.i18n.regenProg.replace( '%1$d', d.processed ).replace( '%2$d', d.total ) );
            },
            function ( d ) {
                $btns.prop( 'disabled', false );
                if ( ! d ) { return; }
                if ( 0 === d.total ) { showNotice( ysWebpToolsAdmin.i18n.regenEmpty, 'success' ); return; }
                var msg = ysWebpToolsAdmin.i18n.regenDone
                    .replace( '%1$d', d.processed ).replace( '%2$d', totDel ).replace( '%3$d', totCre );
                $t.text( msg ); showNotice( msg, 'success' );
            }
        );
    } );

    /**
     * 內容圖片網址：掃描預覽（dry）/ 執行替換
     */
    function renderSamples( $s, samples ) {
        if ( ! samples.length ) { $s.hide().empty(); return; }
        var html = '<div class="ys-webp-samples-title">' + escHtml( ysWebpToolsAdmin.i18n.contentSamplesTitle ) + '</div><ul>';
        samples.forEach( function ( s ) {
            html += '<li><code>' + escHtml( s.old ) + '</code> → <code>' + escHtml( s.new ) + '</code></li>';
        } );
        html += '</ul>';
        $s.html( html ).show();
    }

    function runContent( dry ) {
        if ( ! dry && ! window.confirm( ysWebpToolsAdmin.i18n.contentReplaceConfirm ) ) { return; }
        var $f = $( '#ys-webp-content-fill' ), $t = $( '#ys-webp-content-text' ), $s = $( '#ys-webp-content-samples' );
        var $scan = $( '#ys-webp-content-scan' ), $rep = $( '#ys-webp-content-replace' );
        var totPosts = 0, totUrls = 0, samples = [];
        $scan.prop( 'disabled', true ); $rep.prop( 'disabled', true );
        $( '#ys-webp-content-progress' ).show();
        $f.css( 'width', '0%' ); $t.text( '' ); $s.hide().empty();

        pollBatch( 'ys_webp_tools_content_urls', { dry: dry ? '1' : '0' },
            function ( d ) {
                totPosts += ( d.affected_posts || 0 ); totUrls += ( d.url_count || 0 );
                if ( d.samples && d.samples.length && samples.length < 5 ) {
                    samples = samples.concat( d.samples ).slice( 0, 5 );
                }
                if ( 0 === d.total ) { $t.text( ysWebpToolsAdmin.i18n.contentEmpty ); return; }
                var pct = Math.min( 100, Math.round( d.processed / d.total * 100 ) );
                $f.css( 'width', pct + '%' );
                $t.text( ysWebpToolsAdmin.i18n.contentProg.replace( '%1$d', d.processed ).replace( '%2$d', d.total ) );
            },
            function ( d ) {
                $scan.prop( 'disabled', false );
                if ( ! d ) { return; }
                if ( 0 === d.total ) {
                    showNotice( ysWebpToolsAdmin.i18n.contentEmpty, 'success' );
                    $rep.prop( 'disabled', true );
                    return;
                }
                if ( dry ) {
                    var msg = ysWebpToolsAdmin.i18n.contentScanDone.replace( '%1$d', totPosts ).replace( '%2$d', totUrls );
                    $t.text( msg ); showNotice( msg, 'success' );
                    if ( totUrls > 0 ) {
                        $rep.prop( 'disabled', false );
                        renderSamples( $s, samples );
                    } else {
                        $rep.prop( 'disabled', true );
                    }
                } else {
                    var msg2 = ysWebpToolsAdmin.i18n.contentReplaceDone.replace( '%1$d', totPosts ).replace( '%2$d', totUrls );
                    $t.text( msg2 ); showNotice( msg2, 'success' );
                    $rep.prop( 'disabled', true );
                    $s.hide().empty();
                }
            }
        );
    }
    $( document ).on( 'click', '#ys-webp-content-scan', function ( e ) { e.preventDefault(); runContent( true ); } );
    $( document ).on( 'click', '#ys-webp-content-replace', function ( e ) { e.preventDefault(); runContent( false ); } );

} )( jQuery );
