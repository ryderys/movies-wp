<?php
/**
 * Release year filter widget for movie/TV archives.
 *
 * @package streamit-child
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$current_year = (int) gmdate( 'Y' );
$year_ranges  = array(
	array(
		'id'        => 'current-year',
		'value'     => (string) $current_year,
		'label'     => (string) $current_year,
		'col_class' => 'col-12',
	),
	array(
		'id'        => 'year-2020-current',
		'value'     => '2020-' . ( $current_year - 1 ),
		'label'     => '2020-' . ( $current_year - 1 ),
		'col_class' => 'col-sm-6',
	),
);

$decades = array(
	array( 2000, 2020 ),
	array( 1980, 2000 ),
	array( 1960, 1980 ),
	array( 1940, 1960 ),
	array( 1920, 1940 ),
);

foreach ( $decades as $decade ) {
	$year_ranges[] = array(
		'id'        => 'year-' . $decade[0] . '-' . $decade[1],
		'value'     => $decade[0] . '-' . $decade[1],
		'label'     => $decade[0] . '-' . $decade[1],
		'col_class' => 'col-sm-6',
	);
}
?>
<div class="filter-widget filter-widget-year">
	<h5 class="filter-wiget-title"><?php echo esc_html__( 'By Release Years', 'streamit' ); ?></h5>
	<div class="filter-widget-inner">
		<div class="filter-list">
			<div class="row g-2 w-100">
				<?php foreach ( $year_ranges as $range ) : ?>
					<div class="<?php echo esc_attr( $range['col_class'] ); ?>">
						<div class="form-check">
							<input
								class="form-check-input streamit-filter"
								type="radio"
								name="release_year"
								id="<?php echo esc_attr( $range['id'] ); ?>"
								value="<?php echo esc_attr( $range['value'] ); ?>"
							>
							<label class="form-check-label" for="<?php echo esc_attr( $range['id'] ); ?>">
								<span class="d-flex align-items-center justify-content-center gap-2">
									<i class="icon-calendar-2"></i>
									<span><?php echo esc_html( $range['label'] ); ?></span>
								</span>
							</label>
						</div>
					</div>
				<?php endforeach; ?>
			</div>
		</div>
	</div>
</div>
