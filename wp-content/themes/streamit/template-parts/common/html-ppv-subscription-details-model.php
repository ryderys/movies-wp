<?php

/**
 * The template for displaying the Ppv-Subscription modal.
 *
 * @link https://codex.wordpress.org/Template_Hierarchy
 * @package streamit
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}
?>

<div class="modal view-more-data-modal fade" id="PpvSubscriptionDataModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header pb-0">
                <!-- Include movie title -->
                <?php
                streamit_get_template('movie/content/movie_single_title.php', [
                    'st_data' => $st_data,
                ]);
                ?>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <!-- Include movie metadata list -->
                <?php
                streamit_get_template('movie/single/movie_single_metalist.php', [
                    'st_data' => $st_data,
                ]);
                ?>

                <?php
                $genres_ids = streamit_get_term_relationships($st_data->get_id(), 'movie_genre');
                if (!empty($genres_ids)) : ?>
                    <div class="d-flex align-items-baseline row-gap-1 column-gap-2 mt-3">
                        <div class="viewmore-data-title">
                            <h6 class="m-0"><?php echo esc_html__('Genres:', 'streamit'); ?></h6>
                        </div>
                        <!-- Include movie genres -->
                        <?php
                        streamit_get_template('movie/content/movie_single_genre.php', [
                            'st_data' => $st_data,
                        ]);
                        ?>
                    </div>
                <?php endif; ?>

                <?php
                $tag_ids = streamit_get_term_relationships($st_data->get_id(), 'movie_tag');
                if (!empty($tag_ids)) : ?>
                    <div class="d-flex align-items-baseline row-gap-1 column-gap-2 mt-md-1 mt-2 mb-3">
                        <div class="viewmore-data-title">
                            <h6 class="m-0"><?php echo esc_html__('Tags:', 'streamit'); ?></h6>
                        </div>
                        <!-- Include movie tags -->
                        <?php
                        streamit_get_template('movie/content/movie_tag.php', [
                            'st_data' => $st_data,
                        ]);
                        ?>
                    </div>
                <?php endif; ?>

                <?php
                $post_type = method_exists($st_data, 'get_post_type') ? $st_data->get_post_type() : '';
                $access_type = $st_data->get_meta('_access_type') ?? '';
                $price = floatval($st_data->get_meta('_ppv_price') ?? 0);
                $discount = floatval($st_data->get_meta('_ppv_discount') ?? 0);
                $validity = intval($st_data->get_meta('_ppv_duration_days') ?? 0);

                $currency_code = get_option('pmpro_currency', 'USD');

                global $pmpro_currencies;
                $currency_symbol = isset($pmpro_currencies[$currency_code]['symbol']) ? $pmpro_currencies[$currency_code]['symbol'] : '$';

                $original_price = $price;
                $final_price = function_exists('streamit_calculate_final_ppv_price') ? streamit_calculate_final_ppv_price($st_data->get_id(), $post_type) : $price;

                $rent_url = streamit_get_ppv_checkout_url($st_data->get_id(), $post_type);

                // Get the membership level ID
                $level_id = function_exists('streamit_get_or_create_ppv_level') ? streamit_get_or_create_ppv_level($st_data->get_id(), $post_type) : 0;

                // Get and process the membership level object
                $level = null;
                if ($level_id && function_exists('pmpro_getLevel')) {
                    $level = pmpro_getLevel($level_id);
                }
                ?>

                <div class="ppv-details-modal mt-3">
                    <div class="ppv-details-row">
                        <span><?php esc_html_e('Validity:', 'streamit'); ?></span>
                        <span class="ppv-details-value text-success"><strong><?php echo $validity === 0 ? esc_html__('Lifetime', 'streamit') : esc_html($validity . ' ' . __('Days', 'streamit')); ?></strong></span>
                    </div>
                    <div class="ppv-details-desc">
                        <p class="mb-0">
                            <?php
                            if ($level && !empty($level->description) && function_exists('streamit_replace_placeholders')) {
                                echo wp_kses_post(streamit_replace_placeholders($level->description, $st_data->get_id(), $post_type));
                            } else {
                                esc_html_e('No description available for this content.', 'streamit');
                            }
                            ?>
                        </p>
                    </div>
                    <div class="ppv-details-actions d-flex align-items-center gap-lg-3 gap-2 flex-wrap mt-5">
                        <?php if ($access_type === 'ppv' || $access_type === 'anyone'): ?>
                            <!-- ppv btn  -->
                            <a class="btn btn-warning-subtle" href="<?php echo esc_url($rent_url); ?>">
                                <span class="d-flex align-items-center justify-content-center gap-2">
                                    <span><?php echo st_get_icon('rent'); ?></span>
                                    <span>
                                        <?php
                                        echo __('Rent For', 'streamit') . ' ';
                                        if ($final_price < $original_price) {
                                            echo '<del class="rent-price">' . $currency_symbol . number_format($original_price, 2) . '</del> ';
                                        }
                                        echo $currency_symbol . number_format($final_price, 2);
                                        ?>
                                    </span>
                                </span>
                            </a>
                        <?php elseif ($access_type === 'free'): ?>
                            <a class="btn btn-primary" href="<?php echo esc_url(streamit_get_permalink($post_type, $st_data->get_post_name())); ?>">
                                <span class="d-flex align-items-center justify-content-center gap-2">
                                    <span><?php echo st_get_icon('play'); ?></span>
                                    <span><?php esc_html_e('Watch Now', 'streamit'); ?></span>
                                </span>
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>