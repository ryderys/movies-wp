<?php
if (!defined('ABSPATH')) exit;


$custom_query = new WP_Query($post_args);

if ($custom_query->have_posts()) : ?>
    <div class="data-listing row gy-5 row-cols-1 <?php echo esc_attr($parent_class); ?>">
        <?php while ($custom_query->have_posts()) : $custom_query->the_post(); ?>
            <?php streamit_get_template('elementor-widget/Blog/widget_post.php', ['settings' => $settings]); ?>
        <?php endwhile; ?>
        <?php wp_reset_postdata(); ?>
    </div>

    <!-- Pagination -->
    <?php if ($settings['iq_pagination'] === 'yes') : ?>
        <div class="pagination justify-content-center w-100">
            <?php
            echo paginate_links(array(
                'total'        => $custom_query->max_num_pages,
                'current'      => $paged,
                'prev_text'    => st_get_icon('arrow-left' ,  ['class' => 'ms-1' , 'aria-hidden' => 'true']),
                'next_text'    => st_get_icon('arrow-right' , ['class' => 'ms-1' , 'aria-hidden' => 'true']),
                'type'         => 'list',
            ));
            ?>
        </div>
    <?php endif; ?>

    <?php
    // Prepare extra settings
    $extra_settings = [
        'hide_image'            => $settings['hide_image'],
        'hide_date'             => $settings['hide_date'],
        'hide_content'          => $settings['hide_content'],
        'hide_read_more_button' => $settings['hide_read_more_button'],
        'title_tag'             => $settings['title_tag'],
        'iq_content_line'       => $settings['iq_content_line'],
        'read_more_text'        => $settings['read_more_text']
    ];

    // Pagination types
    $numpages = $custom_query->max_num_pages ?: 1;
    if( $numpages > 1) :
        if( $settings['iq_pagination'] === 'loadmore') : ?>
            <div class="blog-widgetloadmore d-flex justify-content-center w-100 mt-5">
                <?php echo st_get_load_more_button( $numpages, 'widget_post', 1, esc_html__('Load More', 'streamit'), esc_html__('Loading...', 'streamit'), $post_per_page, '', $extra_settings ) ?>
            </div>
        <?php elseif ($settings['iq_pagination'] === 'infinite') : ?>
            <?php echo st_get_loader_wheel_container( $numpages, 'widget_post', 1, $post_per_page, '', $extra_settings ) ?>
        <?php endif; ?>
    <?php endif; ?>

<?php else : ?>
    <p><?php esc_html_e('No posts found.', 'streamit'); ?></p>
<?php endif; ?>