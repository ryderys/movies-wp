<?php
/**
 * CLI tests for Elementor Design System Sync enqueue guard + kill switch.
 *
 * Run:
 *   php -n wp-content/themes/streamit-child/inc/tests/elementor-dss-enqueue-guard-test.php
 *
 * Constant variants run in subprocesses (PHP constants cannot be redefined).
 * Hook tests use a fake store — labeled below; not a proof of real WP_Hook.
 *
 * @package streamit-child
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', sys_get_temp_dir() . '/streamit-child-dss-guard-test/' );
}

/**
 * Minimal WP_Hook-like store for add/remove/has_action.
 *
 * LIMITATION: Does not model WP_Hook nesting, deferred removals, or
 * _wp_filter_build_unique_id. Order within a priority is insertion order.
 */
final class Stc_Dss_Guard_Test_Hooks {
	/** @var array<string, array<int, array<string, array{function: mixed, accepted_args: int}>>> */
	public static $hooks = array();

	public static function reset(): void {
		self::$hooks = array();
	}

	public static function add( $hook, $callback, $priority = 10, $accepted_args = 1 ) {
		$priority = (int) $priority;
		$id       = self::id( $callback );
		self::$hooks[ $hook ][ $priority ][ $id ] = array(
			'function'      => $callback,
			'accepted_args' => (int) $accepted_args,
		);
		return true;
	}

	public static function remove( $hook, $callback, $priority = 10 ) {
		$priority = (int) $priority;
		$id       = self::id( $callback );
		if ( empty( self::$hooks[ $hook ][ $priority ][ $id ] ) ) {
			return false;
		}
		unset( self::$hooks[ $hook ][ $priority ][ $id ] );
		if ( empty( self::$hooks[ $hook ][ $priority ] ) ) {
			unset( self::$hooks[ $hook ][ $priority ] );
		}
		return true;
	}

	public static function has( $hook, $callback = false ) {
		if ( empty( self::$hooks[ $hook ] ) ) {
			return false;
		}
		if ( false === $callback ) {
			return true;
		}
		$id = self::id( $callback );
		foreach ( self::$hooks[ $hook ] as $priority => $group ) {
			if ( isset( $group[ $id ] ) ) {
				return (int) $priority;
			}
		}
		return false;
	}

	/**
	 * Ordered callback labels at a priority (insertion order).
	 *
	 * @return list<string>
	 */
	public static function order_at( $hook, $priority ): array {
		$priority = (int) $priority;
		if ( empty( self::$hooks[ $hook ][ $priority ] ) ) {
			return array();
		}
		$out = array();
		foreach ( self::$hooks[ $hook ][ $priority ] as $item ) {
			$fn = $item['function'];
			if ( is_string( $fn ) ) {
				$out[] = $fn;
			} elseif ( is_array( $fn ) ) {
				$obj   = $fn[0] ?? null;
				$m     = (string) ( $fn[1] ?? '' );
				$out[] = ( is_object( $obj ) ? get_class( $obj ) : (string) $obj ) . '->' . $m;
			} else {
				$out[] = 'other';
			}
		}
		return $out;
	}

	/**
	 * @param mixed $callback Callback.
	 */
	private static function id( $callback ): string {
		if ( is_string( $callback ) ) {
			return $callback;
		}
		if ( is_array( $callback ) ) {
			$obj = $callback[0] ?? null;
			$m   = (string) ( $callback[1] ?? '' );
			if ( is_object( $obj ) ) {
				return spl_object_hash( $obj ) . '::' . $m;
			}
			return (string) $obj . '::' . $m;
		}
		return md5( serialize( $callback ) );
	}
}

$GLOBALS['wp_filter'] = array();

/**
 * Sync global $wp_filter shape used by count_callback.
 */
function stc_dss_guard_test_sync_wp_filter(): void {
	global $wp_filter;
	$wp_filter = array();
	foreach ( Stc_Dss_Guard_Test_Hooks::$hooks as $hook => $priorities ) {
		$obj                = new stdClass();
		$obj->callbacks     = $priorities;
		$wp_filter[ $hook ] = $obj;
	}
}

if ( ! function_exists( 'add_action' ) ) {
	function add_action( $hook, $callback, $priority = 10, $accepted_args = 1 ) {
		$ok = Stc_Dss_Guard_Test_Hooks::add( $hook, $callback, $priority, $accepted_args );
		stc_dss_guard_test_sync_wp_filter();
		return $ok;
	}
}

if ( ! function_exists( 'remove_action' ) ) {
	function remove_action( $hook, $callback, $priority = 10 ) {
		$ok = Stc_Dss_Guard_Test_Hooks::remove( $hook, $callback, $priority );
		stc_dss_guard_test_sync_wp_filter();
		return $ok;
	}
}

if ( ! function_exists( 'has_action' ) ) {
	function has_action( $hook, $callback = false ) {
		return Stc_Dss_Guard_Test_Hooks::has( $hook, $callback );
	}
}

if ( ! function_exists( 'is_admin' ) ) {
	function is_admin() {
		return ! empty( $GLOBALS['stc_dss_test_is_admin'] );
	}
}

/**
 * Fake do_action: run callbacks in priority order (fake-hook only).
 *
 * @param string $hook Hook name.
 */
function stc_dss_guard_test_do_action( $hook ): void {
	if ( empty( Stc_Dss_Guard_Test_Hooks::$hooks[ $hook ] ) ) {
		return;
	}
	$priorities = array_keys( Stc_Dss_Guard_Test_Hooks::$hooks[ $hook ] );
	sort( $priorities, SORT_NUMERIC );
	foreach ( $priorities as $priority ) {
		// Re-read group each time (mirrors live callback map mutation within a priority).
		foreach ( Stc_Dss_Guard_Test_Hooks::$hooks[ $hook ][ $priority ] ?? array() as $item ) {
			call_user_func( $item['function'] );
		}
	}
}

/**
 * Fake Elementor DesignSystemSync module.
 */
final class Stc_Fake_Dss_Module {
	public $calls = 0;

	public function enqueue_sync_stylesheet() {
		++$this->calls;
	}
}

require_once dirname( __DIR__ ) . '/elementor-dss-enqueue-guard.php';

$failures = 0;

/**
 * @param bool   $cond Condition.
 * @param string $msg  Message.
 */
function dss_guard_assert( bool $cond, string $msg ): void {
	global $failures;
	if ( ! $cond ) {
		echo "FAIL: {$msg}\n";
		++$failures;
		return;
	}
	echo "OK: {$msg}\n";
}

/**
 * @return Stc_Fake_Dss_Module
 */
function dss_guard_test_bootstrap_module(): Stc_Fake_Dss_Module {
	Stc_Dss_Guard_Test_Hooks::reset();
	stc_dss_guard_test_sync_wp_filter();
	streamit_child_elementor_dss_guard_reset_state_for_tests();
	$GLOBALS['stc_dss_test_is_admin'] = false;
	$module = new Stc_Fake_Dss_Module();
	streamit_child_elementor_dss_guard_module( $module );
	return $module;
}

/**
 * Run isolated PHP with optional defines. Returns stdout.
 *
 * @param array<string, mixed> $defines Constant name => value.
 * @param string               $body    PHP code after defines + require guard.
 */
function dss_guard_subprocess( array $defines, string $body ): string {
	$guard = dirname( __DIR__ ) . '/elementor-dss-enqueue-guard.php';
	$bits  = "<?php\n";
	$bits .= "define('ABSPATH', sys_get_temp_dir() . '/dss-sub/');\n";
	// Stubs must exist before the guard file registers its installer callback.
	$bits .= <<<'STUB'
final class Stc_Dss_Sub_Hooks {
	public static $hooks = array();
	public static function add( $h, $c, $p = 10 ) {
		self::$hooks[ $h ][ (int) $p ][ self::id( $c ) ] = array( 'function' => $c );
		return true;
	}
	public static function remove( $h, $c, $p = 10 ) {
		$id = self::id( $c );
		if ( empty( self::$hooks[ $h ][ (int) $p ][ $id ] ) ) {
			return false;
		}
		unset( self::$hooks[ $h ][ (int) $p ][ $id ] );
		return true;
	}
	public static function has( $h, $c = false ) {
		if ( empty( self::$hooks[ $h ] ) ) {
			return false;
		}
		if ( false === $c ) {
			return true;
		}
		$id = self::id( $c );
		foreach ( self::$hooks[ $h ] as $p => $g ) {
			if ( isset( $g[ $id ] ) ) {
				return (int) $p;
			}
		}
		return false;
	}
	public static function order( $h, $p ) {
		$o = array();
		foreach ( self::$hooks[ $h ][ (int) $p ] ?? array() as $i ) {
			$f = $i['function'];
			if ( is_string( $f ) ) {
				$o[] = $f;
			} elseif ( is_array( $f ) && is_object( $f[0] ) ) {
				$o[] = get_class( $f[0] ) . '->' . $f[1];
			} else {
				$o[] = '?';
			}
		}
		return $o;
	}
	private static function id( $c ) {
		if ( is_string( $c ) ) {
			return $c;
		}
		if ( is_array( $c ) && isset( $c[0], $c[1] ) && is_object( $c[0] ) ) {
			return spl_object_hash( $c[0] ) . '::' . $c[1];
		}
		return md5( serialize( $c ) );
	}
}
$GLOBALS['wp_filter'] = array();
function stc_dss_sub_sync_wp_filter() {
	global $wp_filter;
	$wp_filter = array();
	foreach ( Stc_Dss_Sub_Hooks::$hooks as $h => $ps ) {
		$o = new stdClass();
		$o->callbacks = $ps;
		$wp_filter[ $h ] = $o;
	}
}
function add_action( $h, $c, $p = 10, $a = 1 ) {
	$r = Stc_Dss_Sub_Hooks::add( $h, $c, $p );
	stc_dss_sub_sync_wp_filter();
	return $r;
}
function remove_action( $h, $c, $p = 10 ) {
	$r = Stc_Dss_Sub_Hooks::remove( $h, $c, $p );
	stc_dss_sub_sync_wp_filter();
	return $r;
}
function has_action( $h, $c = false ) {
	return Stc_Dss_Sub_Hooks::has( $h, $c );
}
function is_admin() {
	return false;
}
STUB;
	foreach ( $defines as $name => $value ) {
		$bits .= 'define(' . var_export( $name, true ) . ', ' . var_export( $value, true ) . ");\n";
	}
	$bits .= 'require ' . var_export( $guard, true ) . ";\n";
	$bits .= $body;

	$tmp = tempnam( sys_get_temp_dir(), 'dssg' );
	if ( false === $tmp ) {
		return "FAIL:tempnam\n";
	}
	$file = $tmp . '.php';
	rename( $tmp, $file );
	file_put_contents( $file, $bits );

	$php = defined( 'PHP_BINARY' ) && PHP_BINARY ? PHP_BINARY : 'php';
	$cmd = escapeshellarg( $php ) . ' -n ' . escapeshellarg( $file ) . ' 2>&1';
	$out = (string) shell_exec( $cmd );
	@unlink( $file );
	return $out;
}

// ---------------------------------------------------------------------------
// Pure decision + kill-switch parser (no constant)
// ---------------------------------------------------------------------------

dss_guard_assert(
	streamit_child_elementor_dss_should_skip_generate( false, array(), array() ),
	'skip: absent + empty inputs'
);
dss_guard_assert(
	! streamit_child_elementor_dss_should_skip_generate( true, array(), array() ),
	'no skip: CSS present'
);
dss_guard_assert(
	! streamit_child_elementor_dss_should_skip_generate( false, null, array() ),
	'fail open: non-array colors'
);

dss_guard_assert( streamit_child_elementor_dss_guard_parse_enabled( null ), 'parse: null → enabled' );
dss_guard_assert( streamit_child_elementor_dss_guard_parse_enabled( true ), 'parse: true → enabled' );
dss_guard_assert( ! streamit_child_elementor_dss_guard_parse_enabled( false ), 'parse: false → disabled' );
dss_guard_assert( streamit_child_elementor_dss_guard_parse_enabled( 1 ), 'parse: 1 → enabled' );
dss_guard_assert( ! streamit_child_elementor_dss_guard_parse_enabled( 0 ), 'parse: 0 → disabled' );
dss_guard_assert( streamit_child_elementor_dss_guard_parse_enabled( '1' ), "parse: '1' → enabled" );
dss_guard_assert( ! streamit_child_elementor_dss_guard_parse_enabled( '0' ), "parse: '0' → disabled" );
dss_guard_assert( ! streamit_child_elementor_dss_guard_parse_enabled( 'false' ), "parse: 'false' → disabled" );
dss_guard_assert( ! streamit_child_elementor_dss_guard_parse_enabled( 'FALSE' ), "parse: 'FALSE' → disabled" );
dss_guard_assert( streamit_child_elementor_dss_guard_parse_enabled( 'true' ), "parse: 'true' (invalid) → enabled default" );
dss_guard_assert( streamit_child_elementor_dss_guard_parse_enabled( 'no' ), "parse: 'no' (invalid) → enabled default" );

dss_guard_assert(
	streamit_child_elementor_dss_guard_enabled(),
	'undefined constant in this process → enabled'
);

// ---------------------------------------------------------------------------
// Disabled before install: no-op, order unchanged (subprocess + fake hooks)
// ---------------------------------------------------------------------------

$out = dss_guard_subprocess(
	array( 'STREAMIT_CHILD_ELEMENTOR_DSS_GUARD' => false ),
	<<<'PHP'
final class M { public function enqueue_sync_stylesheet() {} }
$m = new M();
streamit_child_elementor_dss_guard_reset_state_for_tests();
streamit_child_elementor_dss_guard_module( $m );
$p = streamit_child_elementor_dss_guard_priority();
add_action( 'wp_enqueue_scripts', 'stc_sentinel_before', $p );
add_action( 'wp_enqueue_scripts', array( $m, 'enqueue_sync_stylesheet' ), $p );
add_action( 'wp_enqueue_scripts', 'stc_sentinel_after', $p );
$before = Stc_Dss_Sub_Hooks::order( 'wp_enqueue_scripts', $p );
streamit_child_elementor_dss_guard_install();
streamit_child_elementor_dss_guard_install();
$after = Stc_Dss_Sub_Hooks::order( 'wp_enqueue_scripts', $p );
$ok = ( $before === $after
	&& ! streamit_child_elementor_dss_guard_needs_restore()
	&& 0 === streamit_child_elementor_dss_guard_count_callback( 'streamit_child_elementor_dss_guarded_enqueue' ) );
echo $ok ? "PASS\n" : "FAIL\n";
PHP
);
dss_guard_assert( false !== strpos( $out, "PASS\n" ), 'subprocess: disabled before install leaves order unchanged' );

// ---------------------------------------------------------------------------
// Disabled after wrap: restore once; second install idempotent (subprocess)
// ---------------------------------------------------------------------------

$out = dss_guard_subprocess(
	array( 'STREAMIT_CHILD_ELEMENTOR_DSS_GUARD' => false ),
	<<<'PHP'
final class M { public function enqueue_sync_stylesheet() {} }
$m = new M();
$p = streamit_child_elementor_dss_guard_priority();
streamit_child_elementor_dss_guard_reset_state_for_tests();
streamit_child_elementor_dss_guard_module( $m );
// Pretend wrap was installed (enabled path already ran).
add_action( 'wp_enqueue_scripts', 'streamit_child_elementor_dss_guarded_enqueue', $p );
$st = &streamit_child_elementor_dss_guard_state();
$st['wrap_active'] = true;
streamit_child_elementor_dss_guard_install();
$c1 = streamit_child_elementor_dss_guard_count_callback( array( $m, 'enqueue_sync_stylesheet' ) );
$g1 = streamit_child_elementor_dss_guard_count_callback( 'streamit_child_elementor_dss_guarded_enqueue' );
$prio1 = has_action( 'wp_enqueue_scripts', array( $m, 'enqueue_sync_stylesheet' ) );
streamit_child_elementor_dss_guard_install(); // installer one-shot
$c2 = streamit_child_elementor_dss_guard_count_callback( array( $m, 'enqueue_sync_stylesheet' ) );
$g2 = streamit_child_elementor_dss_guard_count_callback( 'streamit_child_elementor_dss_guarded_enqueue' );
$order1 = Stc_Dss_Sub_Hooks::order( 'wp_enqueue_scripts', $p );
streamit_child_elementor_dss_guard_restore_original(); // idempotent restore
$order2 = Stc_Dss_Sub_Hooks::order( 'wp_enqueue_scripts', $p );
$c3 = streamit_child_elementor_dss_guard_count_callback( array( $m, 'enqueue_sync_stylesheet' ) );
$prio3 = has_action( 'wp_enqueue_scripts', array( $m, 'enqueue_sync_stylesheet' ) );
$ok = ( 1 === $c1 && 0 === $g1 && $p === (int) $prio1
	&& 1 === $c2 && 0 === $g2
	&& 1 === $c3 && $p === (int) $prio3
	&& $order1 === $order2 );
echo $ok ? "PASS\n" : "FAIL\n";
PHP
);
dss_guard_assert( false !== strpos( $out, "PASS\n" ), 'subprocess: disabled after wrap restores once; installer/restore idempotent' );

// ---------------------------------------------------------------------------
// Constant variants via subprocess
// ---------------------------------------------------------------------------

$cases = array(
	array( array(), true, 'undefined → enabled' ),
	array( array( 'STREAMIT_CHILD_ELEMENTOR_DSS_GUARD' => true ), true, 'true → enabled' ),
	array( array( 'STREAMIT_CHILD_ELEMENTOR_DSS_GUARD' => false ), false, 'false → disabled' ),
	array( array( 'STREAMIT_CHILD_ELEMENTOR_DSS_GUARD' => 1 ), true, '1 → enabled' ),
	array( array( 'STREAMIT_CHILD_ELEMENTOR_DSS_GUARD' => 0 ), false, '0 → disabled' ),
	array( array( 'STREAMIT_CHILD_ELEMENTOR_DSS_GUARD' => '1' ), true, "'1' → enabled" ),
	array( array( 'STREAMIT_CHILD_ELEMENTOR_DSS_GUARD' => '0' ), false, "'0' → disabled" ),
	array( array( 'STREAMIT_CHILD_ELEMENTOR_DSS_GUARD' => 'false' ), false, "'false' → disabled" ),
);
foreach ( $cases as $case ) {
	list( $defines, $expect, $label ) = $case;
	$out = dss_guard_subprocess(
		$defines,
		'echo streamit_child_elementor_dss_guard_enabled() ? "1" : "0"; echo "\n";'
	);
	$got = trim( $out ) === '1';
	dss_guard_assert( $got === $expect, 'subprocess constant: ' . $label );
}

// ---------------------------------------------------------------------------
// Enabled install + hook-invoked installer (fake do_action — not real WP_Hook)
// ---------------------------------------------------------------------------

$module = dss_guard_test_bootstrap_module();
$prio   = streamit_child_elementor_dss_guard_priority();
add_action( 'wp_enqueue_scripts', 'streamit_child_elementor_dss_guard_install', PHP_INT_MIN );
add_action( 'wp_enqueue_scripts', array( $module, 'enqueue_sync_stylesheet' ), $prio );
// FAKE-HOOK: stc_dss_guard_test_do_action is not WP_Hook::apply_filters.
stc_dss_guard_test_do_action( 'wp_enqueue_scripts' );
stc_dss_guard_test_do_action( 'wp_enqueue_scripts' ); // installer one-shot

dss_guard_assert(
	1 === streamit_child_elementor_dss_guard_count_callback( 'streamit_child_elementor_dss_guarded_enqueue' ),
	'fake do_action install: wrap exactly once'
);
dss_guard_assert(
	0 === streamit_child_elementor_dss_guard_count_callback( array( $module, 'enqueue_sync_stylesheet' ) ),
	'fake do_action install: Module removed'
);
dss_guard_assert(
	$prio === (int) has_action( 'wp_enqueue_scripts', 'streamit_child_elementor_dss_guarded_enqueue' ),
	'fake do_action install: wrap at DSS priority'
);

// Restore idempotence in-process
streamit_child_elementor_dss_guard_restore_original();
$order1 = Stc_Dss_Guard_Test_Hooks::order_at( 'wp_enqueue_scripts', $prio );
streamit_child_elementor_dss_guard_restore_original();
$order2 = Stc_Dss_Guard_Test_Hooks::order_at( 'wp_enqueue_scripts', $prio );
dss_guard_assert( $order1 === $order2, 'restore_original twice does not reorder (idempotent)' );
dss_guard_assert(
	1 === streamit_child_elementor_dss_guard_count_callback( array( $module, 'enqueue_sync_stylesheet' ) ),
	'after restore: Module exactly once'
);

if ( $failures > 0 ) {
	echo "\n{$failures} failure(s)\n";
	exit( 1 );
}

echo "\nAll assertions passed.\n";
echo <<<'NOTE'

Kill switch STREAMIT_CHILD_ELEMENTOR_DSS_GUARD:
  undefined / true / 1 / '1'     → enabled
  false / 0 / '0' / 'false'      → disabled
  other strings                  → enabled (invalid → default)

NOTE;

exit( 0 );
