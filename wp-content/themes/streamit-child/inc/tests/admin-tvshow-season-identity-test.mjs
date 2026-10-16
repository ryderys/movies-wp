/**
 * Payload tests for TV-show season identity preservation.
 *
 * Run:
 *   node wp-content/themes/streamit-child/inc/tests/admin-tvshow-season-identity-test.mjs
 */

import assert from 'node:assert/strict';
import { createRequire } from 'node:module';
import { fileURLToPath } from 'node:url';

const require = createRequire( import.meta.url );
const scriptPath = fileURLToPath( new URL( '../../assets/js/admin-tvshow-season-identity.js', import.meta.url ) );

globalThis.window = globalThis;
require( scriptPath );

const {
	parseIdentities,
	attributesForIdentity,
	preservedFromBox,
	mergeSeasonIdentity,
} = globalThis.streamitChildPreserveTvshowSeasons;

function boxFromIdentity( identity ) {
	const attributes = attributesForIdentity( identity ) || {};
	return {
		attr( name ) {
			return Object.prototype.hasOwnProperty.call( attributes, name ) ? attributes[ name ] : undefined;
		},
	};
}

function unboundBox() {
	return {
		attr() {
			return undefined;
		},
	};
}

function season( name ) {
	return {
		name,
		episodes: [ name.length ],
		season_year: '2024',
		season_description: name + ' description',
		sesion_upcoming_status: '0',
		season_upcoming_datetime: '',
	};
}

const identities = parseIdentities(
	JSON.stringify( [
		{ has_season_number: true, season_number: '1', has_image_id: true, image_id: 15 },
		{ has_season_number: true, season_number: '0', has_image_id: true, image_id: '' },
		{ has_season_number: false, has_image_id: false },
	] )
);

const boxes = identities.map( boxFromIdentity );

const unchanged = mergeSeasonIdentity(
	{
		_seasons: [ season( 'One' ), season( 'Specials' ), season( 'Plain' ) ],
	},
	boxes.map( preservedFromBox )
);

assert.deepEqual( unchanged._seasons[ 0 ].season_number, '1' );
assert.deepEqual( unchanged._seasons[ 0 ].image_id, 15 );
assert.deepEqual( unchanged._seasons[ 0 ].name, 'One' );
assert.deepEqual( unchanged._seasons[ 0 ].episodes, [ 3 ] );
assert.deepEqual( unchanged._seasons[ 1 ].season_number, '0' );
assert.deepEqual( unchanged._seasons[ 1 ].image_id, '' );
assert.equal( Object.prototype.hasOwnProperty.call( unchanged._seasons[ 2 ], 'season_number' ), false );
assert.equal( Object.prototype.hasOwnProperty.call( unchanged._seasons[ 2 ], 'image_id' ), false );

const reordered = mergeSeasonIdentity(
	{
		_seasons: [ season( 'Specials' ), season( 'One' ) ],
	},
	[ preservedFromBox( boxes[ 1 ] ), preservedFromBox( boxes[ 0 ] ) ]
);

assert.deepEqual( reordered._seasons[ 0 ].season_number, '0' );
assert.deepEqual( reordered._seasons[ 0 ].image_id, '' );
assert.deepEqual( reordered._seasons[ 0 ].name, 'Specials' );
assert.deepEqual( reordered._seasons[ 1 ].season_number, '1' );
assert.deepEqual( reordered._seasons[ 1 ].image_id, 15 );

const added = mergeSeasonIdentity(
	{
		_seasons: [ season( 'One' ), season( 'New' ) ],
	},
	[ preservedFromBox( boxes[ 0 ] ), preservedFromBox( unboundBox() ) ]
);

assert.deepEqual( added._seasons[ 0 ].season_number, '1' );
assert.equal( Object.prototype.hasOwnProperty.call( added._seasons[ 1 ], 'season_number' ), false );
assert.equal( Object.prototype.hasOwnProperty.call( added._seasons[ 1 ], 'image_id' ), false );
assert.deepEqual( added._seasons[ 1 ].name, 'New' );

const deleted = mergeSeasonIdentity(
	{
		_seasons: [ season( 'Specials' ) ],
	},
	[ preservedFromBox( boxes[ 1 ] ) ]
);

assert.deepEqual( deleted._seasons, [
	{
		name: 'Specials',
		episodes: [ 8 ],
		season_year: '2024',
		season_description: 'Specials description',
		sesion_upcoming_status: '0',
		season_upcoming_datetime: '',
		season_number: '0',
		image_id: '',
	},
] );
assert.equal( deleted._seasons.some( ( row ) => row.season_number === '1' ), false );

const corrupt = {
	attr( name ) {
		if ( name === 'data-streamit-season-bound' ) {
			return '1';
		}
		if ( name === 'data-streamit-season-number' ) {
			return '%';
		}
		return undefined;
	},
};
const safe = mergeSeasonIdentity(
	{ _seasons: [ season( 'One' ) ] },
	[ preservedFromBox( corrupt ) ]
);
assert.equal( Object.prototype.hasOwnProperty.call( safe._seasons[ 0 ], 'season_number' ), false );
assert.deepEqual( parseIdentities( '{not json' ), [] );
assert.deepEqual( parseIdentities( '' ), [] );

console.log( 'unchanged', JSON.stringify( unchanged._seasons, null, 2 ) );
console.log( 'reordered', JSON.stringify( reordered._seasons, null, 2 ) );
console.log( 'added', JSON.stringify( added._seasons, null, 2 ) );
console.log( 'deleted', JSON.stringify( deleted._seasons, null, 2 ) );
console.log( 'all passed' );
