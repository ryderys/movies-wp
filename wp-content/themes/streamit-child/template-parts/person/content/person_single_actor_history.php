<?php
/**
 * Person history (cast/crew filmography) — child override.
 *
 * Uses reverse person meta when present, otherwise finds movies/TV shows that
 * list this person in `_cast` / `_crew` (fixes empty history for some imports).
 *
 * @package streamit
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$movies  = array();
$tvshows = array();

$history_ids = function_exists( 'streamit_child_get_person_history_ids' )
	? streamit_child_get_person_history_ids( $st_data )
	: array(
		'movie_ids'  => array(),
		'tvshow_ids' => array(),
	);

$movie_ids  = $history_ids['movie_ids'] ?? array();
$tvshow_ids = $history_ids['tvshow_ids'] ?? array();

if ( ! empty( $movie_ids ) && function_exists( 'streamit_get_movies' ) ) {
	$movies = streamit_get_movies(
		array(
			'per_page' => -1,
			'include'  => $movie_ids,
		)
	)->results;
}

if ( ! empty( $tvshow_ids ) && function_exists( 'streamit_get_tvshows' ) ) {
	$tvshows = streamit_get_tvshows(
		array(
			'per_page' => -1,
			'include'  => $tvshow_ids,
		)
	)->results;
}

$movies  = is_array( $movies ) ? $movies : array();
$tvshows = is_array( $tvshows ) ? $tvshows : array();
?>

<div class="actor-history">
	<?php if ( ! empty( $st_data->get_meta( 'cast_awards' ) ) ) : ?>
		<h5 class="mt-4"><?php esc_html_e( 'Awards :', 'streamit' ); ?></h5>
		<p class="text-uppercase mt-3"><?php echo esc_html( $st_data->get_meta( 'cast_awards' ) ); ?></p>
	<?php endif; ?>

	<div class="title">
		<h4 class="title-tag"><?php esc_html_e( 'Person History', 'streamit' ); ?></h4>
	</div>

	<?php if ( empty( $movies ) && empty( $tvshows ) ) : ?>
		<div class="no-content">
			<p><?php esc_html_e( 'No TV shows or Movies found.', 'streamit' ); ?></p>
		</div>
	</div>
		<?php
		return;
		?>
	<?php endif; ?>

	<ul class="nav nav-underline my-5 list-inline" id="pills-tab" role="tablist">
		<li class="nav-item" role="presentation">
			<button class="nav-link active" id="pills-personall-tab" data-bs-toggle="pill" data-bs-target="#pills-person-all" type="button" role="tab" aria-controls="pills-person-all" aria-selected="true">
				<?php esc_html_e( 'All', 'streamit' ); ?>
			</button>
		</li>
		<?php if ( ! empty( $movies ) ) : ?>
			<li class="nav-item" role="presentation">
				<button class="nav-link" id="pills-person-movie-tab" data-bs-toggle="pill" data-bs-target="#pills-person-movie" type="button" role="tab" aria-controls="pills-person-movie" aria-selected="false">
					<?php esc_html_e( 'Movies', 'streamit' ); ?>
				</button>
			</li>
		<?php endif; ?>
		<?php if ( ! empty( $tvshows ) ) : ?>
			<li class="nav-item" role="presentation">
				<button class="nav-link" id="pills-person-tvshow-tab" data-bs-toggle="pill" data-bs-target="#pills-person-tvshow" type="button" role="tab" aria-controls="pills-person-tvshow" aria-selected="false">
					<?php esc_html_e( 'TV Shows', 'streamit' ); ?>
				</button>
			</li>
		<?php endif; ?>
	</ul>

	<div class="tab-content" id="pills-tabContent">
		<div class="tab-pane fade show active" id="pills-person-all" role="tabpanel">
			<div class="div">
				<div class="row gy-5">
					<?php
					$all_shows = array_merge( $movies, $tvshows );
					foreach ( $all_shows as $show ) :
						$release_year = '';
						if ( 'tvshow' === $show->get_post_type() ) {
							$season_data = $show->get_meta( '_seasons' );
							if ( ! empty( $season_data ) && is_array( $season_data ) ) {
								$season_data  = $season_data[0];
								$release_year = $season_data['season_year'] ?? '';
							}
						} else {
							$release_date = $show->get_meta( '_movie_release_date' );
							if ( ! empty( $release_date ) ) {
								$release_year = wp_date( 'Y', strtotime( $release_date ) );
							}
						}

						$thumbnail_image_id = $show->get_meta( 'thumbnail_id' );
						$portrait_image_id  = $show->get_meta( '_portrait_thumbmail' );
						$image              = ! empty( $thumbnail_image_id ) ? wp_get_attachment_image_url( $thumbnail_image_id, 'full' ) : '';
						$portrait_image     = ! empty( $portrait_image_id ) ? wp_get_attachment_image_url( $portrait_image_id, 'full' ) : '';
						$thumbnail_url      = ! empty( $portrait_image ) ? $portrait_image : ( ! empty( $image ) ? $image : streamit_placeholder_image() );
						?>
						<div class="col-xl-4 col-sm-6">
							<div class="d-flex align-items-center gap-3 bg-gray-900 rounded-3 overflow-hidden">
								<div class="image flex-shrink-0">
									<a href="<?php echo esc_url( streamit_get_permalink( $show->get_post_type(), $show->get_post_name() ) ); ?>">
										<img src="<?php echo esc_url( $thumbnail_url ); ?>"
											alt="<?php echo esc_attr( $show->get_post_title() ); ?>"
											class="img-fluid object-fit-cover person-history-thumbnail">
									</a>
								</div>
								<div class="content">
									<h5 class="mb-1 line-count-2">
										<a href="<?php echo esc_url( streamit_get_permalink( $show->get_post_type(), $show->get_post_name() ) ); ?>">
											<span class="m-0 h6 d-block"><?php echo esc_html( $show->get_post_title() ); ?></span>
										</a>
									</h5>
									<span><?php echo esc_html( $release_year ); ?></span>
								</div>
							</div>
						</div>
					<?php endforeach; ?>
				</div>
			</div>
		</div>

		<?php if ( ! empty( $movies ) ) : ?>
			<div class="tab-pane fade" id="pills-person-movie" role="tabpanel">
				<div class="div">
					<div class="row gy-5">
						<?php
						foreach ( $movies as $movie ) :
							$release_year = '';
							$release_date = $movie->get_meta( '_movie_release_date' );
							if ( ! empty( $release_date ) ) {
								$release_year = wp_date( 'Y', strtotime( $release_date ) );
							}

							$thumbnail_image_id = $movie->get_meta( 'thumbnail_id' );
							$portrait_image_id  = $movie->get_meta( '_portrait_thumbmail' );
							$image              = ! empty( $thumbnail_image_id ) ? wp_get_attachment_image_url( $thumbnail_image_id, 'full' ) : '';
							$portrait_image     = ! empty( $portrait_image_id ) ? wp_get_attachment_image_url( $portrait_image_id, 'full' ) : '';
							$thumbnail_url      = ! empty( $portrait_image ) ? $portrait_image : ( ! empty( $image ) ? $image : streamit_placeholder_image() );
							?>
							<div class="col-xl-4 col-sm-6">
								<div class="d-flex align-items-center gap-3 bg-gray-900 rounded-3 overflow-hidden">
									<div class="image flex-shrink-0">
										<a href="<?php echo esc_url( streamit_get_permalink( $movie->get_post_type(), $movie->get_post_name() ) ); ?>">
											<img src="<?php echo esc_url( $thumbnail_url ); ?>"
												alt="<?php echo esc_attr( $movie->get_post_title() ); ?>"
												class="img-fluid object-fit-cover person-history-thumbnail">
										</a>
									</div>
									<div class="content">
										<h5 class="mb-1 line-count-2">
											<a href="<?php echo esc_url( streamit_get_permalink( $movie->get_post_type(), $movie->get_post_name() ) ); ?>" class="color-inherit">
												<?php echo esc_html( $movie->get_post_title() ); ?>
											</a>
										</h5>
										<span><?php echo esc_html( $release_year ); ?></span>
									</div>
								</div>
							</div>
						<?php endforeach; ?>
					</div>
				</div>
			</div>
		<?php endif; ?>

		<?php if ( ! empty( $tvshows ) ) : ?>
			<div class="tab-pane fade" id="pills-person-tvshow" role="tabpanel">
				<div class="div">
					<div class="row gy-5">
						<?php
						foreach ( $tvshows as $tvshow ) :
							$release_year = '';
							$season_data  = $tvshow->get_meta( '_seasons' );
							if ( ! empty( $season_data ) && is_array( $season_data ) ) {
								$season_data  = $season_data[0];
								$release_year = $season_data['season_year'] ?? '';
							}

							$thumbnail_image_id = $tvshow->get_meta( 'thumbnail_id' );
							$portrait_image_id  = $tvshow->get_meta( '_portrait_thumbmail' );
							$image              = ! empty( $thumbnail_image_id ) ? wp_get_attachment_image_url( $thumbnail_image_id, 'full' ) : '';
							$portrait_image     = ! empty( $portrait_image_id ) ? wp_get_attachment_image_url( $portrait_image_id, 'full' ) : '';
							$thumbnail_url      = ! empty( $portrait_image ) ? $portrait_image : ( ! empty( $image ) ? $image : streamit_placeholder_image() );
							?>
							<div class="col-xl-4 col-sm-6">
								<div class="d-flex align-items-center gap-3 bg-gray-900 rounded-3 overflow-hidden">
									<div class="image flex-shrink-0">
										<a href="<?php echo esc_url( streamit_get_permalink( $tvshow->get_post_type(), $tvshow->get_post_name() ) ); ?>">
											<img src="<?php echo esc_url( $thumbnail_url ); ?>"
												alt="<?php echo esc_attr( $tvshow->get_post_title() ); ?>"
												class="img-fluid object-fit-cover person-history-thumbnail">
										</a>
									</div>
									<div class="content">
										<h5 class="mb-1 line-count-2">
											<a href="<?php echo esc_url( streamit_get_permalink( $tvshow->get_post_type(), $tvshow->get_post_name() ) ); ?>">
												<span class="m-0 h6 d-block"><?php echo esc_html( $tvshow->get_post_title() ); ?></span>
											</a>
										</h5>
										<span><?php echo esc_html( $release_year ); ?></span>
									</div>
								</div>
							</div>
						<?php endforeach; ?>
					</div>
				</div>
			</div>
		<?php endif; ?>
	</div>
</div>
