<?php

/**
 * The template for displaying imdb rating
 *
 * @link https://codex.wordpress.org/Template_Hierarchy
 *
 * @package streamit
 */
if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

if(!empty($st_data->get_meta('name_custom_imdb_rating'))) :
?>
<li>
    <span class="d-flex align-items-center gap-1">
        <span class="fw-medium">
            <span>
                <?php echo esc_html($st_data->get_meta('name_custom_imdb_rating'));  ?>
            </span>
            <?php
            $imdb_logo = streamit_get_imdb_logo();
            if (is_array($imdb_logo)) : ?>
                <span class="imdb-logo ms-1">
                    <?php if ( is_array( $imdb_logo ) ) : ?>
                        <img src="<?php echo esc_url( $imdb_logo['url'] ) ?>" loading="lazy" decoding="async" alt="<?php esc_attr_e( 'imdb logo', 'streamit' ) ?>" >
                    <?php endif ?>
                </span>
            <?php endif; ?>
        </span>
    </span>
</li>
<?php endif; ?>