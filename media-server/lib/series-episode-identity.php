<?php
/**
 * Dedicated SxxExx episode identity parser for Series filenames.
 *
 * Runs before the generic filename parser. Season numbers are canonical digit strings.
 *
 * @package movies-wp
 */

declare(strict_types=1);

const MEDIA_SERIES_EPISODE_IDENTITY_PATTERN = '/(?<![A-Za-z0-9])S([0-9]{1,3})E([0-9]{1,4})(?![0-9])/i';

/**
 * Extract season/episode identity from a filename.
 *
 * @param string $filename Basename or path; only the basename is inspected.
 * @return array{
 *   ok: true,
 *   season_number: string,
 *   episode_number: string,
 *   token: string,
 *   sanitized_filename: string,
 *   warnings: list<array{code: string, message: string}>
 * }|array{ok: false, code: string, message: string, warnings?: list<array{code: string, message: string}>}
 */
function media_parse_series_episode_identity( string $filename ): array {
	$basename = basename( str_replace( '\\', '/', $filename ) );
	if ( $basename === '' ) {
		return array(
			'ok'      => false,
			'code'    => 'missing_episode_identity',
			'message' => 'Filename is empty.',
		);
	}

	if ( preg_match_all( MEDIA_SERIES_EPISODE_IDENTITY_PATTERN, $basename, $matches, PREG_SET_ORDER ) === 0 ) {
		if ( media_series_episode_identity_has_malformed_token( $basename ) ) {
			return array(
				'ok'      => false,
				'code'    => 'malformed_episode_identity',
				'message' => 'Filename contains a malformed S/E token.',
			);
		}

		return array(
			'ok'      => false,
			'code'    => 'missing_episode_identity',
			'message' => 'No SxxExx episode identity found in filename.',
		);
	}

	$identities = array();
	$tokens     = array();

	foreach ( $matches as $match ) {
		$season  = media_series_episode_canonical_number( $match[1] );
		$episode = media_series_episode_canonical_number( $match[2] );

		if ( $episode === '0' ) {
			return array(
				'ok'      => false,
				'code'    => 'malformed_episode_identity',
				'message' => 'Episode number 0 is invalid.',
			);
		}

		$key              = $season . ':' . $episode;
		$identities[ $key ] = array(
			'season_number'  => $season,
			'episode_number' => $episode,
		);
		$tokens[] = strtoupper( $match[0] );
	}

	$unique = array_values( $identities );
	if ( count( $unique ) > 1 ) {
		return array(
			'ok'      => false,
			'code'    => 'conflicting_episode_identity',
			'message' => 'Filename contains conflicting SxxExx identities.',
		);
	}

	$warnings = array();
	if ( count( $matches ) > 1 ) {
		$warnings[] = array(
			'code'    => 'duplicate_episode_identity',
			'message' => 'Duplicate identical SxxExx identity token in filename.',
		);
	}

	$identity = $unique[0];
	$sanitized = media_series_episode_sanitize_filename( $basename, $matches );

	return array(
		'ok'                 => true,
		'season_number'      => $identity['season_number'],
		'episode_number'     => $identity['episode_number'],
		'token'              => $tokens[0],
		'sanitized_filename' => $sanitized,
		'warnings'           => $warnings,
	);
}

function media_series_episode_canonical_number( string $digits ): string {
	$digits = ltrim( $digits, '0' );
	return $digits === '' ? '0' : $digits;
}

function media_series_episode_identity_has_malformed_token( string $basename ): bool {
	return (bool) preg_match( '/(?<![A-Za-z0-9])S(?:[0-9]{0,3}E(?![0-9])|E[0-9]{1,4}|xE[0-9]{1,4})/i', $basename );
}

/**
 * Remove matched SxxExx tokens so the generic parser does not treat them as title tokens.
 *
 * Works on the filename stem only so the extension is not duplicated, and preserves
 * hyphens inside known tokens such as WEB-DL.
 *
 * @param list<array<int|string>> $matches
 */
function media_series_episode_sanitize_filename( string $basename, array $matches ): string {
	$extension = pathinfo( $basename, PATHINFO_EXTENSION );
	$stem      = pathinfo( $basename, PATHINFO_FILENAME );
	if ( $stem === '' ) {
		return $basename;
	}

	foreach ( $matches as $match ) {
		$token = (string) ( $match[0] ?? '' );
		if ( $token === '' ) {
			continue;
		}
		$stem = str_ireplace( $token, ' ', $stem );
	}

	$stem = preg_replace( '/[\s_]+/', '.', $stem ) ?? $stem;
	$stem = preg_replace( '/\.+/', '.', $stem ) ?? $stem;
	$stem = trim( $stem, '.' );
	if ( $stem === '' ) {
		return $basename;
	}

	return $extension !== '' ? $stem . '.' . $extension : $stem;
}
