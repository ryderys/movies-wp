/**
 * Admin panel guide: multi-quality Sources tab workflow.
 */
( function ( $ ) {
	'use strict';

	var cfg = window.streamitChildSourcesGuide || {};
	var storageKey = 'streamit_child_sources_guide_dismissed_' + ( cfg.isEpisode ? 'episode' : 'movie' );

	var GUIDE_STYLE =
		'background:#1e293b!important;background-color:#1e293b!important;color:#fff!important;' +
		'-webkit-text-fill-color:#fff!important;opacity:1!important;direction:rtl;text-align:right;' +
		'padding:14px 16px;margin:0 0 16px;border-right:4px solid #72aee6;border-radius:4px;' +
		'box-sizing:border-box;position:relative;z-index:2;clear:both;max-width:100%;width:100%;';

	var TEXT_STYLE = 'color:#fff!important;-webkit-text-fill-color:#fff!important;opacity:1!important;';
	var HINT_STYLE =
		'background:#334155!important;background-color:#334155!important;color:#fef3c7!important;' +
		'-webkit-text-fill-color:#fef3c7!important;opacity:1!important;padding:10px 12px;border-radius:4px;margin-top:0;';
	var NOTE_STYLE =
		'background:#334155!important;background-color:#334155!important;color:#fff!important;' +
		'-webkit-text-fill-color:#fff!important;opacity:1!important;direction:rtl;text-align:right;' +
		'padding:12px 14px;margin:0 0 14px;border-right:4px solid #eab308;border-radius:4px;clear:both;';

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
			'#streamit_data_section .streamit-child-sources-guide__url-hint{' +
			'background:#334155!important;color:#fef3c7!important;-webkit-text-fill-color:#fef3c7!important;}' +
			'#streamit_data_section .streamit-child-sources-url-note,' +
			'#streamit_data_section .streamit-child-sources-url-note li,' +
			'#streamit_data_section .streamit-child-sources-url-note strong{' +
			'color:#fff!important;-webkit-text-fill-color:#fff!important;opacity:1!important;}' +
			'#streamit_data_section .streamit-child-sources-url-note{' +
			'background:#334155!important;}' +
			'#streamit_data_section .streamit-child-sources-url-note strong{' +
			'color:#fef3c7!important;-webkit-text-fill-color:#fef3c7!important;}' +
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

	function openSourcesTab() {
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

	function highlightSourcesTab() {
		var $tab = $( cfg.tabsList + ' li' ).has( 'a[href="' + cfg.tabSelector + '"]' );
		$tab.addClass( 'streamit-child-sources-tab-hint' );
	}

	function renderSourcesTabNote() {
		var $panel = $( cfg.tabSelector );
		if ( ! $panel.length || $panel.find( '.streamit-child-sources-url-note' ).length ) {
			return;
		}

		var html =
			'<div class="streamit-child-sources-url-note" role="note" style="' +
			NOTE_STYLE +
			'">' +
			'<strong style="' +
			TEXT_STYLE +
			'color:#fef3c7!important;-webkit-text-fill-color:#fef3c7!important;">' +
			( cfg.urlNoteTitle || '' ) +
			'</strong>' +
			'<ul>' +
			'<li style="' +
			TEXT_STYLE +
			'">' +
			( cfg.urlNotePlayback || '' ) +
			'</li>' +
			'<li style="' +
			TEXT_STYLE +
			'">' +
			( cfg.urlNoteDownload || '' ) +
			'</li>' +
			'<li style="' +
			TEXT_STYLE +
			'">' +
			( cfg.urlNoteOptional || '' ) +
			'</li>' +
			'</ul>' +
			'</div>';

		$panel.prepend( html );
	}

	function renderGuide() {
		if ( isDismissed() ) {
			highlightSourcesTab();
			return;
		}

		var $tabs = $( cfg.tabsList ).first();
		var $anchor = $tabs.length
			? $tabs
			: $( '#streamit_data_section .streamit-movie-heading, #streamit_data_section .streamit-heading-wraper' ).first();

		if ( ! $anchor.length ) {
			$anchor = $( '#streamit_data_section > .wrap' ).first();
		}

		var html =
			'<div class="streamit-child-sources-guide" role="note" style="' +
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
			( cfg.urlNoteShort
				? '<p class="streamit-child-sources-guide__intro streamit-child-sources-guide__url-hint" style="' +
				  HINT_STYLE +
				  '">' +
				  cfg.urlNoteShort +
				  '</p>'
				: '' ) +
			'<div class="streamit-child-sources-guide__actions" style="display:flex;flex-wrap:wrap;gap:10px;align-items:center;">' +
			'<button type="button" class="button button-primary streamit-child-sources-guide__open-tab">' +
			( cfg.goToTab || 'Open Sources tab' ) +
			'</button>' +
			'<button type="button" class="button streamit-child-sources-guide__dismiss">' +
			( cfg.dismiss || 'Dismiss' ) +
			'</button>' +
			'</div>' +
			'</div>';

		var $panel = $( html );
		if ( $tabs.length ) {
			$tabs.before( $panel );
		} else {
			$anchor.after( $panel );
		}

		$panel.on( 'click', '.streamit-child-sources-guide__open-tab', function () {
			openSourcesTab();
			renderSourcesTabNote();
		} );

		$panel.on( 'click', '.streamit-child-sources-guide__dismiss', function () {
			dismissGuide( $panel );
			highlightSourcesTab();
		} );

		highlightSourcesTab();
		renderSourcesTabNote();
	}

	$( function () {
		if ( ! $( '#insert_movie, #insert_episode' ).length ) {
			return;
		}
		ensureCriticalCss();
		renderGuide();

		$( cfg.tabsList + ' a[href="' + cfg.tabSelector + '"]' ).on( 'click', function () {
			setTimeout( renderSourcesTabNote, 50 );
		} );
	} );
}( jQuery ) );
