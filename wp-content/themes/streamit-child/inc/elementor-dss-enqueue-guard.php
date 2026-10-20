<?php
/**
 * Compatibility shim: skip Elementor Design System Sync empty generate loop.
 *
 * Elementor 4.3.4 registers:
 *   Module::enqueue_sync_stylesheet on wp_enqueue_scripts @ priority 10
 * When design-system-sync.css is missing and parse_content() is empty, Base_File
 * still delete_option + update_option on _elementor_design_system_sync_css_meta
 * (~120ms+ remote MySQL writes per uncached front request).
 *
 * This shim wraps that callback on frontend only. It does not modify Elementor
 * core, Base_File, REST generate, or elementor/global_classes/update invalidation.
 *
 * Kill switch (deployment-controlled, no per-request DB read):
 *   define( 'STREAMIT_CHILD_ELEMENTOR_DSS_GUARD', false ); // in wp-config.php
 *
 * Parsed values (see streamit_child_elementor_dss_guard_parse_enabled()):
 *   undefined           → enabled (default)
 *   true, 1, '1'        → enabled
 *   false, 0, '0',
 *   'false' (any case)  → disabled
 *   other strings/types → enabled (invalid; same as default)
 *
 * When disabled and the wrap was never installed, install() is a no-op (does not
 * remove/re-add Elementor's callback). Restoration runs only if the wrap is active.
 *
 * Source assumptions (Elementor 4.3.4):
 * - Stylesheet_Manager::parse_content() only adds CSS from
 *   Variables_Provider::get_synced_color_css_entries() (array)
 *   and Classes_Provider::get_synced_typography_css_entries() (array keyed by device).
 * - Stylesheet_Manager::get_path() is the same path enqueue()/generate() use.
 * - Module remains active; we only wrap the frontend enqueue callback.
 *
 * Rollback: define the constant false, or remove this file's require in functions.php.
 *
 * @package streamit-child
 */

defined( 'ABSPATH' ) || exit;

/** Elementor 4.3.4 default priority for Module::enqueue_sync_stylesheet. */
if ( ! defined( 'STREAMIT_CHILD_ELEMENTOR_DSS_GUARD_PRIORITY' ) ) {
	define( 'STREAMIT_CHILD_ELEMENTOR_DSS_GUARD_PRIORITY', 10 );
}

/**
 * Request-local guard bookkeeping (no persistent / cross-request state).
 *
 * @return array{install_attempted: bool, wrap_active: bool, module: object|null, module_resolved: bool}
 */
function &streamit_child_elementor_dss_guard_state() {
	static $state = array(
		'install_attempted' => false,
		'wrap_active'       => false,
		'module'            => null,
		'module_resolved'   => false,
	);
	return $state;
}

/**
 * Reset request-local state (CLI tests only).
 */
function streamit_child_elementor_dss_guard_reset_state_for_tests() {
	$state                      = &streamit_child_elementor_dss_guard_state();
	$state['install_attempted'] = false;
	$state['wrap_active']       = false;
	$state['module']            = null;
	$state['module_resolved']   = false;
}

/**
 * Parse a kill-switch value into enabled/disabled.
 *
 * Does not use bare (bool) casts: the string 'false' disables the guard.
 * Unrecognized values default to enabled (same as undefined).
 *
 * @param mixed $value Raw constant value, or null when undefined.
 * @return bool True = guard enabled.
 */
function streamit_child_elementor_dss_guard_parse_enabled( $value ) {
	if ( null === $value ) {
		return true;
	}
	if ( is_bool( $value ) ) {
		return $value;
	}
	if ( is_int( $value ) || is_float( $value ) ) {
		if ( 1 === (int) $value || 1.0 === (float) $value ) {
			return true;
		}
		if ( 0 === (int) $value || 0.0 === (float) $value ) {
			return false;
		}
		return true;
	}
	if ( is_string( $value ) ) {
		$normalized = strtolower( trim( $value ) );
		if ( '1' === $normalized ) {
			return true;
		}
		if ( '0' === $normalized || 'false' === $normalized ) {
			return false;
		}
		// Invalid string (including 'true' as text): default enabled.
		return true;
	}
	return true;
}

/**
 * Whether the empty-sync enqueue guard is enabled.
 *
 * Default: enabled when STREAMIT_CHILD_ELEMENTOR_DSS_GUARD is undefined.
 * Define false / 0 / '0' / 'false' in wp-config.php to disable.
 *
 * @return bool
 */
function streamit_child_elementor_dss_guard_enabled() {
	if ( ! defined( 'STREAMIT_CHILD_ELEMENTOR_DSS_GUARD' ) ) {
		return streamit_child_elementor_dss_guard_parse_enabled( null );
	}
	return streamit_child_elementor_dss_guard_parse_enabled( STREAMIT_CHILD_ELEMENTOR_DSS_GUARD );
}

/**
 * Whether restoration is required (wrap registered or marked active after a successful replace).
 *
 * @return bool
 */
function streamit_child_elementor_dss_guard_needs_restore() {
	$state = streamit_child_elementor_dss_guard_state();
	if ( ! empty( $state['wrap_active'] ) ) {
		return true;
	}
	return false !== has_action( 'wp_enqueue_scripts', 'streamit_child_elementor_dss_guarded_enqueue' );
}

/**
 * Hook priority used for Elementor DSS enqueue and our wrap.
 *
 * @return int
 */
function streamit_child_elementor_dss_guard_priority() {
	return (int) STREAMIT_CHILD_ELEMENTOR_DSS_GUARD_PRIORITY;
}

/**
 * Decision helper (pure): skip generate only when CSS absent and sync inputs empty.
 *
 * @param bool  $css_exists         Whether design-system-sync.css exists.
 * @param mixed $color_entries      From get_synced_color_css_entries().
 * @param mixed $typography_entries From get_synced_typography_css_entries().
 * @return bool True = skip Elementor generate/enqueue path.
 */
function streamit_child_elementor_dss_should_skip_generate( $css_exists, $color_entries, $typography_entries ) {
	if ( $css_exists ) {
		return false;
	}
	if ( ! is_array( $color_entries ) || ! is_array( $typography_entries ) ) {
		// Unknown shape — do not skip (preserve Elementor behavior).
		return false;
	}
	if ( ! empty( $color_entries ) ) {
		return false;
	}
	foreach ( $typography_entries as $entries ) {
		if ( ! empty( $entries ) ) {
			return false;
		}
	}
	return true;
}

/**
 * Whether the guard may install on this request (frontend only).
 *
 * @return bool
 */
function streamit_child_elementor_dss_guard_is_eligible_request() {
	if ( is_admin() ) {
		return false;
	}
	if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) {
		return false;
	}
	return true;
}

/**
 * Resolve CSS path the same way Elementor enqueue does.
 *
 * @return string|null Absolute path or null if Elementor APIs unavailable.
 */
function streamit_child_elementor_dss_css_path() {
	if ( ! class_exists( '\Elementor\Modules\DesignSystemSync\Classes\Stylesheet_Manager' ) ) {
		return null;
	}
	$manager = new \Elementor\Modules\DesignSystemSync\Classes\Stylesheet_Manager();
	$path    = $manager->get_path();
	return is_string( $path ) && '' !== $path ? $path : null;
}

/**
 * Load parse_content inputs via Elementor's provider APIs.
 *
 * @return array{0: array|null, 1: array|null} Color entries, typography entries (null = unavailable).
 */
function streamit_child_elementor_dss_sync_inputs() {
	if ( ! class_exists( '\Elementor\Modules\DesignSystemSync\Classes\Variables_Provider' )
		|| ! class_exists( '\Elementor\Modules\DesignSystemSync\Classes\Classes_Provider' )
	) {
		return array( null, null );
	}
	if ( ! method_exists( '\Elementor\Modules\DesignSystemSync\Classes\Variables_Provider', 'get_synced_color_css_entries' )
		|| ! method_exists( '\Elementor\Modules\DesignSystemSync\Classes\Classes_Provider', 'get_synced_typography_css_entries' )
	) {
		return array( null, null );
	}

	$colors = \Elementor\Modules\DesignSystemSync\Classes\Variables_Provider::get_synced_color_css_entries();
	$typo   = \Elementor\Modules\DesignSystemSync\Classes\Classes_Provider::get_synced_typography_css_entries();

	if ( ! is_array( $colors ) || ! is_array( $typo ) ) {
		return array( null, null );
	}

	return array( $colors, $typo );
}

/**
 * Guarded replacement for Module::enqueue_sync_stylesheet.
 */
function streamit_child_elementor_dss_guarded_enqueue() {
	$module = streamit_child_elementor_dss_guard_module();
	if ( ! $module || ! method_exists( $module, 'enqueue_sync_stylesheet' ) ) {
		return;
	}

	$path = streamit_child_elementor_dss_css_path();
	if ( null === $path ) {
		$module->enqueue_sync_stylesheet();
		return;
	}

	$css_exists = file_exists( $path );
	if ( $css_exists ) {
		$module->enqueue_sync_stylesheet();
		return;
	}

	list( $colors, $typo ) = streamit_child_elementor_dss_sync_inputs();
	if ( null === $colors || null === $typo ) {
		$module->enqueue_sync_stylesheet();
		return;
	}

	if ( streamit_child_elementor_dss_should_skip_generate( false, $colors, $typo ) ) {
		return;
	}

	$module->enqueue_sync_stylesheet();
}

/**
 * DesignSystemSync Module instance (request-cached).
 *
 * @param object|null|false $override For tests: object to use, or false to clear resolution.
 * @return object|null
 */
function streamit_child_elementor_dss_guard_module( $override = null ) {
	$state = &streamit_child_elementor_dss_guard_state();

	if ( false === $override ) {
		$state['module']          = null;
		$state['module_resolved'] = false;
		return null;
	}
	if ( null !== $override ) {
		$state['module']          = is_object( $override ) ? $override : null;
		$state['module_resolved'] = true;
		return $state['module'];
	}

	if ( $state['module_resolved'] ) {
		return $state['module'];
	}
	$state['module_resolved'] = true;
	if ( ! class_exists( '\Elementor\Plugin' ) || ! isset( \Elementor\Plugin::$instance->modules_manager ) ) {
		$state['module'] = null;
		return null;
	}
	$mod             = \Elementor\Plugin::$instance->modules_manager->get_modules( 'design-system-sync' );
	$state['module'] = is_object( $mod ) ? $mod : null;
	return $state['module'];
}

/**
 * Ensure Elementor's original enqueue callback is registered exactly once at the
 * DSS priority, and our wrap is absent.
 *
 * Idempotent: if the wrap is already gone and Module is already registered once
 * at the expected priority, this is a no-op (does not reorder).
 *
 * @return bool True when the original callback is present afterward.
 */
function streamit_child_elementor_dss_guard_restore_original() {
	$module   = streamit_child_elementor_dss_guard_module();
	$priority = streamit_child_elementor_dss_guard_priority();

	remove_action( 'wp_enqueue_scripts', 'streamit_child_elementor_dss_guarded_enqueue', $priority );

	$state                = &streamit_child_elementor_dss_guard_state();
	$state['wrap_active'] = false;

	if ( ! $module || ! method_exists( $module, 'enqueue_sync_stylesheet' ) ) {
		return false;
	}

	$callback = array( $module, 'enqueue_sync_stylesheet' );
	$count    = streamit_child_elementor_dss_guard_count_callback( $callback );
	$at_prio  = has_action( 'wp_enqueue_scripts', $callback );

	if ( 1 === $count && false !== $at_prio && (int) $at_prio === $priority ) {
		return true;
	}

	// Collapse duplicates / wrong priority, then register once.
	$existing_prio = $at_prio;
	while ( false !== $existing_prio ) {
		remove_action( 'wp_enqueue_scripts', $callback, (int) $existing_prio );
		$existing_prio = has_action( 'wp_enqueue_scripts', $callback );
	}
	add_action( 'wp_enqueue_scripts', $callback, $priority );

	return false !== has_action( 'wp_enqueue_scripts', $callback );
}

/**
 * Count how many times a callback appears on wp_enqueue_scripts (all priorities).
 *
 * @param callable $callback Callback.
 * @return int
 */
function streamit_child_elementor_dss_guard_count_callback( $callback ) {
	global $wp_filter;
	if ( empty( $wp_filter['wp_enqueue_scripts'] ) || ! is_object( $wp_filter['wp_enqueue_scripts'] ) ) {
		return 0;
	}
	$count = 0;
	foreach ( $wp_filter['wp_enqueue_scripts']->callbacks as $group ) {
		if ( ! is_array( $group ) ) {
			continue;
		}
		foreach ( $group as $item ) {
			if ( empty( $item['function'] ) ) {
				continue;
			}
			if ( $item['function'] === $callback ) {
				++$count;
				continue;
			}
			// Array callables: compare class/object + method.
			if ( is_array( $callback ) && is_array( $item['function'] )
				&& ( $item['function'][1] ?? null ) === ( $callback[1] ?? null )
			) {
				$a = $item['function'][0] ?? null;
				$b = $callback[0] ?? null;
				if ( $a === $b || ( is_object( $a ) && is_object( $b ) && $a === $b ) ) {
					++$count;
				}
			}
		}
	}
	return $count;
}

/**
 * Install or skip the frontend wrap according to the kill switch.
 *
 * When disabled: no-op unless the wrap was installed (then restore once).
 * When enabled: replace Module callback with guarded wrap once.
 */
function streamit_child_elementor_dss_guard_install() {
	$state = &streamit_child_elementor_dss_guard_state();
	if ( $state['install_attempted'] ) {
		return;
	}
	$state['install_attempted'] = true;

	if ( ! streamit_child_elementor_dss_guard_enabled() ) {
		if ( streamit_child_elementor_dss_guard_needs_restore() ) {
			streamit_child_elementor_dss_guard_restore_original();
		}
		return;
	}

	if ( ! streamit_child_elementor_dss_guard_is_eligible_request() ) {
		return;
	}

	$module = streamit_child_elementor_dss_guard_module();
	if ( ! $module ) {
		return;
	}

	$priority = streamit_child_elementor_dss_guard_priority();
	$callback = array( $module, 'enqueue_sync_stylesheet' );

	$removed = remove_action( 'wp_enqueue_scripts', $callback, $priority );
	if ( ! $removed ) {
		// Original not at expected priority — do not add a wrap (avoid duplicates / orphans).
		return;
	}

	add_action( 'wp_enqueue_scripts', 'streamit_child_elementor_dss_guarded_enqueue', $priority );
	$state['wrap_active'] = true;
}

add_action( 'wp_enqueue_scripts', 'streamit_child_elementor_dss_guard_install', PHP_INT_MIN );
