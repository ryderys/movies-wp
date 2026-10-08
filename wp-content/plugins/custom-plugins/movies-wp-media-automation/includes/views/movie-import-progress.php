<?php
/**
 * Movie import job progress.
 *
 * @var array<string, mixed> $job
 * @var array{type:string,message:string}|null $notice
 */
 
defined( 'ABSPATH' ) || exit;
 
$job        = is_array( $job ?? null ) ? $job : array();
$notice     = is_array( $notice ?? null ) ? $notice : null;
$token      = (string) ( $job['token'] ?? '' );
$status     = (string) ( $job['status'] ?? '' );
$error      = (string) ( $job['last_error'] ?? '' );
$stalled    = class_exists( 'Movies_WP_Movie_Import_Job_Store' ) ? Movies_WP_Movie_Import_Job_Store::is_possibly_stalled( $job ) : false;
$result     = isset( $job['result'] ) && is_array( $job['result'] ) ? $job['result'] : array();
$result_msg = is_string( $result['message'] ?? null ) ? (string) $result['message'] : '';
?>
<div class="wrap movies-wp-scan-preview">
	<h1><?php esc_html_e( 'Movie Import Progress', 'movies-wp' ); ?></h1>
	<?php if ( is_array( $notice ) ) : ?>
		<div class="notice notice-<?php echo esc_attr( (string) $notice['type'] ); ?>"><p><?php echo esc_html( (string) $notice['message'] ); ?></p></div>
	<?php endif; ?>
 
	<div class="notice notice-info inline">
		<p>
			<strong><?php esc_html_e( 'This import continues in the background.', 'movies-wp' ); ?></strong>
			<?php esc_html_e( 'You can leave this page; the import will not stop. Closing the browser does not cancel it either — Action Scheduler keeps processing on the server.', 'movies-wp' ); ?>
		</p>
	</div>
 
	<?php if ( $stalled ) : ?>
		<div class="notice notice-warning inline" role="status">
			<p>
				<strong><?php esc_html_e( 'Possibly stalled', 'movies-wp' ); ?></strong>
				<?php esc_html_e( 'No recent worker activity was detected. You can Refresh to check again or Cancel and re-run the import.', 'movies-wp' ); ?>
			</p>
		</div>
	<?php endif; ?>
 
	<table class="widefat striped">
		<tbody>
			<tr><th><?php esc_html_e( 'Status', 'movies-wp' ); ?></th><td><?php echo esc_html( Movies_WP_Media_Admin::job_status_label( $status ) ); ?></td></tr>
			<tr><th><?php esc_html_e( 'TMDb ID', 'movies-wp' ); ?></th><td><?php echo esc_html( (string) ( $job['tmdb_id'] ?? '' ) ); ?></td></tr>
			<tr><th><?php esc_html_e( 'Directory', 'movies-wp' ); ?></th><td><?php echo esc_html( (string) ( $job['directory'] ?? '' ) ); ?></td></tr>
			<tr><th><?php esc_html_e( 'Created', 'movies-wp' ); ?></th><td><?php echo esc_html( (string) ( $job['created_at'] ?? '' ) ); ?></td></tr>
			<tr><th><?php esc_html_e( 'Updated', 'movies-wp' ); ?></th><td><?php echo esc_html( (string) ( $job['updated_at'] ?? '' ) ); ?></td></tr>
			<tr><th><?php esc_html_e( 'Elapsed (ms)', 'movies-wp' ); ?></th><td><?php echo esc_html( (string) ( $job['elapsed_ms'] ?? '' ) ); ?></td></tr>
			<?php if ( '' !== $result_msg ) : ?>
				<tr><th><?php esc_html_e( 'Result', 'movies-wp' ); ?></th><td><?php echo esc_html( Movies_WP_Media_Import_Service::safe_text( $result_msg ) ); ?></td></tr>
			<?php endif; ?>
			<?php if ( '' !== $error ) : ?>
				<tr><th><?php esc_html_e( 'Last error', 'movies-wp' ); ?></th><td><?php echo esc_html( $error ); ?></td></tr>
			<?php endif; ?>
		</tbody>
	</table>
 
	<p>
		<a class="button" href="<?php echo esc_url( Movies_WP_Media_Admin::progress_url( $token ) ); ?>"><?php esc_html_e( 'Refresh', 'movies-wp' ); ?></a>
	</p>
 
	<?php if ( in_array( $status, array( 'queued', 'running', 'paused' ), true ) ) : ?>
		<form method="post" style="display:inline;">
			<?php wp_nonce_field( Movies_WP_Media_Admin::PROGRESS_NONCE ); ?>
			<input type="hidden" name="movies_wp_media_action" value="<?php echo esc_attr( Movies_WP_Media_Admin::CANCEL_ACTION ); ?>">
			<input type="hidden" name="job_token" value="<?php echo esc_attr( $token ); ?>">
			<?php submit_button( __( 'Cancel', 'movies-wp' ), 'secondary', 'submit', false ); ?>
		</form>
	<?php endif; ?>
 
	<p><a href="<?php echo esc_url( admin_url( 'admin.php?page=' . Movies_WP_Media_Admin::SLUG ) ); ?>"><?php esc_html_e( 'Back to Movie Automation', 'movies-wp' ); ?></a></p>
</div>

