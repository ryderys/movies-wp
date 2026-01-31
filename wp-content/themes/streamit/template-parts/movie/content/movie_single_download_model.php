<?php

/**
 * Template for displaying download modal.
 *
 * @package streamit
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

// --- Check download permission ---
$sources = (array) $st_data->get_meta('_source');
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

                <?php
                // Collect valid sources first
                $valid_sources = array_filter($sources, function ($src) {
                    return !empty($src['quality']) && !empty($src['language']) && !empty($src['download_content']);
                });
                ?>

                <?php if (!empty($valid_sources)) : ?>
                    <ul class="list-inline m-0 p-0 downloadModal-list">
                        <?php foreach ($valid_sources as $source) : ?>
                            <li>
                                <div class="d-flex align-items-center justify-content-between">
                                    <div class="flex-grow-1">
                                        <h6 class="mt-0 mb-1"><?php echo esc_html($source['quality']); ?></h6>
                                        <p class="m-0 small"><?php echo esc_html($source['language']); ?></p>
                                    </div>
                                    <div class="flex-shrink-0">
                                        <a href="<?php echo esc_url($source['download_content']); ?>" class="link-primary" download>
                                            <?php echo st_get_icon('download-2'); ?>
                                        </a>
                                    </div>
                                </div>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php else : ?>
                    <p class="text-download text-center">
                        <?php esc_html_e('No downloadable sources are available.', 'streamit'); ?>
                    </p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>