<?php
/**
 * Plugin Name: Media Site Access
 * Description: Site-wide free/paid media access. Always requires login. Free = any logged-in user; Paid = active PMPro level from the configured list. Full catalog either way.
 * Version: 1.0.0
 *
 * @package movies-wp
 */

defined( 'ABSPATH' ) || exit;

/**
 * Site access mode: free | paid.
 *
 * @return string
 */
function movies_wp_site_access_mode() {
	$mode = get_option( 'movies_wp_site_access_mode', 'free' );
	$mode = is_string( $mode ) ? strtolower( $mode ) : 'free';
	return in_array( $mode, array( 'free', 'paid' ), true ) ? $mode : 'free';
}

/**
 * PMPro level IDs that unlock the catalog in paid mode.
 *
 * @return int[]
 */
function movies_wp_paid_level_ids() {
	$ids = get_option( 'movies_wp_paid_level_ids', array() );
	if ( ! is_array( $ids ) ) {
		$ids = array();
	}
	return array_values( array_unique( array_filter( array_map( 'intval', $ids ) ) ) );
}

/**
 * Whether the user may play/download media (site-wide policy).
 *
 * Rules:
 * - Not logged in → false
 * - Administrator → true
 * - Mode free → true (any logged-in user)
 * - Mode paid → true if user has any configured paid PMPro level
 *
 * @param int|null $user_id User ID or null for current user.
 * @return bool
 */
function movies_wp_user_can_access_media( $user_id = null ) {
	$user_id = null !== $user_id ? (int) $user_id : get_current_user_id();

	if ( $user_id <= 0 ) {
		return false;
	}

	if ( user_can( $user_id, 'administrator' ) ) {
		return true;
	}

	if ( 'free' === movies_wp_site_access_mode() ) {
		return true;
	}

	$levels = movies_wp_paid_level_ids();
	if ( empty( $levels ) ) {
		return false;
	}

	if ( ! function_exists( 'pmpro_hasMembershipLevel' ) ) {
		return false;
	}

	return (bool) pmpro_hasMembershipLevel( $levels, $user_id );
}

/**
 * Subscribe / levels URL when paid access is denied.
 *
 * @return string
 */
function movies_wp_media_subscribe_url() {
	if ( function_exists( 'streamit_subscribe_page_url' ) ) {
		$url = streamit_subscribe_page_url();
		if ( $url ) {
			return $url;
		}
	}
	if ( function_exists( 'pmpro_url' ) ) {
		$url = pmpro_url( 'levels' );
		if ( $url ) {
			return $url;
		}
	}
	return home_url( '/' );
}

/**
 * Register settings.
 */
function movies_wp_site_access_register_settings() {
	register_setting(
		'movies_wp_site_access',
		'movies_wp_site_access_mode',
		array(
			'type'              => 'string',
			'sanitize_callback' => 'movies_wp_site_access_sanitize_mode',
			'default'           => 'free',
		)
	);

	register_setting(
		'movies_wp_site_access',
		'movies_wp_paid_level_ids',
		array(
			'type'              => 'array',
			'sanitize_callback' => 'movies_wp_site_access_sanitize_level_ids',
			'default'           => array(),
		)
	);
}
add_action( 'admin_init', 'movies_wp_site_access_register_settings' );

/**
 * @param mixed $mode Raw mode.
 * @return string
 */
function movies_wp_site_access_sanitize_mode( $mode ) {
	$mode = is_string( $mode ) ? strtolower( $mode ) : 'free';
	return in_array( $mode, array( 'free', 'paid' ), true ) ? $mode : 'free';
}

/**
 * @param mixed $ids Raw level IDs.
 * @return int[]
 */
function movies_wp_site_access_sanitize_level_ids( $ids ) {
	if ( ! is_array( $ids ) ) {
		return array();
	}
	return array_values( array_unique( array_filter( array_map( 'intval', $ids ) ) ) );
}

/**
 * Admin menu: Settings → Media Access, and Memberships → Media Access (same UI).
 */
function movies_wp_site_access_admin_menu() {
	add_options_page(
		__( 'Media Access', 'movies-wp' ),
		__( 'Media Access', 'movies-wp' ),
		'manage_options',
		'movies-wp-media-access',
		'movies_wp_site_access_render_settings_page'
	);

	// PMPro top-level parent is pmpro-dashboard.
	if ( defined( 'PMPRO_VERSION' ) || function_exists( 'pmpro_getAllLevels' ) ) {
		add_submenu_page(
			'pmpro-dashboard',
			__( 'Media Access', 'movies-wp' ),
			__( 'Media Access', 'movies-wp' ),
			'manage_options',
			'movies-wp-media-access-pmpro',
			'movies_wp_site_access_render_settings_page'
		);
	}
}
add_action( 'admin_menu', 'movies_wp_site_access_admin_menu', 60 );

/**
 * Settings page UI.
 */
function movies_wp_site_access_render_settings_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}

	$mode       = movies_wp_site_access_mode();
	$selected   = movies_wp_paid_level_ids();
	$pmpro_ok   = function_exists( 'pmpro_getAllLevels' );
	$levels     = $pmpro_ok ? pmpro_getAllLevels( true, true ) : array();
	?>
	<div class="wrap">
		<h1><?php esc_html_e( 'Media Access', 'movies-wp' ); ?></h1>
		<p>
			<?php esc_html_e( 'Controls who can play and download the full catalog. Login is always required. Plans only differ by duration/price — not by which titles they unlock.', 'movies-wp' ); ?>
		</p>

		<form method="post" action="options.php">
			<?php settings_fields( 'movies_wp_site_access' ); ?>

			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><?php esc_html_e( 'Site mode', 'movies-wp' ); ?></th>
					<td>
						<fieldset>
							<label>
								<input type="radio" name="movies_wp_site_access_mode" value="free" <?php checked( $mode, 'free' ); ?> />
								<strong><?php esc_html_e( 'Free', 'movies-wp' ); ?></strong>
								— <?php esc_html_e( 'Any logged-in user can watch and download everything.', 'movies-wp' ); ?>
							</label>
							<br /><br />
							<label>
								<input type="radio" name="movies_wp_site_access_mode" value="paid" <?php checked( $mode, 'paid' ); ?> />
								<strong><?php esc_html_e( 'Paid', 'movies-wp' ); ?></strong>
								— <?php esc_html_e( 'Logged-in users need an active membership from the list below.', 'movies-wp' ); ?>
							</label>
						</fieldset>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Paid membership levels', 'movies-wp' ); ?></th>
					<td>
						<?php if ( ! $pmpro_ok ) : ?>
							<p class="description">
								<?php esc_html_e( 'Paid Memberships Pro is not active. Activate it to select levels.', 'movies-wp' ); ?>
							</p>
						<?php elseif ( empty( $levels ) ) : ?>
							<p class="description">
								<?php esc_html_e( 'No PMPro levels found. Create 1-month / 3-month plans first.', 'movies-wp' ); ?>
							</p>
						<?php else : ?>
							<fieldset>
								<?php foreach ( $levels as $level ) : ?>
									<?php
									$level_id = isset( $level->id ) ? (int) $level->id : 0;
									$name     = isset( $level->name ) ? (string) $level->name : ( 'Level #' . $level_id );
									if ( $level_id <= 0 ) {
										continue;
									}
									?>
									<label style="display:block;margin-bottom:6px;">
										<input
											type="checkbox"
											name="movies_wp_paid_level_ids[]"
											value="<?php echo esc_attr( (string) $level_id ); ?>"
											<?php checked( in_array( $level_id, $selected, true ) ); ?>
										/>
										<?php echo esc_html( sprintf( '%s (ID %d)', $name, $level_id ) ); ?>
									</label>
								<?php endforeach; ?>
							</fieldset>
							<p class="description">
								<?php esc_html_e( 'Check every plan that should unlock the full catalog (e.g. 1 month, 3 months). Do not check a “free forever” level unless you intend that to count as paid access.', 'movies-wp' ); ?>
							</p>
						<?php endif; ?>
					</td>
				</tr>
			</table>

			<?php submit_button( __( 'Save media access settings', 'movies-wp' ) ); ?>
		</form>

		<hr />
		<p class="description">
			<?php
			printf(
				/* translators: %s: current mode */
				esc_html__( 'Current mode: %s', 'movies-wp' ),
				'<code>' . esc_html( $mode ) . '</code>'
			);
			?>
			<?php if ( 'paid' === $mode && empty( $selected ) ) : ?>
				<br />
				<span style="color:#b32d2e;">
					<?php esc_html_e( 'Warning: Paid mode is on but no levels are selected — nobody except admins will get media access.', 'movies-wp' ); ?>
				</span>
			<?php endif; ?>
		</p>
	</div>
	<?php
}

/**
 * Admin notice if paid mode has no levels.
 */
function movies_wp_site_access_admin_notice() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	if ( 'paid' !== movies_wp_site_access_mode() ) {
		return;
	}
	if ( ! empty( movies_wp_paid_level_ids() ) ) {
		return;
	}
	$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
	if ( $screen && in_array( $screen->id, array( 'settings_page_movies-wp-media-access', 'memberships_page_movies-wp-media-access-pmpro' ), true ) ) {
		return;
	}
	$url = admin_url( 'options-general.php?page=movies-wp-media-access' );
	echo '<div class="notice notice-warning"><p>';
	echo esc_html__( 'Media Access is in Paid mode but no membership levels are selected.', 'movies-wp' );
	echo ' <a href="' . esc_url( $url ) . '">' . esc_html__( 'Fix settings', 'movies-wp' ) . '</a>';
	echo '</p></div>';
}
add_action( 'admin_notices', 'movies_wp_site_access_admin_notice' );
