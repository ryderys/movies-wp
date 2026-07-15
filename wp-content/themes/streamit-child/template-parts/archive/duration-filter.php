<?php
/**
 * Duration filter widget for movie archives.
 *
 * @package streamit-child
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="filter-widget filter-widget-duration">
	<h5 class="filter-wiget-title"><?php echo esc_html__( 'By Duration', 'streamit' ); ?></h5>
	<div class="filter-widget-inner">
		<div class="filter-list">
			<?php
			$durations = array(
				array(
					'value' => 'under_1_hour',
					'id'    => 'duration_under_1',
					'text'  => 'Under 1 Hour',
				),
				array(
					'value' => '1_2_hours',
					'id'    => 'duration_1_2',
					'text'  => '1-2 Hours',
				),
				array(
					'value' => '2_3_hours',
					'id'    => 'duration_2_3',
					'text'  => '2-3 Hours',
				),
				array(
					'value' => '3_up',
					'id'    => 'duration_3_up',
					'text'  => '3+ Hours',
				),
			);

			foreach ( $durations as $duration ) :
				?>
				<div class="form-check">
					<input
						class="form-check-input streamit-filter"
						type="radio"
						value="<?php echo esc_attr( $duration['value'] ); ?>"
						id="<?php echo esc_attr( $duration['id'] ); ?>"
						name="duration"
					>
					<label class="form-check-label" for="<?php echo esc_attr( $duration['id'] ); ?>">
						<span class="filter-text"><?php echo esc_html__( $duration['text'], 'streamit' ); ?></span>
					</label>
				</div>
				<?php
			endforeach;
			?>
		</div>
	</div>
</div>
