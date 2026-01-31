<?php
defined('ABSPATH') || exit;

if (!wp_is_mobile()) :
?>
    <!-- Single Modal Container -->
    <div class="modal trailerModal fade" id="trailerModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content">
                <div class="modal-body">
                    <div class="streamit_trailer_player">
                        <!-- Iframe Player -->
                        <iframe id="trailerIframe"
                            src=""
                            title="<?php esc_attr_e('Video player', 'streamit'); ?>"
                            allowfullscreen
                            allow="autoplay"
                            loading="lazy"
                            style="display:none;">
                        </iframe>

                        <!-- HTML5 Video Player -->
                        <video id="trailerVideo"
                            controls
                            playsinline
                            preload="metadata"
                            style="display:none; width:100%; height:auto;">
                            <source src="" type="video/mp4">
                            <?php esc_html_e('Your browser does not support HTML5 video.', 'streamit'); ?>
                        </video>
                    </div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?php esc_attr_e('Close', 'streamit'); ?>"></button>
            </div>
        </div>
    </div>
<?php
endif;
