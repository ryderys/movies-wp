<?php
/**
 * Unified Series metadata and media Preview and Import admin view.
 *
 * @var array{tmdb_id:int|string,title:string,summary:string,series_directory:string} $values
 * @var array<string,mixed>|null $preview
 * @var array<string,mixed>|null $plan
 * @var array{type:string,message:string}|null $notice
 * @var array<string,mixed>|null $import_result
 */

defined( 'ABSPATH' ) || exit;

$series  = is_array( $preview ) && is_array( $preview['series'] ?? null ) ? $preview['series'] : array();
$seasons = is_array( $series['seasons'] ?? null ) ? $series['seasons'] : array();
$media    = is_array( $preview ) && is_array( $preview['media'] ?? null ) ? $preview['media'] : array();
$episode_matches = is_array( $preview ) && is_array( $preview['episodes'] ?? null ) ? $preview['episodes'] : array();
$errors   = is_array( $preview ) && is_array( $preview['validation']['errors'] ?? null )
	? $preview['validation']['errors']
	: array();
$warnings = is_array( $preview ) && is_array( $preview['validation']['warnings'] ?? null )
	? $preview['validation']['warnings']
	: array();
$ready = is_array( $preview )
	&& is_array( $plan )
	&& true === ( $plan['ok'] ?? null )
	&& true === ( $plan['ready_to_import'] ?? null )
	&& true === ( $preview['ready_to_import'] ?? null )
	&& array() === $errors;

$season_create_count  = 0;
$season_update_count  = 0;
$episode_create_count = 0;
$episode_update_count = 0;
if ( is_array( $plan['seasons'] ?? null ) ) {
	foreach ( $plan['seasons'] as $season_plan ) {
		if ( ! is_array( $season_plan ) ) {
			continue;
		}
		if ( 'update' === ( $season_plan['action'] ?? '' ) ) {
			++$season_update_count;
		} else {
			++$season_create_count;
		}
		foreach ( is_array( $season_plan['episodes'] ?? null ) ? $season_plan['episodes'] : array() as $episode_plan ) {
			if ( 'update' === ( $episode_plan['action'] ?? '' ) ) {
				++$episode_update_count;
			} else {
				++$episode_create_count;
			}
		}
	}
}

$grouped_warnings = Movies_WP_Series_Admin::grouped_issues( $warnings );
$grouped_errors   = Movies_WP_Series_Admin::grouped_issues( $errors );
$coverage         = Movies_WP_Series_Admin::episode_coverage( $episode_matches );
$series_action    = is_array( $plan ) ? (string) ( $plan['identity']['action'] ?? '' ) : '';
$is_create        = 'create' === $series_action;
$video_count      = (int) ( $media['stats']['video_count'] ?? 0 );
$subtitle_count   = (int) ( $media['stats']['subtitle_count'] ?? 0 );
$poster_action    = is_array( $plan ) ? (string) ( $plan['images']['poster']['action'] ?? '' ) : '';
$backdrop_action  = is_array( $plan ) ? (string) ( $plan['images']['backdrop']['action'] ?? '' ) : '';
$season_total     = $season_create_count + $season_update_count;
$episode_total    = $episode_create_count + $episode_update_count;
$has_preserved_ids = false;
if ( is_array( $plan['seasons'] ?? null ) ) {
	foreach ( $plan['seasons'] as $season_plan ) {
		if ( is_array( $season_plan ) && array() !== array_filter( (array) ( $season_plan['existing_episode_ids'] ?? array() ) ) ) {
			$has_preserved_ids = true;
			break;
		}
	}
}
?>
<div class="wrap movies-wp-scan-preview movies-wp-series-preview">
	<h1><?php esc_html_e( 'Series Automation', 'movies-wp' ); ?></h1>
	<p class="description">
		<?php esc_html_e( 'Scan TMDb and the Series directory, preview metadata and episode media together, then import only after explicit confirmation.', 'movies-wp' ); ?>
	</p>

	<?php
	if ( isset( $recent_jobs ) && is_array( $recent_jobs ) ) {
		include MOVIES_WP_MEDIA_AUTOMATION_DIR . '/includes/views/series-recent-imports.php';
	}
	?>

	<?php if ( is_array( $notice ) ) : ?>
		<div class="notice notice-<?php echo esc_attr( $notice['type'] ); ?> is-dismissible">
			<p><?php echo esc_html( $notice['message'] ); ?></p>
		</div>
	<?php endif; ?>

	<?php if ( is_array( $import_result ) ) : ?>
		<div class="movies-wp-panel movies-wp-import-result <?php echo ! empty( $import_result['ok'] ) ? 'movies-wp-panel-success' : ( ! empty( $import_result['partial'] ) ? 'movies-wp-panel-warning' : 'movies-wp-panel-error' ); ?>">
			<h2><?php esc_html_e( 'Series import result', 'movies-wp' ); ?></h2>
			<ul class="movies-wp-import-result-list">
				<li>
					<?php esc_html_e( 'Status:', 'movies-wp' ); ?>
					<strong>
						<?php
						echo esc_html(
							! empty( $import_result['ok'] )
								? __( 'Successful', 'movies-wp' )
								: ( ! empty( $import_result['partial'] ) ? __( 'Partially completed', 'movies-wp' ) : __( 'Failed', 'movies-wp' ) )
						);
						?>
					</strong>
				</li>
				<li>
					<?php esc_html_e( 'Action:', 'movies-wp' ); ?>
					<strong><?php echo esc_html( Movies_WP_Series_Admin::action_label( $import_result['action'] ?? '' ) ); ?></strong>
				</li>
				<?php if ( null !== ( $import_result['series_id'] ?? null ) ) : ?>
					<li>
						<?php
						printf(
							/* translators: %d: Streamit Series ID */
							esc_html__( 'Series ID: %d', 'movies-wp' ),
							(int) $import_result['series_id']
						);
						?>
					</li>
				<?php endif; ?>
				<li>
					<?php esc_html_e( 'Media episodes completed:', 'movies-wp' ); ?>
					<strong><?php echo (int) ( $import_result['completed'] ?? 0 ); ?></strong>
				</li>
			</ul>

			<?php if ( ! empty( $import_result['errors'] ) && is_array( $import_result['errors'] ) ) : ?>
				<h3><?php esc_html_e( 'Errors', 'movies-wp' ); ?></h3>
				<ul>
					<?php foreach ( $import_result['errors'] as $error ) : ?>
						<li>
							<?php if ( is_array( $error ) && ! empty( $error['code'] ) ) : ?>
								<code dir="ltr"><?php echo esc_html( (string) $error['code'] ); ?></code>
							<?php endif; ?>
							<?php echo esc_html( Movies_WP_Series_Admin::issue_message( $error ) ); ?>
						</li>
					<?php endforeach; ?>
				</ul>
			<?php endif; ?>

			<?php if ( ! empty( $import_result['warnings'] ) && is_array( $import_result['warnings'] ) ) : ?>
				<h3><?php esc_html_e( 'Warnings', 'movies-wp' ); ?></h3>
				<ul>
					<?php foreach ( $import_result['warnings'] as $warning ) : ?>
						<li>
							<?php if ( is_array( $warning ) && ! empty( $warning['code'] ) ) : ?>
								<code dir="ltr"><?php echo esc_html( (string) $warning['code'] ); ?></code>
							<?php endif; ?>
							<?php echo esc_html( Movies_WP_Series_Admin::issue_message( $warning ) ); ?>
						</li>
					<?php endforeach; ?>
				</ul>
			<?php endif; ?>

			<h3><?php esc_html_e( 'Season operations', 'movies-wp' ); ?></h3>
			<table class="widefat striped movies-wp-files">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Season', 'movies-wp' ); ?></th>
						<th><?php esc_html_e( 'Action', 'movies-wp' ); ?></th>
						<th><?php esc_html_e( 'Result', 'movies-wp' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( is_array( $import_result['seasons'] ?? null ) ? $import_result['seasons'] : array() as $season_result ) : ?>
						<tr>
							<td><?php echo esc_html( (string) ( $season_result['season_number'] ?? '' ) ); ?></td>
							<td><?php echo esc_html( Movies_WP_Series_Admin::action_label( $season_result['action'] ?? '' ) ); ?></td>
							<td>
								<?php echo ! empty( $season_result['ok'] ) ? esc_html__( 'Successful', 'movies-wp' ) : esc_html__( 'Failed', 'movies-wp' ); ?>
								<?php if ( ! empty( $season_result['error']['code'] ) ) : ?>
									<code dir="ltr"><?php echo esc_html( (string) $season_result['error']['code'] ); ?></code>
								<?php endif; ?>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>

			<div class="movies-wp-preview-grid">
				<div>
					<h3><?php esc_html_e( 'Episode operations', 'movies-wp' ); ?></h3>
					<table class="widefat striped movies-wp-files">
						<thead>
							<tr>
								<th><?php esc_html_e( 'Episode', 'movies-wp' ); ?></th>
								<th><?php esc_html_e( 'Action', 'movies-wp' ); ?></th>
								<th><?php esc_html_e( 'Result', 'movies-wp' ); ?></th>
							</tr>
						</thead>
						<tbody>
							<?php foreach ( is_array( $import_result['episodes'] ?? null ) ? $import_result['episodes'] : array() as $episode_result ) : ?>
								<tr>
									<td>
										<?php
										printf(
											'S%02dE%02d',
											(int) ( $episode_result['season_number'] ?? 0 ),
											(int) ( $episode_result['episode_number'] ?? 0 )
										);
										?>
									</td>
									<td><?php echo esc_html( Movies_WP_Series_Admin::action_label( $episode_result['action'] ?? '' ) ); ?></td>
									<td>
										<?php echo ! empty( $episode_result['ok'] ) ? esc_html__( 'Successful', 'movies-wp' ) : esc_html__( 'Failed', 'movies-wp' ); ?>
										<?php if ( ! empty( $episode_result['error']['code'] ) ) : ?>
											<code dir="ltr"><?php echo esc_html( (string) $episode_result['error']['code'] ); ?></code>
										<?php endif; ?>
									</td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
				</div>
				<div>
					<h3><?php esc_html_e( 'Image operations', 'movies-wp' ); ?></h3>
					<table class="widefat striped movies-wp-files">
						<thead>
							<tr>
								<th><?php esc_html_e( 'Role', 'movies-wp' ); ?></th>
								<th><?php esc_html_e( 'Action', 'movies-wp' ); ?></th>
								<th><?php esc_html_e( 'Attachment ID', 'movies-wp' ); ?></th>
								<th><?php esc_html_e( 'Result', 'movies-wp' ); ?></th>
							</tr>
						</thead>
						<tbody>
							<?php foreach ( is_array( $import_result['images'] ?? null ) ? $import_result['images'] : array() as $image_result ) : ?>
								<tr>
									<td><code dir="ltr"><?php echo esc_html( (string) ( $image_result['role'] ?? '' ) ); ?></code></td>
									<td><?php echo esc_html( Movies_WP_Series_Admin::action_label( $image_result['action'] ?? '' ) ); ?></td>
									<td><?php echo esc_html( Movies_WP_Series_Admin::dash( $image_result['attachment_id'] ?? null ) ); ?></td>
									<td>
										<?php echo ! empty( $image_result['ok'] ) ? esc_html__( 'Successful', 'movies-wp' ) : esc_html__( 'Failed', 'movies-wp' ); ?>
										<?php if ( ! empty( $image_result['error']['code'] ) ) : ?>
											<code dir="ltr"><?php echo esc_html( (string) $image_result['error']['code'] ); ?></code>
										<?php endif; ?>
									</td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
				</div>
			</div>
		</div>
	<?php endif; ?>

	<div class="movies-wp-panel">
		<h2><?php esc_html_e( 'Series input', 'movies-wp' ); ?></h2>
		<form method="post">
			<?php wp_nonce_field( Movies_WP_Series_Admin::PREVIEW_NONCE ); ?>
			<input type="hidden" name="<?php echo esc_attr( Movies_WP_Series_Admin::ACTION_FIELD ); ?>" value="<?php echo esc_attr( Movies_WP_Series_Admin::PREVIEW_ACTION ); ?>">
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><label for="series-tmdb-id"><?php esc_html_e( 'TMDb Series ID', 'movies-wp' ); ?></label></th>
					<td><input id="series-tmdb-id" name="tmdb_id" type="number" min="1" required value="<?php echo esc_attr( (string) $values['tmdb_id'] ); ?>" class="regular-text" dir="ltr"></td>
				</tr>
				<tr>
					<th scope="row"><label for="series-local-title"><?php esc_html_e( 'Persian / local title', 'movies-wp' ); ?></label></th>
					<td><input id="series-local-title" name="title" type="text" required value="<?php echo esc_attr( (string) $values['title'] ); ?>" class="regular-text" dir="auto"></td>
				</tr>
				<tr>
					<th scope="row"><label for="series-summary"><?php esc_html_e( 'Summary', 'movies-wp' ); ?></label></th>
					<td><textarea id="series-summary" name="summary" rows="5" class="large-text" dir="auto"><?php echo esc_textarea( (string) $values['summary'] ); ?></textarea></td>
				</tr>
				<tr>
					<th scope="row"><label for="series-directory"><?php esc_html_e( 'Series directory', 'movies-wp' ); ?></label></th>
					<td>
						<input id="series-directory" name="series_directory" type="text" required value="<?php echo esc_attr( (string) $values['series_directory'] ); ?>" class="large-text code" dir="ltr" placeholder="series/korea/2024/Marry.My.Husband">
						<p class="description"><?php esc_html_e( 'Relative path under the media root. Absolute and signed paths are rejected.', 'movies-wp' ); ?></p>
					</td>
				</tr>
			</table>
			<?php submit_button( __( 'Scan & Preview', 'movies-wp' ) ); ?>
		</form>
	</div>

	<?php if ( is_array( $preview ) ) : ?>
		<div class="movies-wp-status-banner <?php echo $ready ? 'is-ready' : 'is-blocked'; ?>">
			<h2>
				<?php if ( $ready ) : ?>
					<?php esc_html_e( 'Ready to import', 'movies-wp' ); ?>
				<?php else : ?>
					<?php esc_html_e( 'Import blocked', 'movies-wp' ); ?>
				<?php endif; ?>
			</h2>
			<?php if ( $is_create ) : ?>
				<p><?php esc_html_e( 'New Series will be created', 'movies-wp' ); ?></p>
			<?php elseif ( 'update' === $series_action ) : ?>
				<p><?php esc_html_e( 'Existing Series will be updated', 'movies-wp' ); ?></p>
			<?php endif; ?>
			<?php if ( $ready ) : ?>
				<p><?php esc_html_e( 'No blocking errors', 'movies-wp' ); ?></p>
			<?php endif; ?>
		</div>

		<?php if ( ! empty( $grouped_errors ) ) : ?>
			<div class="movies-wp-panel movies-wp-panel-error">
				<h2><?php esc_html_e( 'Errors', 'movies-wp' ); ?></h2>
				<ul>
					<?php foreach ( $grouped_errors as $group ) : ?>
						<li>
							<?php echo esc_html( $group['summary'] ); ?>
							<?php if ( $group['count'] > 1 ) : ?>
								<details class="movies-wp-issue-details">
									<summary><?php esc_html_e( 'Details', 'movies-wp' ); ?></summary>
									<ul>
										<?php foreach ( $group['details'] as $detail ) : ?>
											<li><?php echo esc_html( $detail ); ?></li>
										<?php endforeach; ?>
									</ul>
								</details>
							<?php endif; ?>
						</li>
					<?php endforeach; ?>
				</ul>
			</div>
		<?php endif; ?>

		<div class="movies-wp-preview-grid">
			<div class="movies-wp-panel movies-wp-panel-happen">
				<h2><?php esc_html_e( 'What will happen', 'movies-wp' ); ?></h2>
				<ul class="movies-wp-will-happen">
					<?php if ( $is_create ) : ?>
						<li><?php echo esc_html( sprintf( __( 'Create %d Series', 'movies-wp' ), 1 ) ); ?></li>
					<?php else : ?>
						<li><?php esc_html_e( 'Update existing Series', 'movies-wp' ); ?></li>
					<?php endif; ?>
					<?php if ( $season_create_count > 0 ) : ?>
						<li>
							<?php
							echo esc_html(
								1 === $season_create_count
									? sprintf( __( 'Create %d Season', 'movies-wp' ), $season_create_count )
									: sprintf( __( 'Create %d Seasons', 'movies-wp' ), $season_create_count )
							);
							?>
						</li>
					<?php endif; ?>
					<?php if ( $season_update_count > 0 ) : ?>
						<li>
							<?php
							echo esc_html(
								1 === $season_update_count
									? sprintf( __( 'Update %d Season', 'movies-wp' ), $season_update_count )
									: sprintf( __( 'Update %d Seasons', 'movies-wp' ), $season_update_count )
							);
							?>
						</li>
					<?php endif; ?>
					<?php if ( $episode_create_count > 0 ) : ?>
						<li>
							<?php
							echo esc_html(
								1 === $episode_create_count
									? sprintf( __( 'Create %d Episode', 'movies-wp' ), $episode_create_count )
									: sprintf( __( 'Create %d Episodes', 'movies-wp' ), $episode_create_count )
							);
							?>
						</li>
					<?php endif; ?>
					<?php if ( $episode_update_count > 0 ) : ?>
						<li>
							<?php
							echo esc_html(
								1 === $episode_update_count
									? sprintf( __( 'Update %d Episode', 'movies-wp' ), $episode_update_count )
									: sprintf( __( 'Update %d Episodes', 'movies-wp' ), $episode_update_count )
							);
							?>
						</li>
					<?php endif; ?>
					<?php if ( $video_count > 0 ) : ?>
						<li><?php echo esc_html( sprintf( __( 'Attach %d videos', 'movies-wp' ), $video_count ) ); ?></li>
					<?php endif; ?>
					<?php if ( $subtitle_count > 0 ) : ?>
						<li><?php echo esc_html( sprintf( __( 'Attach %d subtitles', 'movies-wp' ), $subtitle_count ) ); ?></li>
					<?php endif; ?>
					<?php if ( 'set' === $poster_action && 'set' === $backdrop_action ) : ?>
						<li><?php esc_html_e( 'Set poster and backdrop', 'movies-wp' ); ?></li>
					<?php elseif ( 'set' === $poster_action ) : ?>
						<li><?php esc_html_e( 'Set poster', 'movies-wp' ); ?></li>
					<?php elseif ( 'set' === $backdrop_action ) : ?>
						<li><?php esc_html_e( 'Set backdrop', 'movies-wp' ); ?></li>
					<?php endif; ?>
				</ul>
			</div>

			<div class="movies-wp-panel movies-wp-panel-coverage">
				<h2><?php esc_html_e( 'Episode coverage', 'movies-wp' ); ?></h2>
				<p class="movies-wp-coverage-count">
					<?php
					printf(
						/* translators: 1: matched episode count, 2: total TMDb episode count */
						esc_html__( '%1$d / %2$d episodes matched', 'movies-wp' ),
						(int) $coverage['matched'],
						(int) $coverage['total']
					);
					?>
				</p>
				<?php if ( '' !== $coverage['range'] ) : ?>
					<p><code dir="ltr"><?php echo esc_html( $coverage['range'] ); ?></code></p>
				<?php endif; ?>
				<?php if ( $coverage['uniform'] ) : ?>
					<p>
						<strong>
							<?php
							printf(
								/* translators: 1: video files per episode, 2: subtitle files per episode */
								esc_html__( '%1$d videos + %2$d subtitles per episode', 'movies-wp' ),
								(int) $coverage['videos_per_episode'],
								(int) $coverage['subtitles_per_episode']
							);
							?>
						</strong>
					</p>
				<?php endif; ?>
			</div>
		</div>

		<?php if ( ! empty( $grouped_warnings ) ) : ?>
			<div class="movies-wp-panel movies-wp-panel-warning">
				<h2><?php esc_html_e( 'Warnings', 'movies-wp' ); ?></h2>
				<ul>
					<?php foreach ( $grouped_warnings as $group ) : ?>
						<li>
							<?php echo esc_html( $group['summary'] ); ?>
							<?php if ( $group['count'] > 1 ) : ?>
								<details class="movies-wp-issue-details">
									<summary><?php esc_html_e( 'Details', 'movies-wp' ); ?></summary>
									<ul>
										<?php foreach ( $group['details'] as $detail ) : ?>
											<li><?php echo esc_html( $detail ); ?></li>
										<?php endforeach; ?>
									</ul>
								</details>
							<?php endif; ?>
						</li>
					<?php endforeach; ?>
				</ul>
			</div>
		<?php endif; ?>

		<div class="movies-wp-panel">
			<h2><?php esc_html_e( 'Series metadata preview', 'movies-wp' ); ?></h2>
			<div class="movies-wp-preview-images">
				<?php if ( ! empty( $series['poster_url'] ) ) : ?>
					<img class="movies-wp-poster" src="<?php echo esc_url( (string) $series['poster_url'] ); ?>" alt="<?php esc_attr_e( 'Series poster', 'movies-wp' ); ?>">
				<?php endif; ?>
				<?php if ( ! empty( $series['backdrop_url'] ) ) : ?>
					<img class="movies-wp-backdrop" src="<?php echo esc_url( (string) $series['backdrop_url'] ); ?>" alt="<?php esc_attr_e( 'Series backdrop', 'movies-wp' ); ?>">
				<?php endif; ?>
			</div>
			<table class="widefat striped">
				<tbody>
					<tr><th><?php esc_html_e( 'Local title', 'movies-wp' ); ?></th><td><?php echo esc_html( (string) $values['title'] ); ?></td></tr>
					<tr><th><?php esc_html_e( 'TMDb title', 'movies-wp' ); ?></th><td><?php echo esc_html( Movies_WP_Series_Admin::dash( $series['name'] ?? null ) ); ?></td></tr>
					<tr><th><?php esc_html_e( 'Original title', 'movies-wp' ); ?></th><td><?php echo esc_html( Movies_WP_Series_Admin::dash( $series['original_name'] ?? null ) ); ?></td></tr>
					<tr><th><?php esc_html_e( 'First air date', 'movies-wp' ); ?></th><td dir="ltr"><?php echo esc_html( Movies_WP_Series_Admin::dash( $series['first_air_date'] ?? null ) ); ?></td></tr>
					<tr><th><?php esc_html_e( 'Rating', 'movies-wp' ); ?></th><td><?php echo esc_html( Movies_WP_Series_Admin::dash( $series['rating'] ?? null ) ); ?></td></tr>
					<tr><th><?php esc_html_e( 'Original language', 'movies-wp' ); ?></th><td><code dir="ltr"><?php echo esc_html( Movies_WP_Series_Admin::dash( $series['original_language'] ?? null ) ); ?></code></td></tr>
					<tr><th><?php esc_html_e( 'Countries', 'movies-wp' ); ?></th><td><?php echo esc_html( implode( '، ', is_array( $series['origin_country'] ?? null ) ? $series['origin_country'] : array() ) ); ?></td></tr>
					<tr><th><?php esc_html_e( 'Seasons', 'movies-wp' ); ?></th><td><?php echo (int) count( $seasons ); ?></td></tr>
					<tr><th><?php esc_html_e( 'Episodes', 'movies-wp' ); ?></th><td><?php echo (int) ( $series['number_of_episodes'] ?? 0 ); ?></td></tr>
				</tbody>
			</table>
			<?php if ( ! empty( $series['overview'] ) ) : ?>
				<h3><?php esc_html_e( 'TMDb overview', 'movies-wp' ); ?></h3>
				<p><?php echo esc_html( (string) $series['overview'] ); ?></p>
			<?php endif; ?>
		</div>

		<?php if ( $ready ) : ?>
			<div class="movies-wp-panel movies-wp-panel-success movies-wp-confirm">
				<h2><?php esc_html_e( 'Ready to import', 'movies-wp' ); ?></h2>
				<p class="movies-wp-confirm-copy">
					<?php
					if ( $is_create ) {
						printf(
							/* translators: 1: season count, 2: episode count, 3: video file count, 4: subtitle file count */
							esc_html__( 'This will create a new Series with %1$d season, %2$d episodes, %3$d videos and %4$d subtitles.', 'movies-wp' ),
							(int) $season_total,
							(int) $episode_total,
							$video_count,
							$subtitle_count
						);
					} else {
						printf(
							/* translators: 1: season count, 2: episode count, 3: video file count, 4: subtitle file count */
							esc_html__( 'This will update the existing Series with %1$d season, %2$d episodes, %3$d videos and %4$d subtitles.', 'movies-wp' ),
							(int) $season_total,
							(int) $episode_total,
							$video_count,
							$subtitle_count
						);
					}
					?>
				</p>
				<form method="post" class="movies-wp-import-form">
					<?php wp_nonce_field( Movies_WP_Series_Admin::IMPORT_NONCE ); ?>
					<input type="hidden" name="<?php echo esc_attr( Movies_WP_Series_Admin::ACTION_FIELD ); ?>" value="<?php echo esc_attr( Movies_WP_Series_Admin::IMPORT_ACTION ); ?>">
					<input type="hidden" name="snapshot_token" value="<?php echo esc_attr( (string) ( $snapshot_token ?? '' ) ); ?>">
					<label>
						<input type="checkbox" name="confirm_import" value="1" required>
						<?php esc_html_e( 'I reviewed this plan and approve the Series metadata and media operations.', 'movies-wp' ); ?>
					</label>
					<?php submit_button( __( 'Import Series & Media', 'movies-wp' ), 'primary', 'submit', false ); ?>
				</form>
			</div>
		<?php endif; ?>

		<details class="movies-wp-fold">
			<summary><?php esc_html_e( 'Episode details', 'movies-wp' ); ?></summary>
			<table class="widefat striped movies-wp-files">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Episode', 'movies-wp' ); ?></th>
						<th><?php esc_html_e( 'TMDb title', 'movies-wp' ); ?></th>
						<th><?php esc_html_e( 'Metadata action', 'movies-wp' ); ?></th>
						<th><?php esc_html_e( 'Media status', 'movies-wp' ); ?></th>
						<th><?php esc_html_e( 'Sources', 'movies-wp' ); ?></th>
						<th><?php esc_html_e( 'Subtitles', 'movies-wp' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $episode_matches as $episode_match ) : ?>
						<tr>
							<td><code dir="ltr"><?php printf( 'S%02dE%02d', (int) ( $episode_match['season_number'] ?? 0 ), (int) ( $episode_match['episode_number'] ?? 0 ) ); ?></code></td>
							<td><?php echo esc_html( Movies_WP_Series_Admin::dash( $episode_match['name'] ?? null ) ); ?></td>
							<td><?php echo esc_html( Movies_WP_Series_Admin::action_label( $episode_match['action'] ?? '' ) ); ?></td>
							<td><?php echo esc_html( Movies_WP_Series_Admin::media_status_label( $episode_match['status'] ?? '' ) ); ?></td>
							<td><?php echo (int) ( $episode_match['source_count'] ?? 0 ); ?></td>
							<td><?php echo (int) ( $episode_match['subtitle_count'] ?? 0 ); ?></td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		</details>

		<details class="movies-wp-fold">
			<summary><?php esc_html_e( 'Advanced details', 'movies-wp' ); ?></summary>
			<?php if ( is_array( $plan ) ) : ?>
				<table class="widefat striped">
					<tbody>
						<tr><th><?php esc_html_e( 'Series action', 'movies-wp' ); ?></th><td><?php echo esc_html( Movies_WP_Series_Admin::action_label( $plan['identity']['action'] ?? '' ) ); ?></td></tr>
						<?php if ( null !== ( $plan['identity']['existing_series_id'] ?? null ) && '' !== ( $plan['identity']['existing_series_id'] ?? null ) ) : ?>
							<tr><th><?php esc_html_e( 'Existing Series ID', 'movies-wp' ); ?></th><td><?php echo esc_html( (string) $plan['identity']['existing_series_id'] ); ?></td></tr>
						<?php endif; ?>
						<?php if ( $season_create_count > 0 ) : ?>
							<tr><th><?php esc_html_e( 'Seasons to create', 'movies-wp' ); ?></th><td><?php echo (int) $season_create_count; ?></td></tr>
						<?php endif; ?>
						<?php if ( $season_update_count > 0 ) : ?>
							<tr><th><?php esc_html_e( 'Seasons to update', 'movies-wp' ); ?></th><td><?php echo (int) $season_update_count; ?></td></tr>
						<?php endif; ?>
						<?php if ( $episode_create_count > 0 ) : ?>
							<tr><th><?php esc_html_e( 'Episodes to create', 'movies-wp' ); ?></th><td><?php echo (int) $episode_create_count; ?></td></tr>
						<?php endif; ?>
						<?php if ( $episode_update_count > 0 ) : ?>
							<tr><th><?php esc_html_e( 'Episodes to update', 'movies-wp' ); ?></th><td><?php echo (int) $episode_update_count; ?></td></tr>
						<?php endif; ?>
						<tr><th><?php esc_html_e( 'Media directory', 'movies-wp' ); ?></th><td><code dir="ltr"><?php echo esc_html( (string) ( $media['directory']['path'] ?? $values['series_directory'] ) ); ?></code></td></tr>
						<tr><th><?php esc_html_e( 'Video files', 'movies-wp' ); ?></th><td><?php echo (int) $video_count; ?></td></tr>
						<tr><th><?php esc_html_e( 'Subtitle files', 'movies-wp' ); ?></th><td><?php echo (int) $subtitle_count; ?></td></tr>
						<tr><th><?php esc_html_e( 'Poster action', 'movies-wp' ); ?></th><td><?php echo esc_html( Movies_WP_Series_Admin::action_label( $poster_action ) ); ?></td></tr>
						<tr><th><?php esc_html_e( 'Backdrop action', 'movies-wp' ); ?></th><td><?php echo esc_html( Movies_WP_Series_Admin::action_label( $backdrop_action ) ); ?></td></tr>
						<tr><th><?php esc_html_e( 'Episode sources', 'movies-wp' ); ?></th><td><?php esc_html_e( 'Metadata import does not write episode playback sources.', 'movies-wp' ); ?></td></tr>
						<tr><th><?php esc_html_e( 'Identity match', 'movies-wp' ); ?></th><td><code dir="ltr"><?php echo esc_html( (string) ( $plan['identity']['match_by'] ?? '' ) ); ?></code></td></tr>
						<tr><th><?php esc_html_e( 'Plan version', 'movies-wp' ); ?></th><td><code dir="ltr"><?php echo esc_html( (string) ( $plan['contract']['version'] ?? '' ) ); ?></code></td></tr>
					</tbody>
				</table>
			<?php endif; ?>

			<h3><?php esc_html_e( 'Season and episode plan', 'movies-wp' ); ?></h3>
			<table class="widefat striped movies-wp-files">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Season', 'movies-wp' ); ?></th>
						<th><?php esc_html_e( 'Action', 'movies-wp' ); ?></th>
						<th><?php esc_html_e( 'Episodes', 'movies-wp' ); ?></th>
						<?php if ( $has_preserved_ids ) : ?>
							<th><?php esc_html_e( 'Existing episode IDs preserved', 'movies-wp' ); ?></th>
						<?php endif; ?>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( is_array( $plan['seasons'] ?? null ) ? $plan['seasons'] : array() as $season_plan ) : ?>
						<tr>
							<td>
								<strong><?php echo esc_html( (string) ( $season_plan['season_number'] ?? '' ) ); ?></strong>
								<?php echo esc_html( (string) ( $season_plan['name'] ?? '' ) ); ?>
							</td>
							<td><?php echo esc_html( Movies_WP_Series_Admin::action_label( $season_plan['action'] ?? '' ) ); ?></td>
							<td>
								<ul>
									<?php foreach ( is_array( $season_plan['episodes'] ?? null ) ? $season_plan['episodes'] : array() as $episode_plan ) : ?>
										<li>
											<code dir="ltr">
												<?php
												printf(
													'S%02dE%02d',
													(int) ( $episode_plan['season_number'] ?? 0 ),
													(int) ( $episode_plan['episode_number'] ?? 0 )
												);
												?>
											</code>
											<?php echo esc_html( (string) ( $episode_plan['name'] ?? '' ) ); ?>
											— <?php echo esc_html( Movies_WP_Series_Admin::action_label( $episode_plan['action'] ?? '' ) ); ?>
										</li>
									<?php endforeach; ?>
								</ul>
							</td>
							<?php if ( $has_preserved_ids ) : ?>
								<td>
									<code dir="ltr">
										<?php echo esc_html( implode( ', ', array_map( 'intval', is_array( $season_plan['existing_episode_ids'] ?? null ) ? $season_plan['existing_episode_ids'] : array() ) ) ); ?>
									</code>
								</td>
							<?php endif; ?>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
			<p class="description">
				<?php esc_html_e( 'The server will rebuild and validate the plan immediately before writing to Streamit. Browser plan data is never trusted.', 'movies-wp' ); ?>
			</p>
		</details>
	<?php endif; ?>
</div>
