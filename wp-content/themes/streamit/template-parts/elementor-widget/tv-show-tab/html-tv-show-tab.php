<?php
if (!defined('ABSPATH')) exit;
?>

<div class="css_prefix-tvshow-tab css_prefix-rtl-direction">
	<?php if (!empty($settings['title_text']) || true) : ?>
		<div class="title d-flex align-items-center justify-content-between">
			<h4 class="title-tag">
				<?php echo !empty($settings['title_text']) ? esc_html($settings['title_text']) : esc_html__('Trending', 'streamit'); ?>
			</h4>
		</div>
	<?php endif; ?>

	<div class="trending-tvshow-contens">
		<ul id="<?php echo esc_attr("trending-slider-nav-$id_int"); ?>"
			data-rand="<?php echo esc_attr("trending-slider-nav-$id_int"); ?>"
			class="trending-slider-tab-nav list-inline p-0 mb-0 row align-items-center st-skeleton">

			<?php foreach ($post_data as $post) : ?>
				<li class="slick-item">
					<a href="javascript:void(0);">
						<div class="episod-block position-relative">
							<?php
							echo streamit_render_image([
								'attachment_id' => $post->get_meta('thumbnail_id'),
								'class'         => 'img-fluid',
								'alt'           => esc_attr($post->get_post_title()),
								'decoding'      => 'async'
							]);
							?>
						</div>
					</a>
				</li>
			<?php endforeach; ?>
		</ul>

		<ul id="<?php echo esc_attr("trending-slider-$id_int"); ?>"
			data-rand_id="<?php echo esc_attr($id_int); ?>"
			data-rand="<?php echo esc_attr("trending-slider-$id_int"); ?>"
			class="trending-slider-tab home-slider list-inline p-0 m-0 st-skeleton">

			<?php foreach ($args['post_data'] as $i => $post) :
				$i++;
				$post_id     = $post->get_id();
				$year        = date('F Y', strtotime($post->get_post_date()));
				$season_data = $post->get_meta('_seasons');
				$season_count = is_array($season_data) ? count($season_data) : 0;
				$season_display = $season_count > 0
					? sprintf(_n('%s Season', '%s Seasons', $season_count, 'streamit'), $season_count)
					: esc_html__('Arriving Soon', 'streamit');

				$post_image = wp_get_attachment_image_url($post->get_meta('thumbnail_id'), 'full') ?: streamit_placeholder_image();
				$top_trend_alt = !empty($args['settings']['trending_top_img']['alt'])
					? esc_attr($args['settings']['trending_top_img']['alt'])
					: esc_attr__('image', 'streamit');
			?>
				<li class="slick-item">
					<div class="tranding-block position-relative"
						data-current_slide="<?php echo esc_attr($i); ?>"
						style="background-image: url(<?php echo esc_url($post_image); ?>);">

						<div class="trending-custom-tab">
							<div class="tab-title-info position-relative">
								<ul class="trending-pills streamit-trending-pills d-flex nav nav-pills justify-content-center align-items-center text-center"
									data-slide_id="<?php echo esc_attr($i); ?>" role="tablist">

									<li class="nav-item">
										<a class="nav-link active" data-bs-toggle="pill"
											data-bs-target="#overview_<?php echo esc_attr($i . $id_int); ?>" role="tab">
											<?php echo !empty($settings['tab_text_overview']) ? esc_html($settings['tab_text_overview']) : esc_html__('Overview', 'streamit'); ?>
										</a>
									</li>
									<li class="nav-item">
										<a class="nav-link css_prefix-tv_show-episodes" data-bs-toggle="pill"
											data-bs-target="#episodes_<?php echo esc_attr($i . $id_int); ?>" role="tab"
											data-episodes="<?php echo esc_attr(json_encode($season_data)); ?>">
											<?php echo !empty($settings['tab_text_episode']) ? esc_html($settings['tab_text_episode']) : esc_html__('Episodes', 'streamit'); ?>
										</a>
									</li>
								</ul>
							</div>

							<div class="trending-content tab-content">
								<div id="overview_<?php echo esc_attr($i . $id_int); ?>" class="overview-tab tab-pane fade active show">
									<div class="trending-info align-items-center animated fadeIn">

										<h2 class="slider-text texture-text line-count-2"><?php echo esc_html($post->get_post_title()); ?></h2>

										<div class="d-flex align-items-center flex-wrap gap-3 text-detail mb-4">
											<span class="season_date"><?php echo esc_html($season_display); ?></span>
											<span class="trending-year d-flex align-items-center gap-1">
												<?php echo st_get_icon('calendar-2'); ?><?php echo esc_html($year); ?>
											</span>
										</div>

										<div class="d-flex align-items-center gap-3 series mb-4">
											<?php if (!empty($settings['trending_top_img']['url'])) :
												echo wp_get_attachment_image(
													attachment_url_to_postid($settings['trending_top_img']['url']),
													'full',
													false,
													['class' => 'img-fluid', 'alt' => $top_trend_alt, 'decoding' => 'async']
												);
											endif; ?>
											<span class="text-gold">#<?php echo esc_html($i); ?> <?php esc_html_e('in Series Today', 'streamit'); ?></span>
										</div>

										<div class="trending-dec mb-4 line-count-4">
											<?php
											$excerpt = strip_tags($post->get_post_excerpt());
											if (!empty($excerpt)) echo esc_html($excerpt);
											?>
										</div>

										<a href="<?php echo esc_url(streamit_get_permalink($post->get_post_type(), $post->get_post_name())); ?>"
											class="btn btn-primary">
											<?php esc_html_e('تماشا', 'streamit'); ?>
											<?php echo st_get_icon('play', ['class' => 'ms-2', 'aria-hidden' => 'true']); ?>
										</a>
									</div>
								</div>

								<?php if (!empty($season_data)) : ?>
									<div id="episodes_<?php echo esc_attr($i . $id_int); ?>" class="overlay-tab tab-pane fade">
										<div class="trending-info align-items-center w-100 animated fadeIn">
											<h2 class="slider-text texture-text"><?php echo esc_html($post->get_post_title()); ?></h2>

											<div class="d-flex align-items-center gap-3 flex-wrap text-detail mb-4">
												<span class="season_date"><?php echo esc_html($season_display); ?></span>
												<span class="trending-year d-flex align-items-center gap-1">
													<?php echo st_get_icon('calendar-2'); ?><?php echo esc_html($year); ?>
												</span>
											</div>

											<div class="css_prefix-custom-select d-inline-block">
												<select class="form-control season-select">
													<?php foreach ($season_data as $index => $val) : ?>
														<option value="<?php echo esc_attr($index); ?>"><?php echo isset($val['name']) ? esc_html($val['name']) : ''; ?></option>
													<?php endforeach; ?>
												</select>
											</div>

											<div class="episodes-contens css_prefix-rtl-direction">
												<div class="episodes-slider slick-slider-tvshow-tab active show" data-display="0">
													<?php for ($x = 0; $x < 4; $x++) : ?>
														<div class="episode-item animated fadeInUp ajax">
															<div class="episode-card">
																<div class="episode-image">
																	<img src="#" alt="episode" class="img-fluid object-fit-cover" loading="lazy" decoding="async">
																	<ul class="episode-detail d-flex gap-3"></ul>
																</div>
															</div>
														</div>
													<?php endfor; ?>
												</div>
											</div>
										</div>
									</div>
								<?php endif; ?>
							</div>
						</div>
					</div>
				</li>
			<?php endforeach; ?>
		</ul>
	</div>
</div>