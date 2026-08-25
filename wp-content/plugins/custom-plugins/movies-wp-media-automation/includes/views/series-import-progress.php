<?php
/**
 * Series import job progress.
 *
 * @var array<string, mixed> $job
 * @var array{type:string,message:string}|null $notice
 */

defined( 'ABSPATH' ) || exit;

$job      = is_array( $job ?? null ) ? $job : array();
$notice   = is_array( $notice ?? null ) ? $notice : null;
$token    = (string) ( $job['token'] ?? '' );
$status   = (string) ( $job['status'] ?? '' );
$phase    = (string) ( $job['phase'] ?? '' );
$done     = (int) ( $job['episode_done'] ?? 0 );
$total    = (int) ( $job['episode_total'] ?? 0 );
$error    = (string) ( $job['last_error'] ?? '' );
$warnings = isset( $job['warnings'] ) && is_array( $job['warnings'] ) ? $job['warnings'] : array();
$stalled  = Movies_WP_Series_Import_Job_Store::is_possibly_stalled( $job );
$can_resume = Movies_WP_Series_Admin::job_can_resume( $job );
?>
<div class="wrap movies-wp-scan-preview">
	<h1><?php esc_html_e( 'Series Import Progress', 'movies-wp' ); ?></h1>
	<?php if ( is_array( $notice ) ) : ?>
		<div class="notice notice-<?php echo esc_attr( (string) $notice['type'] ); ?>"><p><?php echo esc_html( (string) $notice['message'] ); ?></p></div>
	<?php endif; ?>

	<div class="notice notice-info movies-wp-series-import-background-note inline">
		<p>
			<strong><?php esc_html_e( 'This import continues in the background.', 'movies-wp' ); ?></strong>
			<?php esc_html_e( 'You can leave this page; the import will not stop. Closing the browser does not cancel it either — Action Scheduler keeps processing on the server.', 'movies-wp' ); ?>
		</p>
	</div>

	<?php if ( $stalled ) : ?>
		<div class="notice notice-warning inline" role="status">
			<p>
				<strong><?php esc_html_e( 'Possibly stalled', 'movies-wp' ); ?></strong>
				<?php if ( $can_resume ) : ?>
					<?php esc_html_e( 'No recent worker activity was detected past the recovery threshold. Resume re-queues this same import from its current phase — it does not restart from the beginning. Use it only if you believe the worker has stopped.', 'movies-wp' ); ?>
				<?php else : ?>
					<?php esc_html_e( 'The claim lease looks expired, but the job still shows recent activity. The worker may still be running. Wait before using Resume.', 'movies-wp' ); ?>
				<?php endif; ?>
			</p>
		</div>
	<?php endif; ?>

	<table class="widefat striped">
		<tbody>
			<tr><th><?php esc_html_e( 'Status', 'movies-wp' ); ?></th><td><?php echo esc_html( Movies_WP_Series_Admin::job_status_label( $status ) ); ?></td></tr>
			<tr><th><?php esc_html_e( 'Phase', 'movies-wp' ); ?></th><td><?php echo esc_html( Movies_WP_Series_Admin::job_phase_label( $phase ) ); ?></td></tr>
			<tr><th><?php esc_html_e( 'Episodes', 'movies-wp' ); ?></th><td><?php echo esc_html( $done . ' / ' . $total ); ?></td></tr>
			<tr><th><?php esc_html_e( 'Last episode ID', 'movies-wp' ); ?></th><td><?php echo esc_html( (string) ( $job['last_episode_id'] ?? '' ) ); ?></td></tr>
			<tr><th><?php esc_html_e( 'Created', 'movies-wp' ); ?></th><td><?php echo esc_html( (string) ( $job['created_at'] ?? '' ) ); ?></td></tr>
			<tr><th><?php esc_html_e( 'Updated', 'movies-wp' ); ?></th><td><?php echo esc_html( (string) ( $job['updated_at'] ?? '' ) ); ?></td></tr>
			<?php if ( '' !== $error ) : ?>
				<tr><th><?php esc_html_e( 'Last error', 'movies-wp' ); ?></th><td><?php echo esc_html( $error ); ?></td></tr>
			<?php endif; ?>
		</tbody>
	</table>
	<?php if ( array() !== $warnings ) : ?>
		<h2><?php esc_html_e( 'Warnings', 'movies-wp' ); ?></h2>
		<ul>
			<?php foreach ( $warnings as $warning ) : ?>
				<li><?php echo esc_html( is_array( $warning ) ? (string) ( $warning['message'] ?? '' ) : (string) $warning ); ?></li>
			<?php endforeach; ?>
		</ul>
	<?php endif; ?>
	<p>
		<a class="button" href="<?php echo esc_url( Movies_WP_Series_Admin::progress_url( $token ) ); ?>"><?php esc_html_e( 'Refresh', 'movies-wp' ); ?></a>
	</p>
	<?php if ( $can_resume ) : ?>
		<form method="post" style="display:inline;">
			<?php wp_nonce_field( Movies_WP_Series_Admin::PROGRESS_NONCE ); ?>
			<input type="hidden" name="<?php echo esc_attr( Movies_WP_Series_Admin::ACTION_FIELD ); ?>" value="<?php echo esc_attr( Movies_WP_Series_Admin::RESUME_ACTION ); ?>">
			<input type="hidden" name="job_token" value="<?php echo esc_attr( $token ); ?>">
			<?php submit_button( __( 'Resume', 'movies-wp' ), 'primary', 'submit', false ); ?>
		</form>
	<?php endif; ?>
	<?php if ( in_array( $status, array( 'queued', 'running', 'paused' ), true ) ) : ?>
		<form method="post" style="display:inline;">
			<?php wp_nonce_field( Movies_WP_Series_Admin::PROGRESS_NONCE ); ?>
			<input type="hidden" name="<?php echo esc_attr( Movies_WP_Series_Admin::ACTION_FIELD ); ?>" value="<?php echo esc_attr( Movies_WP_Series_Admin::CANCEL_ACTION ); ?>">
			<input type="hidden" name="job_token" value="<?php echo esc_attr( $token ); ?>">
			<?php submit_button( __( 'Cancel', 'movies-wp' ), 'secondary', 'submit', false ); ?>
		</form>
	<?php endif; ?>
	<p><a href="<?php echo esc_url( admin_url( 'admin.php?page=' . Movies_WP_Series_Admin::SLUG ) ); ?>"><?php esc_html_e( 'Back to Series Automation', 'movies-wp' ); ?></a></p>
</div>
