<?php
/**
 * Paginated Person Card widget (extends Streamit ST_Person).
 *
 * @package streamit-child
 */

namespace Elementor;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Person Card with real pagination for grid listings (e.g. /casts/).
 */
class ST_Person_Card_Paginated extends ST_Person {

	/**
	 * Render the widget output on the frontend.
	 */
	protected function render() {
		$settings = $this->get_settings_for_display();

		$slick_settings = array(
			'dots'           => false,
			'slidesToShow'   => ! empty( $settings['desk_number'] ) ? intval( $settings['desk_number'] ) : 11,
			'slidesToScroll' => ! empty( $settings['desk_number'] ) ? intval( $settings['desk_number'] ) : 11,
			'arrows'         => ! empty( $settings['nav-arrow'] ) && $settings['nav-arrow'] === 'true',
			'autoplay'       => ! empty( $settings['autoplay'] ) && $settings['autoplay'] === 'true',
			'autoplaySpeed'  => ! empty( $settings['autoplay_speed'] ) ? intval( $settings['autoplay_speed'] ) : 2000,
			'speed'          => ! empty( $settings['speed'] ) ? intval( $settings['speed'] ) : 300,
			'infinite'       => ! empty( $settings['infinite'] ) && $settings['infinite'] === 'true',
			'responsive'     => array(
				array(
					'breakpoint' => 1367,
					'settings'   => array(
						'slidesToShow'   => ! empty( $settings['lap_number'] ) ? intval( $settings['lap_number'] ) : 7,
						'slidesToScroll' => ! empty( $settings['lap_number'] ) ? intval( $settings['lap_number'] ) : 7,
					),
				),
				array(
					'breakpoint' => 1025,
					'settings'   => array(
						'slidesToShow'   => ! empty( $settings['tab_number'] ) ? intval( $settings['tab_number'] ) : 5,
						'slidesToScroll' => ! empty( $settings['tab_number'] ) ? intval( $settings['tab_number'] ) : 5,
					),
				),
				array(
					'breakpoint' => 768,
					'settings'   => array(
						'slidesToShow'   => ! empty( $settings['mob_number'] ) ? intval( $settings['mob_number'] ) : 3,
						'slidesToScroll' => ! empty( $settings['mob_number'] ) ? intval( $settings['mob_number'] ) : 3,
					),
				),
			),
		);

		$template_type = $settings['st_style'];
		$filter_type   = $settings['st_person_filter'];
		$is_grid       = ( 'grid' === $template_type );
		$per_page      = streamit_child_person_card_per_page( $settings );
		$paged         = $is_grid ? streamit_child_get_request_page_number() : 1;

		$args = array(
			'per_page' => $per_page,
			'paged'    => $paged,
		);

		if ( 'selected' === $filter_type && ! empty( $settings['st_selected_person'] ) ) {
			// Keep pagination — do NOT force per_page=-1 like the parent widget.
			$args['include'] = $settings['st_selected_person'];
		} elseif ( ! empty( $settings['st_selected_person_category'] ) ) {
			$term_ids  = $settings['st_selected_person_category'];
			$tax_query = array( 'relation' => 'OR' );
			foreach ( $term_ids as $term_id ) {
				$tax_query[] = array(
					'field'    => 'term_id',
					'terms'    => $term_id,
					'operator' => '=',
				);
			}
			$args['tax_query'] = $tax_query;
		}

		// Sliders still load a single batch; grids paginate.
		if ( ! $is_grid ) {
			$args['paged'] = 1;
		}

		$persons_data = function_exists( 'streamit_get_persons' ) ? streamit_get_persons( $args ) : (object) array( 'results' => array(), 'maxnumpages' => 0 );
		$results      = array();

		if ( ! empty( $persons_data->results ) ) {
			foreach ( $persons_data->results as $person ) {
				$results[] = array( 'data' => $person );
			}
		}

		$title_tag    = $settings['title_tag'] ?? 'h3';
		$slider_title = esc_html( $settings['slider_title'] );
		$maxnumpages = isset( $persons_data->maxnumpages ) ? (int) $persons_data->maxnumpages : 0;

		streamit_get_template(
			'elementor-widget/person-card/html-person-card-' . $template_type . '.php',
			array(
				'slick_settings'          => $slick_settings,
				'results'                 => $results,
				'settings'                => $settings,
				'slingle_slider_settings' => '',
				'title_tag'               => $title_tag,
				'slider_title'            => $slider_title,
				'maxnumpages'             => $maxnumpages,
				'current_page'            => $paged,
				'per_page'                => $per_page,
			)
		);
	}
}
