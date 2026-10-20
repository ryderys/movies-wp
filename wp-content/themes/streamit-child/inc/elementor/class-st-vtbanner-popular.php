<?php
/**
 * Vertical Thumbnail Banner with most_viewed / latest post filters.
 *
 * @package streamit-child
 */

namespace Elementor;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Extends Streamit ST_VTBenner so homepage rails can sort by views or date.
 */
class ST_VTBanner_Popular extends ST_VTBenner {

	/**
	 * Add most_viewed + latest to Posts Filter.
	 */
	protected function register_controls() {
		parent::register_controls();

		$filter           = $this->get_controls( 'st_post_filter' );
		$existing_options = ( is_array( $filter ) && isset( $filter['options'] ) && is_array( $filter['options'] ) )
			? $filter['options']
			: array(
				'selected' => __( 'Selected', 'streamit' ),
				'genre'    => __( 'Genre', 'streamit' ),
				'tag'      => __( 'Tag', 'streamit' ),
			);
		$existing_options['most_viewed'] = __( 'Most Viewed', 'streamit' );
		$existing_options['latest']      = __( 'Latest', 'streamit' );

		$this->update_control(
			'st_post_filter',
			array(
				'options' => $existing_options,
			)
		);
	}

	/**
	 * Handle most_viewed / latest; defer everything else to parent.
	 */
	protected function render() {
		$settings     = $this->get_settings_for_display();
		$filter_value = (string) ( $settings['st_post_filter'] ?? '' );

		if ( ! in_array( $filter_value, array( 'most_viewed', 'latest' ), true ) ) {
			parent::render();
			return;
		}

		$post_type = (string) ( $settings['st_select_content_type'] ?? 'tvshow' );
		$per_page  = isset( $settings['posts_per_page']['size'] ) ? (int) $settings['posts_per_page']['size'] : 6;
		if ( $per_page <= 0 ) {
			$per_page = 6;
		}

		$func_map = array(
			'movie'  => 'streamit_get_movies',
			'tvshow' => 'streamit_get_tvshows',
			'video'  => 'streamit_get_videos',
		);
		if ( empty( $func_map[ $post_type ] ) || ! function_exists( $func_map[ $post_type ] ) ) {
			parent::render();
			return;
		}

		$upcoming_meta_key = "{$post_type}_upcoming_status";
		$args              = array(
			'per_page' => $per_page,
			'paged'    => 1,
		);

		if ( 'latest' === $filter_value ) {
			$args['orderby']    = 'post_date';
			$args['order']      = 'DESC';
			$args['meta_query'] = array(
				'relation' => 'OR',
				array(
					'key'     => $upcoming_meta_key,
					'compare' => 'NOT EXISTS',
				),
				array(
					'key'     => $upcoming_meta_key,
					'value'   => '0',
					'compare' => '=',
				),
			);
		} else {
			$args['orderby']    = 'meta_value_num';
			$args['order']      = 'DESC';
			$args['meta_key']   = 'post_views_count';
			$args['meta_query'] = array(
				'relation' => 'OR',
				array(
					'key'     => $upcoming_meta_key,
					'value'   => '0',
					'compare' => '=',
				),
				array(
					'key'     => $upcoming_meta_key,
					'compare' => 'NOT EXISTS',
				),
			);
		}

		$query   = call_user_func( $func_map[ $post_type ], $args );
		$results = ( is_object( $query ) && isset( $query->results ) && is_array( $query->results ) )
			? $query->results
			: array();

		streamit_get_template(
			'elementor-widget/vertical-thumbnail-banner/html-vertical-thumbnail-banner-slider.php',
			array(
				'results'  => $results,
				'id_int'   => wp_rand( 10, 100 ),
				'settings' => $settings,
			)
		);
	}
}
