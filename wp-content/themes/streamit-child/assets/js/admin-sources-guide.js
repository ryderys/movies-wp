/**
 * Admin panel guide: multi-quality Sources tab workflow.
 */
( function ( $ ) {
	'use strict';

	var cfg = window.streamitChildSourcesGuide || {};
	var storageKey = 'streamit_child_sources_guide_dismissed_' + ( cfg.isEpisode ? 'episode' : 'movie' );

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
			'<div class="streamit-child-sources-guide" role="note">' +
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
			'<button type="button" class="button button-primary streamit-child-sources-guide__open-tab">' +
			( cfg.goToTab || 'Open Sources tab' ) +
			'</button>' +
			'<button type="button" class="button button-link streamit-child-sources-guide__dismiss">' +
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
		} );

		$panel.on( 'click', '.streamit-child-sources-guide__dismiss', function () {
			dismissGuide( $panel );
			highlightSourcesTab();
		} );

		highlightSourcesTab();
	}

	$( function () {
		if ( ! $( '#insert_movie, #insert_episode' ).length ) {
			return;
		}
		renderGuide();
	} );
}( jQuery ) );
