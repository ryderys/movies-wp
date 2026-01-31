<?php

if (!defined('ABSPATH')) {
	exit; // Exit if accessed directly.
}

/**
 * Merlin WP
 * Better WordPress Theme Onboarding
 *
 * The following code is a derivative work from the
 * Envato WordPress Theme Setup Wizard by David Baker.
 *
 * @package   Merlin WP
 * @version   1.0.0
 * @link      https://merlinwp.com/
 * @author    Rich Tabor, from ThemeBeans.com & the team at ProteusThemes.com
 * @copyright Copyright (c) 2018, Merlin WP of Inventionn LLC
 * @license   Licensed GPLv3 for Open Source Use
 */

// Exit if accessed directly.
if (!defined('ABSPATH')) {
	exit;
}

/**
 * Merlin.
 */
class Merlin
{
	/**
	 * Current theme.
	 *
	 * @var object WP_Theme
	 */
	protected $theme;

	protected $main_base_path;

	/**
	 * Current step.
	 *
	 * @var string
	 */
	protected $step = '';

	/**
	 * Steps.
	 *
	 * @var    array
	 */
	protected $steps = array();

	/**
	 * TGMPA instance.
	 *
	 * @var    object
	 */
	protected $tgmpa;

	/**
	 * Importer.
	 *
	 * @var    array
	 */
	protected $importer;

	/**
	 * WP Hook class.
	 *
	 * @var Merlin_Hooks
	 */
	protected $hooks;

	/**
	 * Holds the verified import files.
	 *
	 * @var array
	 */
	public $import_files;

	/**
	 * The base import file name.
	 *
	 * @var string
	 */
	public $import_file_base_name;

	/**
	 * Helper.
	 *
	 * @var    array
	 */
	protected $helper;

	/**
	 * Big Button Url.
	 *
	 * @var    array
	 */
	protected $ready_big_button_url;

	protected $slug;

	protected $hook_suffix;
	/**
	 * Updater.
	 *
	 * @var    array
	 */
	protected $updater;

	/**
	 * The text string array.
	 *
	 * @var array $strings
	 */
	protected $strings = null;

	/**
	 * The base path where Merlin is located.
	 *
	 * @var array $strings
	 */
	protected $base_path = null;

	/**
	 * The base url where Merlin is located.
	 *
	 * @var array $strings
	 */
	protected $base_url = null;

	/**
	 * The location where Merlin is located within the theme or plugin.
	 *
	 * @var string $directory
	 */
	protected $directory = null;

	/**
	 * Top level admin page.
	 *
	 * @var string $merlin_url
	 */
	protected $merlin_url = null;

	/**
	 * The wp-admin parent page slug for the admin menu item.
	 *
	 * @var string $parent_slug
	 */
	protected $parent_slug = null;

	/**
	 * The capability required for this menu to be displayed to the user.
	 *
	 * @var string $capability
	 */
	protected $capability = null;

	/**
	 * The URL for the "Learn more about child themes" link.
	 *
	 * @var string $child_action_btn_url
	 */
	protected $child_action_btn_url = null;

	/**
	 * The flag, to mark, if the theme license step should be enabled.
	 *
	 * @var boolean $license_step_enabled
	 */
	protected $license_step_enabled = false;

	/**
	 * The URL for the "Where can I find the license key?" link.
	 *
	 * @var string $theme_license_help_url
	 */
	protected $theme_license_help_url = null;

	/**
	 * Remove the "Skip" button, if required.
	 *
	 * @var string $license_required
	 */
	protected $license_required = null;

	/**
	 * The item name of the EDD product (this theme).
	 *
	 * @var string $edd_item_name
	 */
	protected $edd_item_name = null;

	/**
	 * The theme slug of the EDD product (this theme).
	 *
	 * @var string $edd_theme_slug
	 */
	protected $edd_theme_slug = null;

	/**
	 * The remote_api_url of the EDD shop.
	 *
	 * @var string $edd_remote_api_url
	 */
	protected $edd_remote_api_url = null;

	/**
	 * Turn on dev mode if you're developing.
	 *
	 * @var string $dev_mode
	 */
	protected $dev_mode = false;

	/**
	 * Ignore.
	 *
	 * @var string $ignore
	 */
	public $ignore = null;

	/**
	 * The object with logging functionality.
	 *
	 * @var Logger $logger
	 */
	public $logger;

	/**
	 * Setup plugin version.
	 *
	 * @access private
	 * @since 1.0
	 * @return void
	 */
	private function version()
	{

		if (!defined('MERLIN_VERSION')) {
			define('MERLIN_VERSION', '1.0.0');
		}
	}

	/**
	 * Class Constructor.
	 *
	 * @param array $config Package-specific configuration args.
	 * @param array $strings Text for the different elements.
	 */
	function __construct($config = array(), $strings = array())
	{

		$this->version();

		$config = wp_parse_args(
			$config,
			array(
				'base_path'            => get_parent_theme_file_path(),
				'base_url'             => get_parent_theme_file_uri(),
				'directory'            => 'merlin',
				'merlin_url'           => 'merlin',
				'parent_slug'          => 'themes.php',
				'capability'           => 'manage_options',
				'child_action_btn_url' => '',
				'dev_mode'             => '',
				'ready_big_button_url' => home_url('/'),
			)
		);
		// Set config arguments.
		$this->main_base_path = $config['base_path'];

		$this->base_path              = $config['base_path'] . "/admin";
		$this->base_url               = $config['base_url'] . "/admin";
		$this->directory              = $config['directory'];
		$this->merlin_url             = $config['merlin_url'];
		$this->parent_slug            = $config['parent_slug'];
		$this->capability             = $config['capability'];
		$this->child_action_btn_url   = $config['child_action_btn_url'];
		$this->license_step_enabled   = $config['license_step'];
		$this->theme_license_help_url = $config['license_help_url'];
		$this->license_required       = $config['license_required'];
		$this->edd_item_name          = $config['edd_item_name'];
		$this->edd_theme_slug         = $config['edd_theme_slug'];
		$this->edd_remote_api_url     = $config['edd_remote_api_url'];
		$this->dev_mode               = $config['dev_mode'];
		$this->ready_big_button_url   = $config['ready_big_button_url'];

		// Strings passed in from the config file.
		$this->strings = $strings;

		// Retrieve a WP_Theme object.
		$this->theme = wp_get_theme();
		$this->slug  = strtolower(preg_replace('#[^a-zA-Z]#', '', $this->theme->template));

		// Set the ignore option.
		$this->ignore = $this->slug . '_ignore';

		// Is Dev Mode turned on?
		if (true !== $this->dev_mode) {

			// Has this theme been setup yet?
			$already_setup = get_option('merlin_' . $this->slug . '_completed');

			// Return if Merlin has already completed it's setup.
			if ($already_setup) {
				return;
			}
		}
		// Get the logger object, so it can be used in the whole class.
		require_once trailingslashit($this->base_path) . $this->directory . '/includes/class-merlin-logger.php';
		$this->logger = Merlin_Logger::get_instance();

		// Get TGMPA.
		if (class_exists('Streamit_TGM_Plugin_Activation')) {
			$this->tgmpa = isset($GLOBALS['tgmpa']) ? $GLOBALS['tgmpa'] : Streamit_TGM_Plugin_Activation::get_instance();
		}


		add_action('admin_init', array($this, 'required_classes'));
		add_action('admin_init', array($this, 'redirect'), 30);
		add_action('admin_init', array($this, 'steps'), 30, 0);
		add_action('admin_menu', array($this, 'add_admin_menu'));
		add_action('admin_init', array($this, 'admin_page'), 30, 0);
		add_action('admin_init', array($this, 'ignore'), 5);
		add_filter('tgmpa_load', array($this, 'load_tgmpa'), 10, 1);
		add_action('wp_ajax_merlin_content', array($this, '_ajax_content'), 10, 0);
		add_action('wp_ajax_merlin_get_total_content_import_items', array($this, '_ajax_get_total_content_import_items'), 10, 0);
		add_action('wp_ajax_merlin_plugins', array($this, '_ajax_plugins'), 10, 0);
		add_action('wp_ajax_merlin_child_theme', array($this, 'generate_child'), 10, 0);
		add_action('wp_ajax_merlin_activate_license', array($this, '_ajax_activate_license'), 10, 0);
		add_action('wp_ajax_merlin_update_selected_import_data_info', array($this, 'update_selected_import_data_info'), 10, 0);
		add_action('wp_ajax_merlin_import_finished', array($this, 'import_finished'), 10, 0);
		add_action('wp_ajax_merlin_import_data', array($this, 'merlin_import_data'), 10, 1);
		add_filter('pt-importer/new_ajax_request_response_data', array($this, 'pt_importer_new_ajax_request_response_data'));
		add_action('import_end', array($this, 'after_content_import_setup'));
		add_action('import_start', array($this, 'before_content_import_setup'));
		add_action('admin_init', array($this, 'register_import_files'));
		add_action('init', array($this, 'st_download_file'));
		add_action('st_stop_redirect_action', array($this, 'st_stop_redirect_action'));
	}

	/**
	 * Require necessary classes.
	 */
	function required_classes()
	{
		if (!class_exists('\WP_Importer')) {
			require ABSPATH . '/wp-admin/includes/class-wp-importer.php';
		}

		require_once trailingslashit($this->base_path) . $this->directory . '/includes/class-merlin-downloader.php';

		$this->importer = new ProteusThemes\WPContentImporter2\Importer(array('fetch_attachments' => true), $this->logger);

		require_once trailingslashit($this->base_path) . $this->directory . '/includes/class-merlin-widget-importer.php';

		if (!class_exists('WP_Customize_Setting')) {
			require_once ABSPATH . 'wp-includes/class-wp-customize-setting.php';
		}

		require_once trailingslashit($this->base_path) . $this->directory . '/includes/class-merlin-customizer-option.php';
		require_once trailingslashit($this->base_path) . $this->directory . '/includes/class-merlin-customizer-importer.php';
		require_once trailingslashit($this->base_path) . $this->directory . '/includes/class-merlin-redux-importer.php';
		require_once trailingslashit($this->base_path) . $this->directory . '/includes/class-merlin-hooks.php';

		$this->hooks = new Merlin_Hooks();

		if (class_exists('EDD_Theme_Updater_Admin')) {
			$this->updater = new EDD_Theme_Updater_Admin();
		}
	}


	/**
	 * Redirection transient.
	 */
	public function redirect()
	{

		if (!get_transient($this->theme->template . '_merlin_redirect')) {
			return;
		}

		delete_transient($this->theme->template . '_merlin_redirect');

		wp_safe_redirect(menu_page_url($this->merlin_url));

		exit;
	}

	/**
	 * Give the user the ability to ignore Merlin WP.
	 */
	public function ignore()
	{

		// Bail out if not on correct page.
		if (!isset($_GET['_wpnonce']) || (!wp_verify_nonce($_GET['_wpnonce'], 'merlinwp-ignore-nounce') || !is_admin() || !isset($_GET[$this->ignore]) || !current_user_can('manage_options'))) {
			return;
		}

		update_option('merlin_' . $this->slug . '_completed', 'ignored');
	}

	/**
	 * Conditionally load TGMPA
	 *
	 * @param string $status User's manage capabilities.
	 */
	public function load_tgmpa($status)
	{
		return is_admin() || current_user_can('install_themes');
	}

	/**
	 * Determine if the user already has theme content installed.
	 * This can happen if swapping from a previous theme or updated the current theme.
	 * We change the UI a bit when updating / swapping to a new theme.
	 *
	 * @access public
	 */
	protected function is_possible_upgrade()
	{
		return false;
	}

	/**
	 * Add the admin menu item, under Appearance.
	 */
	public function add_admin_menu()
	{

		// Strings passed in from the config file.
		$strings = $this->strings;

		$this->hook_suffix = add_submenu_page(
			esc_html($this->parent_slug),
			esc_html($strings['admin-menu']),
			esc_html($strings['admin-menu']),
			sanitize_key($this->capability),
			sanitize_key($this->merlin_url),
			array($this, 'admin_page')
		);
	}

	/**
	 * Add the admin page.
	 */
	public function admin_page()
	{

		// Strings passed in from the config file.
		$strings = $this->strings;

		// Do not proceed, if we're not on the right page.
		if (empty($_GET['page']) || $this->merlin_url !== $_GET['page']) {
			return;
		}

		if (ob_get_length()) {
			ob_end_clean();
		}

		$this->step = isset($_GET['step']) ? sanitize_key($_GET['step']) : current(array_keys($this->steps));

		// Use minified libraries if dev mode is turned on.
		$suffix = ((true === $this->dev_mode)) ? '' : '.min';

		// Enqueue styles.
		wp_enqueue_style('merlin', trailingslashit($this->base_url) . $this->directory . '/assets/css/merlin' . $suffix . '.css', array('wp-admin'), MERLIN_VERSION);

		// Enques bootstrap
		$base_url = get_template_directory_uri(); // For themes
		// $base_url = plugin_dir_url( __FILE__ ); // For plugins

		// Enqueue Bootstrap CSS
		wp_enqueue_style('bootstrap-css', $base_url . '/assets/css/vendor/bootstrap.min.css', array(), '5.0.2', 'all');
		wp_enqueue_script('bootstrap-js', $base_url . '/assets/js/vendor/bootstrap.min.js', array(), '5.0.2', 'all');



		// Enqueue javascript.
		wp_enqueue_script('merlin', trailingslashit($this->base_url) . $this->directory . '/assets/js/merlin' . $suffix . '.js', array('jquery-core'), MERLIN_VERSION);

		$texts = array(
			'something_went_wrong' => esc_html__('Something went wrong. Please refresh the page and try again!', 'streamit'),
		);

		// Localize the javascript.
		if (class_exists('Streamit_TGM_Plugin_Activation')) {
			// Check first if TMGPA is included.
			wp_localize_script(
				'merlin',
				'merlin_params',
				array(
					'tgm_plugin_nonce' => array(
						'update'  => wp_create_nonce('tgmpa-update'),
						'install' => wp_create_nonce('tgmpa-install'),
					),
					'tgm_bulk_url'     => $this->tgmpa->get_tgmpa_url(),
					'ajaxurl'          => admin_url('admin-ajax.php'),
					'wpnonce'          => wp_create_nonce('merlin_nonce'),
					'texts'            => $texts,
				)
			);
		} else {
			// If TMGPA is not included.
			wp_localize_script(
				'merlin',
				'merlin_params',
				array(
					'ajaxurl' => admin_url('admin-ajax.php'),
					'wpnonce' => wp_create_nonce('merlin_nonce'),
					'texts'   => $texts,
				)
			);
		}

		ob_start();

		/**
		 * Start the actual page content.
		 */
		$this->header(); ?>

		<div class="merlin__wrapper">

			<div class="merlin__content merlin__content--<?php echo esc_attr(strtolower($this->steps[$this->step]['name'])); ?>">

				<?php
				// Content Handlers.
				$show_content = true;

				if (!empty($_REQUEST['save_step']) && isset($this->steps[$this->step]['handler'])) {
					$show_content = call_user_func($this->steps[$this->step]['handler']);
				}

				if ($show_content) {
					$this->body();
				}
				?>

				<?php //$this->step_output(); 
				?>

			</div>
			<div class="footer-links">
				<?php echo sprintf('<a class="return-to-dashboard" href="%s">%s</a>', esc_url(admin_url('/')), esc_html($strings['return-to-dashboard'])); ?>

				<?php $ignore_url = wp_nonce_url(admin_url('?' . $this->ignore . '=true'), 'merlinwp-ignore-nounce'); ?>

				<?php echo sprintf('<a class="return-to-dashboard ignore" href="%s">%s</a>', esc_url($ignore_url), esc_html($strings['ignore'])); ?>
			</div>

		</div>

		<?php $this->footer(); ?>

	<?php
		exit;
	}

	/**
	 * Output the header.
	 */
	protected function header()
	{
		// Strings passed in from the config file.
		$strings = $this->strings;
		// Get the current step.
		$current_step = strtolower($this->steps[$this->step]['name']);
	?>

		<!DOCTYPE html>
		<html xmlns="http://www.w3.org/1999/xhtml" <?php language_attributes(); ?>>

		<head>
			<meta name="viewport" content="width=device-width" />
			<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
			<?php printf(esc_html($strings['title%s%s%s%s']), '<ti', 'tle>', esc_html($this->theme->name), '</title>'); ?>
			<?php do_action('admin_print_styles'); ?>
			<?php do_action('admin_print_scripts'); ?>
		</head>

		<body class="merlin__body merlin__body--<?php echo esc_attr($current_step); ?>">
			<?php
			$ouput_steps  = $this->steps;
			$array_keys   = array_keys($this->steps);
			$current_step = array_search($this->step, $array_keys, true);

			$class_welcome = ($this->step == "welcome") ? "in-progress" : "complete";
			array_shift($ouput_steps);
			?>

			<div class="wizard-progress current-step-<?php echo esc_attr($this->step); ?>">
				<div class="step <?php echo esc_attr($class_welcome); ?>">
					<?php echo esc_html__("Welcome", "streamit"); ?>
					<div class="node">
						<span class="step-numbers">
							<?php echo "1"; ?>
						</span>
						<?php
						if ('complete' == $class_welcome) {
							echo $this->merlin_get_svg();
						}
						?>
					</div>
				</div>
				<?php
				$i = 2;
				foreach ($ouput_steps as $step_key => $step) :
					$class_attr = '';
					if ($step_key === $this->step) {
						$class_attr = 'in-progress';
					} elseif ($current_step > array_search($step_key, $array_keys, true)) {
						$class_attr = 'complete';
					}
				?>
					<div class="step <?php echo esc_attr($class_attr); ?>">
						<?php echo esc_attr($step['name']); ?>
						<div class="node">
							<span class="step-numbers">
								<?php
								echo esc_html($i);
								$i++;
								?>
							</span>
							<?php
							if ('complete' == $class_attr || 'ready' == $this->step) {
								echo $this->merlin_get_svg();
							}
							?>
						</div>
					</div>
				<?php endforeach; ?>
			</div>
		<?php
	}
	protected function merlin_get_svg($type = "")
	{
		if (empty($type)) {
			return '<span class="step-icon"></span>';
		} else {
			return '<span class="btn-icon"></span>';
		}
	}
	/**
	 * Output the content for the current step.
	 */
	protected function body()
	{
		isset($this->steps[$this->step]) ? call_user_func($this->steps[$this->step]['view']) : false;
	}

	/**
	 * Output the footer.
	 */
	protected function footer()
	{
		?>
		</body>
		<?php do_action('admin_footer'); ?>
		<?php do_action('admin_print_footer_scripts'); ?>

		</html>
	<?php
	}

	/**
	 * SVG
	 */
	public function svg_sprite()
	{

		// Define SVG sprite file.
		$svg = trailingslashit($this->base_path) . $this->directory . '/assets/images/sprite.svg';

		// If it exists, include it.
		if (file_exists($svg)) {
			require_once apply_filters('merlin_svg_sprite', $svg);
		}
	}

	/**
	 * Return SVG markup.
	 *
	 * @param array $args {
	 *     Parameters needed to display an SVG.
	 *
	 *     @type string $icon  Required SVG icon filename.
	 *     @type string $title Optional SVG title.
	 *     @type string $desc  Optional SVG description.
	 * }
	 * @return string SVG markup.
	 */
	public function svg($args = array())
	{

		// Make sure $args are an array.
		if (empty($args)) {
			return __('Please define default parameters in the form of an array.', 'streamit');
		}

		// Define an icon.
		if (false === array_key_exists('icon', $args)) {
			return __('Please define an SVG icon filename.', 'streamit');
		}

		// Set defaults.
		$defaults = array(
			'icon'        => '',
			'title'       => '',
			'desc'        => '',
			'aria_hidden' => true, // Hide from screen readers.
			'fallback'    => false,
		);

		// Parse args.
		$args = wp_parse_args($args, $defaults);

		// Set aria hidden.
		$aria_hidden = '';

		if (true === $args['aria_hidden']) {
			$aria_hidden = ' aria-hidden="true"';
		}

		// Set ARIA.
		$aria_labelledby = '';

		if ($args['title'] && $args['desc']) {
			$aria_labelledby = ' aria-labelledby="title desc"';
		}

		// Begin SVG markup.
		$svg = '<svg class="icon icon--' . esc_attr($args['icon']) . '"' . $aria_hidden . $aria_labelledby . ' role="img">';

		// If there is a title, display it.
		if ($args['title']) {
			$svg .= '<title>' . esc_html($args['title']) . '</title>';
		}

		// If there is a description, display it.
		if ($args['desc']) {
			$svg .= '<desc>' . esc_html($args['desc']) . '</desc>';
		}

		$svg .= '<use xlink:href="#icon-' . esc_html($args['icon']) . '"></use>';

		// Add some markup to use as a fallback for browsers that do not support SVGs.
		if ($args['fallback']) {
			$svg .= '<span class="svg-fallback icon--' . esc_attr($args['icon']) . '"></span>';
		}

		$svg .= '</svg>';

		return $svg;
	}

	/**
	 * Allowed HTML for sprites.
	 */
	public function svg_allowed_html()
	{

		$array = array(
			'svg' => array(
				'class'       => array(),
				'aria-hidden' => array(),
				'role'        => array(),
			),
			'use' => array(
				'xlink:href' => array(),
			),
		);

		return apply_filters('merlin_svg_allowed_html', $array);
	}

	/**
	 * Loading merlin-spinner.
	 */
	public function loading_spinner()
	{

		// Define the spinner file.
		$spinner = 'admin/' . $this->directory . '/assets/images/spinner';
		// Retrieve the spinner.
		get_template_part(apply_filters('merlin_loading_spinner', $spinner));
	}

	/**
	 * Allowed HTML for the loading spinner.
	 */
	public function loading_spinner_allowed_html()
	{

		$array = array(
			'span' => array(
				'class' => array(),
			),
			'cite' => array(
				'class' => array(),
			),
		);

		return apply_filters('merlin_loading_spinner_allowed_html', $array);
	}

	/**
	 * Setup steps.
	 */
	public function steps()
	{

		$this->steps = array(
			'welcome' => array(
				'name'    => esc_html__('Welcome', 'streamit'),
				'view'    => array($this, 'welcome'),
				'handler' => array($this, 'welcome_handler'),
			),
		);

		$this->steps['child'] = array(
			'name' => esc_html__('Child', 'streamit'),
			'view' => array($this, 'child'),
		);

		if ($this->license_step_enabled) {
			$this->steps['license'] = array(
				'name' => esc_html__('License', 'streamit'),
				'view' => array($this, 'license'),
			);
		}

		// Show the plugin importer, only if TGMPA is included.
		if (class_exists('Streamit_TGM_Plugin_Activation')) {
			$this->steps['plugins'] = array(
				'name' => esc_html__('Plugins', 'streamit'),
				'view' => array($this, 'plugins'),
			);
		}

		$this->steps['datas'] = array(
			'name' => esc_html__('Data', 'streamit'),
			'view' => array($this, 'datas'),
		);


		// Show the content importer, only if there's demo content added.
		if (!empty($this->import_files)) {
			$this->steps['content'] = array(
				'name' => esc_html__('Content', 'streamit'),
				'view' => array($this, 'content'),
			);
		}

		$this->steps['ready'] = array(
			'name' => esc_html__('Ready', 'streamit'),
			'view' => array($this, 'ready'),
		);

		$this->steps = apply_filters($this->theme->template . '_merlin_steps', $this->steps);
	}

	/**
	 * Output the steps
	 */
	protected function step_output()
	{
		$ouput_steps  = $this->steps;
		$array_keys   = array_keys($this->steps);
		$current_step = array_search($this->step, $array_keys, true);

		array_shift($ouput_steps);
	?>

		<ol class="dots">

			<?php
			foreach ($ouput_steps as $step_key => $step) :

				$class_attr = '';
				$show_link  = false;

				if ($step_key === $this->step) {
					$class_attr = 'active';
				} elseif ($current_step > array_search($step_key, $array_keys, true)) {
					$class_attr = 'done';
					$show_link  = true;
				}
			?>

				<li class="<?php echo esc_attr($class_attr); ?>">
					<a href="<?php echo esc_url($this->step_link($step_key)); ?>" title="<?php echo esc_attr($step['name']); ?>"></a>
				</li>

			<?php endforeach; ?>

		</ol>

	<?php
	}

	/**
	 * Get the step URL.
	 *
	 * @param string $step Name of the step, appended to the URL.
	 */
	protected function step_link($step)
	{
		return add_query_arg('step', $step);
	}

	/**
	 * Get the next step link.
	 */
	protected function step_next_link()
	{
		$keys = array_keys($this->steps);
		$step = array_search($this->step, $keys, true) + 1;

		return add_query_arg('step', $keys[$step]);
	}

	/**
	 * Converts PHP INI size strings (e.g., '256M', '1G', '1024K') to bytes.
	 * Handles 'K', 'M', 'G' suffixes (case-insensitive).
	 * Also handles '-1' (often meaning unlimited) by returning -1.
	 *
	 * @param string $size_str The size string to convert.
	 * @return int The size in bytes, or -1 if input was '-1'.
	 */
	private function convert_php_size_to_bytes($size_str)
	{
		$size_str = trim($size_str);
		if (empty($size_str)) {
			return 0;
		}

		// Handle -1 as a special case (often meaning unlimited)
		if ($size_str === '-1') {
			return -1;
		}

		$unit = '';
		// Check last character for unit, case-insensitive
		if (preg_match('/([kmg])$/i', $size_str, $matches)) {
			$unit = strtolower($matches[1]);
		}

		$value = intval($size_str);
		switch ($unit) {
			case 'g':
				$value *= 1024;
			case 'm':
				$value *= 1024;
			case 'k':
				$value *= 1024;
		}
		return $value;
	}

	/**
	 * Introduction step
	 */
	protected function welcome()
	{

		// Has this theme been setup yet? Compare this to the option set when you get to the last panel.
		$already_setup = get_option('merlin_' . $this->slug . '_completed');

		// Theme Name.
		$theme = ucfirst($this->theme);

		// Remove "Child" from the current theme name, if it's installed.
		$theme = str_replace(' Child', '', $theme);

		// Strings passed in from the config file.
		$strings = $this->strings;

		// Text strings.
		$header    = !$already_setup ? $strings['welcome-header%s'] : $strings['welcome-header-success%s'];
		$paragraph = !$already_setup ? $strings['welcome%s'] : $strings['welcome-success%s'];
		$start     = $strings['btn-start'];
		$no        = $strings['btn-no'];

		$requirements_met = true;
		$php_configs = array(
			'max_execution_time' => array('require' => 300, 'current' => ini_get('max_execution_time')),
			'max_input_vars' => array('require' => 3000, 'current' => ini_get('max_input_vars')),
			'max_input_time' => array('require' => 300, 'current' => ini_get('max_input_time')),
			'memory_limit' => array('require' => '256M', 'current' => ini_get('memory_limit')),
			'post_max_size' => array('require' => '64M', 'current' => ini_get('post_max_size')),
			'upload_max_filesize' => array('require' => '64M', 'current' => ini_get('upload_max_filesize')),
		);

	?>

		<div class="merlin__content--transition">
			<div class="content-left">
				<?php
				echo file_get_contents($this->base_url . '/' . $this->directory . '/assets/images/1_Hello.svg');
				?>
			</div>
			<div class="content-right">
				<div class="content-right-inner">

					<h1><?php echo esc_html(sprintf($header, $theme)); ?></h1>

					<p><?php echo esc_html(sprintf($paragraph, $theme)); ?></p>
					<div class="table-wrapper">
						<table class="table">
							<tr class="heading">
								<th><?php esc_html_e('PHP Configuration', 'streamit') ?></th>
								<th><?php esc_html_e('Recommended (Min)', 'streamit') ?></th>
								<th><?php esc_html_e('Existing', 'streamit') ?></th>
							</tr>

							<?php
							foreach ($php_configs as $key => $php_config) {
								$required_val_str = (string) $php_config['require'];
								$current_val_str  = (string) $php_config['current'];

								$required_numeric = $this->convert_php_size_to_bytes($required_val_str);
								$current_numeric  = $this->convert_php_size_to_bytes($current_val_str);

								$is_sufficient = false;
								// Handle unlimited cases first
								if ($current_numeric === -1 || ($current_numeric === 0 && in_array($key, ['max_execution_time', 'max_input_time']))) {
									$is_sufficient = true;
								} else {
									$is_sufficient = ($current_numeric >= $required_numeric);
								}

								if (!$is_sufficient) {
									$requirements_met = false;
								}
							?>
								<tr class="">
									<td><?php esc_html_e($key) ?></td>
									<td><code><?php esc_html_e($php_config['require']) ?></code></td>
									<td><code style="<?php echo $is_sufficient ? '' : 'color:red;'; ?>"><?php esc_html_e($php_config['current']) ?></code></td>
								</tr>
							<?php
							}
							?>
						</table>
					</div>

				</div>
			</div>
		</div>

		<footer class="merlin__content__footer">
			<?php if (!$requirements_met) { ?>

				<div class="error-message mt-3">
					<?php
					esc_html_e('Some of your current server settings do not meet the recommended requirements. To ensure optimal functionality of the theme', 'streamit');
					?>
				</div>
				<?php $link_url = 'https://jetpack.com/blog/wordpress-php-ini/'; ?>
				<b class="text-danger">
					<?php $error_message = __('Follow the provided documentation link for detailed instructions on updating your server configurations.', 'streamit');
					$error_message_with_link = str_replace(' documentation link ', '<a href="' . esc_url($link_url) . '" target="_blank"> documentation link </a>', $error_message);
					echo wp_kses($error_message_with_link, array('a' => array('href' => array(), 'target' => array())));
					?>
				</b>
			<?php } ?>
			<a href="<?php echo esc_url(wp_get_referer() && !strpos(wp_get_referer(), 'update.php') ? wp_get_referer() : admin_url('/')); ?>" class="merlin__button merlin__button--skip"><?php echo esc_html($no); ?></a>
			<?php if ($requirements_met) { ?>
				<a href="<?php echo esc_url($this->step_next_link()); ?>" class="merlin__button merlin__button--next merlin__button--proceed merlin__button--colorchange">
					<span class="btn-label">
						<?php echo esc_html($start); ?>
					</span>
					<?php echo $this->merlin_get_svg("button"); ?>
				</a>
			<?php } ?>
			<?php wp_nonce_field('merlin'); ?>
		</footer>

	<?php
		$this->logger->debug(__('The welcome step has been displayed', 'streamit'));
	}

	/**
	 * Handles save button from welcome page.
	 * This is to perform tasks when the setup wizard has already been run.
	 */
	protected function welcome_handler()
	{

		check_admin_referer('merlin');

		return false;
	}

	/**
	 * Theme EDD license step.
	 */
	protected function license()
	{
		$is_theme_registered = $this->is_theme_registered();
		$action_url          = $this->theme_license_help_url;
		$required            = $this->license_required;

		$is_theme_registered_class = ($is_theme_registered) ? ' is-registered' : null;

		// Theme Name.
		$theme = ucfirst($this->theme);

		// Remove "Child" from the current theme name, if it's installed.
		$theme = str_replace(' Child', '', $theme);

		// Strings passed in from the config file.
		$strings = $this->strings;

		// Text strings.
		$header    = !$is_theme_registered ? $strings['license-header%s'] : $strings['license-header-success%s'];
		$action    = $strings['license-tooltip'];
		$label     = $strings['license-label'];
		$skip      = $strings['btn-license-skip'];
		$next      = $strings['btn-next'];
		$paragraph = !$is_theme_registered ? $strings['license%s'] : $strings['license-success%s'];
		$install   = $strings['btn-license-activate'];
	?>

		<div class="merlin__content--transition">
			<div class="content-left">
				<?php echo wp_kses($this->svg(array('icon' => 'license')), $this->svg_allowed_html()); ?>

				<svg class="icon icon--checkmark" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 52 52">
					<circle class="icon--checkmark__circle" cx="26" cy="26" r="25" fill="none" />
					<path class="icon--checkmark__check" fill="none" d="M14.1 27.2l7.1 7.2 16.7-16.8" />
				</svg>
			</div>
			<div class="content-right">
				<div class="content-right-inner">
					<h1><?php echo esc_html(sprintf($header, $theme)); ?></h1>

					<p id="license-text"><?php echo esc_html(sprintf($paragraph, $theme)); ?></p>

					<?php if (!$is_theme_registered) : ?>
						<div class="merlin__content--license-key">
							<label for="license-key"><?php echo esc_html($label); ?></label>

							<div class="merlin__content--license-key-wrapper">
								<input type="text" id="license-key" class="js-license-key" autocomplete="off" autocorrect="off" autocapitalize="off" spellcheck="false">
								<?php if (!empty($action_url)) : ?>
									<a href="<?php echo esc_url($action_url); ?>" alt="<?php echo esc_attr($action); ?>" target="_blank">
										<span class="hint--top" aria-label="<?php echo esc_attr($action); ?>">
											<?php echo wp_kses($this->svg(array('icon' => 'help')), $this->svg_allowed_html()); ?>
										</span>
									</a>
								<?php endif ?>
							</div>

						</div>
					<?php endif; ?>
				</div>
			</div>
		</div>

		<footer class="merlin__content__footer <?php echo esc_attr($is_theme_registered_class); ?>">

			<?php if (!$is_theme_registered) : ?>

				<?php if (!$required) : ?>
					<a href="<?php echo esc_url($this->step_next_link()); ?>" class="merlin__button merlin__button--skip merlin__button--proceed"><?php echo esc_html($skip); ?></a>
				<?php endif ?>

				<a href="<?php echo esc_url($this->step_next_link()); ?>" class="merlin__button merlin__button--next button-next js-merlin-license-activate-button" data-callback="activate_license">
					<span class="merlin__button--loading__text"><?php echo esc_html($install); ?></span>
					<?php echo wp_kses($this->loading_spinner(), $this->loading_spinner_allowed_html()); ?>
				</a>

			<?php else : ?>
				<a href="<?php echo esc_url($this->step_next_link()); ?>" class="merlin__button merlin__button--next merlin__button--proceed merlin__button--colorchange">
					<span class="btn-label">
						<?php echo esc_html($next); ?>
					</span>
					<?php echo $this->merlin_get_svg("button"); ?>
				</a>
			<?php endif; ?>
			<?php wp_nonce_field('merlin'); ?>
		</footer>
	<?php
		$this->logger->debug(__('The license activation step has been displayed', 'streamit'));
	}


	/**
	 * Check, if the theme is currently registered.
	 *
	 * @return boolean
	 */
	private function is_theme_registered()
	{
		$is_registered = get_option($this->edd_theme_slug . '_license_key_status', false) === 'valid';
		return apply_filters('merlin_is_theme_registered', $is_registered);
	}

	/**
	 * Child theme generator.
	 */
	protected function child()
	{

		// Variables.
		$is_child_theme     = false;
		$name = $this->theme . ' Child';
		$slug = sanitize_title($name);
		$path = get_theme_root() . '/' . $slug;
		if (file_exists($path)) {
			$is_child_theme = true;
		}

		$child_theme_option = get_option('merlin_' . $this->slug . '_child');
		$theme              = $child_theme_option ? wp_get_theme($child_theme_option)->name : $this->theme . ' Child';
		$action_url         = $this->child_action_btn_url;

		// Strings passed in from the config file.
		$strings = $this->strings;

		// Text strings.
		$header    = !$is_child_theme ? $strings['child-header'] : $strings['child-header-success'];
		$action    = $strings['child-action-link'];
		$skip      = $strings['btn-skip'];
		$next      =  $strings['btn-next'];
		$paragraph = !$is_child_theme ? $strings['child'] : $strings['child-success%s'];
		$install   = !$is_child_theme ? $strings['btn-child-install'] : $strings['btn-next'];


	?>

		<div class="merlin__content--transition">
			<div class="content-left">
				<?php echo file_get_contents($this->base_url . '/' . $this->directory . '/assets/images/2_Child_Theme.svg'); ?>

				<svg class="icon icon--checkmark" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 52 52">
					<circle class="icon--checkmark__circle" cx="26" cy="26" r="25" fill="none" />
					<path class="icon--checkmark__check" fill="none" d="M14.1 27.2l7.1 7.2 16.7-16.8" />
				</svg>
			</div>
			<div class="content-right">
				<div class="content-right-inner">

					<h1><?php echo esc_html($header); ?></h1>

					<p id="child-theme-text"><?php echo esc_html(sprintf($paragraph, $theme)); ?></p><br>
					<p>
					<h3><strong><?php esc_html_e("About child theme", "streamit"); ?></strong></h3>
					<?php
					esc_html_e("A child theme enables you to modify specific aspects of your site's appearance while preserving the core design and functionality of the parent theme. It provides a safe and efficient way to implement customizations without affecting the original theme files.", "streamit");
					?><br>
					<?php
					esc_html_e("For a deeper understanding of how child themes work,", "streamit"); ?>
					<a href="<?php echo esc_url($action_url); ?>" target="_blank"><?php echo esc_html('click here to learn more.', 'streamit'); ?></a>
					</p>
				</div>

			</div>
		</div>

		<footer class="merlin__content__footer">

			<?php if (!$is_child_theme) : ?>

				<a href="<?php echo esc_url($this->step_next_link()); ?>" class="merlin__button merlin__button--skip merlin__button--proceed"><?php echo esc_html($skip); ?></a>

				<a href="<?php echo esc_url($this->step_next_link()); ?>" class="merlin__button merlin__button--next button-next" data-callback="install_child">
					<span class="merlin__button--loading__text">
						<span class="btn-label">
							<?php echo esc_html($install); ?>
						</span>
						<?php echo $this->merlin_get_svg("button"); ?>
					</span>
					<?php echo wp_kses($this->loading_spinner(), $this->loading_spinner_allowed_html()); ?>
				</a>

			<?php else : ?>
				<a href="<?php echo esc_url($this->step_next_link()); ?>" class="merlin__button merlin__button--next merlin__button--proceed merlin__button--colorchange">
					<span class="btn-label">
						<?php echo esc_html($next); ?>
					</span>
					<?php echo $this->merlin_get_svg("button"); ?>
				</a>
			<?php endif; ?>
			<?php wp_nonce_field('merlin'); ?>
		</footer>
	<?php
		$this->logger->debug(__('The child theme installation step has been displayed', 'streamit'));
	}

	/**
	 * Theme plugins
	 */
	protected function plugins()
	{

		// Variables.
		$url    = wp_nonce_url(add_query_arg(array('plugins' => 'go')), 'merlin');
		$method = '';
		$fields = array_keys($_POST);
		$creds  = request_filesystem_credentials(esc_url_raw($url), $method, false, false, $fields);

		tgmpa_load_bulk_installer();

		if (false === $creds) {
			return true;
		}

		if (!WP_Filesystem($creds)) {
			request_filesystem_credentials(esc_url_raw($url), $method, true, false, $fields);
			return true;
		}

		// Are there plugins that need installing/activating?
		$plugins          = $this->get_tgmpa_plugins();
		$required_plugins = $recommended_plugins = array();
		$count            = count($plugins['all']);
		$class            = $count ? null : 'no-plugins';

		// Split the plugins into required and recommended.
		foreach ($plugins['all'] as $slug => $plugin) {
			if (!empty($plugin['required']) && ($plugin['required'] == true)) {
				$required_plugins[$slug] = $plugin;
			} else {
				$recommended_plugins[$slug] = $plugin;
			}
		}
		// Strings passed in from the config file.
		$strings = $this->strings;

		// Text strings.
		$header    = $count ? $strings['plugins-header'] : $strings['plugins-header-success'];
		$paragraph = $count ? $strings['plugins'] : $strings['plugins-success%s'];
		$action    = $strings['plugins-action-link'];
		$skip      = $strings['btn-skip'];
		$next      = $strings['btn-next'];
		$install   = $strings['btn-plugins-install'];
	?>

		<div class="merlin__content--transition">
			<div class="content-left">
				<?php echo file_get_contents($this->base_url . '/' . $this->directory . '/assets/images/3_Plugins.svg'); ?>

				<svg class="icon icon--checkmark" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 52 52">
					<circle class="icon--checkmark__circle" cx="26" cy="26" r="25" fill="none" />
					<path class="icon--checkmark__check" fill="none" d="M14.1 27.2l7.1 7.2 16.7-16.8" />
				</svg>
			</div>
			<div class="content-right">

				<div class="content-right-inner">
					<h1><?php echo esc_html($header); ?></h1>

					<p><?php echo esc_html($paragraph); ?></p>
					<?php if ($count) :
						$required = $other = '';  ?>
						<?php if (!empty($required_plugins)) : ?>
							<?php foreach ($required_plugins as $slug => $plugin) :
								$required .= '<li data-slug="' . esc_attr($slug) . '">
										<input type="checkbox" name="default_plugins[' . esc_attr($slug) . ']" class="checkbox" id="default_plugins_' . esc_attr($slug) . '" value="1" checked disabled>
										<label for="default_plugins_' . esc_attr($slug) . '">
											<i></i>
											<span>' . esc_html($plugin['name']) . '</span>
											<span class="badge">
										<span class="hint--top" aria-label="' . esc_html__('Required', 'streamit') . '">
											' . esc_html__('req', 'streamit') . '
										</span>
									</span>
									</label>
									</li>';
							endforeach; ?>
						<?php endif; ?>

						<?php if (!empty($recommended_plugins)) :
						?>
							<?php foreach ($recommended_plugins as $slug => $plugin) :
								$other .= '<li data-slug="' . esc_attr($slug) . '">
										<input type="checkbox" name="default_plugins[' . esc_attr($slug) . ']" class="checkbox plugin_single_checkbox" id="default_plugins_' . esc_attr($slug) . '" value="1" checked>
										<label for="default_plugins_' . esc_attr($slug) . '">
											<i></i><span>' . esc_html($plugin['name']) . '</span>
										</label>
									</li>';
							endforeach; ?>
						<?php endif;
						$pluginArrays = array(
							'Required' => $required,
							'Other' => $other
						); ?>
						<div class="merlin__drawer--install-plugins">
							<div class="accordion accordion-setup-wizard" id="accordion-setup-wizard">
								<div class="accordion-item">
									<div class="accordion-item-inner">
										<div class="accordion-button collapsed" type="button" data-bs-toggle="collapse">
											<h3 class="m-0"><?php esc_html_e(' Plugins', 'streamit'); ?></h3>
										</div>
									</div>
									<ul id="plugins" class="list-inline my-2 mx-0 py-0 px-4 accordion-collapse collapse">
										<?php foreach ($pluginArrays as $section => $pluginMarkup) : ?>
											<?php if (!empty($pluginMarkup)) : ?>
												<?php echo $pluginMarkup; ?>
											<?php endif; ?>
										<?php endforeach; ?>
									</ul>
								</div>
							</div>
						</div>
					<?php endif; ?>
				</div>
			</div>
		</div>

		<form action="" method="post">

			<footer class="merlin__content__footer <?php echo esc_attr($class); ?>">
				<?php if ($count) : ?>
					<a id="close" href="<?php echo esc_url($this->step_next_link()); ?>" class="merlin__button merlin__button--skip merlin__button--closer merlin__button--proceed"><?php echo esc_html($skip); ?></a>
					<a id="skip" href="<?php echo esc_url($this->step_next_link()); ?>" class="merlin__button merlin__button--skip merlin__button--proceed"><?php echo esc_html($skip); ?></a>
					<a href="<?php echo esc_url($this->step_next_link()); ?>" class="merlin__button merlin__button--next button-next" data-callback="install_plugins">
						<span class="merlin__button--loading__text">
							<span class="btn-label">
								<?php echo esc_html($install); ?>
							</span>
							<?php echo $this->merlin_get_svg("button"); ?>
						</span>
						<?php echo wp_kses($this->loading_spinner(), $this->loading_spinner_allowed_html()); ?>
					</a>
				<?php else : ?>
					<a href="<?php echo esc_url($this->step_next_link()); ?>" class="merlin__button merlin__button--next merlin__button--proceed merlin__button--colorchange">
						<span class="btn-label">
							<?php echo esc_html($next); ?>
						</span>
						<?php echo $this->merlin_get_svg("button"); ?>
					</a>
				<?php endif; ?>
				<?php wp_nonce_field('merlin'); ?>
			</footer>
		</form>

		<?php
		$this->logger->debug(__('The plugin installation step has been displayed', 'streamit'));
	}

	/**
	 * Data setup
	 */
	protected function datas()
	{
		$plugins          = $this->get_tgmpa_plugins();
		$count            = count($plugins['all']);
		$countss = '1';
		foreach ($plugins['all'] as $slug => $plugin) {
			if (!empty($plugin['required']) && ($plugin['required'] == true)) {
				$countss++;
			}
		}
		if ($count && ($countss > '1')) {
			$flag = '1';
		?>
			<div class="plugin-installation-error">
				<div class="error-icon"></div>
				<h1>
					<?php esc_html_e("Something went worng !", "streamit"); ?>
				</h1>
				<strong>
					<?php esc_html_e(" Below listed plugin has been not installed. Install it mannually or submit the ticket for	 Help.", "streamit"); ?>
				</strong>
				<div class="table-required-plugins">
					<div class="table-responsive">
						<table class="table table-striped table-bordered ">
							<thead>
								<tr>
									<th><?php esc_html_e("No", "streamit"); ?></th>
									<th><?php esc_html_e("Required Plugins", "streamit"); ?></th>
									<th><?php esc_html_e("Download Link", "streamit"); ?> </th>
								</tr>
							</thead>
							<tbody>
								<?php foreach ($plugins['all'] as $slug => $plugin) {
									if (!empty($plugin['required']) && ($plugin['required'] == true)) {

										if ($plugin['source'] == "repo") {
											$action = 'plugin-information';
											$slug = $plugin['slug'];
											$url	= wp_nonce_url(
												add_query_arg(
													array(
														'tab' => $action,
														'plugin' => $slug
													),
													admin_url('plugin-install.php')
												),
												$action . '_' . $slug
											);
										} else {
											$url = "?download_item=" . $plugin['slug'];
										}
								?>
										<tr>
											<td>
												<?php echo $flag; ?>
											</td>
											<td>
												<?php echo $plugin['name']; ?>
											</td>
											<td>
												<span class="download-link">
													<a href="<?php echo esc_url($url) ?>" target="_blank"><?php esc_html_e("Download", "streamit"); ?></a>
												</span>
											</td>
										</tr>


								<?php $flag++;
									}
								} ?>
							</tbody>
						</table>
					</div>
				</div>
				<div class="plugin-installation-links">
					<a href='<?php echo admin_url('themes.php?page=streamit-setup&step=plugins'); ?>' class='merlin__button merlin__button--knockout merlin__button--no-chevron merlin__button--external'><?php esc_html_e("Try Again", "streamit"); ?></a>
					<a href="https://iqonic.desky.support/" class="merlin__button merlin__button--knockout merlin__button--no-chevron merlin__button--external" target="_blank"><?php esc_html_e('Get Support', 'streamit'); ?></a>
				</div>
			</div>
		<?php
			return;
		}
		$strings = $this->strings;
		$header    = $strings['data-import'];
		$paragraph = $strings['data'];
		$skip      = $strings['btn-skip'];

		?>
		<div class="merlin__content--transition">
			<div class="content-left">
				<?php echo file_get_contents($this->base_url . '/' . $this->directory . '/assets/images/6_Content.svg'); ?>

				<svg class="icon icon--checkmark" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 52 52">
					<circle class="icon--checkmark__circle" cx="26" cy="26" r="25" fill="none" />
					<path class="icon--checkmark__check" fill="none" d="M14.1 27.2l7.1 7.2 16.7-16.8" />
				</svg>
			</div>
			<div class="content-right">
				<div class="content-right-inner">
					<h1><?php echo esc_html($header); ?></h1>
					<p><?php echo esc_html($paragraph); ?></p>
					<ul class="merlin__drawer--import-content js-merlin-drawer-import-content">
						<li data-slug="movies" class="import-item">
							<input type="checkbox" name="default_content[movies]" class="checkbox import-checkbox" id="default_content_movies" value="1" checked disabled>
							<label for="default_content_movies">
								<i></i><span><?php echo esc_html__('Movies', 'streamit'); ?></span>
							</label>
							<div class="import-status" id="import-movies-status"></div>
						</li>
						<li data-slug="videos" class="import-item">
							<input type="checkbox" name="default_content[videos]" class="checkbox import-checkbox" id="default_content_videos" value="1" checked disabled>
							<label for="default_content_videos">
								<i></i><span><?php echo esc_html__('Videos', 'streamit'); ?></span>
							</label>
							<div class="import-status" id="import-videos-status"></div>
						</li>
						<li data-slug="persons" class="import-item">
							<input type="checkbox" name="default_content[persons]" class="checkbox import-checkbox" id="default_content_persons" value="1" checked disabled>
							<label for="default_content_persons">
								<i></i><span><?php echo esc_html__('Persons', 'streamit'); ?></span>
							</label>
							<div class="import-status" id="import-persons-status"></div>
						</li>
						<li data-slug="tvshows" class="import-item">
							<input type="checkbox" name="default_content[tvshows]" class="checkbox import-checkbox" id="default_content_tvshows" value="1" checked disabled>
							<label for="default_content_tvshows">
								<i></i><span><?php echo esc_html__('TV Shows', 'streamit'); ?></span>
							</label>
							<div class="import-status" id="import-tvshows-status"></div>
						</li>
						<li data-slug="episodes" class="import-item">
							<input type="checkbox" name="default_content[episodes]" class="checkbox import-checkbox" id="default_content_episodes" value="1" checked disabled>
							<label for="default_content_episodes">
								<i></i><span><?php echo esc_html__('Episodes', 'streamit'); ?></span>
							</label>
							<div class="import-status" id="import-episodes-status"></div>
						</li>
						<li data-slug="terms" class="import-item">
							<input type="checkbox" name="default_content[terms]" class="checkbox import-checkbox" id="default_content_terms" value="1" checked disabled>
							<label for="default_content_terms">
								<i></i><span><?php echo esc_html__('Terms', 'streamit'); ?></span>
							</label>
							<div class="import-status" id="import-terms-status"></div>
						</li>
						<?php if (class_exists('Live_Streaming')) : ?>
							<li data-slug="channels" class="import-item">
								<input type="checkbox" name="default_content[channel]" class="checkbox import-checkbox" id="default_content_channels" value="1" checked disabled>
								<label for="default_content_channels">
									<i></i><span><?php echo esc_html__('Channels', 'streamit'); ?></span>
								</label>
								<div class="import-status" id="import-channels-status"></div>
							</li>
							<li data-slug="channel_categorys" class="import-item">
								<input type="checkbox" name="default_content[channel_category]" class="checkbox import-checkbox" id="default_content_channel_categorys" value="1" checked disabled>
								<label for="default_content_channel_categorys">
									<i></i><span><?php echo esc_html__('Channel Categories', 'streamit'); ?></span>
								</label>
								<div class="import-status" id="import-terms-status"></div>
							</li>
						<?php endif; ?>
					</ul>
				</div>
			</div>
		</div>
		<footer class="merlin__content__footer">
			<a id="close" href="<?php echo esc_url($this->step_next_link()); ?>" class="merlin__button merlin__button--skip merlin__button--closer merlin__button--proceed"><?php echo esc_html($skip); ?></a>

			<a id="skip" href="<?php echo esc_url($this->step_next_link()); ?>" class="merlin__button merlin__button--skip merlin__button--proceed"><?php echo esc_html($skip); ?></a>
			<a href="<?php echo esc_url($this->step_next_link()); ?>" class="merlin__button merlin__button--next button-next" data-callback="import_data">
				<span class="merlin__button--loading__text">
					<span class="btn-label">
						<?php echo esc_html('Import', 'streamit'); ?>
					</span>
					<?php echo $this->merlin_get_svg("button"); ?>
				</span>
				<?php echo wp_kses($this->loading_spinner(), $this->loading_spinner_allowed_html()); ?>
			</a>
			<?php wp_nonce_field('merlin'); ?>
		</footer>
	<?php
	}

	/**
	 * Page setup
	 */
	protected function content()
	{
		// Strings passed in from the config file.
		flush_rewrite_rules();
		$strings = $this->strings;
		$import_info = $this->get_import_data_info();

		// Text strings.
		$header    = $strings['import-header'];
		$paragraph = $strings['import'];
		$action    = $strings['import-action-link'];
		$skip      = $strings['btn-skip'];
		$next      = $strings['btn-next'];
		$import    = $strings['btn-import'];

		$multi_import = (1 < count($this->import_files)) ? 'is-multi-import' : null;
	?>

		<div class="merlin__content--transition">
			<div class="content-left">
				<?php echo file_get_contents($this->base_url . '/' . $this->directory . '/assets/images/6_Content.svg'); ?>

				<svg class="icon icon--checkmark" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 52 52">
					<circle class="icon--checkmark__circle" cx="26" cy="26" r="25" fill="none" />
					<path class="icon--checkmark__check" fill="none" d="M14.1 27.2l7.1 7.2 16.7-16.8" />
				</svg>
			</div>
			<div class="content-right">
				<div class="content-right-inner">
					<h1><?php echo esc_html($header); ?></h1>

					<p><?php echo esc_html($paragraph); ?></p>

					<?php if (1 < count($this->import_files)) : ?>

						<div class="merlin__select-control-wrapper">

							<select class="merlin__select-control js-merlin-demo-import-select">
								<?php foreach ($this->import_files as $index => $import_file) : ?>
									<option value="<?php echo esc_attr($index); ?>"><?php echo esc_html($import_file['import_file_name']); ?></option>
								<?php endforeach; ?>
							</select>

							<div class="merlin__select-control-help">
								<span class="hint--top" aria-label="<?php echo esc_attr__('Select Demo', 'streamit'); ?>">
									<?php echo wp_kses($this->svg(array('icon' => 'downarrow')), $this->svg_allowed_html()); ?>
								</span>
							</div>
						</div>
					<?php endif; ?>

					<ul class=" merlin__drawer--import-content js-merlin-drawer-import-content">
						<?php echo $this->get_import_steps_html($import_info); ?>
					</ul>
				</div>
			</div>
		</div>

		<form action="" method="post" class="<?php echo esc_attr($multi_import); ?>">



			<footer class="merlin__content__footer">

				<a id="close" href="<?php echo esc_url($this->step_next_link()); ?>" class="merlin__button merlin__button--skip merlin__button--closer merlin__button--proceed"><?php echo esc_html($skip); ?></a>

				<a id="skip" href="<?php echo esc_url($this->step_next_link()); ?>" class="merlin__button merlin__button--skip merlin__button--proceed"><?php echo esc_html($skip); ?></a>

				<a href="<?php echo esc_url($this->step_next_link()); ?>" class="merlin__button merlin__button--next button-next" data-callback="install_content">
					<span class="merlin__button--loading__text">
						<span class="btn-label">
							<?php echo esc_html($import); ?>
						</span>
						<?php echo $this->merlin_get_svg("button"); ?>
					</span>

					<div class="merlin__progress-bar">
						<span class="js-merlin-progress-bar"></span>
					</div>

					<span class="js-merlin-progress-bar-percentage">0%</span>
				</a>

				<?php wp_nonce_field('merlin'); ?>
			</footer>
		</form>

	<?php
		$this->logger->debug(__('The content import step has been displayed', 'streamit'));
	}


	/**
	 * Final step
	 */
	protected function ready()
	{

		// Author name.
		flush_rewrite_rules();
		$author = $this->theme->author;

		// Theme Name.
		$theme = ucfirst($this->theme);

		// Remove "Child" from the current theme name, if it's installed.
		$theme = str_replace(' Child', '', $theme);

		// Strings passed in from the config file.
		$strings = $this->strings;

		// Text strings.
		$header    = $strings['ready-header'];
		$paragraph = $strings['ready%s'];
		$action    = $strings['ready-action-link'];
		$skip      = $strings['btn-skip'];
		$next      = $strings['btn-next'];
		$big_btn   = $strings['ready-big-button'];

		// Links.
		$links = array();

		for ($i = 1; $i < 4; $i++) {
			if (!empty($strings["ready-link-$i"])) {
				$links[] = $strings["ready-link-$i"];
			}
		}

		$links_class = empty($links) ? 'merlin__content__footer--nolinks' : null;

		$allowed_html_array = array(
			'a' => array(
				'href'   => array(),
				'title'  => array(),
				'target' => array(),
				'class'	 => array(),
			),
		);

		update_option('merlin_' . $this->slug . '_completed', time());
	?>

		<div class="merlin__content--transition">
			<div class="content-left">
				<?php echo file_get_contents($this->base_url . '/' . $this->directory . '/assets/images/5_Done.svg'); ?>
			</div>
			<div class="content-right">
				<div class="content-right-inner">
					<h1><?php echo esc_html(sprintf($header, $theme)); ?></h1>
					<p><?php wp_kses(printf($paragraph, $author), $allowed_html_array); ?></p>
					<a href="<?php echo esc_url($this->ready_big_button_url); ?>" class="merlin__button merlin__button--blue merlin__button--popin">
						<span class="btn-label">
							<?php echo esc_html($big_btn); ?>
						</span>
						<?php echo $this->merlin_get_svg("button"); ?>
					</a>
				</div>
			</div>
		</div>

		<footer class="merlin__content__footer merlin__content__footer--fullwidth <?php echo esc_attr($links_class); ?>">

			<?php if (!empty($links)) : ?>
				<ul class="merlin__drawer--extras">

					<?php foreach ($links as $link) : ?>
						<li><?php echo wp_kses($link, $allowed_html_array); ?></li>
					<?php endforeach; ?>

				</ul>
			<?php endif; ?>

		</footer>

	<?php
		$this->logger->debug(__('The final step has been displayed', 'streamit'));
	}

	/**
	 * Get registered TGMPA plugins
	 *
	 * @return    array
	 */
	protected function get_tgmpa_plugins()
	{
		$plugins = array(
			'all'      => array(), // Meaning: all plugins which still have open actions.
			'install'  => array(),
			'update'   => array(),
			'activate' => array(),
		);

		foreach ($this->tgmpa->plugins as $slug => $plugin) {
			if ($this->tgmpa->is_plugin_active($slug) && false === $this->tgmpa->does_plugin_have_update($slug)) {
				continue;
			} else {

				$plugins['all'][$slug] = $plugin;

				if (!$this->tgmpa->is_plugin_installed($slug)) {
					$plugins['install'][$slug] = $plugin;
				} else {
					if (false !== $this->tgmpa->does_plugin_have_update($slug)) {
						$plugins['update'][$slug] = $plugin;
					}
					if ($this->tgmpa->can_plugin_activate($slug)) {
						$plugins['activate'][$slug] = $plugin;
					}
				}
			}
		}
		return $plugins;
	}

	/**
	 * Generate the child theme via AJAX.
	 */
	public function generate_child()
	{

		// Strings passed in from the config file.
		$strings = $this->strings;

		// Text strings.
		$success = $strings['child-json-success%s'];
		$already = $strings['child-json-already%s'];

		$name = $this->theme . ' Child';
		$slug = sanitize_title($name);

		$path = get_theme_root() . '/' . $slug;

		if (!file_exists($path)) {

			WP_Filesystem();

			global $wp_filesystem;

			$wp_filesystem->mkdir($path);
			$wp_filesystem->put_contents($path . '/style.css', $this->generate_child_style_css($this->theme->template, $this->theme->name, $this->theme->author, $this->theme->version));
			$wp_filesystem->put_contents($path . '/functions.php', $this->generate_child_functions_php($this->theme->template));

			$this->generate_child_screenshot($path);

			$allowed_themes          = get_option('allowedthemes');
			$allowed_themes[$slug] = true;
			update_option('allowedthemes', $allowed_themes);
		} else {



			$this->logger->debug(__('The existing child theme was activated', 'streamit'));

			wp_send_json(
				array(
					'done'    => 1,
					'message' => sprintf(
						esc_html($success),
						$slug
					),
				)
			);
		}


		$this->logger->debug(__('The newly generated child theme was activated', 'streamit'));

		wp_send_json(
			array(
				'done'    => 1,
				'message' => sprintf(
					esc_html($already),
					$name
				),
			)
		);
	}

	/**
	 * Activate the theme (license key) via AJAX.
	 */
	public function _ajax_activate_license()
	{

		if (!check_ajax_referer('merlin_nonce', 'wpnonce')) {
			wp_send_json(
				array(
					'success' => false,
					'message' => esc_html__('Yikes! The theme activation failed. Please try again or contact support.', 'streamit'),
				)
			);
		}

		if (empty($_POST['license_key'])) {
			wp_send_json(
				array(
					'success' => false,
					'message' => esc_html__('Please add your license key before attempting to activate one.', 'streamit'),
				)
			);
		}

		$license_key = sanitize_key($_POST['license_key']);

		if (!has_filter('merlin_ajax_activate_license')) {
			$result = $this->edd_activate_license($license_key);
		} else {
			$result = apply_filters('merlin_ajax_activate_license', $license_key);
		}

		$this->logger->debug(__('The license activation was performed with the following results', 'streamit'), $result);

		wp_send_json(array_merge(array('done' => 1), $result));
	}

	/**
	 * Activate the EDD license.
	 *
	 * This code was taken from the EDD licensing addon theme example code
	 * (`activate_license` method of the `EDD_Theme_Updater_Admin` class).
	 *
	 * @param string $license The license key.
	 *
	 * @return array
	 */
	protected function edd_activate_license($license)
	{
		$success = false;

		// Strings passed in from the config file.
		$strings = $this->strings;

		// Theme Name.
		$theme = ucfirst($this->theme);

		// Remove "Child" from the current theme name, if it's installed.
		$theme = str_replace(' Child', '', $theme);

		// Text strings.
		$success_message = $strings['license-json-success%s'];

		// Data to send in our API request.
		$api_params = array(
			'edd_action' => 'activate_license',
			'license'    => rawurlencode($license),
			'item_name'  => rawurlencode($this->edd_item_name),
			'url'        => esc_url(home_url('/')),
		);

		$response = $this->edd_get_api_response($api_params);

		// Make sure the response came back okay.
		if (is_wp_error($response) || 200 !== wp_remote_retrieve_response_code($response)) {

			if (is_wp_error($response)) {
				$message = $response->get_error_message();
			} else {
				$message = esc_html__('An error occurred, please try again.', 'streamit');
			}
		} else {

			$license_data = json_decode(wp_remote_retrieve_body($response));

			if (false === $license_data->success) {

				switch ($license_data->error) {

					case 'expired':
						$message = sprintf(
							/* translators: Expiration date */
							esc_html__('Your license key expired on %s.', 'streamit'),
							date_i18n(get_option('date_format'), strtotime($license_data->expires, current_time('timestamp')))
						);
						break;

					case 'revoked':
						$message = esc_html__('Your license key has been disabled.', 'streamit');
						break;

					case 'missing':
						$message = esc_html__('This appears to be an invalid license key. Please try again or contact support.', 'streamit');
						break;

					case 'invalid':
					case 'site_inactive':
						$message = esc_html__('Your license is not active for this URL.', 'streamit');
						break;

					case 'item_name_mismatch':
						/* translators: EDD Item Name */
						$message = sprintf(esc_html__('This appears to be an invalid license key for %s.', 'streamit'), $this->edd_item_name);
						break;

					case 'no_activations_left':
						$message = esc_html__('Your license key has reached its activation limit.', 'streamit');
						break;

					default:
						$message = esc_html__('An error occurred, please try again.', 'streamit');
						break;
				}
			} else {
				if ('valid' === $license_data->license) {
					$message = sprintf(esc_html($success_message), $theme);
					$success = true;

					// Removes the default EDD hook for this option, which breaks the AJAX call.
					remove_all_actions('update_option_' . $this->edd_theme_slug . '_license_key', 10);

					update_option($this->edd_theme_slug . '_license_key_status', $license_data->license);
					update_option($this->edd_theme_slug . '_license_key', $license);
				}
			}
		}

		return compact('success', 'message');
	}

	/**
	 * Makes a call to the API.
	 *
	 * This code was taken from the EDD licensing addon theme example code
	 * (`get_api_response` method of the `EDD_Theme_Updater_Admin` class).
	 *
	 * @param array $api_params to be used for wp_remote_get.
	 * @return array $response JSON response.
	 */
	private function edd_get_api_response($api_params)
	{

		$verify_ssl = (bool) apply_filters('edd_sl_api_request_verify_ssl', true);

		$response = wp_remote_post(
			$this->edd_remote_api_url,
			array(
				'timeout'   => 15,
				'sslverify' => $verify_ssl,
				'body'      => $api_params,
			)
		);

		return $response;
	}

	/**
	 * Content template for the child theme functions.php file.
	 *
	 * @link https://gist.github.com/richtabor/688327dd103b1aa826ebae47e99a0fbe
	 *
	 * @param string $slug Parent theme slug.
	 */
	public function generate_child_functions_php($slug)
	{

		$slug_no_hyphens = strtolower(preg_replace('#[^a-zA-Z]#', '', $slug));

		$output = "
			<?php
			/**
			 * Theme functions and definitions.
			 * This child theme was generated by streamit.
			 *
			 * @link https://developer.wordpress.org/themes/basics/theme-functions/
			 */

			/*
			 * If your child theme has more than one .css file (eg. ie.css, style.css, main.css) then
			 * you will have to make sure to maintain all of the parent theme dependencies.
			 *
			 * Make sure you're using the correct handle for loading the parent theme's styles.
			 * Failure to use the proper tag will result in a CSS file needlessly being loaded twice.
			 * This will usually not affect the site appearance, but it's inefficient and extends your page's loading time.
			 *
			 * @link https://codex.wordpress.org/Child_Themes
			 */
			function {$slug_no_hyphens}_child_enqueue_styles() {
			    wp_enqueue_style( '{$slug}-style' , get_template_directory_uri() . '/style.css' );
			    wp_enqueue_style( '{$slug}-child-style',
			        get_stylesheet_directory_uri() . '/style.css',
			        array( '{$slug}-style' ),
			        wp_get_theme()->get('Version')
			    );
			}

			add_action(  'wp_enqueue_scripts', '{$slug_no_hyphens}_child_enqueue_styles' );\n
		";

		// Let's remove the tabs so that it displays nicely.
		$output = trim(preg_replace('/\t+/', '', $output));

		$this->logger->debug(__('The child theme functions.php content was generated', 'streamit'));

		// Filterable return.
		return apply_filters('merlin_generate_child_functions_php', $output, $slug);
	}

	/**
	 * Content template for the child theme functions.php file.
	 *
	 * @link https://gist.github.com/richtabor/7d88d279706fc3093911e958fd1fd791
	 *
	 * @param string $slug    Parent theme slug.
	 * @param string $parent  Parent theme name.
	 * @param string $author  Parent theme author.
	 * @param string $version Parent theme version.
	 */
	public function generate_child_style_css($slug, $parent, $author, $version)
	{

		$output = "
			/**
			* Theme Name: {$parent} Child
			* Description: This is a child theme of {$parent}, generated by iQonic Design.
			* Author: {$author}
			* Template: {$slug}
			* Version: {$version}
			*/\n
		";

		// Let's remove the tabs so that it displays nicely.
		$output = trim(preg_replace('/\t+/', '', $output));

		$this->logger->debug(__('The child theme style.css content was generated', 'streamit'));

		return apply_filters('merlin_generate_child_style_css', $output, $slug, $author, $parent, $version);
	}

	/**
	 * Generate child theme screenshot file.
	 *
	 * @param string $path    Child theme path.
	 */
	public function generate_child_screenshot($path)
	{

		$screenshot = apply_filters('merlin_generate_child_screenshot', '');

		if (!empty($screenshot)) {
			// Get custom screenshot file extension
			if ('.png' === substr($screenshot, -4)) {
				$screenshot_ext = 'png';
			} else {
				$screenshot_ext = 'jpg';
			}
		} else {
			if (file_exists($this->base_path . '/screenshot.png')) {
				$screenshot     = $this->base_path . '/screenshot.png';
				$screenshot_ext = 'png';
			} elseif (file_exists($this->base_path . '/screenshot.jpg')) {
				$screenshot     = $this->base_path . '/screenshot.jpg';
				$screenshot_ext = 'jpg';
			}
		}
		if (!empty($screenshot) && file_exists($screenshot)) {
			$copied = copy($screenshot, $path . '/screenshot.' . $screenshot_ext);
			$this->logger->debug(__('The child theme screenshot was copied to the child theme, with the following result', 'streamit'), array('copied' => $copied));
		} else {
			$this->logger->debug(__('The child theme screenshot was not generated, because of these results', 'streamit'), array('screenshot' => $screenshot));
		}
	}

	/**
	 * Do plugins' AJAX
	 *
	 * @internal    Used as a calback.
	 */
	function _ajax_plugins()
	{

		if (!check_ajax_referer('merlin_nonce', 'wpnonce') || empty($_POST['slug'])) {
			exit(0);
		}

		$json      = array();
		$tgmpa_url = $this->tgmpa->get_tgmpa_url();
		$plugins   = $this->get_tgmpa_plugins();

		foreach ($plugins['activate'] as $slug => $plugin) {
			if ($_POST['slug'] === $slug) {
				$json = array(
					'url'           => $tgmpa_url,
					'plugin'        => array($slug),
					'tgmpa-page'    => $this->tgmpa->menu,
					'plugin_status' => 'all',
					'_wpnonce'      => wp_create_nonce('bulk-plugins'),
					'action'        => 'tgmpa-bulk-activate',
					'action2'       => -1,
					'message'       => esc_html__('Activating', 'streamit'),
				);
				break;
			}
		}

		foreach ($plugins['update'] as $slug => $plugin) {
			if ($_POST['slug'] === $slug) {
				$json = array(
					'url'           => $tgmpa_url,
					'plugin'        => array($slug),
					'tgmpa-page'    => $this->tgmpa->menu,
					'plugin_status' => 'all',
					'_wpnonce'      => wp_create_nonce('bulk-plugins'),
					'action'        => 'tgmpa-bulk-update',
					'action2'       => -1,
					'message'       => esc_html__('Updating', 'streamit'),
				);
				break;
			}
		}

		foreach ($plugins['install'] as $slug => $plugin) {
			if ($_POST['slug'] === $slug) {
				$json = array(
					'url'           => $tgmpa_url,
					'plugin'        => array($slug),
					'tgmpa-page'    => $this->tgmpa->menu,
					'plugin_status' => 'all',
					'_wpnonce'      => wp_create_nonce('bulk-plugins'),
					'action'        => 'tgmpa-bulk-install',
					'action2'       => -1,
					'message'       => esc_html__('Installing', 'streamit'),
				);
				break;
			}
		}

		if ($json) {
			$this->logger->debug(
				__('A plugin with the following data will be processed', 'streamit'),
				array(
					'plugin_slug' => $_POST['slug'],
					'message'     => $json['message'],
				)
			);

			$json['hash']    = md5(serialize($json));
			$json['message'] = esc_html__('Installing', 'streamit');
			wp_send_json($json);
		} else {
			$this->logger->debug(
				__('A plugin with the following data was processed', 'streamit'),
				array(
					'plugin_slug' => $_POST['slug'],
				)
			);

			wp_send_json(
				array(
					'done'    => 1,
					'message' => esc_html__('Success', 'streamit'),
				)
			);
		}

		exit;
	}

	/**
	 * Do content's AJAX
	 *
	 * @internal    Used as a callback.
	 */
	function _ajax_content()
	{

		static $content = null;

		$selected_import = intval($_POST['selected_index']);

		if (null === $content) {
			$content = $this->get_import_data($selected_import);
		}

		if (!check_ajax_referer('merlin_nonce', 'wpnonce') || empty($_POST['content']) && isset($content[$_POST['content']])) {
			$this->logger->error(__('The content importer AJAX call failed to start, because of incorrect data', 'streamit'));

			wp_send_json_error(
				array(
					'error'   => 1,
					'message' => esc_html__('Invalid content!', 'streamit'),
				)
			);
		}

		$json         = false;
		$this_content = $content[$_POST['content']];

		if (isset($_POST['proceed'])) {
			if (is_callable($this_content['install_callback'])) {
				$this->logger->info(
					__('The content import AJAX call will be executed with this import data', 'streamit'),
					array(
						'title' => $this_content['title'],
						'data'  => $this_content['data'],
					)
				);

				$logs = call_user_func($this_content['install_callback'], $this_content['data']);
				if ($logs) {
					$json = array(
						'done'    => 1,
						'message' => $this_content['success'],
						'debug'   => '',
						'logs'    => $logs,
						'errors'  => '',
					);

					// The content import ended, so we should mark that all posts were imported.
					if ('content' === $_POST['content']) {
						$json['num_of_imported_posts'] = 'all';
					}
				}
			}
		} else {
			$json = array(
				'url'            => admin_url('admin-ajax.php'),
				'action'         => 'merlin_content',
				'proceed'        => 'true',
				'content'        => $_POST['content'],
				'_wpnonce'       => wp_create_nonce('merlin_nonce'),
				'selected_index' => $selected_import,
				'message'        => $this_content['installing'],
				'logs'           => '',
				'errors'         => '',
			);
		}

		if ($json) {
			$json['hash'] = md5(serialize($json));
			wp_send_json($json);
		} else {
			$this->logger->error(
				__('The content import AJAX call failed with this passed data', 'streamit'),
				array(
					'selected_content_index' => $selected_import,
					'importing_content'      => $_POST['content'],
					'importing_data'         => $this_content['data'],
				)
			);

			wp_send_json(
				array(
					'error'   => 1,
					'message' => esc_html__('Error', 'streamit'),
					'logs'    => '',
					'errors'  => '',
				)
			);
		}
	}


	/**
	 * AJAX call to retrieve total items (posts, pages, CPT, attachments) for the content import.
	 */
	public function _ajax_get_total_content_import_items()
	{
		if (!check_ajax_referer('merlin_nonce', 'wpnonce') && empty($_POST['selected_index'])) {
			$this->logger->error(__('The content importer AJAX call for retrieving total content import items failed to start, because of incorrect data.', 'streamit'));

			wp_send_json_error(
				array(
					'error'   => 1,
					'message' => esc_html__('Invalid data!', 'streamit'),
				)
			);
		}

		$selected_import = intval($_POST['selected_index']);
		$import_files    = $this->get_import_files_paths($selected_import);

		wp_send_json_success($this->importer->get_number_of_posts_to_import($import_files['content']));
	}


	/**
	 * Get import data from the selected import.
	 * Which data does the selected import have for the import.
	 *
	 * @param int $selected_import_index The index of the predefined demo import.
	 *
	 * @return bool|array
	 */
	public function get_import_data_info($selected_import_index = 0)
	{
		$import_data = array(
			'content'      => false,
			'widgets'      => false,
			'options'      => false,
			'sliders'      => false,
			'redux'        => false,
			'after_import' => false,
		);

		if (empty($this->import_files[$selected_import_index])) {
			return false;
		}

		if (
			!empty($this->import_files[$selected_import_index]['import_file_url']) ||
			!empty($this->import_files[$selected_import_index]['local_import_file'])
		) {
			$import_data['content'] = true;
		}

		if (
			!empty($this->import_files[$selected_import_index]['import_widget_file_url']) ||
			!empty($this->import_files[$selected_import_index]['local_import_widget_file'])
		) {
			$import_data['widgets'] = true;
		}

		if (
			!empty($this->import_files[$selected_import_index]['import_customizer_file_url']) ||
			!empty($this->import_files[$selected_import_index]['local_import_customizer_file'])
		) {
			$import_data['options'] = true;
		}

		if (
			!empty($this->import_files[$selected_import_index]['import_rev_slider_file_url']) ||
			!empty($this->import_files[$selected_import_index]['local_import_rev_slider_file'])
		) {
			$import_data['sliders'] = true;
		}

		if (
			!empty($this->import_files[$selected_import_index]['import_redux']) ||
			!empty($this->import_files[$selected_import_index]['local_import_redux'])
		) {
			$import_data['redux'] = true;
		}

		if (false !== has_action('merlin_after_all_import')) {
			$import_data['after_import'] = true;
		}

		return $import_data;
	}


	/**
	 * Get the import files/data.
	 *
	 * @param int $selected_import_index The index of the predefined demo import.
	 *
	 * @return    array
	 */
	protected function get_import_data($selected_import_index = 0)
	{
		$content = array();

		$import_files = $this->get_import_files_paths($selected_import_index);

		if (!empty($import_files['content'])) {
			$content['content'] = array(
				'title'            => esc_html__('Content', 'streamit'),
				'description'      => esc_html__('Demo content data.', 'streamit'),
				'pending'          => esc_html__('Pending', 'streamit'),
				'installing'       => esc_html__('Installing', 'streamit'),
				'success'          => esc_html__('Success', 'streamit'),
				'checked'          => $this->is_possible_upgrade() ? 0 : 1,
				'install_callback' => array($this->importer, 'import'),
				'data'             => $import_files['content'],
			);
		}

		if (!empty($import_files['widgets'])) {
			$content['widgets'] = array(
				'title'            => esc_html__('Widgets', 'streamit'),
				'description'      => esc_html__('Sample widgets data.', 'streamit'),
				'pending'          => esc_html__('Pending', 'streamit'),
				'installing'       => esc_html__('Installing', 'streamit'),
				'success'          => esc_html__('Success', 'streamit'),
				'install_callback' => array('Merlin_Widget_Importer', 'import'),
				'checked'          => $this->is_possible_upgrade() ? 0 : 1,
				'data'             => $import_files['widgets'],
			);
		}

		if (!empty($import_files['sliders'])) {
			$content['sliders'] = array(
				'title'            => esc_html__('Revolution Slider', 'streamit'),
				'description'      => esc_html__('Sample Revolution sliders data.', 'streamit'),
				'pending'          => esc_html__('Pending', 'streamit'),
				'installing'       => esc_html__('Installing', 'streamit'),
				'success'          => esc_html__('Success', 'streamit'),
				'install_callback' => array($this, 'import_revolution_sliders'),
				'checked'          => $this->is_possible_upgrade() ? 0 : 1,
				'data'             => $import_files['sliders'],
			);
		}

		if (!empty($import_files['options'])) {
			$content['options'] = array(
				'title'            => esc_html__('Options', 'streamit'),
				'description'      => esc_html__('Sample theme options data.', 'streamit'),
				'pending'          => esc_html__('Pending', 'streamit'),
				'installing'       => esc_html__('Installing', 'streamit'),
				'success'          => esc_html__('Success', 'streamit'),
				'install_callback' => array('Merlin_Customizer_Importer', 'import'),
				'checked'          => $this->is_possible_upgrade() ? 0 : 1,
				'data'             => $import_files['options'],
			);
		}

		if (!empty($import_files['redux'])) {
			$content['redux'] = array(
				'title'            => esc_html__('Redux Options', 'streamit'),
				'description'      => esc_html__('Redux framework options.', 'streamit'),
				'pending'          => esc_html__('Pending', 'streamit'),
				'installing'       => esc_html__('Installing', 'streamit'),
				'success'          => esc_html__('Success', 'streamit'),
				'install_callback' => array('Merlin_Redux_Importer', 'import'),
				'checked'          => $this->is_possible_upgrade() ? 0 : 1,
				'data'             => $import_files['redux'],
			);
		}

		if (false !== has_action('merlin_after_all_import')) {
			$content['after_import'] = array(
				'title'            => esc_html__('After import setup', 'streamit'),
				'description'      => esc_html__('After import setup.', 'streamit'),
				'pending'          => esc_html__('Pending', 'streamit'),
				'installing'       => esc_html__('Installing', 'streamit'),
				'success'          => esc_html__('Success', 'streamit'),
				'install_callback' => array($this->hooks, 'after_all_import_action'),
				'checked'          => $this->is_possible_upgrade() ? 0 : 1,
				'data'             => $selected_import_index,
			);
		}

		$content = apply_filters('merlin_get_base_content', $content, $this);

		return $content;
	}

	/**
	 * Import revolution slider.
	 *
	 * @param string $file Path to the revolution slider zip file.
	 */
	public function import_revolution_sliders($file)
	{
		if (!class_exists('RevSlider', false)) {
			return 'failed';
		}

		$importer = new RevSlider();

		$response = $importer->importSliderFromPost(true, true, $file);

		$this->logger->info(__('The revolution slider import was executed', 'streamit'));

		if (defined('DOING_AJAX') && DOING_AJAX) {
			return 'true';
		}
	}

	/**
	 * Change the new AJAX request response data.
	 *
	 * @param array $data The default data.
	 *
	 * @return array The updated data.
	 */
	public function pt_importer_new_ajax_request_response_data($data)
	{
		$data['url']      = admin_url('admin-ajax.php');
		$data['message']  = esc_html__('Installing', 'streamit');
		$data['proceed']  = 'true';
		$data['action']   = 'merlin_content';
		$data['content']  = 'content';
		$data['_wpnonce'] = wp_create_nonce('merlin_nonce');
		$data['hash']     = md5(rand()); // Has to be unique (check JS code catching this AJAX response).

		return $data;
	}

	/**
	 * After content import setup code.
	 */
	public function after_content_import_setup()
	{
		// Set static homepage.
		$homepage = get_page_by_path(apply_filters('merlin_content_home_page_title', 'Home'));

		if ($homepage) {
			update_option('page_on_front', $homepage->ID);
			update_option('show_on_front', 'page');

			$this->logger->debug(__('The home page was set', 'streamit'), array('homepage_id' => $homepage));
		}

		// Set static blog page.
		$blogpage = get_page_by_path(apply_filters('merlin_content_blog_page_title', 'Blog'));

		if ($blogpage) {
			update_option('page_for_posts', $blogpage->ID);
			update_option('show_on_front', 'page');

			$this->logger->debug(__('The blog page was set', 'streamit'), array('blog_page_id' => $blogpage));
		}
	}

	/**
	 * Before content import setup code.
	 */
	public function before_content_import_setup()
	{

		// Update the Hello World! post by making it a draft.
		$hello_world = get_page_by_path('Hello World!', OBJECT, 'post');

		if (!empty($hello_world)) {
			$hello_world->post_status = 'draft';
			wp_update_post($hello_world);

			$this->logger->debug(__('The Hello world post status was set to draft', 'streamit'));
		}
	}

	/**
	 * Register the import files via the `merlin_import_files` filter.
	 */
	public function register_import_files()
	{

		$this->import_files = $this->validate_import_file_info(apply_filters('merlin_import_files', array()));
	}

	/**
	 * Filter through the array of import files and get rid of those who do not comply.
	 *
	 * @param  array $import_files list of arrays with import file details.
	 * @return array list of filtered arrays.
	 */
	public function validate_import_file_info($import_files)
	{

		$filtered_import_file_info = array();
		foreach ($import_files as $import_file) {
			if (!empty($import_file['import_file_name'])) {
				$filtered_import_file_info[] = $import_file;
			} else {
				$this->logger->warning(__('This predefined demo import does not have the name parameter: import_file_name', 'streamit'), $import_file);
			}
		}

		return $filtered_import_file_info;
	}

	/**
	 * Set the import file base name.
	 * Check if an existing base name is available (saved in a transient).
	 */
	public function set_import_file_base_name()
	{
		$existing_name = get_transient('merlin_import_file_base_name');

		if (!empty($existing_name)) {
			$this->import_file_base_name = $existing_name;
		} else {
			$this->import_file_base_name = date('Y-m-d__H-i-s');
		}

		set_transient('merlin_import_file_base_name', $this->import_file_base_name, MINUTE_IN_SECONDS);
	}

	/**
	 * Get the import file paths.
	 * Grab the defined local paths, download the files or reuse existing files.
	 *
	 * @param int $selected_import_index The index of the selected import.
	 *
	 * @return array
	 */
	public function get_import_files_paths($selected_import_index)
	{
		$selected_import_data = empty($this->import_files[$selected_import_index]) ? false : $this->import_files[$selected_import_index];

		if (empty($selected_import_data)) {
			return array();
		}

		// Set the base name for the import files.
		$this->set_import_file_base_name();

		$base_file_name = $this->import_file_base_name;
		$import_files   = array(
			'content' => '',
			'widgets' => '',
			'options' => '',
			'redux'   => array(),
			'sliders' => '',
		);

		$downloader = new Merlin_Downloader();

		// Check if 'import_file_url' is not defined. That would mean a local file.
		if (empty($selected_import_data['import_file_url'])) {
			if (!empty($selected_import_data['local_import_file']) && file_exists($selected_import_data['local_import_file'])) {
				$import_files['content'] = $selected_import_data['local_import_file'];
			}
		} else {
			// Set the filename string for content import file.
			$content_filename = 'content-' . $base_file_name . '.xml';

			// Retrieve the content import file.
			$import_files['content'] = $downloader->fetch_existing_file($content_filename);

			// Download the file, if it's missing.
			if (empty($import_files['content'])) {
				$import_files['content'] = $downloader->download_file($selected_import_data['import_file_url'], $content_filename);
			}

			// Reset the variable, if there was an error.
			if (is_wp_error($import_files['content'])) {
				$import_files['content'] = '';
			}
		}

		// Get widgets file as well. If defined!
		if (!empty($selected_import_data['import_widget_file_url'])) {
			// Set the filename string for widgets import file.
			$widget_filename = 'widgets-' . $base_file_name . '.json';

			// Retrieve the content import file.
			$import_files['widgets'] = $downloader->fetch_existing_file($widget_filename);

			// Download the file, if it's missing.
			if (empty($import_files['widgets'])) {
				$import_files['widgets'] = $downloader->download_file($selected_import_data['import_widget_file_url'], $widget_filename);
			}

			// Reset the variable, if there was an error.
			if (is_wp_error($import_files['widgets'])) {
				$import_files['widgets'] = '';
			}
		} elseif (!empty($selected_import_data['local_import_widget_file'])) {
			if (file_exists($selected_import_data['local_import_widget_file'])) {
				$import_files['widgets'] = $selected_import_data['local_import_widget_file'];
			}
		}

		// Get customizer import file as well. If defined!
		if (!empty($selected_import_data['import_customizer_file_url'])) {
			// Setup filename path to save the customizer content.
			$customizer_filename = 'options-' . $base_file_name . '.dat';

			// Retrieve the content import file.
			$import_files['options'] = $downloader->fetch_existing_file($customizer_filename);

			// Download the file, if it's missing.
			if (empty($import_files['options'])) {
				$import_files['options'] = $downloader->download_file($selected_import_data['import_customizer_file_url'], $customizer_filename);
			}

			// Reset the variable, if there was an error.
			if (is_wp_error($import_files['options'])) {
				$import_files['options'] = '';
			}
		} elseif (!empty($selected_import_data['local_import_customizer_file'])) {
			if (file_exists($selected_import_data['local_import_customizer_file'])) {
				$import_files['options'] = $selected_import_data['local_import_customizer_file'];
			}
		}

		// Get revolution slider import file as well. If defined!
		if (!empty($selected_import_data['import_rev_slider_file_url'])) {
			// Setup filename path to save the customizer content.
			$rev_slider_filename = 'slider-' . $base_file_name . '.zip';

			// Retrieve the content import file.
			$import_files['sliders'] = $downloader->fetch_existing_file($rev_slider_filename);

			// Download the file, if it's missing.
			if (empty($import_files['sliders'])) {
				$import_files['sliders'] = $downloader->download_file($selected_import_data['import_rev_slider_file_url'], $rev_slider_filename);
			}

			// Reset the variable, if there was an error.
			if (is_wp_error($import_files['sliders'])) {
				$import_files['sliders'] = '';
			}
		} elseif (!empty($selected_import_data['local_import_rev_slider_file'])) {
			if (file_exists($selected_import_data['local_import_rev_slider_file'])) {
				$import_files['sliders'] = $selected_import_data['local_import_rev_slider_file'];
			}
		}

		// Get redux import file as well. If defined!
		if (!empty($selected_import_data['import_redux'])) {
			$redux_items = array();

			// Setup filename paths to save the Redux content.
			foreach ($selected_import_data['import_redux'] as $index => $redux_item) {
				$redux_filename = 'redux-' . $index . '-' . $base_file_name . '.json';

				// Retrieve the content import file.
				$file_path = $downloader->fetch_existing_file($redux_filename);

				// Download the file, if it's missing.
				if (empty($file_path)) {
					$file_path = $downloader->download_file($redux_item['file_url'], $redux_filename);
				}

				// Reset the variable, if there was an error.
				if (is_wp_error($file_path)) {
					$file_path = '';
				}

				$redux_items[] = array(
					'option_name' => $redux_item['option_name'],
					'file_path'   => $file_path,
				);
			}

			// Download the Redux import file.
			$import_files['redux'] = $redux_items;
		} elseif (!empty($selected_import_data['local_import_redux'])) {
			$redux_items = array();

			// Setup filename paths to save the Redux content.
			foreach ($selected_import_data['local_import_redux'] as $redux_item) {
				if (file_exists($redux_item['file_path'])) {
					$redux_items[] = $redux_item;
				}
			}

			// Download the Redux import file.
			$import_files['redux'] = $redux_items;
		}

		return $import_files;
	}

	/**
	 * AJAX callback for the 'merlin_update_selected_import_data_info' action.
	 */
	public function update_selected_import_data_info()
	{
		$selected_index = !isset($_POST['selected_index']) ? false : intval($_POST['selected_index']);

		if (false === $selected_index) {
			wp_send_json_error();
		}

		$import_info      = $this->get_import_data_info($selected_index);
		$import_info_html = $this->get_import_steps_html($import_info);

		wp_send_json_success($import_info_html);
	}

	/**
	 * Get the import steps HTML output.
	 *
	 * @param array $import_info The import info to prepare the HTML for.
	 *
	 * @return string
	 */
	public function get_import_steps_html($import_info)
	{
		ob_start();
	?>
		<?php foreach ($import_info as $slug => $available) : ?>
			<?php
			if (!$available) {
				continue;
			}
			?>

			<li class="merlin__drawer--import-content__list-item status status--Pending" data-content="<?php echo esc_attr($slug); ?>">
				<input type="checkbox" name="default_content[<?php echo esc_attr($slug); ?>]" class="checkbox checkbox-<?php echo esc_attr($slug); ?>" id="default_content_<?php echo esc_attr($slug); ?>" value="1" checked>
				<label for="default_content_<?php echo esc_attr($slug); ?>">
					<i></i><span><?php echo esc_html(ucfirst(str_replace('_', ' ', $slug))); ?></span>
				</label>
			</li>

		<?php endforeach; ?>
<?php

		return ob_get_clean();
	}


	/**
	 * AJAX call for cleanup after the importing steps are done -> import finished.
	 */
	public function import_finished()
	{
		delete_transient('merlin_import_file_base_name');
		wp_send_json_success();
	}
	public function st_download_file()
	{
		if (!isset($_GET['download_item']) || empty($_GET['download_item']) || !is_user_logged_in()) {
			return;
		}


		if (!current_user_can('manage_options') || !isset($_GET['_wpnonce']) || !wp_verify_nonce($_GET['_wpnonce'], 'streamit_download')) {
			wp_die(__('Unauthorized request.', 'streamit'));
		}

		$streamit_file = basename($_GET['download_item']);
		$streamit_filepath = WP_CONTENT_DIR . "/uploads/$streamit_file";

		// Validate file existence
		if (!file_exists($streamit_filepath)) {
			wp_die(__('File not found.', 'streamit'));
		}

		// Validate allowed file types
		$streamit_allowed_types = ['zip']; // Allowed extensions
		$streamit_file_info = wp_check_filetype($streamit_file);
		if (!in_array($streamit_file_info['ext'], $streamit_allowed_types)) {
			wp_die(__('Invalid file type.', 'streamit'));
		}

		header('Content-Type: application/octet-stream');
		header('Content-Disposition: attachment; filename="' . $streamit_file . '"');
		readfile($streamit_filepath);
		exit;
	}

	public function merlin_import_data()
	{

		if ($_POST['content'] == 'movie') {
			$this->import_movie();
			return wp_send_json_success(
				[
					'status' => true,
					'data' 	=> 'video'
				]
			);
		} else if ($_POST['content'] == 'video') {
			$this->import_video();
			return wp_send_json_success(
				[
					'status' => true,
					'data' 	=> 'person'
				]
			);
		} else if ($_POST['content'] == 'person') {
			$this->import_person();
			return wp_send_json_success(
				[
					'status' => true,
					'data' 	=> 'tvshow'
				]
			);
		} else if ($_POST['content'] == 'tvshow') {
			$this->import_tvshow();
			return wp_send_json_success(
				[
					'status' => true,
					'data' 	=> 'episode'
				]
			);
		} else if ($_POST['content'] == 'episode') {
			$this->import_episode();
			return wp_send_json_success(
				[
					'status' => true,
					'data' 	=> 'term'
				]
			);
		} else if ($_POST['content'] == 'term') {

			$this->import_term();
			return wp_send_json_success(
				[
					'status' => true,
					'data' 	=> class_exists('Live_Streaming') ? 'channel' : ''
				]
			);
		} else if ($_POST['content'] == 'channel') {

			$this->import_channel();
			return wp_send_json_success(
				[
					'status' => true,
					'data' 	=> class_exists('Live_Streaming') ? 'channel_category' : ''
				]
			);
		} else if ($_POST['content'] == 'channel_category') {

			$this->import_channel_category();
			return wp_send_json_success(
				[
					'status' => false,
					'data' 	=> ''
				]
			);
		}
	}

	public function import_movie()
	{

		// Path to the JSON file
		$movies_file = get_template_directory() . '/admin/import/Data/streamit_movie.json';
		// Check if the file exists
		if (file_exists($movies_file)) {
			$movies_json = file_get_contents($movies_file);

			$movies = json_decode($movies_json, true);

			if ($movies && is_array($movies)) {
				// Loop through each movie
				foreach ($movies as $movie) {
					$data = array(
						'ID'					=> isset($movie['ID']) ? $movie['ID'] : '',
						'post_author'           => isset($movie['post_author']) ? $movie['post_author'] : get_current_user_id(),
						'post_date'             => isset($movie['post_date']) ? $movie['post_date'] : current_time('mysql'),
						'post_date_gmt'         => isset($movie['post_date_gmt']) ? $movie['post_date_gmt'] : current_time('mysql', 1),
						'post_title'            => isset($movie['post_title']) ? $movie['post_title'] : 'Untitled Movie',
						'post_content'          => isset($movie['post_content']) ? $movie['post_content'] : '',
						'post_excerpt'          => isset($movie['post_excerpt']) ? $movie['post_excerpt'] : '',
						'post_status'           => isset($movie['post_status']) ? $movie['post_status'] : 'publish',
						'comment_status'        => isset($movie['comment_status']) ? $movie['comment_status'] : 'closed',
						'ping_status'           => isset($movie['ping_status']) ? $movie['ping_status'] : 'closed',
						'post_name'             => isset($movie['post_name']) ? $movie['post_name'] : sanitize_title($movie['post_title']),
						'post_modified'         => isset($movie['post_modified']) ? $movie['post_modified'] : current_time('mysql'),
						'post_modified_gmt'     => isset($movie['post_modified_gmt']) ? $movie['post_modified_gmt'] : current_time('mysql', 1),
						'post_parent'           => isset($movie['post_parent']) ? $movie['post_parent'] : 0,
						'menu_order'            => isset($movie['menu_order']) ? $movie['menu_order'] : 0,
						'post_type'             => isset($movie['post_type']) ? $movie['post_type'] : 'movie',
						'post_mime_type'        => isset($movie['post_mime_type']) ? $movie['post_mime_type'] : '',
						'comment_count'         => isset($movie['comment_count']) ? $movie['comment_count'] : 0,
					);

					$movie_id = streamit_add_movie($data);
					if (!is_wp_error($movie_id)) {
						// Prepare metadata array
						$meta_data = array();

						// Define the keys for attachment-related meta
						$attachment_array = array(
							'thumbnail_id',
							'_movie_attachment_id',
							'_name_logo',
							'_name_trailer_img',
							'_portrait_thumbmail'
						);

						foreach ($movie['meta_data'] as $meta) {
							if (isset($meta['key']) && isset($meta['value'])) {
								$meta_key = $meta['key'];
								$meta_value = $meta['value'];

								// Check if the meta key is in the attachment array (image-related keys)
								if (in_array($meta_key, $attachment_array) && !empty($meta_value)) {
									$attachment_id = $this->download_media_to_library($meta_value);

									if (!is_wp_error($attachment_id)) {
										$meta_value = $attachment_id;
									} else {
										error_log('Failed to download image for key ' . $meta_key . ': ' . $attachment_id->get_error_message());
										continue;
									}
								}
								$meta_data[$meta_key] = $meta_value;
							}
						}
						streamit_add_group_movie_meta_entry($movie_id, $meta_data);
					}
				}
			}
		}

		return;
	}

	public function import_video()
	{
		// Path to the JSON file
		$videos_file = get_template_directory() . '/admin/import/Data/streamit_video.json';

		// Check if the file exists
		if (file_exists($videos_file)) {
			$videos_json = file_get_contents($videos_file);
			$videos = json_decode($videos_json, true);

			if ($videos && is_array($videos)) {
				// Loop through each video
				foreach ($videos as $video) {
					$data = array(
						'ID'                  => isset($video['ID']) ? $video['ID'] : '',
						'post_author'         => isset($video['post_author']) ? $video['post_author'] : get_current_user_id(),
						'post_date'           => isset($video['post_date']) ? $video['post_date'] : current_time('mysql'),
						'post_date_gmt'       => isset($video['post_date_gmt']) ? $video['post_date_gmt'] : current_time('mysql', 1),
						'post_title'          => isset($video['post_title']) ? $video['post_title'] : 'Untitled Video',
						'post_content'        => isset($video['post_content']) ? $video['post_content'] : '',
						'post_excerpt'        => isset($video['post_excerpt']) ? $video['post_excerpt'] : '',
						'post_status'         => isset($video['post_status']) ? $video['post_status'] : 'publish',
						'comment_status'      => isset($video['comment_status']) ? $video['comment_status'] : 'closed',
						'ping_status'         => isset($video['ping_status']) ? $video['ping_status'] : 'closed',
						'post_name'           => isset($video['post_name']) ? $video['post_name'] : sanitize_title($video['post_title']),
						'post_modified'       => isset($video['post_modified']) ? $video['post_modified'] : current_time('mysql'),
						'post_modified_gmt'   => isset($video['post_modified_gmt']) ? $video['post_modified_gmt'] : current_time('mysql', 1),
						'post_parent'         => isset($video['post_parent']) ? $video['post_parent'] : 0,
						'menu_order'          => isset($video['menu_order']) ? $video['menu_order'] : 0,
						'post_type'           => isset($video['post_type']) ? $video['post_type'] : 'video',
						'post_mime_type'      => isset($video['post_mime_type']) ? $video['post_mime_type'] : '',
						'comment_count'       => isset($video['comment_count']) ? $video['comment_count'] : 0,
					);

					$video_id = streamit_add_video($data);
					if (!is_wp_error($video_id)) {
						// Prepare metadata array
						$meta_data = array();

						// Define the keys for attachment-related meta
						$attachment_array = array(
							'thumbnail_id',
							'_video_attachment_id',
							'_name_logo',
							'_name_trailer_img',
							'_portrait_thumbmail'
						);

						foreach ($video['meta_data'] as $meta) {
							if (isset($meta['key']) && isset($meta['value'])) {
								$meta_key = $meta['key'];
								$meta_value = $meta['value'];

								// Check if the meta key is in the attachment array (video-related keys)
								if (in_array($meta_key, $attachment_array) && !empty($meta_value)) {
									$attachment_id = $this->download_media_to_library($meta_value);

									if (!is_wp_error($attachment_id)) {
										$meta_value = $attachment_id;
									} else {
										error_log('Failed to download video for key ' . $meta_key . ': ' . $attachment_id->get_error_message());
										continue;
									}
								}
								$meta_data[$meta_key] = $meta_value;
							}
						}
						streamit_group_video_meta_entry($video_id, $meta_data);
					}
				}
			}
		}

		return;
	}

	public function import_person()
	{
		// Path to the JSON file
		$persons_file = get_template_directory() . '/admin/import/Data/streamit_person.json';

		// Check if the file exists
		if (file_exists($persons_file)) {
			$persons_json = file_get_contents($persons_file);
			$persons = json_decode($persons_json, true);

			if ($persons && is_array($persons)) {
				// Loop through each person
				foreach ($persons as $person) {
					$data = array(
						'ID'                  => isset($person['ID']) ? $person['ID'] : '',
						'post_author'         => isset($person['post_author']) ? $person['post_author'] : get_current_user_id(),
						'post_date'           => isset($person['post_date']) ? $person['post_date'] : current_time('mysql'),
						'post_date_gmt'       => isset($person['post_date_gmt']) ? $person['post_date_gmt'] : current_time('mysql', 1),
						'post_title'          => isset($person['post_title']) ? $person['post_title'] : 'Untitled Person',
						'post_content'        => isset($person['post_content']) ? $person['post_content'] : '',
						'post_excerpt'        => isset($person['post_excerpt']) ? $person['post_excerpt'] : '',
						'post_status'         => isset($person['post_status']) ? $person['post_status'] : 'publish',
						'comment_status'      => isset($person['comment_status']) ? $person['comment_status'] : 'closed',
						'ping_status'         => isset($person['ping_status']) ? $person['ping_status'] : 'closed',
						'post_name'           => isset($person['post_name']) ? $person['post_name'] : sanitize_title($person['post_title']),
						'post_modified'       => isset($person['post_modified']) ? $person['post_modified'] : current_time('mysql'),
						'post_modified_gmt'   => isset($person['post_modified_gmt']) ? $person['post_modified_gmt'] : current_time('mysql', 1),
						'post_parent'         => isset($person['post_parent']) ? $person['post_parent'] : 0,
						'menu_order'          => isset($person['menu_order']) ? $person['menu_order'] : 0,
						'post_type'           => isset($person['post_type']) ? $person['post_type'] : 'person',
						'post_mime_type'      => isset($person['post_mime_type']) ? $person['post_mime_type'] : '',
						'comment_count'       => isset($person['comment_count']) ? $person['comment_count'] : 0,
					);

					$person_id = streamit_add_person($data);
					if (!is_wp_error($person_id)) {
						// Prepare metadata array
						$meta_data = array();

						// Define the keys for attachment-related meta
						$attachment_array = array(
							'thumbnail_id'
						);

						foreach ($person['meta_data'] as $meta) {
							if (isset($meta['key']) && isset($meta['value'])) {
								$meta_key = $meta['key'];
								$meta_value = $meta['value'];

								// Check if the meta key is in the attachment array (person-related keys)
								if (in_array($meta_key, $attachment_array) && !empty($meta_value)) {
									$attachment_id = $this->download_media_to_library($meta_value);

									if (!is_wp_error($attachment_id)) {
										$meta_value = $attachment_id;
									} else {
										error_log('Failed to download media for key ' . $meta_key . ': ' . $attachment_id->get_error_message());
										continue;
									}
								}
								$meta_data[$meta_key] = $meta_value;
							}
						}
						streamit_add_group_person_meta_entry($person_id, $meta_data);
					}
				}
			}
		}

		return;
	}

	public function import_tvshow()
	{
		// Path to the JSON file
		$tv_shows_file = get_template_directory() . '/admin/import/Data/streamit_tvshow.json';

		// Check if the file exists
		if (file_exists($tv_shows_file)) {
			$tv_shows_json = file_get_contents($tv_shows_file);
			$tv_shows = json_decode($tv_shows_json, true);

			if ($tv_shows && is_array($tv_shows)) {
				// Loop through each TV show
				foreach ($tv_shows as $tv_show) {
					$data = array(
						'ID'                  => isset($tv_show['ID']) ? $tv_show['ID'] : '',
						'post_author'         => isset($tv_show['post_author']) ? $tv_show['post_author'] : get_current_user_id(),
						'post_date'           => isset($tv_show['post_date']) ? $tv_show['post_date'] : current_time('mysql'),
						'post_date_gmt'       => isset($tv_show['post_date_gmt']) ? $tv_show['post_date_gmt'] : current_time('mysql', 1),
						'post_title'          => isset($tv_show['post_title']) ? $tv_show['post_title'] : 'Untitled TV Show',
						'post_content'        => isset($tv_show['post_content']) ? $tv_show['post_content'] : '',
						'post_excerpt'        => isset($tv_show['post_excerpt']) ? $tv_show['post_excerpt'] : '',
						'post_status'         => isset($tv_show['post_status']) ? $tv_show['post_status'] : 'publish',
						'comment_status'      => isset($tv_show['comment_status']) ? $tv_show['comment_status'] : 'closed',
						'ping_status'         => isset($tv_show['ping_status']) ? $tv_show['ping_status'] : 'closed',
						'post_name'           => isset($tv_show['post_name']) ? $tv_show['post_name'] : sanitize_title($tv_show['post_title']),
						'post_modified'       => isset($tv_show['post_modified']) ? $tv_show['post_modified'] : current_time('mysql'),
						'post_modified_gmt'   => isset($tv_show['post_modified_gmt']) ? $tv_show['post_modified_gmt'] : current_time('mysql', 1),
						'post_parent'         => isset($tv_show['post_parent']) ? $tv_show['post_parent'] : 0,
						'menu_order'          => isset($tv_show['menu_order']) ? $tv_show['menu_order'] : 0,
						'post_type'           => isset($tv_show['post_type']) ? $tv_show['post_type'] : 'tv_show',
						'post_mime_type'      => isset($tv_show['post_mime_type']) ? $tv_show['post_mime_type'] : '',
						'comment_count'       => isset($tv_show['comment_count']) ? $tv_show['comment_count'] : 0,
					);

					$tv_show_id = streamit_add_tvshow($data);
					if (!is_wp_error($tv_show_id)) {
						// Prepare metadata array
						$meta_data = array();

						$attachment_array = array(
							'thumbnail_id',
							'_name_logo',
							'_name_trailer_img',
							'_portrait_thumbmail'
						);

						foreach ($tv_show['meta_data'] as $meta) {
							if (isset($meta['key']) && isset($meta['value'])) {
								$meta_key = $meta['key'];
								$meta_value = $meta['value'];

								// Check if the meta key is in the attachment array (TV show-related keys)
								if (in_array($meta_key, $attachment_array) && !empty($meta_value)) {
									$attachment_id = $this->download_media_to_library($meta_value);

									if (!is_wp_error($attachment_id)) {
										$meta_value = $attachment_id;
									} else {
										error_log('Failed to download media for key ' . $meta_key . ': ' . $attachment_id->get_error_message());
										continue;
									}
								}
								$meta_data[$meta_key] = $meta_value;
							}
						}
						streamit_add_group_tvshow_meta_entry($tv_show_id, $meta_data);
					}
				}
			}
		}

		return;
	}


	public function import_episode()
	{
		// Path to the JSON file
		$episodes_file = get_template_directory() . '/admin/import/Data/streamit_episode.json';

		// Check if the file exists
		if (file_exists($episodes_file)) {
			$episodes_json = file_get_contents($episodes_file);
			$episodes = json_decode($episodes_json, true);

			if ($episodes && is_array($episodes)) {
				// Loop through each episode
				foreach ($episodes as $episode) {
					$data = array(
						'ID'                  => isset($episode['ID']) ? $episode['ID'] : '',
						'post_author'         => isset($episode['post_author']) ? $episode['post_author'] : get_current_user_id(),
						'post_date'           => isset($episode['post_date']) ? $episode['post_date'] : current_time('mysql'),
						'post_date_gmt'       => isset($episode['post_date_gmt']) ? $episode['post_date_gmt'] : current_time('mysql', 1),
						'post_title'          => isset($episode['post_title']) ? $episode['post_title'] : 'Untitled Episode',
						'post_content'        => isset($episode['post_content']) ? $episode['post_content'] : '',
						'post_excerpt'        => isset($episode['post_excerpt']) ? $episode['post_excerpt'] : '',
						'post_status'         => isset($episode['post_status']) ? $episode['post_status'] : 'publish',
						'comment_status'      => isset($episode['comment_status']) ? $episode['comment_status'] : 'closed',
						'ping_status'         => isset($episode['ping_status']) ? $episode['ping_status'] : 'closed',
						'post_name'           => isset($episode['post_name']) ? $episode['post_name'] : sanitize_title($episode['post_title']),
						'post_modified'       => isset($episode['post_modified']) ? $episode['post_modified'] : current_time('mysql'),
						'post_modified_gmt'   => isset($episode['post_modified_gmt']) ? $episode['post_modified_gmt'] : current_time('mysql', 1),
						'post_parent'         => isset($episode['post_parent']) ? $episode['post_parent'] : 0, // Assuming it's linked to a TV show
						'menu_order'          => isset($episode['menu_order']) ? $episode['menu_order'] : 0,
						'post_type'           => isset($episode['post_type']) ? $episode['post_type'] : 'episode',
						'post_mime_type'      => isset($episode['post_mime_type']) ? $episode['post_mime_type'] : '',
						'comment_count'       => isset($episode['comment_count']) ? $episode['comment_count'] : 0,
					);

					$episode_id = streamit_add_episode($data);
					if (!is_wp_error($episode_id)) {
						// Prepare metadata array
						$meta_data = array();

						// Define the keys for attachment-related meta
						$attachment_array = array(
							'thumbnail_id',
							'_name_trailer_img'
						);

						foreach ($episode['meta_data'] as $meta) {
							if (isset($meta['key']) && isset($meta['value'])) {
								$meta_key = $meta['key'];
								$meta_value = $meta['value'];

								// Check if the meta key is in the attachment array (episode-related keys)
								if (in_array($meta_key, $attachment_array) && !empty($meta_value)) {
									$attachment_id = $this->download_media_to_library($meta_value);

									if (!is_wp_error($attachment_id)) {
										$meta_value = $attachment_id;
									} else {
										error_log('Failed to download media for key ' . $meta_key . ': ' . $attachment_id->get_error_message());
										continue;
									}
								}
								$meta_data[$meta_key] = $meta_value;
							}
						}
						streamit_add_group_episode_meta_entry($episode_id, $meta_data);
					}
				}
			}
		}

		return;
	}


	public function import_term()
	{
		// Path to the JSON file
		$terms_file = get_template_directory() . '/admin/import/Data/streamit_term.json';
		// Check if the file exists
		if (file_exists($terms_file)) {
			$terms_json = file_get_contents($terms_file);
			$terms = json_decode($terms_json, true);

			if ($terms && is_array($terms)) {
				foreach ($terms as $term) {
					$data = array(
						'term_id'           => isset($term['term_id']) ? $term['term_id'] : '',
						'term_name'         => isset($term['term_name']) ? $term['term_name'] : '',
						'term_slug'        	=> isset($term['term_slug']) ? $term['term_slug'] : '',
						'taxonomy'         	=> isset($term['taxonomy']) ? $term['taxonomy'] : '',
						'parent_term'		=> isset($term['parent_term']) ? $term['parent_term'] : 0,
						'description'		=> isset($term['description']) ? $term['description'] : '',
					);
					$url = isset($term['thumbnail']) ? $term['thumbnail'] : 0;
					$image_id = (!is_wp_error($url)) ? $this->download_media_to_library($url) : 0;
					$data['thumbnail'] 	= $image_id;
					$term_id = streamit_add_term($data);
					if (!is_wp_error($term_id)) {
						$term_relation = isset($term['term_relation']) ? $term['term_relation'] : '';
						if (!empty($term_relation)) {
							foreach ($term_relation as $relation) {
								streamit_insert_term_relationships($relation['post_id'], (array)$relation['term_id'], $relation['taxonomy']);
							}
						}
					}
				}
			}
		}
		return;
	}

	public function import_channel()
	{
		// Path to the JSON file
		if (!function_exists('ls_add_channel')) {
			return;
		}
		$channel_file = get_template_directory() . '/admin/import/Data/streamit_channel.json';
		if (file_exists($channel_file)) {
			$channel_json = file_get_contents($channel_file);

			$channels = json_decode($channel_json, true);

			if ($channels && is_array($channels)) {
				// Loop through each channel
				foreach ($channels as $channel) {
					$data = array(
						'ID'					=> isset($channel['ID']) ? $channel['ID'] : '',
						'post_author'           => isset($channel['post_author']) ? $channel['post_author'] : get_current_user_id(),
						'post_date'             => isset($channel['post_date']) ? $channel['post_date'] : current_time('mysql'),
						'post_date_gmt'         => isset($channel['post_date_gmt']) ? $channel['post_date_gmt'] : current_time('mysql', 1),
						'post_title'            => isset($channel['post_title']) ? $channel['post_title'] : 'Untitled channel',
						'post_content'          => isset($channel['post_content']) ? $channel['post_content'] : '',
						'post_excerpt'          => isset($channel['post_excerpt']) ? $channel['post_excerpt'] : '',
						'post_status'           => isset($channel['post_status']) ? $channel['post_status'] : 'publish',
						'comment_status'        => isset($channel['comment_status']) ? $channel['comment_status'] : 'closed',
						'ping_status'           => isset($channel['ping_status']) ? $channel['ping_status'] : 'closed',
						'post_name'             => isset($channel['post_name']) ? $channel['post_name'] : sanitize_title($channel['post_title']),
						'post_modified'         => isset($channel['post_modified']) ? $channel['post_modified'] : current_time('mysql'),
						'post_modified_gmt'     => isset($channel['post_modified_gmt']) ? $channel['post_modified_gmt'] : current_time('mysql', 1),
						'post_parent'           => isset($channel['post_parent']) ? $channel['post_parent'] : 0,
						'menu_order'            => isset($channel['menu_order']) ? $channel['menu_order'] : 0,
						'post_type'             => isset($channel['post_type']) ? $channel['post_type'] : 'channel',
						'post_mime_type'        => isset($channel['post_mime_type']) ? $channel['post_mime_type'] : '',
						'comment_count'         => isset($channel['comment_count']) ? $channel['comment_count'] : 0,
					);

					$channel_id = ls_add_channel($data);
					if (!is_wp_error($channel_id)) {
						// Prepare metadata array
						$meta_data = array();

						// Define the keys for attachment-related meta
						$attachment_array = array(
							'thumbnail_id',
							'_portrait_thumbmail'
						);

						foreach ($channel['meta_data'] as $meta) {
							if (isset($meta['key']) && isset($meta['value'])) {
								$meta_key = $meta['key'];
								$meta_value = $meta['value'];

								// Check if the meta key is in the attachment array (image-related keys)
								if (in_array($meta_key, $attachment_array) && !empty($meta_value)) {
									$attachment_id = $this->download_media_to_library($meta_value);

									if (!is_wp_error($attachment_id)) {
										$meta_value = $attachment_id;
									} else {
										error_log('Failed to download image for key ' . $meta_key . ': ' . $attachment_id->get_error_message());
										continue;
									}
								}
								$meta_data[$meta_key] = $meta_value;
							}
						}
						ls_add_group_channel_meta_entry($channel_id, $meta_data);
					}
				}
			}
		}

		return;
	}

	public function import_channel_category()
	{

		// Path to the JSON file
		$terms_file = get_template_directory() . '/admin/import/Data/streamit_channel_category.json';
		// Check if the file exists
		if (file_exists($terms_file)) {
			$terms_json = file_get_contents($terms_file);
			$terms = json_decode($terms_json, true);

			if ($terms && is_array($terms)) {
				foreach ($terms as $term) {
					$data = array(
						'term_id'           => isset($term['term_id']) ? $term['term_id'] : '',
						'term_name'         => isset($term['term_name']) ? $term['term_name'] : '',
						'term_slug'        	=> isset($term['term_slug']) ? $term['term_slug'] : '',
						'taxonomy'         	=> isset($term['taxonomy']) ? $term['taxonomy'] : '',
						'parent_term'		=> isset($term['parent_term']) ? $term['parent_term'] : 0,
						'description'		=> isset($term['description']) ? $term['description'] : '',
					);
					$url = isset($term['thumbnail']) ? $term['thumbnail'] : 0;
					$image_id = (!is_wp_error($url)) ? $this->download_media_to_library($url) : 0;
					$data['thumbnail'] 	= $image_id;
					$term_id = ls_add_term($data);
					if (!is_wp_error($term_id)) {
						$term_relation = isset($term['term_relation']) ? $term['term_relation'] : '';
						if (!empty($term_relation)) {
							foreach ($term_relation as $relation) {
								ls_insert_term_relationships($relation['post_id'], (array)$relation['term_id'], $relation['taxonomy']);
							}
						}
					}
				}
			}
		}
		return;
	}

	public function download_media_to_library($media_url)
	{
		// Validate the provided URL
		if (empty($media_url) || !filter_var($media_url, FILTER_VALIDATE_URL)) {
			return 0;
		}

		// Check if the media URL already exists in the media library
		global $wpdb;
		$attachment_id = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT ID FROM {$wpdb->posts} WHERE post_type = 'attachment' AND guid = %s LIMIT 1",
				$media_url
			)
		);

		// If an attachment with this URL exists, return its ID
		if ($attachment_id) {
			return $attachment_id;
		}

		// Get the file name and its extension
		$file_name = basename($media_url);
		$upload_dir = wp_upload_dir();

		// Prepare the file path
		$file_path = $upload_dir['path'] . '/' . $file_name;

		// Download the file from the provided URL
		$response = wp_remote_get($media_url, ['timeout' => 20]); // Added timeout for long requests
		if (is_wp_error($response) || wp_remote_retrieve_response_code($response) !== 200) {
			return 0;
		}

		// Write the file content to disk
		$file_data = wp_remote_retrieve_body($response);
		if (!file_put_contents($file_path, $file_data)) {
			return 0;
		}

		// Ensure the file was created
		if (!file_exists($file_path)) {
			return 0;
		}

		// Get MIME type dynamically
		$file_type = wp_check_filetype($file_name, null);
		if (empty($file_type['type'])) {
			return 0;
		}

		// Prepare attachment data
		$attachment = [
			'post_mime_type' => $file_type['type'], // Set MIME type
			'post_title'     => sanitize_file_name($file_name),
			'post_content'   => '',
			'post_status'    => 'inherit',
			'guid'           => $media_url, // Set the guid to the original URL
		];

		// Insert the attachment into the media library
		$attachment_id = wp_insert_attachment($attachment, $file_path);
		if (is_wp_error($attachment_id)) {
			return 0;
		}

		// Generate and update attachment metadata
		require_once(ABSPATH . 'wp-admin/includes/image.php');
		$attachment_metadata = wp_generate_attachment_metadata($attachment_id, $file_path);
		if (is_wp_error($attachment_metadata)) {
			return 0;
		}

		wp_update_attachment_metadata($attachment_id, $attachment_metadata);

		return $attachment_id; // Return the attachment ID
	}
}
