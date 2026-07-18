<?php
/**
 * Person Card grid — with numbered pagination for /casts/.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$maxnumpages  = isset( $maxnumpages ) ? (int) $maxnumpages : 0;
$current_page = isset( $current_page ) ? max( 1, (int) $current_page ) : 1;
?>
<div class="streamit-person-title">
	<div class="title d-flex align-items-center justify-content-between">
		<?php if ( ! empty( $slider_title ) ) : ?>
			<<?php echo esc_attr( $title_tag ); ?> class="title-tag">
				<?php echo esc_html( $slider_title ); ?>
			</<?php echo esc_attr( $title_tag ); ?>>
		<?php endif; ?>

		<?php if ( ! empty( $settings['use_custom_link_text'] ) ) : ?>
			<div class="view-all-btn">
				<a href="<?php echo esc_url( streamit_get_permalink( 'person' ) ); ?>">
					<?php echo esc_html( $settings['use_custom_link_text'] ); ?>
				</a>
			</div>
		<?php endif; ?>
	</div>
</div>

<?php if ( ! empty( $results ) && is_array( $results ) ) : ?>
	<?php
	/*
	 * Do NOT use class "data-listing" here. Theme ArchiveAppend auto-inits on
	 * .data-listing and treats ?paged / query args as filters, which empties
	 * the grid on page 2+.
	 */
	?>
	<div class="streamit-casts-listing row gy-5 row-cols-2 row-cols-sm-3 row-cols-md-4 row-cols-lg-<?php echo esc_attr( $settings['iq_columns'] ); ?> row-cols-xl-<?php echo esc_attr( $settings['iq_columns'] ); ?>">
		<?php
		foreach ( $results as $details ) :
			$person = $details['data'] ?? null;
			if ( ! $person ) {
				continue;
			}

			$person_id    = $person->get_id();
			$person_type  = $person->get_post_type();
			$person_slug  = $person->get_post_name();
			$person_title = $person->get_post_title();
			$image_id     = $person->get_meta( 'thumbnail_id' );

			$terms_data = array();
			$terms_ids  = streamit_get_term_relationships( $person_id, 'person_category' );
			if ( ! empty( $terms_ids ) ) {
				$terms_data = streamit_get_terms(
					array(
						'per_page' => 2,
						'include'  => $terms_ids,
					)
				)->results;
			}
			?>
			<div class="col">
				<div class="person-card position-relative">
					<div class="cast-images position-relative">
						<a href="<?php echo esc_url( streamit_get_permalink( $person_type, $person_slug ) ); ?>" class="color-inherit">
							<?php
							echo streamit_render_image( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
								array(
									'attachment_id' => $image_id,
									'class'         => 'img-fluid',
									'alt'           => esc_attr( $person_title ),
									'decoding'      => 'async',
								)
							);
							?>
						</a>
					</div>
					<div class="person-detail">
						<h6 class="person-title">
							<a href="<?php echo esc_url( streamit_get_permalink( $person_type, $person_slug ) ); ?>" class="color-inherit">
								<?php echo esc_html( $person_title ); ?>
							</a>
						</h6>

						<?php if ( ! empty( $details['character'] ) ) : ?>
							<ul class="d-flex align-items-center justify-content-center gap-2 list-inline p-0 m-0">
								<li><?php echo esc_html( $details['character'] ); ?></li>
							</ul>
						<?php endif; ?>

						<?php if ( ! empty( $terms_data ) && is_array( $terms_data ) ) : ?>
							<ul class="d-flex align-items-center justify-content-center gap-2 list-inline p-0 m-0">
								<?php foreach ( $terms_data as $term ) : ?>
									<li>
										<a href="<?php echo esc_url( streamit_get_permalink( 'person_category', $term->get_term_slug() ) ); ?>" class="person-cats d-block">
											<?php echo esc_html( wp_unslash( $term->get_term_name() ) ); ?>
										</a>
									</li>
								<?php endforeach; ?>
							</ul>
						<?php endif; ?>
					</div>
				</div>
			</div>
		<?php endforeach; ?>
	</div>

	<?php if ( $maxnumpages > 1 && function_exists( 'streamit_child_casts_pagination' ) ) : ?>
		<div class="row mt-4 streamit-person-card-pagination">
			<?php streamit_child_casts_pagination( $maxnumpages, $current_page ); ?>
		</div>
	<?php endif; ?>
<?php endif; ?>
