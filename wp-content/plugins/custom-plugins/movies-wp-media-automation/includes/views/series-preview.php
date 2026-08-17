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
?>
<div class="wrap movies-wp-scan-preview movies-wp-series-preview">
	<h1><?php esc_html_e( 'Series Automation', 'movies-wp' ); ?></h1>
	<p class="description">
		<?php esc_html_e( 'Scan TMDb and the Series directory, preview metadata and episode media together, then import only after explicit confirmation.', 'movies-wp' ); ?>
	</p>

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
		<div class="movies-wp-preview-grid">
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

			<div class="movies-wp-panel">
				<h2><?php esc_html_e( 'Import Plan summary', 'movies-wp' ); ?></h2>
				<?php if ( is_array( $plan ) ) : ?>
					<table class="widefat striped">
						<tbody>
							<tr><th><?php esc_html_e( 'Series action', 'movies-wp' ); ?></th><td><strong><?php echo esc_html( Movies_WP_Series_Admin::action_label( $plan['identity']['action'] ?? '' ) ); ?></strong></td></tr>
							<tr><th><?php esc_html_e( 'Existing Series ID', 'movies-wp' ); ?></th><td><?php echo esc_html( Movies_WP_Series_Admin::dash( $plan['identity']['existing_series_id'] ?? null ) ); ?></td></tr>
							<tr><th><?php esc_html_e( 'Seasons to create', 'movies-wp' ); ?></th><td><?php echo (int) $season_create_count; ?></td></tr>
							<tr><th><?php esc_html_e( 'Seasons to update', 'movies-wp' ); ?></th><td><?php echo (int) $season_update_count; ?></td></tr>
							<tr><th><?php esc_html_e( 'Episodes to create', 'movies-wp' ); ?></th><td><?php echo (int) $episode_create_count; ?></td></tr>
							<tr><th><?php esc_html_e( 'Episodes to update', 'movies-wp' ); ?></th><td><?php echo (int) $episode_update_count; ?></td></tr>
							<tr><th><?php esc_html_e( 'Media directory', 'movies-wp' ); ?></th><td><code dir="ltr"><?php echo esc_html( (string) ( $media['directory']['path'] ?? $values['series_directory'] ) ); ?></code></td></tr>
							<tr><th><?php esc_html_e( 'Video files', 'movies-wp' ); ?></th><td><?php echo (int) ( $media['stats']['video_count'] ?? 0 ); ?></td></tr>
							<tr><th><?php esc_html_e( 'Subtitle files', 'movies-wp' ); ?></th><td><?php echo (int) ( $media['stats']['subtitle_count'] ?? 0 ); ?></td></tr>
							<tr><th><?php esc_html_e( 'Poster action', 'movies-wp' ); ?></th><td><?php echo esc_html( Movies_WP_Series_Admin::action_label( $plan['images']['poster']['action'] ?? '' ) ); ?></td></tr>
							<tr><th><?php esc_html_e( 'Backdrop action', 'movies-wp' ); ?></th><td><?php echo esc_html( Movies_WP_Series_Admin::action_label( $plan['images']['backdrop']['action'] ?? '' ) ); ?></td></tr>
							<tr><th><?php esc_html_e( 'Episode sources', 'movies-wp' ); ?></th><td><strong><?php esc_html_e( 'Always preserved', 'movies-wp' ); ?></strong></td></tr>
						</tbody>
					</table>
				<?php endif; ?>

				<div class="movies-wp-import-status <?php echo $ready ? 'is-ready' : 'is-blocked'; ?>">
					<?php if ( $ready ) : ?>
						<span><?php esc_html_e( 'Ready to import', 'movies-wp' ); ?></span>
					<?php else : ?>
						<span><?php esc_html_e( 'Import blocked', 'movies-wp' ); ?></span>
					<?php endif; ?>
				</div>
			</div>
		</div>

		<?php if ( ! empty( $warnings ) ) : ?>
			<div class="movies-wp-panel movies-wp-panel-warning">
				<h2><?php esc_html_e( 'Warnings', 'movies-wp' ); ?></h2>
				<ul>
					<?php foreach ( $warnings as $warning ) : ?>
						<li><?php echo esc_html( Movies_WP_Series_Admin::issue_message( $warning ) ); ?></li>
					<?php endforeach; ?>
				</ul>
			</div>
		<?php endif; ?>

		<?php if ( ! empty( $errors ) ) : ?>
			<div class="movies-wp-panel movies-wp-panel-error">
				<h2><?php esc_html_e( 'Errors', 'movies-wp' ); ?></h2>
				<ul>
					<?php foreach ( $errors as $error ) : ?>
						<li><?php echo esc_html( Movies_WP_Series_Admin::issue_message( $error ) ); ?></li>
					<?php endforeach; ?>
				</ul>
			</div>
		<?php endif; ?>

		<div class="movies-wp-panel">
			<h2><?php esc_html_e( 'Season and episode plan', 'movies-wp' ); ?></h2>
			<table class="widefat striped movies-wp-files">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Season', 'movies-wp' ); ?></th>
						<th><?php esc_html_e( 'Action', 'movies-wp' ); ?></th>
						<th><?php esc_html_e( 'Episodes', 'movies-wp' ); ?></th>
						<th><?php esc_html_e( 'Existing episode IDs preserved', 'movies-wp' ); ?></th>
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
							<td>
								<code dir="ltr">
									<?php echo esc_html( implode( ', ', array_map( 'intval', is_array( $season_plan['existing_episode_ids'] ?? null ) ? $season_plan['existing_episode_ids'] : array() ) ) ); ?>
								</code>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		</div>

		<div class="movies-wp-panel">
			<h2><?php esc_html_e( 'Episode media matches', 'movies-wp' ); ?></h2>
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
							<td><code dir="ltr"><?php echo esc_html( (string) ( $episode_match['status'] ?? '' ) ); ?></code></td>
							<td><?php echo (int) ( $episode_match['source_count'] ?? 0 ); ?></td>
							<td><?php echo (int) ( $episode_match['subtitle_count'] ?? 0 ); ?></td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		</div>

		<?php if ( $ready ) : ?>
			<div class="movies-wp-panel movies-wp-panel-warning">
				<h2><?php esc_html_e( 'Confirm Series import', 'movies-wp' ); ?></h2>
				<p><?php esc_html_e( 'The server will rebuild and validate the plan immediately before writing to Streamit. Browser plan data is never trusted.', 'movies-wp' ); ?></p>
				<form method="post" class="movies-wp-import-form">
					<?php wp_nonce_field( Movies_WP_Series_Admin::IMPORT_NONCE ); ?>
					<input type="hidden" name="<?php echo esc_attr( Movies_WP_Series_Admin::ACTION_FIELD ); ?>" value="<?php echo esc_attr( Movies_WP_Series_Admin::IMPORT_ACTION ); ?>">
					<input type="hidden" name="tmdb_id" value="<?php echo esc_attr( (string) $values['tmdb_id'] ); ?>">
					<input type="hidden" name="title" value="<?php echo esc_attr( (string) $values['title'] ); ?>">
					<input type="hidden" name="summary" value="<?php echo esc_attr( (string) $values['summary'] ); ?>">
					<input type="hidden" name="series_directory" value="<?php echo esc_attr( (string) $values['series_directory'] ); ?>">
					<label>
						<input type="checkbox" name="confirm_import" value="1" required>
						<?php esc_html_e( 'I reviewed this plan and approve the Series metadata and media operations.', 'movies-wp' ); ?>
					</label>
					<?php submit_button( __( 'Import Series & Media', 'movies-wp' ), 'primary', 'submit', false ); ?>
				</form>
			</div>
		<?php endif; ?>
	<?php endif; ?>
</div>
