/**
 * Admin panel guide: TV show seasons & episodes workflow shortcuts.
 */
( function ( $ ) {
	'use strict';

	var cfg = window.streamitChildTvshowGuide || {};
	var storageKey = 'streamit_child_tvshow_episodes_guide_dismissed';

	var GUIDE_STYLE =
		'background:#1e293b!important;background-color:#1e293b!important;color:#fff!important;' +
		'-webkit-text-fill-color:#fff!important;opacity:1!important;direction:rtl;text-align:right;' +
		'padding:14px 16px;margin:0 0 16px;border-right:4px solid #72aee6;border-radius:4px;' +
		'box-sizing:border-box;position:relative;z-index:2;clear:both;max-width:100%;width:100%;';

	var TEXT_STYLE = 'color:#fff!important;-webkit-text-fill-color:#fff!important;opacity:1!important;';

	function ensureCriticalCss() {
		if ( document.getElementById( 'streamit-child-sources-guide-critical' ) ) {
			return;
		}
		var css =
			'#streamit_data_section .streamit-child-sources-guide,' +
			'#streamit_data_section .streamit-child-sources-guide p,' +
			'#streamit_data_section .streamit-child-sources-guide li,' +
			'#streamit_data_section .streamit-child-sources-guide ol,' +
			'#streamit_data_section .streamit-child-sources-guide strong{' +
			'color:#fff!important;-webkit-text-fill-color:#fff!important;opacity:1!important;}' +
			'#streamit_data_section .streamit-child-sources-guide{' +
			'background:#1e293b!important;background-color:#1e293b!important;}' +
			'#streamit_data_section .streamit-child-sources-guide__actions .button,' +
			'#streamit_data_section .streamit-child-sources-guide__actions a.button{' +
			'position:static!important;float:none!important;display:inline-flex!important;' +
			'margin:0!important;flex:0 0 auto;}';
		$( '<style id="streamit-child-sources-guide-critical">' ).text( css ).appendTo( 'head' );
	}

	function isDismissed() {
		try {
			return window.localStorage.getItem( storageKey ) === '1';
		} catch ( e ) {
			return false;
		}
	}

	function dismissGuide( $panel ) {
		$panel.addClass( 'is-dismissed' );
		try {
			window.localStorage.setItem( storageKey, '1' );
		} catch ( e ) {
			// ignore
		}
	}

	function openSeasonsTab() {
		var $tabLink = $( cfg.tabsList + ' a[href="' + cfg.tabSelector + '"]' );
		if ( $tabLink.length ) {
			$tabLink.trigger( 'click' );
			$( 'html, body' ).animate(
				{
					scrollTop: $( cfg.tabSelector ).offset().top - 80,
				},
				300
			);
		}
	}

	function highlightSeasonsTab() {
		var $tab = $( cfg.tabsList + ' li' ).has( 'a[href="' + cfg.tabSelector + '"]' );
		$tab.addClass( 'streamit-child-sources-tab-hint' );
	}

	function renderGuide() {
		if ( isDismissed() ) {
			highlightSeasonsTab();
			return;
		}

		var $tabs = $( cfg.tabsList ).first();
		var $anchor = $tabs.length
			? $tabs
			: $( '#streamit_data_section .streamit-tvshow-heading, #streamit_data_section .streamit-heading-wraper' ).first();

		if ( ! $anchor.length ) {
			$anchor = $( '#streamit_data_section > .wrap' ).first();
		}

		var html =
			'<div class="streamit-child-sources-guide streamit-child-tvshow-guide" role="note" style="' +
			GUIDE_STYLE +
			'">' +
			'<p class="streamit-child-sources-guide__title" style="' +
			TEXT_STYLE +
			'margin:0 0 8px;font-size:14px;font-weight:600;">' +
			( cfg.title || '' ) +
			'</p>' +
			'<p class="streamit-child-sources-guide__intro" style="' +
			TEXT_STYLE +
			'margin:0 0 10px;font-size:13px;line-height:1.7;">' +
			( cfg.intro || '' ) +
			'</p>' +
			'<ol class="streamit-child-sources-guide__steps" style="' +
			TEXT_STYLE +
			'margin:0 24px 12px 0;padding:0;font-size:13px;line-height:1.7;">' +
			'<li style="' +
			TEXT_STYLE +
			'">' +
			( cfg.step1 || '' ) +
			'</li>' +
			'<li style="' +
			TEXT_STYLE +
			'">' +
			( cfg.step2 || '' ) +
			'</li>' +
			'<li style="' +
			TEXT_STYLE +
			'">' +
			( cfg.step3 || '' ) +
			'</li>' +
			'<li style="' +
			TEXT_STYLE +
			'">' +
			( cfg.step4 || '' ) +
			'</li>' +
			'</ol>' +
			'<div class="streamit-child-sources-guide__actions" style="display:flex;flex-wrap:wrap;gap:10px;align-items:center;">' +
			'<button type="button" class="button button-primary streamit-child-tvshow-guide__open-seasons">' +
			( cfg.goToSeasonsTab || '' ) +
			'</button>' +
			'<a class="button streamit-child-tvshow-guide__episodes-list" href="' +
			( cfg.episodesListUrl || '#' ) +
			'">' +
			( cfg.manageEpisodes || '' ) +
			'</a>' +
			'<a class="button streamit-child-tvshow-guide__add-episode" href="' +
			( cfg.addEpisodeUrl || '#' ) +
			'" target="_blank" rel="noopener noreferrer">' +
			( cfg.addEpisode || '' ) +
			'</a>' +
			'<button type="button" class="button streamit-child-sources-guide__dismiss">' +
			( cfg.dismiss || '' ) +
			'</button>' +
			'</div>' +
			'</div>';

		var $panel = $( html );
		if ( $tabs.length ) {
			$tabs.before( $panel );
		} else {
			$anchor.after( $panel );
		}

		$panel.on( 'click', '.streamit-child-tvshow-guide__open-seasons', function () {
			openSeasonsTab();
		} );

		$panel.on( 'click', '.streamit-child-sources-guide__dismiss', function () {
			dismissGuide( $panel );
			highlightSeasonsTab();
		} );

		highlightSeasonsTab();
	}

	$( function () {
		if ( ! $( '#insert_tvshow' ).length ) {
			return;
		}
		ensureCriticalCss();
		renderGuide();
	} );
}( jQuery ) );
