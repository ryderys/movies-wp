<?php
/**
 * Digits phone-login integration for Streamit child theme.
 *
 * - Enforces Streamit device limits on Digits login (not registration).
 * - Renders phone-login with Streamit login layout/styling only (fields stay Digits-controlled).
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
 * Raw page source used to detect which login shortcode a page uses.
 *
 * @param WP_Post|null $post Optional page object.
 * @return string
 */
function streamit_child_get_page_source_content( $post = null ) {
	if ( null === $post ) {
		$post = get_queried_object();
	}

	if ( ! $post instanceof WP_Post ) {
		return '';
	}

	$chunks = array( (string) $post->post_content );

	$elementor_data = get_post_meta( $post->ID, '_elementor_data', true );
	if ( is_string( $elementor_data ) && '' !== $elementor_data ) {
		$chunks[] = $elementor_data;
	}

	return implode( "\n", $chunks );
}

/**
 * Whether the current page is Streamit's native username/password sign-in page.
 *
 * @param WP_Post|null $post Optional page object.
 * @return bool
 */
function streamit_child_is_streamit_signin_page( $post = null ) {
	if ( null === $post ) {
		$post = get_queried_object();
	}

	return streamit_child_page_uses_streamit_login( $post );
}

/**
 * Whether a page renders the native Streamit login shortcode.
 *
 * @param WP_Post|null $post Optional page object.
 * @return bool
 */
function streamit_child_page_uses_streamit_login( $post = null ) {
	if ( null === $post ) {
		$post = get_queried_object();
	}

	if ( ! $post instanceof WP_Post || 'page' !== $post->post_type ) {
		return false;
	}

	$source = streamit_child_get_page_source_content( $post );

	return has_shortcode( $source, 'streamit_login_form' )
		|| false !== strpos( $source, '[streamit_login_form' )
		|| false !== strpos( $source, '"streamit_login_form"' );
}

/**
 * Whether a page renders the Digits login shortcode.
 *
 * @param WP_Post|null $post Optional page object.
 * @return bool
 */
function streamit_child_page_uses_digits_login( $post = null ) {
	if ( null === $post ) {
		$post = get_queried_object();
	}

	if ( ! $post instanceof WP_Post || 'page' !== $post->post_type ) {
		return false;
	}

	$source = streamit_child_get_page_source_content( $post );

	return has_shortcode( $source, 'df-form-login' )
		|| false !== strpos( $source, '[df-form-login' )
		|| false !== strpos( $source, '"df-form-login"' );
}

/**
 * Whether the current front-end view is the Digits phone-login page only.
 */
function streamit_child_is_digits_login_page() {
	if ( is_admin() || streamit_child_is_streamit_signin_page() ) {
		return false;
	}

	if ( streamit_child_page_uses_digits_login() ) {
		return true;
	}

	// Slug fallback for pages built outside post_content/Elementor data.
	return is_page( 'phone-login' );
}

/**
 * Whether rendered markup is a Digits login form (not Streamit login).
 *
 * @param string $content Rendered HTML.
 * @return bool
 */
function streamit_child_content_is_digits_login_form( $content ) {
	if ( empty( $content ) ) {
		return false;
	}

	if ( false !== strpos( $content, 'streamit-login-form' ) || false !== strpos( $content, 'id="streamit-login-form"' ) ) {
		return false;
	}

	return false !== strpos( $content, 'digits_ui' )
		|| false !== strpos( $content, 'digits-form_container' )
		|| false !== strpos( $content, 'digits_embed-form' )
		|| false !== strpos( $content, 'digits-form_login' );
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
 * Split Digits form HTML into field markup and submit button block.
 *
 * @param string $output Digits shortcode HTML.
 * @return array{fields:string,submit:string}
 */
function streamit_child_split_digits_login_output( $output ) {
	$fields = $output;
	$submit = '';

	if ( preg_match( '/<div[^>]*class="[^"]*digits-form_button_row[^"]*"[^>]*>.*?<\/div>/is', $output, $match ) ) {
		$submit = $match[0];
		$fields = str_replace( $submit, '', $output );
	} elseif ( preg_match( '/<button[^>]*class="[^"]*digits-form_button[^"]*"[^>]*>.*?<\/button>/is', $output, $match ) ) {
		$submit = $match[0];
		$fields = str_replace( $submit, '', $output );
	}

	if ( '' !== $submit ) {
		$submit = preg_replace(
			'/(<button[^>]*class=")([^"]*)(")/i',
			'$1$2 btn btn-primary w-100$3',
			$submit,
			1
		);
		$submit = '<div class="submit">' . $submit . '</div>';
	}

	return array(
		'fields' => trim( $fields ),
		'submit' => $submit,
	);
}

/**
 * Build Streamit login shell around Digits markup.
 *
 * @param string $inner_html   Digits HTML to place inside the shell.
 * @param bool   $split_submit Whether to pull the submit button into .submit.
 * @return string
 */
function streamit_child_build_digits_login_shell( $inner_html, $split_submit = true ) {
	$parts = $split_submit ? streamit_child_split_digits_login_output( $inner_html ) : array(
		'fields' => $inner_html,
		'submit' => '',
	);

	if ( $split_submit && '' === $parts['submit'] ) {
		$parts['fields'] = $inner_html;
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

		<div id="streamit-digits-login-form" class="digits-streamit-form">
			<div class="login-fields row">
				<div class="mb-3 position-relative col-md-12 digits-form-field">
					<?php echo $parts['fields']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				</div>
			</div>

			<?php echo $parts['submit']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>

			<?php echo streamit_child_digits_login_footer_markup(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
		</div>
	</div>
	<?php
	return ob_get_clean();
}

/**
 * Wrap rendered page content that contains a Digits login form.
 *
 * @param string $content Rendered content.
 * @return string
 */
function streamit_child_wrap_digits_login_content( $content ) {
	if ( ! streamit_child_is_digits_login_page() || streamit_child_is_streamit_signin_page() ) {
		return $content;
	}

	if ( false !== strpos( $content, 'streamit-digits-login' ) || false !== strpos( $content, 'streamit-login-form' ) ) {
		return $content;
	}

	if ( ! streamit_child_content_is_digits_login_form( $content ) ) {
		return $content;
	}

	return streamit_child_build_digits_login_shell( $content );
}

/**
 * Wrap Digits shortcode output.
 *
 * @param string $output Shortcode HTML.
 * @param string $tag    Shortcode tag.
 * @return string
 */
function streamit_child_wrap_digits_login_shortcode( $output, $tag ) {
	if ( ! in_array( $tag, array( 'df-form-login', 'df-form' ), true ) || streamit_child_is_streamit_signin_page() ) {
		return $output;
	}

	if ( empty( $output ) || ! streamit_child_content_is_digits_login_form( $output ) ) {
		return $output;
	}

	return streamit_child_build_digits_login_shell( $output );
}
add_filter( 'do_shortcode_tag', 'streamit_child_wrap_digits_login_shortcode', 20, 2 );
add_filter( 'the_content', 'streamit_child_wrap_digits_login_content', 999 );
add_filter( 'elementor/frontend/the_content', 'streamit_child_wrap_digits_login_content', 999 );
add_filter( 'elementor/widget/render_content', 'streamit_child_wrap_digits_login_content', 999 );

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
 * Enqueue phone-login styles on the Digits page only.
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

