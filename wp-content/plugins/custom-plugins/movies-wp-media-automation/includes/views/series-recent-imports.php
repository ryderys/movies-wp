<?php
/**
 * Recent Series import jobs for the current admin user.
 *
 * @var list<array<string, mixed>> $recent_jobs
 */

defined( 'ABSPATH' ) || exit;

$recent_jobs = isset( $recent_jobs ) && is_array( $recent_jobs ) ? $recent_jobs : array();
?>
<section class="movies-wp-panel movies-wp-series-recent-imports" aria-labelledby="movies-wp-series-recent-imports-heading">
	<h2 id="movies-wp-series-recent-imports-heading"><?php esc_html_e( 'Recent Imports', 'movies-wp' ); ?></h2>
	<?php if ( array() === $recent_jobs ) : ?>
		<p class="description"><?php esc_html_e( 'No Series import jobs yet for your account on this site.', 'movies-wp' ); ?></p>
	<?php else : ?>
		<table class="widefat striped movies-wp-series-recent-imports-table">
			<thead>
				<tr>
					<th scope="col"><?php esc_html_e( 'Series', 'movies-wp' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Status', 'movies-wp' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Progress', 'movies-wp' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Last activity', 'movies-wp' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Actions', 'movies-wp' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $recent_jobs as $recent_job ) : ?>
					<?php
					if ( ! is_array( $recent_job ) ) {
						continue;
					}
					$token   = isset( $recent_job['token'] ) ? (string) $recent_job['token'] : '';
					$status  = (string) ( $recent_job['status'] ?? '' );
					$stalled = Movies_WP_Series_Import_Job_Store::is_possibly_stalled( $recent_job );
					$error   = 'failed' === $status ? Movies_WP_Series_Admin::job_error_summary( $recent_job ) : '';
					$tmdb_id = (int) ( $recent_job['tmdb_id'] ?? 0 );
					$dir     = (string) ( $recent_job['directory'] ?? '' );
					?>
					<tr class="<?php echo esc_attr( Movies_WP_Series_Admin::job_row_css_class( $recent_job ) ); ?>">
						<td>
							<strong><?php echo esc_html( Movies_WP_Series_Admin::job_display_title( $recent_job ) ); ?></strong>
							<?php if ( $tmdb_id > 0 || '' !== $dir ) : ?>
								<br>
								<span class="description">
									<?php if ( $tmdb_id > 0 ) : ?>
										<?php
										echo esc_html(
											sprintf(
												/* translators: %d: TMDb ID */
												__( 'TMDb %d', 'movies-wp' ),
												$tmdb_id
											)
										);
										?>
									<?php endif; ?>
									<?php if ( $tmdb_id > 0 && '' !== $dir ) : ?>
										<span aria-hidden="true"> · </span>
									<?php endif; ?>
									<?php if ( '' !== $dir ) : ?>
										<?php echo esc_html( basename( str_replace( '\\', '/', $dir ) ) ); ?>
									<?php endif; ?>
								</span>
							<?php endif; ?>
						</td>
						<td>
							<span class="movies-wp-series-job-status"><?php echo esc_html( Movies_WP_Series_Admin::job_status_label( $status ) ); ?></span>
							<?php if ( $stalled ) : ?>
								<br>
								<span class="movies-wp-series-job-stalled" role="status">
									<?php esc_html_e( 'Possibly stalled', 'movies-wp' ); ?>
								</span>
								<?php if ( ! Movies_WP_Series_Admin::job_can_resume( $recent_job ) ) : ?>
									<br>
									<span class="description"><?php esc_html_e( 'Worker may still be running — wait before Resume.', 'movies-wp' ); ?></span>
								<?php endif; ?>
							<?php endif; ?>
							<?php if ( '' !== $error ) : ?>
								<br>
								<span class="movies-wp-series-job-error"><?php echo esc_html( $error ); ?></span>
							<?php endif; ?>
						</td>
						<td><?php echo esc_html( Movies_WP_Series_Admin::job_progress_label( $recent_job ) ); ?></td>
						<td><?php echo esc_html( Movies_WP_Series_Admin::job_activity_label( $recent_job ) ); ?></td>
						<td class="movies-wp-series-job-actions">
							<?php if ( '' !== $token ) : ?>
								<a class="button button-small" href="<?php echo esc_url( Movies_WP_Series_Admin::progress_url( $token ) ); ?>">
									<?php esc_html_e( 'View', 'movies-wp' ); ?>
								</a>
								<?php if ( Movies_WP_Series_Admin::job_can_resume( $recent_job ) ) : ?>
									<form method="post" class="movies-wp-series-job-resume-form">
										<?php wp_nonce_field( Movies_WP_Series_Admin::PROGRESS_NONCE ); ?>
										<input type="hidden" name="<?php echo esc_attr( Movies_WP_Series_Admin::ACTION_FIELD ); ?>" value="<?php echo esc_attr( Movies_WP_Series_Admin::RESUME_ACTION ); ?>">
										<input type="hidden" name="job_token" value="<?php echo esc_attr( $token ); ?>">
										<?php submit_button( __( 'Resume', 'movies-wp' ), 'secondary', 'submit', false ); ?>
									</form>
								<?php endif; ?>
							<?php else : ?>
								<span class="description"><?php esc_html_e( 'Progress link unavailable for this older job.', 'movies-wp' ); ?></span>
							<?php endif; ?>
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
	<?php endif; ?>
</section>
