<?php
/**
 * Digits phone-login integration for Streamit child theme.
 *
 * - Enforces Streamit device limits on Digits login (not registration).
 * - Styles the phone-login page to match Streamit auth UI.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Whether the current request is a Digits login AJAX call.
 */
function streamit_child_is_digits_login_request() {
	if ( ! wp_doing_ajax() ) {
		return false;
	}

	$action = isset( $_REQUEST['action'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['action'] ) ) : '';
	$type   = isset( $_REQUEST['type'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['type'] ) ) : '';

	return ( 'digits_forms_ajax' === $action && 'login' === $type );
}

/**
 * Whether the current front-end view is the configured sign-in page.
 */
function streamit_child_is_digits_login_page() {
	if ( is_admin() ) {
		return false;
	}

	if ( is_page( 'phone-login' ) ) {
		return true;
	}

	global $streamit_options;

	if ( ! empty( $streamit_options['streamit_signin_link'] ) ) {
		return is_page( (int) $streamit_options['streamit_signin_link'] );
	}

	return false;
}

/**
 * Apply Streamit device limits to Digits login only.
 *
 * @param WP_Error $validation_error Existing validation errors.
 * @param WP_User  $user             User attempting to log in.
 * @return WP_Error
 */
function streamit_child_digits_login_device_limit( $validation_error, $user ) {
	if ( ! streamit_child_is_digits_login_request() ) {
		return $validation_error;
	}

	if ( ! $user instanceof WP_User || ! function_exists( 'streamit_can_add_device' ) ) {
		return $validation_error;
	}

	$account_status = get_user_meta( $user->ID, 'account_status', true );
	if ( ! empty( $account_status ) && 'active' !== $account_status ) {
		$validation_error->add(
			'account_inactive',
			esc_html__( 'Your account is not active. Please contact support.', 'streamit' )
		);
		return $validation_error;
	}

	$can_add_device = streamit_can_add_device( $user->ID, 'web' );

	if ( true === $can_add_device ) {
		return $validation_error;
	}

	if ( is_array( $can_add_device ) && ! empty( $can_add_device['error_code'] ) ) {
		switch ( $can_add_device['error_code'] ) {
			case 'LOGIN_NOT_ALLOWED':
				$message = esc_html__( 'Login not allowed on this platform for your membership plan.', 'streamit' );
				break;
			case 'LOGIN_LIMIT_EXCEEDED':
				$message = esc_html__( 'Login limit exceeded. Please remove a device to continue.', 'streamit' );
				break;
			default:
				$message = esc_html__( 'Login not allowed. Please check your membership plan settings.', 'streamit' );
				break;
		}
	} else {
		$message = esc_html__( 'Login not allowed. Please check your membership plan settings.', 'streamit' );
	}

	$validation_error->add( 'streamit_device_limit', $message );

	return $validation_error;
}
add_filter( 'digits_check_user_login', 'streamit_child_digits_login_device_limit', 20, 2 );

/**
 * Add a body class on the phone-login page.
 *
 * @param string[] $classes Body classes.
 * @return string[]
 */
function streamit_child_digits_login_body_class( $classes ) {
	if ( streamit_child_is_digits_login_page() ) {
		$classes[] = 'streamit-digits-login-page';
	}

	return $classes;
}
add_filter( 'body_class', 'streamit_child_digits_login_body_class' );

/**
 * Wrap Digits shortcode output in Streamit auth markup.
 *
 * @param string $content Post content.
 * @return string
 */
function streamit_child_wrap_digits_login_content( $content ) {
	if ( ! streamit_child_is_digits_login_page() || ! in_the_loop() || ! is_main_query() ) {
		return $content;
	}

	if ( false === strpos( $content, 'digits' ) && false === strpos( $content, 'df-form' ) ) {
		return $content;
	}

	global $streamit_options;

	$site_name = get_bloginfo( 'name' );
	$logo_url  = ( ! empty( $streamit_options['streamit_logo']['url'] ) )
		? esc_url( $streamit_options['streamit_logo']['url'] )
		: esc_url( get_template_directory_uri() . '/static/assets/images/logo.png' );

	ob_start();
	?>
	<div class="streamit-login streamit-digits-login">
		<a href="<?php echo esc_url( home_url() ); ?>">
			<img class="img-fluid logo" src="<?php echo esc_url( $logo_url ); ?>" alt="<?php echo esc_attr( $site_name ); ?>">
		</a>
		<?php echo $content; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
	</div>
	<?php
	return ob_get_clean();
}
add_filter( 'the_content', 'streamit_child_wrap_digits_login_content', 20 );

/**
 * Enqueue phone-login styles on the sign-in page only.
 */
function streamit_child_enqueue_digits_login_styles() {
	if ( ! streamit_child_is_digits_login_page() ) {
		return;
	}

	$css_path = get_stylesheet_directory() . '/assets/css/digits-login-rtl.css';

	wp_enqueue_style(
		'streamit-child-digits-login',
		get_stylesheet_directory_uri() . '/assets/css/digits-login-rtl.css',
		array( 'child-style' ),
		file_exists( $css_path ) ? (string) filemtime( $css_path ) : '1.0'
	);
}
add_action( 'wp_enqueue_scripts', 'streamit_child_enqueue_digits_login_styles', 100 );
