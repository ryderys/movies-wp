/**
 * Copy stored season_number and image_id back onto the TV-show save payload.
 *
 * Each existing .streamit_seasons box is stamped once. The stamp moves with
 * the box if it is reordered and disappears with the box if it is removed.
 * A season added after load has no stamp, so it stays without those keys.
 *
 * @package streamit-child
 */
( function ( root, $ ) {
	'use strict';

	var NUMBER_ATTR = 'data-streamit-season-number';
	var IMAGE_ATTR = 'data-streamit-season-image-id';
	var BOUND_ATTR = 'data-streamit-season-bound';

	function parseIdentities( seasonsJson ) {
		if ( typeof seasonsJson !== 'string' || seasonsJson === '' ) {
			return [];
		}

		try {
			var parsed = JSON.parse( seasonsJson );
			return Array.isArray( parsed ) ? parsed : [];
		} catch ( error ) {
			return [];
		}
	}

	function encodeScalar( value ) {
		return encodeURIComponent( JSON.stringify( value ) );
	}

	function decodeScalar( encoded ) {
		return JSON.parse( decodeURIComponent( encoded ) );
	}

	/**
	 * Attributes for one stored season. Empty when the row has no scalar identity.
	 *
	 * @param {object|null} identity Identity slot from PHP.
	 * @return {object|null}
	 */
	function attributesForIdentity( identity ) {
		if ( ! identity || typeof identity !== 'object' ) {
			return null;
		}

		var attributes = {};
		attributes[ BOUND_ATTR ] = '1';

		if ( identity.has_season_number ) {
			attributes[ NUMBER_ATTR ] = encodeScalar( identity.season_number );
		}
		if ( identity.has_image_id ) {
			attributes[ IMAGE_ATTR ] = encodeScalar( identity.image_id );
		}

		return attributes;
	}

	/**
	 * Read a stamp. Null means this box was not an existing season.
	 *
	 * @param {object} box Object with attr(name).
	 * @return {object|null}
	 */
	function preservedFromBox( box ) {
		if ( ! box || typeof box.attr !== 'function' || box.attr( BOUND_ATTR ) !== '1' ) {
			return null;
		}

		var preserved = {};
		var numberAttr = box.attr( NUMBER_ATTR );
		var imageAttr = box.attr( IMAGE_ATTR );

		if ( typeof numberAttr === 'string' ) {
			try {
				preserved.season_number = decodeScalar( numberAttr );
			} catch ( error ) {
				// Leave the Streamit field untouched when the stamp is corrupt.
			}
		}
		if ( typeof imageAttr === 'string' ) {
			try {
				preserved.image_id = decodeScalar( imageAttr );
			} catch ( error ) {
				// Leave the Streamit field untouched when the stamp is corrupt.
			}
		}

		return preserved;
	}

	/**
	 * Patch visible season rows in DOM order. Other season fields stay as Streamit built them.
	 *
	 * @param {object} formData tvshow_form_data payload.
	 * @param {Array<object|null>} preservedByVisibleIndex One entry per visible box, in that order.
	 * @return {object}
	 */
	function mergeSeasonIdentity( formData, preservedByVisibleIndex ) {
		if ( ! formData || typeof formData !== 'object' || ! Array.isArray( formData._seasons ) ) {
			return formData;
		}
		if ( ! Array.isArray( preservedByVisibleIndex ) ) {
			return formData;
		}

		formData._seasons.forEach( function ( season, index ) {
			var preserved = preservedByVisibleIndex[ index ];
			if ( ! season || typeof season !== 'object' || ! preserved || typeof preserved !== 'object' ) {
				return;
			}
			if ( Object.prototype.hasOwnProperty.call( preserved, 'season_number' ) ) {
				season.season_number = preserved.season_number;
			}
			if ( Object.prototype.hasOwnProperty.call( preserved, 'image_id' ) ) {
				season.image_id = preserved.image_id;
			}
		} );

		return formData;
	}

	root.streamitChildPreserveTvshowSeasons = {
		parseIdentities: parseIdentities,
		attributesForIdentity: attributesForIdentity,
		preservedFromBox: preservedFromBox,
		mergeSeasonIdentity: mergeSeasonIdentity,
	};

	if ( ! $ ) {
		return;
	}

	var config = root.streamitChildTvshowSeasons || {};

	function stampExistingSeasons() {
		var identities = parseIdentities( config.seasonsJson );
		var $boxes = $( '.streamit-tvshow_seasons .streamit_seasons' );

		$boxes.each( function ( index ) {
			if ( index >= identities.length || $( this ).attr( BOUND_ATTR ) === '1' ) {
				return;
			}

			var attributes = attributesForIdentity( identities[ index ] );
			if ( ! attributes ) {
				return;
			}

			$( this ).attr( attributes );
		} );
	}

	function visibleSeasonBoxes() {
		return $( '.streamit_seasons' ).filter( function () {
			return $( this ).css( 'display' ) !== 'none';
		} );
	}

	function registerFormDataFilter() {
		if ( ! root.wp || ! root.wp.hooks ) {
			return;
		}

		root.wp.hooks.addFilter( 'tvshow_form_data', 'streamit-child/tvshow-season-identity', function ( formData ) {
			var preserved = [];

			visibleSeasonBoxes().each( function () {
				preserved.push( preservedFromBox( $( this ) ) );
			} );

			return mergeSeasonIdentity( formData, preserved );
		} );
	}

	$( function () {
		stampExistingSeasons();
		registerFormDataFilter();
	} );
} )( typeof window !== 'undefined' ? window : globalThis, typeof jQuery !== 'undefined' ? jQuery : null );
