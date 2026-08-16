<?php
/**
 * Deterministic checks for the Persian Media Automation admin localization.
 *
 * Run:
 *   php wp-content/plugins/custom-plugins/movies-wp-media-automation/includes/tests/persian-localization-test.php
 */

declare(strict_types=1);

$plugin = dirname( __DIR__, 2 );
$catalog_file = $plugin . '/languages/movies-wp-fa_IR.l10n.php';
$failures = 0;

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', sys_get_temp_dir() . '/movies-wp-persian-localization-test/' );
}

/**
 * @param bool   $condition Assertion result.
 * @param string $label     Assertion label.
 * @return void
 */
function assert_true( $condition, $label ) {
	global $failures;
	if ( $condition ) {
		echo "ok {$label}\n";
		return;
	}

	++$failures;
	echo "FAIL {$label}\n";
}

assert_true( is_readable( $catalog_file ), 'Persian translation catalog exists' );

$catalog  = require $catalog_file;
$messages = isset( $catalog['messages'] ) && is_array( $catalog['messages'] ) ? $catalog['messages'] : array();

assert_true( 'fa_IR' === ( $catalog['language'] ?? null ), 'catalog locale is fa_IR' );
assert_true( count( $messages ) >= 100, 'catalog covers the complete admin surface' );

$php_files = array(
	$plugin . '/movies-wp-media-automation.php',
);
$iterator = new RecursiveIteratorIterator(
	new RecursiveDirectoryIterator( $plugin . '/includes', FilesystemIterator::SKIP_DOTS )
);
foreach ( $iterator as $file ) {
	if ( ! $file->isFile() || 'php' !== strtolower( $file->getExtension() ) ) {
		continue;
	}
	if ( false !== strpos( str_replace( '\\', '/', $file->getPathname() ), '/includes/tests/' ) ) {
		continue;
	}
	$php_files[] = $file->getPathname();
}

$msgids = array();
foreach ( $php_files as $file ) {
	$content = (string) file_get_contents( $file );
	preg_match_all(
		"/(?:__|_e|esc_html__|esc_html_e|esc_attr__|esc_attr_e)\\(\\s*'((?:\\\\'|[^'])*)'\\s*,\\s*'movies-wp'/s",
		$content,
		$matches
	);
	foreach ( $matches[1] as $msgid ) {
		$msgids[ str_replace( "\\'", "'", $msgid ) ] = true;
	}
}

$missing = array();
$not_persian = array();
foreach ( array_keys( $msgids ) as $msgid ) {
	if ( ! array_key_exists( $msgid, $messages ) ) {
		$missing[] = $msgid;
		continue;
	}
	if ( 1 !== preg_match( '/[\x{0600}-\x{06FF}]/u', (string) $messages[ $msgid ] ) ) {
		$not_persian[] = $msgid;
	}
}

assert_true(
	array() === $missing,
	'one Persian translation exists for every static movies-wp UI string'
);
if ( $missing ) {
	echo '  missing: ' . implode( ' | ', $missing ) . "\n";
}

assert_true(
	array() === $not_persian,
	'every catalog translation contains Persian text'
);
if ( $not_persian ) {
	echo '  not Persian: ' . implode( ' | ', $not_persian ) . "\n";
}

$required = array(
	'Media Automation'                                  => 'اتوماسیون رسانه',
	'Scan & Preview'                                    => 'اسکن و پیش‌نمایش',
	'Import Movie'                                      => 'درون‌ریزی فیلم',
	'Movie imported successfully.'                      => 'فیلم با موفقیت درون‌ریزی شد.',
	'Movie updated successfully.'                       => 'فیلم با موفقیت به‌روزرسانی شد.',
	'Could not load this movie from TMDb. Please try again.' => 'امکان دریافت اطلاعات این فیلم از TMDb وجود ندارد. لطفاً دوباره تلاش کنید.',
	'No video files were detected.'                     => 'هیچ فایل ویدئویی شناسایی نشد.',
	'Subtitle language could not be detected.'          => 'زبان زیرنویس قابل تشخیص نیست.',
);
foreach ( $required as $english => $persian ) {
	assert_true(
		isset( $messages[ $english ] ) && $persian === $messages[ $english ],
		"required translation: {$english}"
	);
}

$view = (string) file_get_contents( $plugin . '/includes/views/scan-preview.php' );
assert_true(
	false !== strpos( $view, "dir=\"ltr\" value=\"<?php echo esc_attr( \$values['media_directory'] ); ?>\"" ),
	'technical media directory input remains LTR'
);
assert_true(
	false !== strpos( $view, "dir=\"auto\"><?php echo esc_html( (string) ( \$tmdb['original_title'] ?? '' ) ); ?>" ),
	'TMDb original title keeps automatic direction'
);

$css = (string) file_get_contents( $plugin . '/assets/css/admin-scan-preview.css' );
assert_true( false !== strpos( $css, 'border-inline-start:' ), 'status accents use logical border properties' );
assert_true( false !== strpos( $css, 'text-align: start;' ), 'panel text alignment follows document direction' );
assert_true( false !== strpos( $css, 'unicode-bidi: isolate;' ), 'LTR technical values are isolated in RTL' );

if ( ! function_exists( '__' ) ) {
	function __( $text, $domain = 'default' ) {
		unset( $domain );
		return $text;
	}
}
require_once $plugin . '/includes/class-movies-wp-media-admin.php';

$issue = Movies_WP_Media_Admin::issue_message(
	array(
		'code'    => 'unexpected_subdirectory',
		'message' => 'Unexpected subdirectory; not scanned.',
		'file'    => 'Extras',
	)
);
assert_true(
	'Unexpected subdirectory; it was not scanned.' === $issue,
	'media-server warning codes localize at presentation time without mutating payloads'
);

$details_issue = Movies_WP_Media_Admin::issue_message(
	array(
		'code'    => 'unclassified_tokens',
		'message' => 'Unclassified tokens: SS, PROPER',
	)
);
assert_true(
	false !== strpos( $details_issue, 'Some filename tokens could not be classified.' )
		&& false !== strpos( $details_issue, 'SS, PROPER' ),
	'parameterized media-server warnings keep technical details'
);

echo "\n";
if ( $failures > 0 ) {
	fwrite( STDERR, "{$failures} assertion(s) failed.\n" );
	exit( 1 );
}

echo "All Persian localization assertions passed.\n";
