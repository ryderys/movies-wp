<?php
/**
 * Associate Series subtitle files to explicit or seasonless episode identity.
 *
 * Pure grouping helper for scan output. Does not mutate filesystem state.
 *
 * @package movies-wp
 */

declare(strict_types=1);

require_once __DIR__ . '/series-episode-identity.php';

/**
 * Group subtitle files under episode keys and collect association warnings.
 *
 * @param list<array<string, mixed>> $files Enriched scan files including episode identity.
 * @return array{
 *   subtitles_by_episode: array<string, list<array<string, mixed>>>,
 *   unassociated_subtitles: list<string>,
 *   warnings: list<array{code: string, message: string, name?: string}>
 * }
 */
function media_associate_series_subtitles( array $files ): array {
	$by_episode = array();
	$unassociated = array();
	$warnings     = array();
	$seen_paths   = array();

	foreach ( $files as $file ) {
		if ( ! is_array( $file ) || ( $file['kind'] ?? '' ) !== 'subtitle' ) {
			continue;
		}

		$path = isset( $file['media_path'] ) ? (string) $file['media_path'] : '';
		if ( $path === '' ) {
			continue;
		}

		$normalized = media_series_subtitle_normalize_path( $path );
		if ( isset( $seen_paths[ $normalized ] ) ) {
			$warnings[] = array(
				'code'    => 'duplicate_media_path',
				'message' => 'Duplicate subtitle media path detected.',
				'name'    => basename( $path ),
			);
			continue;
		}
		$seen_paths[ $normalized ] = true;

		$episode_key = media_series_subtitle_episode_key( $file );
		if ( $episode_key === null ) {
			$unassociated[] = $path;
			$warnings[] = array(
				'code'    => 'subtitle_association_conflict',
				'message' => 'Subtitle could not be associated to an episode identity.',
				'name'    => basename( $path ),
			);
			continue;
		}

		if ( ! isset( $by_episode[ $episode_key ] ) ) {
			$by_episode[ $episode_key ] = array();
		}
		$by_episode[ $episode_key ][] = $file;
	}

	return array(
		'subtitles_by_episode'   => $by_episode,
		'unassociated_subtitles' => $unassociated,
		'warnings'               => $warnings,
	);
}

/**
 * @param array<string, mixed> $file
 */
function media_series_subtitle_episode_key( array $file ): ?string {
	if ( ! isset( $file['episode'] ) || ! is_array( $file['episode'] ) ) {
		return null;
	}
	return media_series_episode_identity_key( $file['episode'] );
}

function media_series_subtitle_normalize_path( string $path ): string {
	$path = str_replace( '\\', '/', trim( $path ) );
	$path = preg_replace( '#/+#', '/', $path ) ?? $path;
	return trim( $path, '/' );
}

function media_series_subtitle_format_label( string $srclang ): string {
	$srclang = strtolower( trim( $srclang ) );
	return $srclang !== '' ? strtoupper( $srclang ) : 'SUB';
}

function media_series_subtitle_format_from_extension( string $extension ): string {
	$map = array(
		'srt' => 'SRT',
		'vtt' => 'VTT',
		'ass' => 'ASS',
		'ssa' => 'SSA',
		'sub' => 'SUB',
	);
	$key = strtolower( $extension );
	return $map[ $key ] ?? strtoupper( $key );
}

function media_series_subtitle_playback_supported( string $extension ): bool {
	return in_array( strtolower( $extension ), array( 'srt', 'vtt' ), true );
}
