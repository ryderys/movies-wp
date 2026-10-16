/**
 * Put stored crew labels back onto movie and TV-show edit saves.
 *
 * TriggerCrewData() rebuilds each row as id, role, and category. Automation
 * stores the label in job. Matching the submitted person id to the stored
 * row keeps job, and keeps role or character when the form field is empty.
 *
 * @package streamit-child
 */
( function ( root ) {
	'use strict';

	function labelText( value ) {
		if ( value === null || value === undefined ) {
			return '';
		}
		if ( typeof value !== 'string' && typeof value !== 'number' ) {
			return '';
		}
		return String( value ).trim();
	}

	function labelMeaningful( value ) {
		return labelText( value ) !== '';
	}

	function parsePeople( peopleJson ) {
		if ( typeof peopleJson !== 'string' || peopleJson === '' ) {
			return [];
		}

		try {
			var parsed = JSON.parse( peopleJson );
			return Array.isArray( parsed ) ? parsed : [];
		} catch ( error ) {
			return [];
		}
	}

	/**
	 * Value to show in an empty crew role or cast character input.
	 *
	 * @param {string} current Current input value.
	 * @param {object} row Stored person row.
	 * @param {string[]} keys Preference order.
	 * @return {string}
	 */
	function prefillLabel( current, row, keys ) {
		if ( labelMeaningful( current ) || ! row || typeof row !== 'object' ) {
			return current;
		}

		for ( var index = 0; index < keys.length; index++ ) {
			if ( labelMeaningful( row[ keys[ index ] ] ) ) {
				return labelText( row[ keys[ index ] ] );
			}
		}

		return current;
	}

	function cloneRow( row ) {
		return JSON.parse( JSON.stringify( row ) );
	}

	/**
	 * Merge stored cast/crew onto the rows the edit form is about to save.
	 *
	 * Submitted order is the saved order. A person who is no longer in the
	 * form stays removed. A new person stays as submitted.
	 *
	 * @param {Array<object>} storedRows Rows loaded with the edit screen.
	 * @param {Array<object>|string} submittedRows TriggercastData/TriggerCrewData result.
	 * @param {string} kind `cast` or `crew`.
	 * @return {Array<object>|string}
	 */
	function mergePeopleList( storedRows, submittedRows, kind ) {
		if ( ! Array.isArray( submittedRows ) ) {
			return submittedRows;
		}

		var pools = Object.create( null );
		( Array.isArray( storedRows ) ? storedRows : [] ).forEach( function ( row ) {
			if ( ! row || typeof row !== 'object' || row.id === undefined || row.id === null || row.id === '' ) {
				return;
			}
			var key = String( row.id );
			if ( ! pools[ key ] ) {
				pools[ key ] = [];
			}
			pools[ key ].push( row );
		} );

		return submittedRows.map( function ( submitted ) {
			if ( ! submitted || typeof submitted !== 'object' ) {
				return submitted;
			}

			var key = submitted.id === undefined || submitted.id === null || submitted.id === '' ? '' : String( submitted.id );
			var stored = key && pools[ key ] && pools[ key ].length ? pools[ key ].shift() : null;
			if ( ! stored ) {
				return submitted;
			}

			var merged = cloneRow( stored );
			if ( kind === 'crew' ) {
				if ( labelMeaningful( submitted.role ) ) {
					merged.role = submitted.role;
				}
				if ( labelMeaningful( submitted.category ) ) {
					merged.category = submitted.category;
				}
			} else if ( labelMeaningful( submitted.character ) ) {
				merged.character = submitted.character;
			}

			return merged;
		} );
	}

	function mergePeopleFormData( formData, stored ) {
		if ( ! formData || typeof formData !== 'object' ) {
			return formData;
		}

		var people = stored || {};
		formData._cast = mergePeopleList( people.cast, formData._cast, 'cast' );
		formData._crew = mergePeopleList( people.crew, formData._crew, 'crew' );
		return formData;
	}

	root.streamitChildPreservePeopleLabels = {
		parsePeople: parsePeople,
		prefillLabel: prefillLabel,
		mergePeopleList: mergePeopleList,
		mergePeopleFormData: mergePeopleFormData,
	};

	var $ = typeof jQuery !== 'undefined' ? jQuery : null;
	if ( ! $ ) {
		return;
	}

	var config = root.streamitChildPeople || {};
	var storedCast = parsePeople( config.castJson );
	var storedCrew = parsePeople( config.crewJson );

	function takeStored( pools, personId ) {
		var key = personId === undefined || personId === null ? '' : String( personId );
		if ( ! key || ! pools[ key ] || ! pools[ key ].length ) {
			return null;
		}
		return pools[ key ].shift();
	}

	function poolsFrom( rows ) {
		var pools = Object.create( null );
		rows.forEach( function ( row ) {
			if ( ! row || typeof row !== 'object' || row.id === undefined || row.id === null || row.id === '' ) {
				return;
			}
			var key = String( row.id );
			if ( ! pools[ key ] ) {
				pools[ key ] = [];
			}
			pools[ key ].push( row );
		} );
		return pools;
	}

	function prefillBoxes( boxSelector, idSelector, inputSelector, rows, keys ) {
		var pools = poolsFrom( rows );
		$( boxSelector ).each( function () {
			var box = $( this );
			var stored = takeStored( pools, box.find( idSelector ).val() );
			if ( ! stored ) {
				return;
			}
			var input = box.find( inputSelector );
			input.val( prefillLabel( input.val(), stored, keys ) );
		} );
	}

	function prefillPeopleFields() {
		prefillBoxes(
			'.streamit_cast',
			'input[id="streamit_cast_person_id"]',
			'input[id="cast_character_name"]',
			storedCast,
			[ 'character', 'role', 'job' ]
		);
		prefillBoxes(
			'.streamit_crew',
			'input[id="streamit_crew_person_id"]',
			'input[id="crew_role_name"]',
			storedCrew,
			[ 'role', 'character', 'job' ]
		);
	}

	function registerFormDataFilters() {
		if ( ! root.wp || ! root.wp.hooks ) {
			return;
		}

		var filter = function ( formData ) {
			return mergePeopleFormData( formData, {
				cast: storedCast,
				crew: storedCrew,
			} );
		};

		root.wp.hooks.addFilter( 'movie_form_data', 'streamit-child/people-labels', filter );
		root.wp.hooks.addFilter( 'tvshow_form_data', 'streamit-child/people-labels', filter );
	}

	$( function () {
		prefillPeopleFields();
		registerFormDataFilters();
	} );
} )( typeof window !== 'undefined' ? window : globalThis );
