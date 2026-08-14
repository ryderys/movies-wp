<?php
/**
 * Pure filename parser for media properties.
 *
 * Independent of HTTP, WordPress, Streamit, the directory scanner, and ffprobe.
 * Uses only the basename — directory segments (country/year) are ignored.
 * Does not guess: unrecognized tokens stay unclassified.
 *
 * @package movies-wp
 */

declare(strict_types=1);

/** @var list<string> */
const MEDIA_PARSE_VIDEO_EXTENSIONS = array( 'mkv', 'mp4', 'avi' );

/** @var list<string> */
const MEDIA_PARSE_SUBTITLE_EXTENSIONS = array( 'srt', 'ass', 'ssa', 'vtt', 'sub' );

/**
 * Parse a single filename or path into structured media properties.
 *
 * @param string $path Filename or path. Only the basename is inspected.
 * @return array<string, mixed>
 */
function media_parse_filename( string $path ): array {
	$normalized = media_parse_normalize( $path );
	$result     = media_parse_empty_result( $normalized );

	if ( $normalized['stem'] === '' ) {
		$result['warnings'][] = media_parse_warning( 'empty_name', 'Filename is empty.' );
		return $result;
	}

	$work = media_parse_protect_compounds( $normalized['stem'] );
	$work = media_parse_extract_release_group( $work, $result );
	$work = media_parse_tokenize( $work['stem'] );

	media_parse_detect_quality( $work['tokens'], $result );
	media_parse_detect_source( $work['tokens'], $result );
	media_parse_detect_provider( $work['tokens'], $result );
	media_parse_detect_video_codec( $work['tokens'], $result );
	media_parse_detect_audio_codec( $work['tokens'], $result );
	media_parse_detect_audio_language( $work['tokens'], $result );
	media_parse_detect_encoder( $work['tokens'], $result );
	media_parse_detect_year_hint( $work['tokens'], $result );

	if ( $result['kind'] === 'subtitle' ) {
		media_parse_detect_subtitle_language( $work['tokens'], $result );
	}

	media_parse_collect_leftovers( $work['tokens'], $result );
	media_parse_finalize_warnings( $result );

	return $result;
}

/**
 * @return array{basename: string, stem: string, extension: string, kind: string}
 */
function media_parse_normalize( string $path ): array {
	$path = str_replace( '\\', '/', $path );
	$path = rtrim( $path, '/' );
	$base = basename( $path );

	$extension = strtolower( (string) pathinfo( $base, PATHINFO_EXTENSION ) );
	$stem      = (string) pathinfo( $base, PATHINFO_FILENAME );

	if ( in_array( $extension, MEDIA_PARSE_VIDEO_EXTENSIONS, true ) ) {
		$kind = 'video';
	} elseif ( in_array( $extension, MEDIA_PARSE_SUBTITLE_EXTENSIONS, true ) ) {
		$kind = 'subtitle';
	} else {
		$kind = 'other';
	}

	return array(
		'basename'  => $base,
		'stem'      => $stem,
		'extension' => $extension,
		'kind'      => $kind,
	);
}

/**
 * @param array{basename: string, stem: string, extension: string, kind: string} $normalized
 * @return array<string, mixed>
 */
function media_parse_empty_result( array $normalized ): array {
	return array(
		'kind'                 => $normalized['kind'],
		'input'                => $normalized['basename'],
		'format'               => $normalized['extension'] !== '' ? $normalized['extension'] : null,
		'title_hint'           => null,
		'year_hint'            => null,
		'quality'              => null,
		'source_type'          => null,
		'provider'             => null,
		'video_codec'          => null,
		'audio_codec'          => null,
		'release_group'        => null,
		'group_hint'           => null,
		'encoder'              => null,
		'audio_languages'      => array(),
		'audio_label'          => null,
		'audio_confidence'     => 'unknown',
		'subtitle_lang'        => null,
		'subtitle_confidence'  => 'unknown',
		'unclassified'         => array(),
		'warnings'             => array(),
		'_consumed'            => array(),
	);
}

/**
 * Replace known multi-token compounds so later splits do not tear them apart.
 *
 * @return array{stem: string}
 */
function media_parse_protect_compounds( string $stem ): array {
	foreach ( media_parse_compound_map() as $pattern => $placeholder ) {
		$stem = preg_replace( $pattern, $placeholder, $stem ) ?? $stem;
	}

	return array( 'stem' => $stem );
}

/**
 * Longer / more specific patterns first.
 *
 * @return array<string, string> regex => placeholder token
 */
function media_parse_compound_map(): array {
	return array(
		'/\bdubbed[\.\s_\-]?persian\b/i' => 'PERSIANDUB',
		'/\bpersian[\.\s_\-]?dub\b/i'    => 'PERSIANDUB',
		'/\bfarsi[\.\s_\-]?dub\b/i'      => 'PERSIANDUB',
		'/\bfa[\.\s_\-]?dub\b/i'         => 'PERSIANDUB',
		'/\bdual[\.\s_\-]?audio\b/i'     => 'DUALAUDIO',
		'/\btrue[\.\s_\-]?french\b/i'    => 'TRUEFRENCH',
		'/\bfa[\-\.]ir\b/i'              => 'FAIR',
		'/\baac[\.\s]?2[\.\s]?0\b/i'     => 'AAC20',
		'/\bdd[p\+][\.\s]?5[\.\s]?1\b/i' => 'DDP51',
		'/\bdd[\.\s]?5[\.\s]?1\b/i'      => 'DD51',
		'/\bdts[\.\s_\-]?hd\b/i'         => 'DTSHD',
		'/\bh[\.\s]?265\b/i'             => 'H265',
		'/\bh[\.\s]?264\b/i'             => 'H264',
		'/\bweb[\.\s_\-]?dl\b/i'         => 'WEBDL',
		'/\bweb[\.\s_\-]?rip\b/i'        => 'WEBRIP',
		'/\bblu[\.\s_\-]?ray\b/i'        => 'BLURAY',
		'/\bdvd[\.\s_\-]?rip\b/i'        => 'DVDRIP',
		'/\bbd[\.\s_\-]?rip\b/i'         => 'BDRIP',
		'/\bbr[\.\s_\-]?rip\b/i'         => 'BRRIP',
		'/\bhd[\.\s_\-]?rip\b/i'         => 'HDRIP',
		'/\bhd[\.\s_\-]?tv\b/i'          => 'HDTV',
	);
}

/**
 * Scene release group is the trailing -Group after compounds are protected.
 * Never treats -DL / -Rip as a group.
 *
 * @param array{stem: string} $work
 * @param array<string, mixed> $result
 * @return array{stem: string}
 */
function media_parse_extract_release_group( array $work, array &$result ): array {
	$stem = $work['stem'];

	if ( preg_match( '/-([A-Za-z][A-Za-z0-9]{1,30})$/', $stem, $match ) !== 1 ) {
		return $work;
	}

	$group = $match[1];
	$block = array( 'DL', 'RIP', 'RAY', 'TV', 'HD', 'WEB' );
	if ( in_array( strtoupper( $group ), $block, true ) ) {
		return $work;
	}

	$result['release_group'] = $group;
	$stem                    = substr( $stem, 0, -strlen( $match[0] ) );

	return array( 'stem' => $stem );
}

/**
 * @return array{tokens: list<array{raw: string, key: string}>}
 */
function media_parse_tokenize( string $stem ): array {
	$parts  = preg_split( '/[.\s_]+/', $stem ) ?: array();
	$tokens = array();

	foreach ( $parts as $part ) {
		if ( $part === '' ) {
			continue;
		}
		$tokens[] = array(
			'raw' => $part,
			'key' => strtolower( $part ),
		);
	}

	return array( 'tokens' => $tokens );
}

/**
 * @param list<array{raw: string, key: string}> $tokens
 * @param array<string, mixed> $result
 */
function media_parse_detect_quality( array $tokens, array &$result ): void {
	$rank = array(
		'2160p' => 50,
		'4k'    => 50,
		'uhd'   => 50,
		'2160'  => 50,
		'1080p' => 40,
		'fhd'   => 40,
		'fullhd'=> 40,
		'1080'  => 40,
		'720p'  => 30,
		'720'   => 30,
		'480p'  => 20,
		'480'   => 20,
		'360p'  => 10,
		'360'   => 10,
		'hd'    => 5,
	);

	$canonical = array(
		'2160p' => '2160p',
		'4k'    => '2160p',
		'uhd'   => '2160p',
		'2160'  => '2160p',
		'1080p' => '1080p',
		'fhd'   => '1080p',
		'fullhd'=> '1080p',
		'1080'  => '1080p',
		'720p'  => '720p',
		'720'   => '720p',
		'hd'    => '720p',
		'480p'  => '480p',
		'480'   => '480p',
		'360p'  => '360p',
		'360'   => '360p',
	);

	$best_key  = null;
	$best_rank = -1;
	$saw_hd    = false;

	foreach ( $tokens as $i => $token ) {
		$key = $token['key'];
		if ( ! isset( $rank[ $key ] ) ) {
			continue;
		}
		if ( 'hd' === $key ) {
			$saw_hd = true;
		}
		if ( $rank[ $key ] > $best_rank ) {
			$best_rank = $rank[ $key ];
			$best_key  = $key;
		}
		$result['_consumed'][ $i ] = 'quality';
	}

	if ( null === $best_key ) {
		return;
	}

	$result['quality'] = $canonical[ $best_key ];

	if ( $saw_hd && $best_key === 'hd' ) {
		$result['warnings'][] = media_parse_warning(
			'ambiguous_quality_hd',
			'HD is ambiguous; normalized to 720p.'
		);
	}
}

/**
 * @param list<array{raw: string, key: string}> $tokens
 * @param array<string, mixed> $result
 */
function media_parse_detect_source( array $tokens, array &$result ): void {
	$map = array(
		'webdl'  => 'WEB-DL',
		'webrip' => 'WEBRip',
		'bluray' => 'BluRay',
		'bdrip'  => 'BDRip',
		'brrip'  => 'BRRip',
		'hdtv'   => 'HDTV',
		'hdrip'  => 'HDRip',
		'dvdrip' => 'DVDRip',
	);

	foreach ( $tokens as $i => $token ) {
		if ( ! isset( $map[ $token['key'] ] ) ) {
			continue;
		}
		$result['source_type']       = $map[ $token['key'] ];
		$result['_consumed'][ $i ]   = 'source';
		return;
	}
}

/**
 * Conservative streaming-provider allowlist. Not release groups.
 *
 * @param list<array{raw: string, key: string}> $tokens
 * @param array<string, mixed> $result
 */
function media_parse_detect_provider( array $tokens, array &$result ): void {
	$map = array(
		'nf'      => 'NF',
		'netflix' => 'NF',
		'wavve'   => 'WAVVE',
		'amzn'    => 'AMZN',
		'amazon'  => 'AMZN',
		'dsnp'    => 'DSNP',
		'disney'  => 'DSNP',
		'atvp'    => 'ATVP',
		'hulu'    => 'HULU',
		'hmax'    => 'HMAX',
		'pcok'    => 'PCOK',
		'itunes'  => 'iTunes',
	);

	foreach ( $tokens as $i => $token ) {
		if ( ! isset( $map[ $token['key'] ] ) ) {
			continue;
		}
		$result['provider']        = $map[ $token['key'] ];
		$result['_consumed'][ $i ] = 'provider';
		return;
	}
}

/**
 * @param list<array{raw: string, key: string}> $tokens
 * @param array<string, mixed> $result
 */
function media_parse_detect_video_codec( array $tokens, array &$result ): void {
	$map = array(
		'h264' => 'H.264',
		'x264' => 'H.264',
		'avc'  => 'H.264',
		'h265' => 'H.265',
		'x265' => 'H.265',
		'hevc' => 'H.265',
		'av1'  => 'AV1',
		'xvid' => 'XviD',
	);

	foreach ( $tokens as $i => $token ) {
		if ( ! isset( $map[ $token['key'] ] ) ) {
			continue;
		}
		$result['video_codec']     = $map[ $token['key'] ];
		$result['_consumed'][ $i ] = 'video_codec';
		return;
	}
}

/**
 * @param list<array{raw: string, key: string}> $tokens
 * @param array<string, mixed> $result
 */
function media_parse_detect_audio_codec( array $tokens, array &$result ): void {
	$map = array(
		'aac20'  => 'AAC2.0',
		'aac'    => 'AAC',
		'ddp51'  => 'DDP5.1',
		'dd51'   => 'DD5.1',
		'dtshd'  => 'DTS-HD',
		'dts'    => 'DTS',
		'ac3'    => 'AC3',
		'eac3'   => 'EAC3',
		'truehd' => 'TrueHD',
		'atmos'  => 'Atmos',
		'flac'   => 'FLAC',
		'opus'   => 'Opus',
	);

	foreach ( $tokens as $i => $token ) {
		if ( ! isset( $map[ $token['key'] ] ) ) {
			continue;
		}
		$result['audio_codec']     = $map[ $token['key'] ];
		$result['_consumed'][ $i ] = 'audio_codec';
		return;
	}
}

/**
 * Explicit audio-language tokens only. Never inferred from country or subtitles.
 *
 * @param list<array{raw: string, key: string}> $tokens
 * @param array<string, mixed> $result
 */
function media_parse_detect_audio_language( array $tokens, array &$result ): void {
	foreach ( $tokens as $i => $token ) {
		$hit = media_parse_audio_token( $token['key'] );
		if ( null === $hit ) {
			continue;
		}

		$result['audio_languages']  = $hit['languages'];
		$result['audio_label']      = $hit['label'];
		$result['audio_confidence'] = 'high';
		$result['_consumed'][ $i ]  = 'audio';
		return;
	}
}

/**
 * @return array{languages: list<string>, label: string}|null
 */
function media_parse_audio_token( string $key ): ?array {
	$map = array(
		'dual'        => array( 'languages' => array(), 'label' => 'Dual Audio' ),
		'dualaudio'   => array( 'languages' => array(), 'label' => 'Dual Audio' ),
		'multi'       => array( 'languages' => array(), 'label' => 'Multi' ),
		'multiaudio'  => array( 'languages' => array(), 'label' => 'Multi' ),
		'persiandub'  => array( 'languages' => array( 'fa' ), 'label' => 'Persian Dub' ),
		'persian'     => array( 'languages' => array( 'fa' ), 'label' => 'Persian' ),
		'farsi'       => array( 'languages' => array( 'fa' ), 'label' => 'Persian' ),
		'english'     => array( 'languages' => array( 'en' ), 'label' => 'English' ),
		'eng'         => array( 'languages' => array( 'en' ), 'label' => 'English' ),
		'hindi'       => array( 'languages' => array( 'hi' ), 'label' => 'Hindi' ),
		'hin'         => array( 'languages' => array( 'hi' ), 'label' => 'Hindi' ),
		'french'      => array( 'languages' => array( 'fr' ), 'label' => 'French' ),
		'truefrench'  => array( 'languages' => array( 'fr' ), 'label' => 'French' ),
		'korean'      => array( 'languages' => array( 'ko' ), 'label' => 'Korean' ),
		'kor'         => array( 'languages' => array( 'ko' ), 'label' => 'Korean' ),
		'japanese'    => array( 'languages' => array( 'ja' ), 'label' => 'Japanese' ),
		'jpn'         => array( 'languages' => array( 'ja' ), 'label' => 'Japanese' ),
		'chinese'     => array( 'languages' => array( 'zh' ), 'label' => 'Chinese' ),
		'mandarin'    => array( 'languages' => array( 'zh' ), 'label' => 'Chinese' ),
		'spanish'     => array( 'languages' => array( 'es' ), 'label' => 'Spanish' ),
		'german'      => array( 'languages' => array( 'de' ), 'label' => 'German' ),
		'arabic'      => array( 'languages' => array( 'ar' ), 'label' => 'Arabic' ),
		'turkish'     => array( 'languages' => array( 'tr' ), 'label' => 'Turkish' ),
		'russian'     => array( 'languages' => array( 'ru' ), 'label' => 'Russian' ),
	);

	return $map[ $key ] ?? null;
}

/**
 * Known encoder/group names only. Short leftovers like SS are not encoders.
 *
 * @param list<array{raw: string, key: string}> $tokens
 * @param array<string, mixed> $result
 */
function media_parse_detect_encoder( array $tokens, array &$result ): void {
	$map = array(
		'yify'  => 'YIFY',
		'yts'   => 'YTS',
		'rarbg' => 'RARBG',
	);

	foreach ( $tokens as $i => $token ) {
		if ( ! isset( $map[ $token['key'] ] ) ) {
			continue;
		}
		$result['encoder']         = $map[ $token['key'] ];
		$result['_consumed'][ $i ] = 'encoder';
		return;
	}
}

/**
 * @param list<array{raw: string, key: string}> $tokens
 * @param array<string, mixed> $result
 */
function media_parse_detect_year_hint( array $tokens, array &$result ): void {
	foreach ( $tokens as $i => $token ) {
		if ( preg_match( '/^(19|20)\d{2}$/', $token['key'] ) !== 1 ) {
			continue;
		}
		$result['year_hint']       = (int) $token['key'];
		$result['_consumed'][ $i ] = 'year';
		return;
	}
}

/**
 * Subtitle language from explicit codes/names only. Last match wins.
 *
 * @param list<array{raw: string, key: string}> $tokens
 * @param array<string, mixed> $result
 */
function media_parse_detect_subtitle_language( array $tokens, array &$result ): void {
	$map = array(
		'fa'      => 'fa',
		'fair'    => 'fa',
		'farsi'   => 'fa',
		'persian' => 'fa',
		'en'      => 'en',
		'eng'     => 'en',
		'english' => 'en',
		'ko'      => 'ko',
		'kor'     => 'ko',
		'korean'  => 'ko',
		'zh'      => 'zh',
		'chi'     => 'zh',
		'chinese' => 'zh',
		'hi'      => 'hi',
		'hin'     => 'hi',
		'hindi'   => 'hi',
		'fr'      => 'fr',
		'fre'     => 'fr',
		'french'  => 'fr',
		'ja'      => 'ja',
		'jpn'     => 'ja',
		'es'      => 'es',
		'spa'     => 'es',
		'de'      => 'de',
		'ger'     => 'de',
		'ar'      => 'ar',
		'ara'     => 'ar',
		'ru'      => 'ru',
		'rus'     => 'ru',
		'tr'      => 'tr',
		'tur'     => 'tr',
	);

	$lang  = null;
	$index = null;

	foreach ( $tokens as $i => $token ) {
		if ( ! isset( $map[ $token['key'] ] ) ) {
			continue;
		}
		$lang  = $map[ $token['key'] ];
		$index = $i;
	}

	if ( null === $lang || null === $index ) {
		return;
	}

	$result['subtitle_lang']       = $lang;
	$result['subtitle_confidence'] = 'high';
	$result['_consumed'][ $index ] = 'subtitle_lang';
}

/**
 * Leftover leading tokens become title_hint. A single remaining group-like
 * token may become group_hint (not encoder). Everything else is unclassified.
 *
 * @param list<array{raw: string, key: string}> $tokens
 * @param array<string, mixed> $result
 */
function media_parse_collect_leftovers( array $tokens, array &$result ): void {
	$consumed = $result['_consumed'];
	$leading  = array();
	$rest     = array();
	$hit_tech = false;

	foreach ( $tokens as $i => $token ) {
		if ( isset( $consumed[ $i ] ) ) {
			$hit_tech = true;
			continue;
		}
		if ( ! $hit_tech ) {
			$leading[] = $token['raw'];
			continue;
		}
		$rest[] = $token;
	}

	if ( $leading !== array() ) {
		$result['title_hint'] = implode( '.', $leading );
	}

	/*
	 * Prefer a single group_hint for the first group-like leftover.
	 * Remaining tokens stay unclassified (e.g. KNPSK → hint, SS → unclassified).
	 * Never put the same token in both group_hint and unclassified.
	 */
	$hint_set = false;
	foreach ( $rest as $token ) {
		if ( ! $hint_set && media_parse_looks_like_group( $token['raw'] ) ) {
			$result['group_hint'] = $token['raw'];
			$result['warnings'][] = media_parse_warning(
				'unconfirmed_group',
				'Possible group/team token was not a trailing -Group tag: ' . $token['raw']
			);
			$hint_set = true;
			continue;
		}
		$result['unclassified'][] = $token['raw'];
	}
}

function media_parse_looks_like_group( string $raw ): bool {
	if ( strlen( $raw ) < 3 ) {
		return false;
	}
	return (bool) preg_match( '/^[A-Za-z][A-Za-z0-9]+$/', $raw );
}

/**
 * @param array<string, mixed> $result
 */
function media_parse_finalize_warnings( array &$result ): void {
	if ( $result['kind'] === 'video' && null === $result['quality'] ) {
		$result['warnings'][] = media_parse_warning( 'quality_unknown', 'No quality token found.' );
	}

	if ( $result['kind'] === 'video' && null === $result['source_type'] ) {
		$result['warnings'][] = media_parse_warning( 'source_unknown', 'No source-type token found.' );
	}

	if ( $result['kind'] === 'subtitle' && null === $result['subtitle_lang'] ) {
		$result['warnings'][] = media_parse_warning(
			'subtitle_lang_unknown',
			'No explicit subtitle language token found.'
		);
	}

	if ( $result['unclassified'] !== array() ) {
		$result['warnings'][] = media_parse_warning(
			'unclassified_tokens',
			'Unclassified tokens: ' . implode( ', ', $result['unclassified'] )
		);
	}

	unset( $result['_consumed'] );
}

/**
 * @return array{code: string, message: string}
 */
function media_parse_warning( string $code, string $message ): array {
	return array(
		'code'    => $code,
		'message' => $message,
	);
}
