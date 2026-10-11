<?php
/**
 * Main Card Slider with a "TV Show Status" post filter (extends Streamit ST_Slider).
 *
 * @package streamit-child
 */

namespace Elementor;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Adds a `status` option to "Select Post Filter" backed by the `tv_status` taxonomy.
 */
class ST_Slider_Tv_Status extends ST_Slider {

	/**
	 * Register parent controls, then add the status filter + term picker.
	 */
	protected function register_controls() {
		parent::register_controls();

		$post_filter      = $this->get_controls( 'st_select_post_filter' );
		$existing_options = ( is_array( $post_filter ) && isset( $post_filter['options'] ) && is_array( $post_filter['options'] ) )
			? $post_filter['options']
			: array(
				'selected'  => __( 'Selected', 'streamit' ),
				'genre'     => __( 'Genre', 'streamit' ),
				'tag'       => __( 'Tags', 'streamit' ),
				'upcoming'  => __( 'Upcoming', 'streamit' ),
				'latest'    => __( 'Latest', 'streamit' ),
				'most_like' => __( 'Most Like', 'streamit' ),
				'rent'      => __( 'Rent', 'streamit' ),
				'plan'      => __( 'Paid', 'streamit' ),
				'anyone'    => __( 'Rent or Paid', 'streamit' ),
			);
		$existing_options['status'] = __( 'TV Show Status', 'streamit' );

		$this->update_control(
			'st_select_post_filter',
			array(
				'options' => $existing_options,
			)
		);

		$this->start_injection( array( 'of' => 'st_selected_video_tag' ) );

		$this->add_control(
			'st_selected_tvshow_status',
			array(
				'label'             => __( 'Display Tv Show From Specific Status', 'streamit' ),
				'type'              => 'st_ajax_select',
				'ajax_action'       => 'streamit_elementor_select_ajax',
				'multiple'          => true,
				'placeholder'       => __( 'Select items...', 'streamit' ),
				'custom_attributes' => array(
					'data-ajax-params' => wp_json_encode(
						array(
							'callback' => 'streamit_get_term_list',
							'argument' => array(
								'per_page' => 100,
								'taxonomy' => array( STREAMIT_CHILD_TV_STATUS_TAXONOMY ),
							),
						)
					),
				),
				'condition'         => array(
					'st_select_content_type' => 'tvshow',
					'st_select_post_filter'  => 'status',
				),
				'description'       => __( '💡 Drag and drop to set proper content.', 'streamit' ),
			)
		);

		$this->end_injection();
	}

	/**
	 * Feed selected status term IDs to the parent render.
	 *
	 * The parent render() sends any unknown post filter to its `default` branch, which builds a
	 * term_id tax_query from the content type's *genre* setting. Status terms live in the same
	 * Streamit term table, so swapping them in reuses that query without copying render().
	 *
	 * @param string|null $setting_key Optional single setting key.
	 * @return mixed
	 */
	public function get_settings_for_display( $setting_key = null ) {
		$settings = parent::get_settings_for_display( $setting_key );

		if ( null !== $setting_key || ! is_array( $settings ) || 'status' !== ( $settings['st_select_post_filter'] ?? '' ) ) {
			return $settings;
		}

		$settings['st_selected_tvshow_genre'] = $settings['st_selected_tvshow_status'] ?? array();
		$settings['st_selected_movie_genre']  = array();
		$settings['st_selected_video_genre']  = array();

		return $settings;
	}
}
