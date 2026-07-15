/**
 * Admin panel guide: TV show seasons & episodes workflow shortcuts.
 */
( function ( $ ) {
	'use strict';

	var cfg = window.streamitChildTvshowGuide || {};
	var storageKey = 'streamit_child_tvshow_episodes_guide_dismissed';

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
			'<div class="streamit-child-sources-guide streamit-child-tvshow-guide" role="note">' +
			'<p class="streamit-child-sources-guide__title">' +
			( cfg.title || '' ) +
			'</p>' +
			'<p class="streamit-child-sources-guide__intro">' +
			( cfg.intro || '' ) +
			'</p>' +
			'<ol class="streamit-child-sources-guide__steps">' +
			'<li>' +
			( cfg.step1 || '' ) +
			'</li>' +
			'<li>' +
			( cfg.step2 || '' ) +
			'</li>' +
			'<li>' +
			( cfg.step3 || '' ) +
			'</li>' +
			'<li>' +
			( cfg.step4 || '' ) +
			'</li>' +
			'</ol>' +
			'<div class="streamit-child-sources-guide__actions">' +
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
			'<button type="button" class="button button-link streamit-child-sources-guide__dismiss">' +
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
		renderGuide();
	} );
}( jQuery ) );
