<?php
/**
 * Movie download button links to the Movie download section.
 *
 * @package streamit-child
 */

defined( 'ABSPATH' ) || exit;

if ( ! streamit_child_has_download_modal_content( $st_data, '_source' ) ) {
	return;
}

?>
<li>
	<a class="action-btn btn btn-secondary border" href="#movie-download" aria-label="<?php esc_attr_e( 'Download', 'streamit' ); ?>">
		<span class="h-100 w-100 d-block" data-bs-toggle="tooltip" data-bs-placement="top" data-bs-title="<?php esc_attr_e( 'Download', 'streamit' ); ?>">
			<?php echo st_get_icon( 'download-2' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
		</span>
	</a>
</li>
