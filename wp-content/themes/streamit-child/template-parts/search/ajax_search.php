<?php
/**
 * AJAX search results for the fullscreen modal (Persian labels).
 *
 * @package streamit-child
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$search_text = isset( $args['s'] ) ? sanitize_text_field( $args['s'] ) : '';

if ( empty( $search_text ) ) :
	?>
	<div class="col-md-12">
		<div class="d-flex align-items-center justify-content-center gap-3 my-5">
			<div class="image">
				<img src="<?php echo esc_url( streamit_search_not_found_image() ); ?>" class="img-fluid" alt="<?php esc_attr_e( 'نتیجه‌ای یافت نشد', 'streamit' ); ?>">
			</div>
			<div class="content">
				<h5 class="mt-0 mb-2"><?php esc_html_e( 'متأسفیم، نتیجه‌ای یافت نشد!', 'streamit' ); ?></h5>
				<span><?php esc_html_e( 'عبارت دیگری امتحان کنید', 'streamit' ); ?></span>
			</div>
		</div>
	</div>
	<?php
	return;
endif;

$query_args = array(
	's'              => $search_text,
	'posts_per_page' => -1,
);

$movie_data   = function_exists( 'streamit_get_movies' ) ? streamit_get_movies( $query_args )->results : '';
$video_data   = function_exists( 'streamit_get_videos' ) ? streamit_get_videos( $query_args )->results : '';
$person_data  = function_exists( 'streamit_get_persons' ) ? streamit_get_persons( $query_args )->results : '';
$tvshow_data  = function_exists( 'streamit_get_tvshows' ) ? streamit_get_tvshows( $query_args )->results : '';
$episode_data = function_exists( 'streamit_get_episodes' ) ? streamit_get_episodes( $query_args )->results : '';

$tabs = array(
	'all'     => array_merge( $movie_data, $video_data, $person_data, $tvshow_data, $episode_data ),
	'movie'   => $movie_data,
	'tvshow'  => $tvshow_data,
	'video'   => $video_data,
	'person'  => $person_data,
	'episode' => $episode_data,
);

$tab_labels = array(
	'all'     => __( 'همه', 'streamit' ),
	'movie'   => __( 'فیلم', 'streamit' ),
	'tvshow'  => __( 'سریال', 'streamit' ),
	'video'   => __( 'ویدیو', 'streamit' ),
	'person'  => __( 'بازیگر', 'streamit' ),
	'episode' => __( 'قسمت', 'streamit' ),
);

if ( empty( $tabs['all'] ) ) :
	?>
	<div class="col-md-12">
		<div class="d-flex align-items-center justify-content-center gap-3 my-5">
			<div class="image">
				<img src="<?php echo esc_url( streamit_search_not_found_image() ); ?>" class="img-fluid" alt="<?php esc_attr_e( 'نتیجه‌ای یافت نشد', 'streamit' ); ?>">
			</div>
			<div class="content">
				<h5 class="mt-0 mb-2"><?php esc_html_e( 'متأسفیم، نتیجه‌ای یافت نشد!', 'streamit' ); ?></h5>
				<span><?php esc_html_e( 'عبارت دیگری امتحان کنید', 'streamit' ); ?></span>
			</div>
		</div>
	</div>
	<?php
	return;
endif;
?>

<div class="col-md-12">
	<div id="item-nav">
		<div class="item-list-tabs no-ajax css_prefix-tab-lists" id="object-nav">
			<div class="left" onclick="slide('left', event)" style="display: none;">
				<?php echo st_get_icon( 'arrow-left' ); ?>
			</div>
			<ul class="custom-tab-slider nav nav-underline data-search-tab my-5" id="pills-tab" role="tablist">
				<?php $first_tab = true; ?>
				<?php foreach ( $tabs as $key => $data ) : ?>
					<?php if ( ! empty( $data ) ) : ?>
						<li class="nav-item" role="presentation">
							<button class="nav-link <?php echo $first_tab ? 'active' : ''; ?>"
								id="pills-<?php echo esc_attr( $key ); ?>-tab"
								data-bs-toggle="pill"
								data-bs-target="#pills-<?php echo esc_attr( $key ); ?>"
								type="button"
								role="tab"
								aria-controls="pills-<?php echo esc_attr( $key ); ?>"
								aria-selected="<?php echo $first_tab ? 'true' : 'false'; ?>">
								<?php echo esc_html( $tab_labels[ $key ] ?? $key ); ?>
							</button>
						</li>
						<?php $first_tab = false; ?>
					<?php endif; ?>
				<?php endforeach; ?>
			</ul>
			<div class="right" onclick="slide('right', event)" style="display: none;">
				<?php echo st_get_icon( 'arrow-right' ); ?>
			</div>
		</div>
	</div>
	<div class="tab-content" id="pills-tabContent">
		<?php $first_tab = true; ?>
		<?php foreach ( $tabs as $key => $data ) : ?>
			<?php if ( ! empty( $data ) ) : ?>
				<div class="tab-pane fade <?php echo $first_tab ? 'show active' : ''; ?>"
					id="pills-<?php echo esc_attr( $key ); ?>"
					role="tabpanel"
					aria-labelledby="pills-<?php echo esc_attr( $key ); ?>-tab"
					tabindex="0">
					<div class="row gy-4 row-cols-2 row-cols-sm-2 row-cols-md-3 row-cols-lg-4 row-cols-xl-6">
						<?php foreach ( $data as $st_data ) : ?>
							<?php echo streamit_get_template( 'common/html-common-card.php', array( 'st_data' => $st_data ) ); ?>
						<?php endforeach; ?>
					</div>
				</div>
				<?php $first_tab = false; ?>
			<?php endif; ?>
		<?php endforeach; ?>
	</div>
</div>
