<?php
/**
 * Series import job progress.
 *
 * @var array<string, mixed> $job
 * @var array{type:string,message:string}|null $notice
 */

defined( 'ABSPATH' ) || exit;

$job     = is_array( $job ?? null ) ? $job : array();
$notice  = is_array( $notice ?? null ) ? $notice : null;
$token   = (string) ( $job['token'] ?? '' );
$status  = (string) ( $job['status'] ?? '' );
$phase   = (string) ( $job['phase'] ?? '' );
$done    = (int) ( $job['episode_done'] ?? 0 );
$total   = (int) ( $job['episode_total'] ?? 0 );
$error   = (string) ( $job['last_error'] ?? '' );
$warnings = isset( $job['warnings'] ) && is_array( $job['warnings'] ) ? $job['warnings'] : array();
?>
<div class="wrap movies-wp-scan-preview">
	<h1><?php esc_html_e( 'Series Import Progress', 'movies-wp' ); ?></h1>
	<?php if ( is_array( $notice ) ) : ?>
		<div class="notice notice-<?php echo esc_attr( (string) $notice['type'] ); ?>"><p><?php echo esc_html( (string) $notice['message'] ); ?></p></div>
	<?php endif; ?>
	<table class="widefat striped">
		<tbody>
			<tr><th><?php esc_html_e( 'Status', 'movies-wp' ); ?></th><td><?php echo esc_html( $status ); ?></td></tr>
			<tr><th><?php esc_html_e( 'Phase', 'movies-wp' ); ?></th><td><?php echo esc_html( $phase ); ?></td></tr>
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
	<?php if ( in_array( $status, array( 'paused', 'failed' ), true ) ) : ?>
		<form method="post" style="display:inline;">
			<?php wp_nonce_field( Movies_WP_Series_Admin::PROGRESS_NONCE ); ?>
			<input type="hidden" name="<?php echo esc_attr( Movies_WP_Series_Admin::ACTION_FIELD ); ?>" value="<?php echo esc_attr( Movies_WP_Series_Admin::RESUME_ACTION ); ?>">
			<input type="hidden" name="job_token" value="<?php echo esc_attr( $token ); ?>">
			<?php submit_button( __( 'Resume / Retry', 'movies-wp' ), 'primary', 'submit', false ); ?>
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
