<?php

/**
 * Class streamit_pmp_Helper
 *
 * Handles authentication form customizations and shortcode tasks for the theme or plugin.
 *
 * @package streamit
 */
if (! class_exists('streamit_pmp_Helper')) {

	/**
	 * streamit_pmp_Helper class.
	 *
	 * This class manages hooks, filters, and shortcode handling for login forms and related functionalities.
	 */
	class streamit_pmp_Helper
	{

		/**
		 * streamit_pmp_Helper constructor.
		 *
		 * Adds filters to customize login and register forms.
		 */
		public function __construct()
		{
			$this->add_filters();
		}

		/**
		 * Adds necessary filters for customizing login and registration forms.
		 *
		 * @return void
		 */
		public function add_filters()
		{
			add_filter('login_form_top',                [$this, 'st_login_header'], 10);
			add_filter('pms_register_form_top',         [$this, 'st_login_header'], 10);
			add_filter('login_form_middle',             [$this, 'st_forgot_password'], 11);
			add_filter('login_form_bottom',             [$this, 'st_social_login'], 11);
			add_filter('pmpro_login_forms_handler_nav', [$this, 'st_pmpro_login_form_bottom'], 10, 2);
			add_filter('pmpro_actions_nav_separator',   [$this, 'st_pmpro_remove_separator'], 10, 2);
			add_filter('pmpro_element_class',           [$this, 'st_pmpro_element_class'], 10, 2);
		}

		/**
		 * Retrieves the current shortcode from the post content.
		 *
		 * Scans the current post for shortcodes and returns the first match.
		 *
		 * @return string The current shortcode found in the post content.
		 */
		public function current_shortcode()
		{
			global $post;

			$current_shortcode = '';

			if (isset($post->post_content)) {
				preg_match_all('/' . get_shortcode_regex() . '/', $post->post_content, $matches, PREG_SET_ORDER);
				foreach ($matches as $shortcode) {
					if (strpos($shortcode[0], '[' . $shortcode[2]) === 0) {
						$current_shortcode = $shortcode[2];
						break;
					}
				}
			}

			return $current_shortcode;
		}

		/**
		 * Renders the custom logo in the login/register form header.
		 *
		 * Displays a logo link in the login/register form header using the stored logo URL.
		 *
		 * @return string HTML markup for the logo link.
		 */
		public function st_login_header()
		{
			global $streamit_options;

			$logo = '';
			$options = isset($streamit_options['streamit_logo']['url']) ? $streamit_options['streamit_logo']['url'] : '';

			if (! empty($options)) {
				$logo = st_get_image_or_svg($options, 'img-fluid logo');
			}

			return '<a class="logo-link" href="' . esc_url(home_url('/')) . '">' . $logo . '</a>';
		}

		/**
		 * Adds a custom "Forgot Password" link to the login form.
		 *
		 * Displays a "Forgot Password?" link and modifies the URL for password reset.
		 *
		 * @return string HTML markup for the "Forgot Password" link.
		 */
		public function st_forgot_password()
		{
			$lost_password_url = wp_lostpassword_url();
			$current_shortcode = $this->current_shortcode();

			// Modify the URL if the shortcode is related to PMPro login.
			if ('pmpro_login' === $current_shortcode) {
				$lost_password_url = esc_url(add_query_arg('action', urlencode('reset_pass'), pmpro_login_url()));
			}

			return '
				<div class="custom-links mt-0 forgot-password">
					<a href="' . esc_url($lost_password_url) . '" class="st-sub-card setting-dropdown">
						<h6 class="mb-0">' . esc_html__('Forgot Password?', 'streamit') . '</h6>
					</a>
				</div>';
		}

		/**
		 * Handles the display of social login/register links in the login form.
		 *
		 * Modifies the login form to include a "Register" link and social login options.
		 *
		 * @param string $html Existing HTML output for the login form.
		 * @return string Modified HTML output for the login form.
		 */
		public function st_social_login($html)
		{
			global $streamit_options;

			$return_html = '';
			$signup_link = '';
			$signup_title = '';

			$current_shortcode = $this->current_shortcode();

			if ('pmpro_login' === $current_shortcode) {
				if (isset($streamit_options['streamit_signup_link'])) {
					$signup_link  = get_page_link($streamit_options['streamit_signup_link']);
					$signup_title = $streamit_options['streamit_signup_title'];
				} elseif (function_exists('pmpro_getOption') && ! empty(pmpro_getOption('levels_page_id'))) {
					$signup_link = get_page_link(pmpro_getOption('levels_page_id'));
				}

				if (empty($signup_link)) {
					return $html;
				}

				$domain_name = get_bloginfo('name');

				$return_html .= '<div class="login-form-bottom">
										<div class="d-flex flex-column justify-content-center align-items-center gap-2 links my-3">'
										. esc_html__('New to ', 'streamit') . esc_html($domain_name) . '? 
											<a href="' . esc_url($signup_link) . '" class="st-sub-card setting-dropdown ms-2">
								<h6 class="m-0 text-primary">';

				$return_html .= ! empty($signup_title) ? esc_html($signup_title) : esc_html__('Register', 'streamit');

				$return_html .= '</h6>
						</a>
					</div>
				</div>';
			}

			// Add social login options if shortcode exists and user is not logged in.
			if (shortcode_exists('miniorange_social_login') && ! is_user_logged_in()) {
				$return_html .= '<div class="css_prefix-separator">
									<span class="or-section">' . esc_html__('OR', 'streamit') . ' </span>
								</div>';

				$return_html .= '<div class="css_prefix-social-login-section">';
				$return_html .= do_shortcode('[miniorange_social_login]');
				$return_html .= '</div>';
			}

			return $html . $return_html;
		}

		/**
		 * Modifies the login form links for PMPro.
		 *
		 * Removes the "Lost password" and "Register" links based on the current form type.
		 *
		 * @param array  $links     Existing links for the PMPro login form.
		 * @param string $pmpro_form Current PMPro form type.
		 * @return array Modified links for the PMPro login form.
		 */
		public function st_pmpro_login_form_bottom($links, $pmpro_form)
		{
			if ('lost_password' !== $pmpro_form) {
				unset($links['lost_password']);
			}

			if ('login' === $pmpro_form) {
				unset($links['register']);
			}

			return $links;
		}

		/**
		 * Removes the separator from the PMPro form.
		 *
		 * @param string $separator Existing separator string.
		 * @return string Modified separator string (empty).
		 */
		public function st_pmpro_remove_separator($separator)
		{
			return '';
		}

		/**
		 * Add custom class names to specific elements.
		 *
		 * Modifies the class array for PMPro elements.
		 *
		 * @param array  $class   Array of existing class names.
		 * @param string $element The element to which classes are being added.
		 * @return array Modified array of class names.
		 */
		public function st_pmpro_element_class($class, $element)
		{
			if ('pmpro' === $element) {
				$class[] = 'container';
			}
			return $class;
		}
	}

	// Instantiate the streamit_pmp_Helper class.
	new streamit_pmp_Helper();
}
