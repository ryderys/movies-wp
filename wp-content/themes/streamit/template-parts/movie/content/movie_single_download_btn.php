<?php

/**
 * The template for displaying movie genre pages
 *
 * @link https://codex.wordpress.org/Template_Hierarchy
 *
 * @package streamit
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

// Check if the download button should be displayed
$has_movies = $st_data->get_meta('_source');

if (!empty($has_movies)) :
?>
    <li>
        <button type="button" class="action-btn btn btn-secondary border" data-bs-toggle="modal" data-bs-target="#downloadModal">
            <span class="h-100 w-100 d-block" data-bs-toggle="tooltip" data-bs-placement="top" data-bs-title="<?php esc_attr_e('Download', 'streamit'); ?>">
                <?php echo st_get_icon('download-2'); ?>
            </span>
        </button>
    </li>
<?php endif; ?>