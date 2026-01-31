<?php

/**
 * Template for displaying the download modal
 *
 * @link https://codex.wordpress.org/Template_Hierarchy
 * @package streamit
 */

if (!defined('ABSPATH')) {
    exit;
}

// Fetch TV show and check access
$sources          = $st_data->get_meta('_sources');
if (empty($sources)) {
    return;
}

?>

<div class="modal downloadModal fade st-download-modal" id="downloadModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered playlist-modal">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title m-0" id="downloadModalLabel">
                    <?php esc_html_e('Download Quality', 'streamit'); ?>
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?php esc_attr_e('Close', 'streamit'); ?>"></button>
            </div>

            <div class="modal-body pt-0">
                <ul class="list-inline m-0 p-0 downloadModal-list">
                    <?php
                    $has_valid_source = false;

                    foreach ($sources as $source) :
                        $quality   = $source['quality'] ?? '';
                        $language  = $source['language'] ?? '';
                        $download  = $source['download_content'] ?? '';

                        if (!$quality || !$language || !$download) {
                            continue;
                        }

                        $has_valid_source = true;
                    ?>
                        <li>
                            <div class="d-flex align-items-center justify-content-between">
                                <div class="flex-grow-1">
                                    <h6 class="mt-0 mb-1"><?php echo esc_html($quality); ?></h6>
                                    <p class="m-0 small"><?php echo esc_html($language); ?></p>
                                </div>
                                <div class="flex-shrink-0">
                                    <a href="<?php echo esc_url($download); ?>" class="link-primary" download>
                                        <?php echo st_get_icon('download-2'); ?>
                                    </a>
                                </div>
                            </div>
                        </li>
                    <?php endforeach; ?>

                    <?php if (!$has_valid_source) : ?>
                        <li>
                            <p class="text-muted text-center m-0">
                                <?php esc_html_e('No downloadable content available.', 'streamit'); ?>
                            </p>
                        </li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
    </div>
</div>