<?php
/**
 * Digits phone-login integration for Streamit child theme.
 *
 * - Enforces Streamit device limits on Digits login (not registration).
 * - Renders phone-login with the same structure/classes as streamit-login.
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
 * Match Streamit login button copy on the phone-login page.
 *
 * @param string $translated Translated text.
 * @param string $text       Original text.
 * @param string $domain     Text domain.
 * @return string
 */
function streamit_child_digits_login_strings( $translated, $text, $domain ) {
	if ( ! streamit_child_is_digits_login_page() || 'digits' !== $domain ) {
		return $translated;
	}

	if ( 'Continue' === $text ) {
		return esc_html__( 'Login', 'streamit' );
	}

	return $translated;
}
add_filter( 'gettext', 'streamit_child_digits_login_strings', 20, 3 );

/**
 * Streamit-style footer links below the Digits form.
 */
function streamit_child_digits_login_footer_markup() {
	global $streamit_options;

	ob_start();
	?>
	<div class="css_prefix-separator">
		<span class="or-section"><?php echo esc_html__( 'یا', 'streamit' ); ?></span>
	</div>
	<?php
	if ( ! empty( $streamit_options['streamit_signup_link'] ) ) :
		$signup_link  = streamit_signup_page_url();
		$signup_title = ( ! empty( $streamit_options['streamit_signup_title'] ) )
			? $streamit_options['streamit_signup_title']
			: esc_html__( 'Signup', 'streamit' );
		?>
		<div class="login-form-bottom">
			<div class="d-flex justify-content-center align-items-center gap-2 links my-3">
				<a href="<?php echo esc_url( $signup_link ); ?>" class="st-sub-card setting-dropdown">
					<h6 class="m-0 text-primary"><?php echo esc_html( $signup_title ); ?></h6>
				</a>
			</div>
		</div>
		<?php
	endif;

	if ( shortcode_exists( 'miniorange_social_login' ) ) :
		?>
		<div class="css_prefix-social-login-section">
			<?php echo do_shortcode( '[miniorange_social_login]' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
		</div>
		<?php
	endif;

	return ob_get_clean();
}

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
 * Wrap Digits shortcode output in Streamit login markup.
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
	<div class="streamit-login">
		<a href="<?php echo esc_url( home_url() ); ?>">
			<img class="img-fluid logo" src="<?php echo esc_url( $logo_url ); ?>" alt="<?php echo esc_attr( $site_name ); ?>">
		</a>

		<div class="digits-streamit-form">
			<div class="login-fields row">
				<div class="mb-3 position-relative col-md-12 digits-phone-field">
					<label class="digits-streamit-label" for="digits_phone">
						<?php esc_html_e( 'Phone Number', 'digits' ); ?>
					</label>
					<?php echo $content; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				</div>
			</div>

			<?php echo streamit_child_digits_login_footer_markup(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
		</div>
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
	$deps     = array( 'child-style' );

	foreach ( array( 'digits-login-style', 'digits-form', 'digits-form-style', 'digits-main' ) as $handle ) {
		if ( wp_style_is( $handle, 'registered' ) || wp_style_is( $handle, 'enqueued' ) ) {
			$deps[] = $handle;
		}
	}

	wp_enqueue_style(
		'streamit-child-digits-login',
		get_stylesheet_directory_uri() . '/assets/css/digits-login-rtl.css',
		$deps,
		file_exists( $css_path ) ? (string) filemtime( $css_path ) : '1.0'
	);
}
add_action( 'wp_enqueue_scripts', 'streamit_child_enqueue_digits_login_styles', 999 );
