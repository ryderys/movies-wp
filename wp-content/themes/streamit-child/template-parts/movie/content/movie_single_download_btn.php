<?php
/**
 * Download button — shown when at least one valid source row exists.
 *
 * @package streamit-child
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( empty( $st_data ) || ! streamit_child_has_downloadable_sources( $st_data, '_source' ) ) {
	return;
}
?>
<li>
	<button type="button" class="action-btn btn btn-secondary border" data-bs-toggle="modal" data-bs-target="#downloadModal">
		<span class="h-100 w-100 d-block" data-bs-toggle="tooltip" data-bs-placement="top" data-bs-title="دانلود">
			<?php echo st_get_icon( 'download-2' ); ?>
		</span>
	</button>
</li>
