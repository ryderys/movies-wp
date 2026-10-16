/**
 * Load/save round trip for movie and TV-show cast and crew labels.
 *
 * Run:
 *   node wp-content/themes/streamit-child/inc/tests/admin-people-labels-test.mjs
 */

import assert from 'node:assert/strict';
import { createRequire } from 'node:module';
import { fileURLToPath } from 'node:url';

const require = createRequire( import.meta.url );
const scriptPath = fileURLToPath( new URL( '../../assets/js/admin-people-labels.js', import.meta.url ) );

globalThis.window = globalThis;
require( scriptPath );

const { prefillLabel, mergePeopleList, mergePeopleFormData } = globalThis.streamitChildPreservePeopleLabels;

function saveCrew( stored, submittedRole, submittedCategory = '' ) {
	const submitted = stored.map( ( row, index ) => ( {
		id: String( row.id ),
		role: Array.isArray( submittedRole ) ? submittedRole[ index ] : submittedRole,
		category: submittedCategory,
	} ) );
	return mergePeopleList( stored, submitted, 'crew' );
}

const jobOnly = [ { id: 4, job: 'Director', position: 2 } ];
assert.equal( prefillLabel( '', jobOnly[ 0 ], [ 'role', 'character', 'job' ] ), 'Director' );
const jobRoundTrip = saveCrew( jobOnly, 'Director' );
assert.equal( jobRoundTrip[ 0 ].job, 'Director' );
assert.equal( jobRoundTrip[ 0 ].role, 'Director' );
assert.equal( jobRoundTrip[ 0 ].id, 4 );
assert.equal( jobRoundTrip[ 0 ].position, 2 );

const editedJob = saveCrew( jobOnly, 'Producer' );
assert.equal( editedJob[ 0 ].role, 'Producer' );
assert.equal( editedJob[ 0 ].job, 'Director' );

const roleOnly = [ { id: 5, role: 'Writer' } ];
assert.equal( prefillLabel( 'Writer', roleOnly[ 0 ], [ 'role', 'character', 'job' ] ), 'Writer' );
const roleRoundTrip = saveCrew( roleOnly, 'Writer' );
assert.equal( roleRoundTrip[ 0 ].role, 'Writer' );
assert.equal( Object.hasOwn( roleRoundTrip[ 0 ], 'job' ), false );

const characterOnly = [ { id: 6, character: 'Host' } ];
assert.equal( prefillLabel( '', characterOnly[ 0 ], [ 'role', 'character', 'job' ] ), 'Host' );
const characterKept = saveCrew( characterOnly, '' );
assert.equal( characterKept[ 0 ].character, 'Host' );
assert.equal( Object.hasOwn( characterKept[ 0 ], 'role' ), false );
const characterShown = saveCrew( characterOnly, 'Host' );
assert.equal( characterShown[ 0 ].character, 'Host' );
assert.equal( characterShown[ 0 ].role, 'Host' );

const labeled = [ { id: 7, role: 'Writer', job: 'Director', character: 'Narrator', category: 'Writing' } ];
const emptied = saveCrew( labeled, '   ' );
assert.equal( emptied[ 0 ].role, 'Writer' );
assert.equal( emptied[ 0 ].job, 'Director' );
assert.equal( emptied[ 0 ].character, 'Narrator' );
assert.equal( emptied[ 0 ].category, 'Writing' );

const castStored = [ { id: 2, character: 'Bea', position: 1 }, { id: 1, character: 'Ada', position: 0 } ];
const castSubmitted = [
	{ id: '1', character: 'Ada' },
	{ id: '9', character: 'New' },
];
const castSaved = mergePeopleList( castStored, castSubmitted, 'cast' );
assert.deepEqual( castSaved.map( ( row ) => row.id ), [ 1, '9' ] );
assert.equal( castSaved[ 0 ].character, 'Ada' );
assert.equal( castSaved[ 0 ].position, 0 );
assert.equal( Object.hasOwn( castSaved[ 1 ], 'position' ), false );

const castCleared = mergePeopleList( castStored, [ { id: '2', character: '' } ], 'cast' );
assert.equal( castCleared[ 0 ].character, 'Bea' );
assert.equal( castCleared.length, 1 );

const form = mergePeopleFormData(
	{
		_cast: '',
		_crew: '',
		_seasons: [ { name: 'Season 1' } ],
	},
	{ cast: castStored, crew: jobOnly }
);
assert.equal( form._cast, '' );
assert.equal( form._crew, '' );
assert.equal( form._seasons[ 0 ].name, 'Season 1' );

const duplicates = mergePeopleList(
	[ { id: 3, job: 'First' }, { id: 3, job: 'Second' } ],
	[
		{ id: '3', role: 'First', category: '' },
		{ id: '3', role: 'Second', category: '' },
	],
	'crew'
);
assert.deepEqual( duplicates.map( ( row ) => row.job ), [ 'First', 'Second' ] );

console.log( 'all people-label assertions passed' );
