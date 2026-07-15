<?php
/**
 * Episode download button (child override — visible when video sources or subtitles exist).
 *
 * @package streamit-child
 */

defined( 'ABSPATH' ) || exit;

if ( ! streamit_child_has_download_modal_content( $st_data, '_sources' ) ) {
	return;
}
?>
<li>
	<button type="button" class="action-btn btn btn-secondary border" data-bs-toggle="modal" data-bs-target="#downloadModal">
		<span class="h-100 w-100 d-block" data-bs-toggle="tooltip" data-bs-placement="top" data-bs-title="<?php esc_attr_e( 'Download', 'streamit' ); ?>">
			<?php echo st_get_icon( 'download-2' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
		</span>
	</button>
</li>
