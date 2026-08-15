<?php
/**
 * CLI tests for Movies_WP_Media_Import_Plan (read-only).
 *
 * Run: php wp-content/plugins/custom-plugins/movies-wp-media-automation/includes/tests/class-movies-wp-media-import-plan-test.php
 *
 * Uses media-server parser/validation/normalize/association. Does not write Streamit data.
 * Does not call TMDb or the media HTTP API.
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', sys_get_temp_dir() . '/movies-wp-import-plan-test/' );
}

if ( ! class_exists( 'WP_Error' ) ) {
	class WP_Error {
		/** @var string */
		private $code;
		/** @var string */
		private $message;
		/** @var mixed */
		private $data;

		public function __construct( $code = '', $message = '', $data = '' ) {
			$this->code    = (string) $code;
			$this->message = (string) $message;
			$this->data    = $data;
		}

		public function get_error_code() {
			return $this->code;
		}

		public function get_error_message() {
			return $this->message;
		}

		public function get_error_data() {
			return $this->data;
		}
	}
}

if ( ! function_exists( 'is_wp_error' ) ) {
	function is_wp_error( $thing ) {
		return $thing instanceof WP_Error;
	}
}

if ( ! function_exists( 'absint' ) ) {
	function absint( $value ) {
		return abs( (int) $value );
	}
}

if ( ! function_exists( 'maybe_unserialize' ) ) {
	function maybe_unserialize( $value ) {
		if ( ! is_string( $value ) ) {
			return $value;
		}
		$out = @unserialize( $value );
		return false === $out && 'b:0;' !== $value ? $value : $out;
	}
}

if ( ! function_exists( 'wp_json_encode' ) ) {
	function wp_json_encode( $data, $options = 0, $depth = 512 ) {
		return json_encode( $data, $options );
	}
}

$repo = dirname( __DIR__, 6 );
require_once $repo . '/media-server/lib/filename-parser.php';
require_once $repo . '/media-server/lib/media-validation.php';
require_once $repo . '/media-server/lib/media-detected-file.php';
require_once $repo . '/media-server/lib/media-association.php';
require_once dirname( __DIR__ ) . '/class-movies-wp-media-import-plan.php';

$failures = 0;

function assert_true( bool $cond, string $label ): void {
	global $failures;
	if ( $cond ) {
		echo "  ok  {$label}\n";
		return;
	}
	$failures++;
	echo "  FAIL  {$label}\n";
}

/**
 * @param array<string, mixed> $extra
 * @return array<string, mixed>
 */
function plan_video( string $basename, string $directory, array $probe, array $extra = array() ): array {
	$parsed = media_parse_filename( $basename );
	$file   = array_merge(
		array(
			'name'       => $basename,
			'media_path' => $directory . '/' . $basename,
			'extension'  => strtolower( (string) pathinfo( $basename, PATHINFO_EXTENSION ) ),
			'kind'       => 'video',
			'size_bytes' => 1000,
			'size_label' => '2.4 GB',
		),
		$extra
	);
	$skip = array( 'kind' => true, 'input' => true, 'format' => true );
	foreach ( $parsed as $key => $value ) {
		if ( isset( $skip[ $key ] ) || str_starts_with( (string) $key, '_' ) ) {
			continue;
		}
		$file[ $key ] = $value;
	}
	$file['probe']       = $probe;
	$file['validation']  = media_validate_video_file( $file );
	$file['detected']    = media_normalize_detected_file( $file );
	return $file;
}

/**
 * @param array<string, mixed> $extra
 * @return array<string, mixed>
 */
function plan_subtitle( string $basename, string $directory, array $extra = array() ): array {
	$parsed = media_parse_filename( $basename );
	$file   = array_merge(
		array(
			'name'       => $basename,
			'media_path' => $directory . '/' . $basename,
			'extension'  => strtolower( (string) pathinfo( $basename, PATHINFO_EXTENSION ) ),
			'kind'       => 'subtitle',
			'size_bytes' => 40,
			'size_label' => '40 B',
		),
		$extra
	);
	$skip = array( 'kind' => true, 'input' => true, 'format' => true );
	foreach ( $parsed as $key => $value ) {
		if ( isset( $skip[ $key ] ) || str_starts_with( (string) $key, '_' ) ) {
			continue;
		}
		$file[ $key ] = $value;
	}
	$file['detected'] = media_normalize_detected_file( $file );
	return $file;
}

/**
 * @param list<array{language?:?string,codec?:?string,channels?:?int}> $audio
 * @return array<string, mixed>
 */
function ok_probe( string $codec, int $w, int $h, array $audio = array() ): array {
	return array(
		'ok'        => true,
		'duration'  => 6152,
		'video'     => array( 'codec' => $codec, 'width' => $w, 'height' => $h ),
		'audio'     => $audio,
		'subtitles' => array(),
	);
}

/**
 * @param list<array<string, mixed>> $files
 * @param array<string, mixed>       $opts
 * @return array<string, mixed>|WP_Error
 */
function build_preview_plan( array $files, array $input, array $tmdb, array $opts = array() ) {
	$preview = array(
		'ok'    => true,
		'input' => $input,
		'tmdb'  => $tmdb,
		'media' => array(
			'directory'  => $input['media_directory'],
			'country'    => 'Korea',
			'year'       => 2018,
			'movie_name' => 'Fixture',
			'files'      => $files,
			'warnings'   => array(),
		),
		'validation' => array(
			'errors'   => array(),
			'warnings' => array(),
		),
		'ready_to_import' => true,
	);

	// Mirror Preview Service: audio_unknown only when filename unknown AND no probe languages.
	foreach ( $files as $file ) {
		if ( ! is_array( $file ) || ( $file['kind'] ?? '' ) !== 'video' ) {
			continue;
		}
		$conf = $file['audio_confidence'] ?? ( $file['detected']['audio']['confidence'] ?? 'unknown' );
		$probe_langs = $file['validation']['facts']['probe_audio_languages']
			?? $file['detected']['validation']['facts']['probe_audio_languages']
			?? array();
		if ( ! is_array( $probe_langs ) ) {
			$probe_langs = array();
		}
		if ( ( 'unknown' === $conf || '' === $conf ) && $probe_langs === array() ) {
			$preview['validation']['warnings'][] = array(
				'code'    => 'audio_unknown',
				'message' => 'Audio language could not be detected.',
				'file'    => (string) ( $file['name'] ?? '' ),
			);
		}
		if ( empty( $file['quality'] ) && empty( $file['detected']['identity']['quality']['value'] ) ) {
			$preview['validation']['warnings'][] = array(
				'code'    => 'quality_unknown',
				'message' => 'Quality could not be detected.',
				'file'    => (string) ( $file['name'] ?? '' ),
			);
		}
	}

	$videos = array_filter(
		$files,
		static function ( $f ) {
			return is_array( $f ) && ( $f['kind'] ?? '' ) === 'video';
		}
	);
	if ( $videos === array() ) {
		$preview['validation']['errors'][] = array(
			'code'    => 'no_video_files',
			'message' => 'No video files were detected.',
		);
	}

	return Movies_WP_Media_Import_Plan::build( $preview, $opts );
}

$soul_dir = 'Movie/Korea/2018/The.Soul.Mate';
$vapor_dir = 'Movie/Korea/2018/Vapor';
$bel_dir = 'Movie/Korea/2023/Believer.2';

$base_input = array(
	'tmdb_id'         => 123456,
	'title'           => 'مبارزان شکارچی',
	'summary'         => 'خلاصه فارسی ادمین',
	'media_directory' => $soul_dir,
);

$base_tmdb = array(
	'id'             => 123456,
	'title'          => 'Bounty Hunters',
	'original_title' => 'Bounty Hunters',
	'overview'       => 'English TMDb overview',
	'year'           => 2016,
);

$opts_create = array(
	'find_by_tmdb' => static function () {
		return array( 'ids' => array() );
	},
);

echo "create plan: Soul Mate 1080p + 720p SS\n";
$files = array(
	plan_video( 'The.Soul.Mate.1080p.WEB-DL.mkv', $soul_dir, ok_probe( 'h264', 1920, 1080, array( array( 'language' => 'en', 'codec' => 'aac', 'channels' => 2 ) ) ) ),
	plan_video( 'The.Soul.Mate.2018.WEB-DL.720p.SS.mkv', $soul_dir, ok_probe( 'h264', 1280, 720, array( array( 'language' => 'en', 'codec' => 'aac', 'channels' => 2 ) ) ) ),
);
$plan = build_preview_plan( $files, $base_input, $base_tmdb, $opts_create );
assert_true( ! is_wp_error( $plan ) && ( $plan['ok'] ?? false ) === true, 'plan ok' );
assert_true( ( $plan['identity']['action'] ?? null ) === 'create', 'action create' );
assert_true( ( $plan['identity']['existing_movie_id'] ?? null ) === null, 'no existing id' );
assert_true( ( $plan['metadata']['title'] ?? null ) === 'مبارزان شکارچی', 'admin title authoritative' );
assert_true( ( $plan['metadata']['title_source'] ?? null ) === 'admin', 'title_source admin' );
assert_true( ( $plan['metadata']['summary'] ?? null ) === 'خلاصه فارسی ادمین', 'admin summary authoritative' );
assert_true( ( $plan['metadata']['summary_source'] ?? null ) === 'admin', 'summary_source admin' );
assert_true( ( $plan['movie']['tmdb_title'] ?? null ) === 'Bounty Hunters', 'TMDb localized title kept for adapter persistence' );
assert_true( ( $plan['movie']['tmdb_original_title'] ?? null ) === 'Bounty Hunters', 'TMDb original title kept for adapter persistence' );
assert_true( ! isset( $plan['metadata']['original_title'] ), 'no invented original_title write field' );
assert_true( count( $plan['sources'] ) === 2, 'two video sources' );
assert_true( ( $plan['sources'][0]['link'] ?? null ) === ( $plan['sources'][0]['media_path'] ?? null ), 'link is relative media_path' );
assert_true( ( $plan['sources'][0]['download_content'] ?? null ) === ( $plan['sources'][0]['media_path'] ?? null ), 'download_content relative' );
assert_true( str_starts_with( (string) ( $plan['sources'][0]['media_path'] ?? '' ), 'Movie/' ), 'relative Movie/ path' );
assert_true( false === strpos( (string) ( $plan['sources'][0]['media_path'] ?? '' ), '/v/' ), 'no signed /v/ URL' );
assert_true( ( $plan['sources'][0]['language'] ?? null ) === null, 'language not defaulted' );
assert_true( ( $plan['sources'][1]['name'] ?? null ) === '', 'SS not encoder → name empty' );
assert_true( in_array( 'SS', $plan['sources'][1]['release']['unclassified'] ?? array(), true ), 'SS unclassified preserved' );
assert_true( ( $plan['ready_to_import'] ?? false ) === true, 'warnings do not block' );
$warn_codes = array_column( $plan['warnings'], 'code' );
assert_true( ! in_array( 'audio_unknown', $warn_codes, true ), 'no audio_unknown when probe has languages' );
assert_true( ! in_array( 'probe_audio_language_detected', $warn_codes, true ), 'probe reconciliation not user-facing' );

echo "\nVapor fixtures: WAVVE, tG1R0, codecs not encoder\n";
$vapor_files = array(
	plan_video( 'Vapor.2018.480p.WAVVE.WEB-DL.mkv', $vapor_dir, ok_probe( 'h264', 854, 480 ) ),
	plan_video( 'Vapor.2018.1080p.WAVVE.WEB-DL.AAC2.0.H.264-tG1R0.mkv', $vapor_dir, ok_probe( 'h264', 1920, 1080, array( array( 'language' => 'fa', 'codec' => 'aac', 'channels' => 2 ), array( 'language' => 'en', 'codec' => 'aac', 'channels' => 2 ) ) ) ),
);
$vplan = build_preview_plan(
	$vapor_files,
	array_merge( $base_input, array( 'media_directory' => $vapor_dir ) ),
	$base_tmdb,
	$opts_create
);
assert_true( ( $vplan['sources'][0]['provider'] ?? null ) === 'WAVVE', '480p provider WAVVE' );
assert_true( ( $vplan['sources'][1]['release']['release_group'] ?? null ) === 'tG1R0', 'tG1R0 release_group' );
assert_true( ( $vplan['sources'][1]['name'] ?? null ) === '', 'tG1R0 not encoder' );
assert_true( ( $vplan['sources'][1]['detected']['video']['codec_filename']['value'] ?? null ) === 'H.264', 'filename codec preserved in detected' );
assert_true( ( $vplan['sources'][1]['detected']['video']['codec']['value'] ?? null ) === 'h264', 'ffprobe codec in detected' );
assert_true( count( $vplan['sources'][1]['audio']['tracks'] ) === 2, 'dual probe audio tracks preserved' );
assert_true( ( $vplan['sources'][1]['language'] ?? null ) === null, 'probe fa/en not written as source.language' );
$track_langs = array_column( $vplan['sources'][1]['audio']['tracks'], 'language' );
assert_true( ! in_array( 'ko', $track_langs, true ), 'Korea never becomes audio language' );

echo "\nBeliever: MARK, AirenTeam, fa.srt associated, AirenTeam unassociated\n";
$bel_files = array(
	plan_video( 'Believer.2.2023.1080p.NF.WEB-DL.H.264-MARK.mkv', $bel_dir, ok_probe( 'h264', 1920, 1080 ) ),
	plan_subtitle( 'Believer.2.NF.AirenTeam.srt', $bel_dir ),
	plan_subtitle( 'Believer.2.2023.1080p.NF.WEB-DL.H.264-MARK.fa.srt', $bel_dir ),
	plan_subtitle( 'Believer.2.2023.1080p.NF.WEB-DL.H.264-MARK.en.srt', $bel_dir ),
);
$bplan = build_preview_plan(
	$bel_files,
	array_merge( $base_input, array( 'media_directory' => $bel_dir, 'summary' => '' ) ),
	$base_tmdb,
	$opts_create
);
assert_true( ( $bplan['sources'][0]['release']['release_group'] ?? null ) === 'MARK', 'MARK release_group' );
assert_true( ( $bplan['sources'][0]['name'] ?? null ) === '', 'MARK not encoder' );
assert_true( ( $bplan['metadata']['summary_source'] ?? null ) === 'tmdb', 'empty admin summary → tmdb fallback' );
assert_true( ( $bplan['metadata']['summary'] ?? null ) === 'English TMDb overview', 'tmdb overview used as fallback summary' );
assert_true( count( $bplan['subtitles'] ) === 3, 'fa + en associated + AirenTeam unassociated persistable' );
assert_true( count( $bplan['unassociated_subtitles'] ) === 1, 'AirenTeam unassociated' );
assert_true( ( $bplan['unassociated_subtitles'][0]['media_path'] ?? '' ) !== '', 'unassociated path kept' );
$fa = null;
foreach ( $bplan['subtitles'] as $sub ) {
	if ( ( $sub['language'] ?? null ) === 'fa' ) {
		$fa = $sub;
	}
}
assert_true( is_array( $fa ), 'fa subtitle in plan' );
assert_true( ( $fa['url_plan']['signed'] ?? true ) === false, 'no signed URL planned' );
assert_true( ( $fa['url_plan']['ready'] ?? false ) === true, 'url_plan ready for relative persist' );
assert_true( ( $fa['persistence'] ?? null ) === 'relative_path', 'fa persistence relative_path' );
assert_true( ( $fa['url_plan']['value'] ?? null ) === ( $fa['media_path'] ?? null ), 'subtitle relative path only' );
assert_true( ( $fa['association']['confidence'] ?? null ) === 'high', 'association confidence high' );
// Subtitle language must not become audio.
assert_true( ( $bplan['sources'][0]['language'] ?? null ) === null, 'subtitle fa does not become source language' );
assert_true( ( $bplan['unassociated_subtitles'][0]['detected']['release']['group_hint'] ?? null ) === 'AirenTeam', 'AirenTeam group_hint' );
assert_true( ( $bplan['unassociated_subtitles'][0]['detected']['release']['encoder'] ?? null ) === null, 'AirenTeam not encoder' );
$airen = null;
foreach ( $bplan['subtitles'] as $sub ) {
	if ( ( $sub['reason'] ?? null ) === 'unassociated_movie_directory' ) {
		$airen = $sub;
	}
}
assert_true( is_array( $airen ), 'AirenTeam also in plan subtitles' );
assert_true( array_key_exists( 'association', $airen ) && null === $airen['association'], 'AirenTeam has no invented video association' );
assert_true( str_starts_with( (string) ( $airen['media_path'] ?? '' ), 'Movie/' ), 'AirenTeam relative Movie/ path' );

echo "\nfilename Dual Audio claim\n";
$dual = plan_video(
	'Movie.2025.1080p.WEB-DL.Dual.Audio.mkv',
	'Movie/Test/2025/Sample',
	ok_probe(
		'h264',
		1920,
		1080,
		array(
			array( 'language' => 'fa', 'codec' => 'aac', 'channels' => 2 ),
			array( 'language' => 'en', 'codec' => 'aac', 'channels' => 2 ),
		)
	)
);
$dplan = build_preview_plan(
	array( $dual ),
	array_merge( $base_input, array( 'media_directory' => 'Movie/Test/2025/Sample' ) ),
	$base_tmdb,
	$opts_create
);
assert_true( ( $dplan['sources'][0]['audio']['label'] ?? null ) === 'Dual Audio', 'Dual Audio label from filename' );
assert_true( ( $dplan['sources'][0]['audio']['confidence'] ?? null ) === 'high', 'dual confidence high' );
assert_true( ( $dplan['sources'][0]['language'] ?? null ) === null, 'still no Streamit language mapping' );

echo "\nfailed ffprobe does not invent values\n";
$fail = plan_video(
	'The.Soul.Mate.1080p.WEB-DL.mkv',
	$soul_dir,
	array(
		'ok'        => false,
		'code'      => 'ffprobe_failed',
		'message'   => 'failed',
		'duration'  => null,
		'video'     => null,
		'audio'     => array(),
		'subtitles' => array(),
	)
);
$fplan = build_preview_plan( array( $fail ), $base_input, $base_tmdb, $opts_create );
assert_true( ( $fplan['sources'][0]['quality'] ?? null ) === '1080p', 'quality from filename remains' );
assert_true( ( $fplan['sources'][0]['detected']['video']['codec']['value'] ?? null ) === null, 'no invented probe codec' );
assert_true( ( $fplan['sources'][0]['detected']['audio']['tracks'] ?? null ) === array(), 'no invented audio tracks' );

echo "\nresolution mismatch remains warning, does not invent quality rewrite\n";
$mis = plan_video( 'The.Soul.Mate.1080p.WEB-DL.mkv', $soul_dir, ok_probe( 'h264', 1280, 720 ) );
$mplan = build_preview_plan( array( $mis ), $base_input, $base_tmdb, $opts_create );
assert_true( ( $mplan['sources'][0]['quality'] ?? null ) === '1080p', 'quality not overwritten by 720p probe' );
$mcodes = array_column( $mplan['warnings'], 'code' );
assert_true( in_array( 'filename_probe_resolution_mismatch', $mcodes, true ), 'resolution mismatch warning carried' );
assert_true( ( $mplan['ready_to_import'] ?? false ) === true, 'mismatch warning does not block' );

echo "\nno video files → blocked\n";
$empty = build_preview_plan( array(), $base_input, $base_tmdb, $opts_create );
assert_true( ( $empty['ready_to_import'] ?? true ) === false, 'no videos blocks import' );
assert_true( in_array( 'no_video_files', array_column( $empty['errors'], 'code' ), true ), 'no_video_files error' );

echo "\nduplicate TMDb identity blocks\n";
$dup = build_preview_plan(
	array( plan_video( 'The.Soul.Mate.1080p.WEB-DL.mkv', $soul_dir, ok_probe( 'h264', 1920, 1080 ) ) ),
	$base_input,
	$base_tmdb,
	array(
		'find_by_tmdb' => static function () {
			return array( 'ids' => array( 10, 20 ) );
		},
	)
);
assert_true( ( $dup['identity']['action'] ?? null ) === 'blocked', 'duplicate action blocked' );
assert_true( ( $dup['ready_to_import'] ?? true ) === false, 'duplicate not ready' );
assert_true( in_array( 'duplicate_tmdb_id', array_column( $dup['errors'], 'code' ), true ), 'duplicate_tmdb_id error' );

echo "\nexisting movie update + source keep/add\n";
$existing_path = 'Movie/Korea/2018/The.Soul.Mate/manual-extra.mkv';
$upd = build_preview_plan(
	array( plan_video( 'The.Soul.Mate.1080p.WEB-DL.mkv', $soul_dir, ok_probe( 'h264', 1920, 1080 ) ) ),
	$base_input,
	$base_tmdb,
	array(
		'find_by_tmdb' => static function () {
			return array( 'ids' => array( 42 ) );
		},
		'get_sources'  => static function () use ( $existing_path, $soul_dir ) {
			return array(
				array(
					'name'             => '',
					'link'             => $soul_dir . '/The.Soul.Mate.1080p.WEB-DL.mkv',
					'download_content' => $soul_dir . '/The.Soul.Mate.1080p.WEB-DL.mkv',
					'quality'          => '1080p',
					'language'         => 'English',
				),
				array(
					'name'             => 'Manual',
					'link'             => $existing_path,
					'download_content' => $existing_path,
					'quality'          => '720p',
					'language'         => 'Persian',
				),
			);
		},
	)
);
assert_true( ( $upd['identity']['action'] ?? null ) === 'update', 'action update' );
assert_true( ( $upd['identity']['existing_movie_id'] ?? null ) === 42, 'existing id 42' );
$actions = array_column( $upd['sources'], 'action' );
assert_true( in_array( 'update', $actions, true ), 'scanned path → update' );
assert_true( in_array( 'keep_existing', $actions, true ), 'manual path kept' );
$keep = null;
foreach ( $upd['sources'] as $src ) {
	if ( ( $src['action'] ?? '' ) === 'keep_existing' ) {
		$keep = $src;
	}
}
assert_true( is_array( $keep ) && ( $keep['media_path'] ?? null ) === $existing_path, 'keep uses media_path identity' );
assert_true( is_array( $keep ) && ( $keep['name'] ?? null ) === 'Manual', 'manual name preserved on keep' );

echo "\nambiguous subtitle not silently assigned\n";
$amb_dir = 'Movie/Test/2025/Sample';
$amb = build_preview_plan(
	array(
		plan_video( 'Movie.2025.1080p.WEB-DL.A.mkv', $amb_dir, ok_probe( 'h264', 1920, 1080 ) ),
		plan_video( 'Movie.2025.1080p.WEB-DL.B.mkv', $amb_dir, ok_probe( 'h264', 1920, 1080 ) ),
		plan_subtitle( 'Movie.2025.1080p.WEB-DL.fa.srt', $amb_dir ),
	),
	array_merge( $base_input, array( 'media_directory' => $amb_dir ) ),
	$base_tmdb,
	$opts_create
);
assert_true( count( $amb['associations'] ) === 0, 'ambiguous subtitle not assigned a video' );
assert_true( count( $amb['unassociated_subtitles'] ) === 1, 'ambiguous listed unassociated' );
assert_true( count( $amb['subtitles'] ) === 1, 'ambiguous movie-dir subtitle still planned' );
assert_true( array_key_exists( 'association', $amb['subtitles'][0] ) && null === $amb['subtitles'][0]['association'], 'ambiguous plan row has no video association' );
assert_true( ( $amb['subtitles'][0]['reason'] ?? null ) === 'unassociated_movie_directory', 'ambiguous persist reason' );

echo "\nunknown encoder stays empty name\n";
$enc = build_preview_plan(
	array( plan_video( 'Believer.2.2023.1080p.NF.WEB-DL.H.264-MARK.mkv', $bel_dir, ok_probe( 'h264', 1920, 1080 ) ) ),
	array_merge( $base_input, array( 'media_directory' => $bel_dir ) ),
	$base_tmdb,
	$opts_create
);
assert_true( ( $enc['sources'][0]['encoder'] ?? null ) === null, 'encoder null' );
assert_true( ( $enc['sources'][0]['name'] ?? null ) === '', 'name empty string not Unknown' );

echo "\nYIFY allowlisted encoder becomes name\n";
$yify = build_preview_plan(
	array( plan_video( 'Movie.2020.1080p.BluRay.YIFY.mkv', 'Movie/Test/2020/Sample', ok_probe( 'h264', 1920, 1080 ) ) ),
	array_merge( $base_input, array( 'media_directory' => 'Movie/Test/2020/Sample' ) ),
	$base_tmdb,
	$opts_create
);
assert_true( ( $yify['sources'][0]['encoder'] ?? null ) === 'YIFY', 'YIFY encoder' );
assert_true( ( $yify['sources'][0]['name'] ?? null ) === 'YIFY', 'name=YIFY' );

echo "\nYTS / RARBG allowlisted encoders\n";
foreach ( array( 'YTS' => 'Movie.2020.1080p.BluRay.YTS.mkv', 'RARBG' => 'Movie.2020.1080p.BluRay.RARBG.mkv' ) as $enc_name => $fname ) {
	$p = build_preview_plan(
		array( plan_video( $fname, 'Movie/Test/2020/Sample', ok_probe( 'h264', 1920, 1080 ) ) ),
		array_merge( $base_input, array( 'media_directory' => 'Movie/Test/2020/Sample' ) ),
		$base_tmdb,
		$opts_create
	);
	assert_true( ( $p['sources'][0]['name'] ?? null ) === $enc_name, "{$enc_name} → name" );
}

echo "\nPhase harden: contract flags + subtitle relative_path + no delete\n";
$harden_files = array(
	plan_video( 'The.Soul.Mate.1080p.WEB-DL.mkv', $soul_dir, ok_probe( 'h264', 1920, 1080 ) ),
	plan_subtitle( 'The.Soul.Mate.1080p.WEB-DL.fa.srt', $soul_dir ),
);
$preview_snapshot = array(
	'ok'    => true,
	'input' => $base_input,
	'tmdb'  => $base_tmdb,
	'media' => array(
		'directory'  => $soul_dir,
		'country'    => 'Korea',
		'year'       => 2018,
		'movie_name' => 'The.Soul.Mate',
		'files'      => $harden_files,
		'warnings'   => array(
			array(
				'code'    => 'unexpected_subdirectory',
				'message' => 'Unexpected subdirectory SUB.',
				'name'    => 'SUB',
			),
		),
	),
	'validation' => array(
		'errors'   => array(),
		'warnings' => array(
			array(
				'code'    => 'audio_unknown',
				'message' => 'Audio language could not be detected.',
				'file'    => 'The.Soul.Mate.1080p.WEB-DL.mkv',
			),
			array(
				'code'    => 'unexpected_subdirectory',
				'message' => 'Unexpected subdirectory SUB.',
				'name'    => 'SUB',
			),
		),
	),
	'ready_to_import' => true,
);
$files_before = $harden_files;
$detected_before = $harden_files[0]['detected'];
$hplan = Movies_WP_Media_Import_Plan::build(
	$preview_snapshot,
	array(
		'find_by_tmdb' => static function () {
			return array( 'ids' => array() );
		},
	)
);
assert_true( ! is_wp_error( $hplan ), 'harden plan ok' );
assert_true( ( $hplan['contract']['read_only'] ?? false ) === true, 'contract.read_only' );
assert_true( ( $hplan['contract']['kind'] ?? null ) === 'import_plan', 'contract.kind' );
assert_true( ( $hplan['source_identity']['delete'] ?? true ) === false, 'source_identity.delete=false' );
assert_true( ( $hplan['subtitle_persistence']['ready'] ?? false ) === true, 'subtitle_persistence ready' );
assert_true( ( $hplan['subtitle_persistence']['status'] ?? null ) === 'relative_path', 'subtitle persistence relative_path' );
assert_true( ( $hplan['language_decision']['streamit_source_language'] ?? null ) === null, 'language deferred null' );
assert_true( $files_before === $harden_files, 'input files array not mutated' );
assert_true( $detected_before === $harden_files[0]['detected'], 'detected object not mutated' );
assert_true( ! in_array( 'delete', array_column( $hplan['sources'], 'action' ), true ), 'no delete action' );
assert_true( in_array( 'unexpected_subdirectory', array_column( $hplan['warnings'], 'code' ), true ), 'unexpected subdirectory warning carried' );
assert_true( count( $hplan['subtitles'] ) === 1, 'fa.srt associated in harden plan' );
assert_true( ( $hplan['subtitles'][0]['persistence'] ?? null ) === 'relative_path', 'associated subtitle persistence relative_path' );
assert_true( ( $hplan['subtitles'][0]['url_plan']['ready'] ?? false ) === true, 'url_plan.ready true' );
assert_true( ( $hplan['subtitles'][0]['url_plan']['signed'] ?? true ) === false, 'url_plan.signed false' );
assert_true( ( $hplan['subtitles'][0]['url_plan']['render_time'] ?? null ) === 'streamit_child_resolve_subtitle_url', 'render_time signer named' );
assert_true( false === strpos( wp_json_encode( $hplan['subtitles'] ), '/v/' ), 'no /v/ in subtitle plan JSON' );

echo "\nupdate preserves manual name when detected encoder empty\n";
$manual_name_plan = build_preview_plan(
	array( plan_video( 'The.Soul.Mate.1080p.WEB-DL.mkv', $soul_dir, ok_probe( 'h264', 1920, 1080 ) ) ),
	$base_input,
	$base_tmdb,
	array(
		'find_by_tmdb' => static function () {
			return array( 'ids' => array( 77 ) );
		},
		'get_sources'  => static function () use ( $soul_dir ) {
			return array(
				array(
					'name'             => 'HandEnteredEncoder',
					'link'             => $soul_dir . '/The.Soul.Mate.1080p.WEB-DL.mkv',
					'download_content' => $soul_dir . '/The.Soul.Mate.1080p.WEB-DL.mkv',
					'quality'          => '1080p',
					'language'         => 'English',
					'file_size'        => '1.0 GB',
					'custom_note'      => 'keep-me',
				),
			);
		},
	)
);
assert_true( ( $manual_name_plan['identity']['action'] ?? null ) === 'update', 'manual name case is update' );
assert_true( ( $manual_name_plan['sources'][0]['action'] ?? null ) === 'update', 'source action update' );
assert_true( ( $manual_name_plan['sources'][0]['encoder'] ?? null ) === null, 'detected encoder still null' );
assert_true( ( $manual_name_plan['sources'][0]['name'] ?? null ) === 'HandEnteredEncoder', 'manual name preserved' );
assert_true( ( $manual_name_plan['sources'][0]['name_preserved_from_existing'] ?? false ) === true, 'name_preserved flag' );
assert_true( ( $manual_name_plan['sources'][0]['existing_row']['custom_note'] ?? null ) === 'keep-me', 'unknown existing fields retained on existing_row' );
assert_true( ( $manual_name_plan['sources'][0]['identity_key'] ?? null ) === $soul_dir . '/The.Soul.Mate.1080p.WEB-DL.mkv', 'identity_key is normalized path' );
assert_true( ( $manual_name_plan['sources'][0]['action_reason'] ?? null ) === 'identity_match_normalized_link_or_download_content', 'action_reason set' );

echo "\nidentity uses download_content when link empty\n";
$dc_plan = build_preview_plan(
	array( plan_video( 'The.Soul.Mate.1080p.WEB-DL.mkv', $soul_dir, ok_probe( 'h264', 1920, 1080 ) ) ),
	$base_input,
	$base_tmdb,
	array(
		'find_by_tmdb' => static function () {
			return array( 'ids' => array( 88 ) );
		},
		'get_sources'  => static function () use ( $soul_dir ) {
			return array(
				array(
					'name'             => '',
					'link'             => '',
					'download_content' => $soul_dir . '/The.Soul.Mate.1080p.WEB-DL.mkv',
					'quality'          => '1080p',
				),
			);
		},
	)
);
assert_true( ( $dc_plan['sources'][0]['action'] ?? null ) === 'update', 'matched via download_content' );

echo "\nquality alone never matches identity\n";
$q_plan = build_preview_plan(
	array( plan_video( 'The.Soul.Mate.1080p.WEB-DL.mkv', $soul_dir, ok_probe( 'h264', 1920, 1080 ) ) ),
	$base_input,
	$base_tmdb,
	array(
		'find_by_tmdb' => static function () {
			return array( 'ids' => array( 99 ) );
		},
		'get_sources'  => static function () {
			return array(
				array(
					'name'             => 'Other',
					'link'             => 'Movie/Other/2018/Other/other.1080p.mkv',
					'download_content' => 'Movie/Other/2018/Other/other.1080p.mkv',
					'quality'          => '1080p',
				),
			);
		},
	)
);
$q_actions = array_column( $q_plan['sources'], 'action' );
assert_true( in_array( 'add', $q_actions, true ), 'scanned file is add' );
assert_true( in_array( 'keep_existing', $q_actions, true ), 'same-quality different path kept' );

echo "\nprovider / release_group / SS never become name\n";
$nf_plan = build_preview_plan(
	array( plan_video( 'Believer.2.2023.1080p.NF.WEB-DL.H.264-MARK.mkv', $bel_dir, ok_probe( 'h264', 1920, 1080 ) ) ),
	array_merge( $base_input, array( 'media_directory' => $bel_dir ) ),
	$base_tmdb,
	$opts_create
);
assert_true( ( $nf_plan['sources'][0]['provider'] ?? null ) === 'NF', 'provider NF preserved as fact' );
assert_true( ( $nf_plan['sources'][0]['name'] ?? null ) === '', 'provider not name' );
assert_true( ( $nf_plan['sources'][0]['release']['release_group'] ?? null ) === 'MARK', 'MARK in release' );
assert_true( ( $nf_plan['sources'][0]['name'] ?? null ) !== 'MARK', 'MARK not name' );
assert_true( ( $nf_plan['sources'][0]['streamit_safe']['language'] ?? null ) === null, 'streamit_safe.language null' );

$ss_plan = build_preview_plan(
	array( plan_video( 'The.Soul.Mate.2018.WEB-DL.720p.SS.mkv', $soul_dir, ok_probe( 'h264', 1280, 720 ) ) ),
	$base_input,
	$base_tmdb,
	$opts_create
);
assert_true( ( $ss_plan['sources'][0]['name'] ?? null ) === '', 'SS not name' );
assert_true( in_array( 'SS', $ss_plan['sources'][0]['release']['unclassified'] ?? array(), true ), 'SS unclassified' );

echo "\nprobe audio / subtitle language never become source.language; Korea not ko\n";
$fa_audio = build_preview_plan(
	array(
		plan_video(
			'Believer.2.2023.1080p.NF.WEB-DL.H.264-MARK.mkv',
			$bel_dir,
			ok_probe( 'h264', 1920, 1080, array( array( 'language' => 'fa', 'codec' => 'aac', 'channels' => 2 ) ) )
		),
		plan_subtitle( 'Believer.2.2023.1080p.NF.WEB-DL.H.264-MARK.fa.srt', $bel_dir ),
	),
	array_merge( $base_input, array( 'media_directory' => $bel_dir, 'title' => 'عنوان فارسی' ) ),
	$base_tmdb,
	$opts_create
);
assert_true( ( $fa_audio['sources'][0]['language'] ?? null ) === null, 'ffprobe fa not source.language' );
assert_true( ( $fa_audio['sources'][0]['audio']['tracks'][0]['language'] ?? null ) === 'fa', 'fa remains under audio.tracks' );
assert_true( ( $fa_audio['subtitles'][0]['language'] ?? null ) === 'fa', 'subtitle language fa' );
assert_true( ( $fa_audio['sources'][0]['language'] ?? null ) !== 'fa', 'subtitle fa ≠ audio language field' );
assert_true( ( $fa_audio['sources'][0]['audio']['tracks'][0]['language'] ?? null ) !== 'ko', 'Korea not ko audio' );

echo "\nsubtitle without language still associable; unknown lang warning from preview\n";
$nolang_sub = plan_subtitle( 'The.Soul.Mate.1080p.WEB-DL.srt', $soul_dir );
$nolang_plan = build_preview_plan(
	array(
		plan_video( 'The.Soul.Mate.1080p.WEB-DL.mkv', $soul_dir, ok_probe( 'h264', 1920, 1080 ) ),
		$nolang_sub,
	),
	$base_input,
	$base_tmdb,
	$opts_create
);
assert_true( count( $nolang_plan['subtitles'] ) === 1, 'stem-matched srt associated without language' );
assert_true( ( $nolang_plan['subtitles'][0]['language'] ?? null ) === null, 'subtitle language unknown remains null' );

echo "\nmultiple subtitles to one video\n";
$multi = build_preview_plan(
	array(
		plan_video( 'Movie.2025.1080p.WEB-DL.mkv', 'Movie/Test/2025/Sample', ok_probe( 'h264', 1920, 1080 ) ),
		plan_subtitle( 'Movie.2025.1080p.WEB-DL.fa.srt', 'Movie/Test/2025/Sample' ),
		plan_subtitle( 'Movie.2025.1080p.WEB-DL.en.srt', 'Movie/Test/2025/Sample' ),
	),
	array_merge( $base_input, array( 'media_directory' => 'Movie/Test/2025/Sample' ) ),
	$base_tmdb,
	$opts_create
);
assert_true( count( $multi['subtitles'] ) === 2, 'fa+en both associated' );
assert_true( ( $multi['subtitles'][0]['association']['video'] ?? null ) === ( $multi['subtitles'][1]['association']['video'] ?? null ), 'same video' );

echo "\nunassociated movie-directory subtitle enters plan (Decision.to.Leave)\n";
$dtl_dir = 'Movie/Korea/2022/Decision.to.Leave';
$dtl_srt = 'Movie/Korea/2022/Decision.to.Leave/Decision.to.Leave.2022.WEB-DL.srt';
$dtl = build_preview_plan(
	array(
		plan_video( 'Decision.to.Leave.2022.1080p.KNPSK.WEB-DL.DDP5.1.x264-tG1R0.mkv', $dtl_dir, ok_probe( 'h264', 1920, 1080 ) ),
		plan_video( 'Decision.to.Leave.2022.720p.KNPSK.WEB-DL.DDP5.1.x264-tG1R0.mkv', $dtl_dir, ok_probe( 'h264', 1280, 720 ) ),
		plan_subtitle( 'Decision.to.Leave.2022.WEB-DL.srt', $dtl_dir ),
		plan_subtitle( 'Decision.to.Leave.2022.WEB-DL.fa.srt', $dtl_dir ),
	),
	array_merge( $base_input, array( 'media_directory' => $dtl_dir ) ),
	$base_tmdb,
	$opts_create
);
assert_true( ( $dtl['associations'] ?? array() ) === array(), 'association behavior unchanged: no stem match' );
assert_true( count( $dtl['unassociated_subtitles'] ) === 2, 'both srt remain diagnostic unassociated' );
assert_true( count( $dtl['subtitles'] ) === 2, 'unassociated movie-dir subtitles enter plan' );

$unknown = null;
$known   = null;
foreach ( $dtl['subtitles'] as $sub ) {
	if ( ( $sub['media_path'] ?? '' ) === $dtl_srt ) {
		$unknown = $sub;
	}
	if ( str_ends_with( (string) ( $sub['media_path'] ?? '' ), 'Decision.to.Leave.2022.WEB-DL.fa.srt' ) ) {
		$known = $sub;
	}
}
assert_true( is_array( $unknown ), 'WEB-DL.srt in plan subtitles' );
assert_true( ( $unknown['media_path'] ?? null ) === $dtl_srt, 'relative path preserved' );
assert_true( str_starts_with( (string) $unknown['media_path'], 'Movie/' ), 'starts with Movie/' );
assert_true( ! str_contains( (string) $unknown['media_path'], '/data/' ), 'no /data/' );
assert_true( ! str_contains( (string) ( $unknown['url_plan']['value'] ?? '' ), '/v/' ), 'no /v/' );
assert_true( ! str_contains( (string) ( $unknown['url_plan']['value'] ?? '' ), '/d/' ), 'no /d/' );
assert_true( ( $unknown['url_plan']['signed'] ?? true ) === false, 'no signed URL planned' );
assert_true( ( $unknown['url_plan']['ready'] ?? false ) === true, 'url_plan ready' );
assert_true( ( $unknown['url_plan']['storage'] ?? null ) === 'relative_path', 'url_plan storage relative_path' );
assert_true( ( $unknown['url_plan']['render_time'] ?? null ) === 'streamit_child_resolve_subtitle_url', 'render_time signer named' );
assert_true( array_key_exists( 'association', $unknown ) && null === $unknown['association'], 'no invented video association' );
assert_true( ( $unknown['reason'] ?? null ) === 'unassociated_movie_directory', 'unassociated_movie_directory reason' );
assert_true( ( $unknown['action'] ?? null ) === 'add', 'action add' );
assert_true( ( $unknown['persistence'] ?? null ) === 'relative_path', 'persistence relative_path' );
assert_true( array_key_exists( 'language', $unknown ) && null === $unknown['language'], 'unknown language remains null' );
assert_true( is_array( $known ), 'fa.srt in plan subtitles' );
assert_true( ( $known['language'] ?? null ) === 'fa', 'known language preserved' );
assert_true( array_key_exists( 'association', $known ) && null === $known['association'], 'fa.srt not assigned a video' );
assert_true( false === strpos( wp_json_encode( $dtl['subtitles'] ), '/v/' ), 'no /v/ in Decision.to.Leave subtitle plan JSON' );

if ( $failures > 0 ) {
	echo "\n{$failures} failure(s)\n";
	exit( 1 );
}

echo "\nall passed\n";
exit( 0 );
