<?php

/**
 * Class streamit_theme_helper
 *
 * This class handles the inclusion of custom functions and classes for the theme.
 *
 * @package streamit
 */
if (! class_exists('streamit_theme_helper')) {

	/**
	 * streamit_theme_helper class.
	 * 
	 * This class is responsible for loading custom classes and functions for the theme.
	 */
	class streamit_theme_helper
	{

		/**
		 * Constructor function.
		 *
		 * Initializes the inclusion of custom classes and functions.
		 * 
		 * @return void
		 */
		public function __construct()
		{
			$this->include_classes();
			$this->include_functions();

			add_action('st_before_primary_content_area', [$this, 'enqueue_episode_overlay_template'], 10);
		
		}

		/**
		 * Includes custom class files.
		 *
		 * This method includes necessary custom class files for the theme. 
		 * These classes provide helper functions for various theme features like movie, video, person, etc.
		 *
		 * @return void
		 */
		public function include_classes()
		{
			require_once get_template_directory() . '/inc/theme-helper/classes/class-streamit-general-helper.php';
			require_once get_template_directory() . '/inc/theme-helper/classes/class-streamit-shortcode-helper.php';
			require_once get_template_directory() . '/inc/theme-helper/classes/class-streamit-woo-helper.php';
			require_once get_template_directory() . '/inc/theme-helper/classes/class-streamit-pmp-helper.php';
			require_once get_template_directory() . '/inc/theme-helper/classes/class-streamit-profile-tabs-manger.php';
		}

		/**
		 * Includes custom function files.
		 *
		 * This method includes necessary custom function files for the theme. 
		 * These functions assist in handling various aspects like general helper functions, movie-related functions, etc.
		 *
		 * @return void
		 */
		public function include_functions()
		{
			require_once get_template_directory() . '/inc/theme-helper/streamit-general-helper-function.php';
		}

		/**
		 * Generate and localize the overlay template for episodes only
		 *
		 * @param string $content_view Format: 'episode_single', 'movie_archive', etc.
		 */
		public function enqueue_episode_overlay_template($content_view)
		{
			if (strpos($content_view, 'episode_') === 0) {
				try {
					$args = [
						'thumbnail'     => '${nextEpisode.thumbnail}',
						'seasonNumber'  => '${nextEpisode.seasonNumber}',
						'title'         => '${nextEpisode.title}',
					];

					$args = apply_filters('streamit_next_episode_overlay_args', $args);

					// Capture template output
					ob_start();
					streamit_get_template('episode/content/episode_next_overlay.php',  ["args" => $args]);
					$template_html = ob_get_clean();

					$template_html = apply_filters('streamit_next_episode_overlay_html', $template_html, $args);

					wp_localize_script('streamit-main', 'streamitPlayerVars', array(
						'nextEpisodeOverlayHTML' => $template_html
					));
				} catch (Exception $e) {
					error_log('Error generating next episode overlay template: ' . $e->getMessage());
				}
			}
		}

	
	}

	// Initialize the helper class.
	new streamit_theme_helper();
}
