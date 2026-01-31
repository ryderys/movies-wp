<?php

/**
 * The template for displaying trailer model
 *
 * @link https://codex.wordpress.org/Template_Hierarchy
 *
 * @package streamit
 */
if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

$media_url = $st_data->get_meta('_name_trailer_link');
if (empty($media_url)) return;

$extension = strtolower(pathinfo($media_url, PATHINFO_EXTENSION));
$content = '';

if (!empty($extension) && in_array($extension, ['mp4', 'mkv', 'webm'], true)) {
    ob_start();
?>
    <video id="streamit_player"
        controls
        crossorigin
        playsinline>
        <source src="<?php echo esc_url($media_url); ?>" type="video/<?php echo esc_attr($extension); ?>" />
    </video>
    <?php
    $content = ob_get_clean();
} else {
    // Handle YouTube URLs
    $parsed_url = parse_url($media_url);
    if (isset($parsed_url['host'])) {
        $host = strtolower($parsed_url['host']);
        if (in_array($host, ['youtube.com', 'www.youtube.com', 'youtu.be'], true)) {
            parse_str($parsed_url['query'], $query_params);
            $youtube_id = isset($query_params['v']) ? $query_params['v'] : null;
            if ($youtube_id) {
                $media_url = 'https://www.youtube.com/embed/' . esc_attr($youtube_id);
                ob_start();
    ?>
                <div id="streamit_player">
                    <iframe width="560" height="315" src="<?php echo esc_url($media_url); ?>" title="YouTube video player"
                        allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share"
                        allowfullscreen></iframe>
                </div>
    <?php
                $content = ob_get_clean();
            }
        }
    }
}
if (!empty($content)) :
    ?>
    <div class="modal fade" id="watchTrailerModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-centered watch-Trailer-modal">
            <div class="modal-content">
                <div class="modal-header">
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <?php echo $content;  // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped  
                    ?>
                </div>
            </div>
        </div>
    </div>
<?php
endif;
