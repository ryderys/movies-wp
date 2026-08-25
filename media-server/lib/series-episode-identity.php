<?php
/**
 * Dedicated SxxExx / EPxx / bare E## / Episode N identity parser for Series filenames.
 *
 * Runs before the generic filename parser. EPxx, bare E##, and Episode N remain
 * seasonless until the WordPress layer resolves them against authoritative episode metadata.
 *
 * @package movies-wp
 */

declare(strict_types=1);

const MEDIA_SERIES_EPISODE_IDENTITY_PATTERN = '/(?<![A-Za-z0-9])S([0-9]{1,3})E([0-9]{1,4})(?![0-9])/i';
const MEDIA_SERIES_EPISODE_ONLY_PATTERN = '/(?<![A-Za-z0-9])EP([0-9]{1,4})(?![A-Za-z0-9])/i';
/** Bare E## (e.g. .E04.); lookbehind prevents matching the E inside S01E04. */
const MEDIA_SERIES_BARE_E_PATTERN = '/(?<![A-Za-z0-9])E([0-9]{1,4})(?![0-9])/i';
const MEDIA_SERIES_EPISODE_WORD_PATTERN = '/(?<![A-Za-z0-9])Episode\s+([0-9]{1,4})(?![A-Za-z0-9])/i';

/**
 * Extract season/episode identity from a filename.
 *
 * @param string $filename Basename or path; only the basename is inspected.
 * @return array{
 *   ok: true,
 *   identity_type: 'season_episode'|'episode_only',
 *   season_number: string|null,
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

	$season_match_count = preg_match_all( MEDIA_SERIES_EPISODE_IDENTITY_PATTERN, $basename, $matches, PREG_SET_ORDER );
	preg_match_all( MEDIA_SERIES_EPISODE_ONLY_PATTERN, $basename, $ep_matches, PREG_SET_ORDER );
	preg_match_all( MEDIA_SERIES_BARE_E_PATTERN, $basename, $bare_e_matches, PREG_SET_ORDER );
	preg_match_all( MEDIA_SERIES_EPISODE_WORD_PATTERN, $basename, $word_matches, PREG_SET_ORDER );
	$episode_only_matches = array_merge( $ep_matches, $bare_e_matches, $word_matches );
	$episode_match_count  = count( $episode_only_matches );

	if ( $season_match_count > 0 && $episode_match_count > 0 ) {
		return array(
			'ok'      => false,
			'code'    => 'conflicting_episode_identity',
			'message' => 'Filename contains both SxxExx and episode-only identities.',
		);
	}

	if ( $season_match_count === 0 && $episode_match_count === 0 ) {
		if ( media_series_episode_identity_has_malformed_token( $basename ) ) {
			return array(
				'ok'      => false,
				'code'    => 'malformed_episode_identity',
				'message' => 'Filename contains a malformed S/E or EP token.',
			);
		}

		return array(
			'ok'      => false,
			'code'    => 'missing_episode_identity',
			'message' => 'No SxxExx, EPxx, E##, or Episode N identity found in filename.',
		);
	}

	if ( $episode_match_count > 0 ) {
		return media_parse_series_episode_only_identity( $basename, $episode_only_matches );
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
		'identity_type'      => 'season_episode',
		'season_number'      => $identity['season_number'],
		'episode_number'     => $identity['episode_number'],
		'token'              => $tokens[0],
		'sanitized_filename' => $sanitized,
		'warnings'           => $warnings,
	);
}

/**
 * @param list<array<int|string>> $matches
 * @return array<string, mixed>
 */
function media_parse_series_episode_only_identity( string $basename, array $matches ): array {
	$identities = array();
	$tokens     = array();

	foreach ( $matches as $match ) {
		$episode = media_series_episode_canonical_number( (string) $match[1] );
		if ( $episode === '0' ) {
			return array(
				'ok'      => false,
				'code'    => 'malformed_episode_identity',
				'message' => 'Episode number 0 is invalid.',
			);
		}
		$identities[ $episode ] = $episode;
		$tokens[] = strtoupper( (string) $match[0] );
	}

	$unique = array_values( $identities );
	if ( count( $unique ) > 1 ) {
		return array(
			'ok'      => false,
			'code'    => 'conflicting_episode_identity',
			'message' => 'Filename contains conflicting EPxx identities.',
		);
	}

	$warnings = array();
	if ( count( $matches ) > 1 ) {
		$warnings[] = array(
			'code'    => 'duplicate_episode_identity',
			'message' => 'Duplicate identical EPxx identity token in filename.',
		);
	}

	return array(
		'ok'                 => true,
		'identity_type'      => 'episode_only',
		'season_number'      => null,
		'episode_number'     => $unique[0],
		'token'              => $tokens[0],
		'sanitized_filename' => media_series_episode_sanitize_filename( $basename, $matches ),
		'warnings'           => $warnings,
	);
}

function media_series_episode_canonical_number( string $digits ): string {
	$digits = ltrim( $digits, '0' );
	return $digits === '' ? '0' : $digits;
}

function media_series_episode_identity_has_malformed_token( string $basename ): bool {
	return (bool) preg_match( '/(?<![A-Za-z0-9])(?:S(?:[0-9]{0,3}E(?![0-9])|E[0-9]{1,4}|xE[0-9]{1,4})|EP(?!isode)(?:$|[^0-9]|[0-9]{5,}))/i', $basename );
}

/**
 * Internal grouping key. Episode-only identities deliberately omit a season.
 *
 * @param array<string, mixed> $episode
 */
function media_series_episode_identity_key( array $episode ): ?string {
	$episode_number = isset( $episode['episode_number'] ) ? (string) $episode['episode_number'] : '';
	if ( ! preg_match( '/^[1-9][0-9]*$/', $episode_number ) ) {
		return null;
	}

	$season_number = $episode['season_number'] ?? null;
	if ( null === $season_number || '' === (string) $season_number ) {
		return ( $episode['identity_type'] ?? '' ) === 'episode_only' ? 'EP:' . $episode_number : null;
	}
	$season_number = (string) $season_number;
	if ( ! preg_match( '/^[0-9]+$/', $season_number ) ) {
		return null;
	}
	return $season_number . ':' . $episode_number;
}

/**
 * Remove matched SxxExx/EPxx tokens so the generic parser does not treat them as title tokens.
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
