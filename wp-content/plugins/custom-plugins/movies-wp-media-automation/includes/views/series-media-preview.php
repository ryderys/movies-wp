<?php
/**
 * Series media preview and import admin view.
 *
 * @var array{tvshow_id:int|string,expected_tmdb_id:int|string,series_directory:string} $values
 * @var array<string,mixed>|null $preview
 * @var array<string,mixed>|null $plan
 * @var array{type:string,message:string}|null $notice
 * @var array<string,mixed>|null $import_result
 */

defined( 'ABSPATH' ) || exit;

$errors = is_array( $plan ) && is_array( $plan['errors'] ?? null )
	? $plan['errors']
	: ( is_array( $preview['validation']['errors'] ?? null ) ? $preview['validation']['errors'] : array() );
$warnings = is_array( $plan ) && is_array( $plan['warnings'] ?? null )
	? $plan['warnings']
	: ( is_array( $preview['validation']['warnings'] ?? null ) ? $preview['validation']['warnings'] : array() );
$ready = is_array( $plan )
	&& true === ( $plan['ready_to_import'] ?? null )
	&& array() === $errors;
$media = is_array( $preview ) && is_array( $preview['media'] ?? null ) ? $preview['media'] : array();
$episodes = is_array( $preview ) && is_array( $preview['episodes'] ?? null ) ? $preview['episodes'] : array();
?>
<div class="wrap movies-wp-scan-preview movies-wp-series-media-preview">
	<h1><?php esc_html_e( 'Series Media Automation', 'movies-wp' ); ?></h1>
	<p class="description">
		<?php esc_html_e( 'Scan a Series filesystem directory, match discovered media to existing Streamit episodes, and import _sources/_subtitles only after explicit confirmation.', 'movies-wp' ); ?>
	</p>
	<p class="description">
		<?php esc_html_e( 'Importing _sources does not automatically enable multi-source episode playback in the current player.', 'movies-wp' ); ?>
	</p>

	<?php if ( is_array( $notice ) ) : ?>
		<div class="notice notice-<?php echo esc_attr( $notice['type'] ); ?> is-dismissible">
			<p><?php echo esc_html( $notice['message'] ); ?></p>
		</div>
	<?php endif; ?>

	<form method="post">
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><label for="tvshow_id"><?php esc_html_e( 'TV show ID', 'movies-wp' ); ?></label></th>
				<td><input name="tvshow_id" id="tvshow_id" type="number" min="1" class="regular-text" value="<?php echo esc_attr( (string) ( $values['tvshow_id'] ?? '' ) ); ?>" required></td>
			</tr>
			<tr>
				<th scope="row"><label for="expected_tmdb_id"><?php esc_html_e( 'Expected TMDb ID (optional)', 'movies-wp' ); ?></label></th>
				<td><input name="expected_tmdb_id" id="expected_tmdb_id" type="number" min="0" class="regular-text" value="<?php echo esc_attr( (string) ( $values['expected_tmdb_id'] ?? '' ) ); ?>"></td>
			</tr>
			<tr>
				<th scope="row"><label for="series_directory"><?php esc_html_e( 'Series directory', 'movies-wp' ); ?></label></th>
				<td>
					<input name="series_directory" id="series_directory" type="text" class="large-text code" dir="ltr" value="<?php echo esc_attr( (string) ( $values['series_directory'] ?? '' ) ); ?>" placeholder="series/korea/2024/Marry.My.Husband" required>
					<p class="description"><?php esc_html_e( 'Relative path under the media root. Example: series/korea/2024/Marry.My.Husband', 'movies-wp' ); ?></p>
				</td>
			</tr>
		</table>

		<?php wp_nonce_field( Movies_WP_Series_Media_Admin::PREVIEW_NONCE ); ?>
		<input type="hidden" name="<?php echo esc_attr( Movies_WP_Series_Media_Admin::ACTION_FIELD ); ?>" value="<?php echo esc_attr( Movies_WP_Series_Media_Admin::PREVIEW_ACTION ); ?>">
		<p class="submit">
			<button type="submit" class="button button-primary"><?php esc_html_e( 'Scan & Preview', 'movies-wp' ); ?></button>
		</p>
	</form>

	<?php if ( is_array( $preview ) ) : ?>
		<div class="movies-wp-panel">
			<h2><?php esc_html_e( 'Scan summary', 'movies-wp' ); ?></h2>
			<ul>
				<li><?php esc_html_e( 'Directory:', 'movies-wp' ); ?> <code dir="ltr"><?php echo esc_html( (string) ( $media['directory']['path'] ?? $values['series_directory'] ?? '' ) ); ?></code></li>
				<li><?php esc_html_e( 'Video files:', 'movies-wp' ); ?> <?php echo esc_html( (string) ( $media['stats']['video_count'] ?? 0 ) ); ?></li>
				<li><?php esc_html_e( 'Subtitle files:', 'movies-wp' ); ?> <?php echo esc_html( (string) ( $media['stats']['subtitle_count'] ?? 0 ) ); ?></li>
				<li><?php esc_html_e( 'Grouped episodes:', 'movies-wp' ); ?> <?php echo esc_html( (string) count( $episodes ) ); ?></li>
			</ul>
		</div>

		<?php if ( $episodes !== array() ) : ?>
			<div class="movies-wp-panel">
				<h2><?php esc_html_e( 'Episode matches', 'movies-wp' ); ?></h2>
				<table class="widefat striped">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Season', 'movies-wp' ); ?></th>
							<th><?php esc_html_e( 'Episode', 'movies-wp' ); ?></th>
							<th><?php esc_html_e( 'Status', 'movies-wp' ); ?></th>
							<th><?php esc_html_e( 'Sources', 'movies-wp' ); ?></th>
							<th><?php esc_html_e( 'Subtitles', 'movies-wp' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $episodes as $episode ) : ?>
							<tr>
								<td><?php echo esc_html( Movies_WP_Series_Media_Admin::dash( $episode['season_number'] ?? '' ) ); ?></td>
								<td><?php echo esc_html( Movies_WP_Series_Media_Admin::dash( $episode['episode_number'] ?? '' ) ); ?></td>
								<td><code dir="ltr"><?php echo esc_html( Movies_WP_Series_Media_Admin::dash( $episode['status'] ?? '' ) ); ?></code></td>
								<td><?php echo esc_html( (string) ( $episode['source_count'] ?? 0 ) ); ?></td>
								<td><?php echo esc_html( (string) ( $episode['subtitle_count'] ?? 0 ) ); ?></td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			</div>
		<?php endif; ?>

		<?php if ( $warnings !== array() ) : ?>
			<div class="movies-wp-panel movies-wp-panel-warning">
				<h2><?php esc_html_e( 'Warnings', 'movies-wp' ); ?></h2>
				<ul>
					<?php foreach ( $warnings as $warning ) : ?>
						<li>
							<?php if ( is_array( $warning ) && ! empty( $warning['code'] ) ) : ?>
								<code dir="ltr"><?php echo esc_html( (string) $warning['code'] ); ?></code>
							<?php endif; ?>
							<?php echo esc_html( Movies_WP_Series_Media_Admin::issue_message( $warning ) ); ?>
						</li>
					<?php endforeach; ?>
				</ul>
			</div>
		<?php endif; ?>

		<?php if ( $errors !== array() ) : ?>
			<div class="movies-wp-panel movies-wp-panel-error">
				<h2><?php esc_html_e( 'Errors', 'movies-wp' ); ?></h2>
				<ul>
					<?php foreach ( $errors as $error ) : ?>
						<li>
							<?php if ( is_array( $error ) && ! empty( $error['code'] ) ) : ?>
								<code dir="ltr"><?php echo esc_html( (string) $error['code'] ); ?></code>
							<?php endif; ?>
							<?php echo esc_html( Movies_WP_Series_Media_Admin::issue_message( $error ) ); ?>
						</li>
					<?php endforeach; ?>
				</ul>
			</div>
		<?php endif; ?>

		<?php if ( $ready ) : ?>
			<form method="post" class="movies-wp-import-form">
				<div class="movies-wp-panel">
					<h2><?php esc_html_e( 'Import', 'movies-wp' ); ?></h2>
					<p><?php esc_html_e( 'The server rebuilds the authoritative preview and import plan from the whitelisted inputs above. Browser-supplied source rows are ignored.', 'movies-wp' ); ?></p>
					<label>
						<input type="checkbox" name="confirm_import" value="1">
						<?php esc_html_e( 'I confirm this Series media import.', 'movies-wp' ); ?>
					</label>
				</div>
				<?php wp_nonce_field( Movies_WP_Series_Media_Admin::IMPORT_NONCE ); ?>
				<input type="hidden" name="tvshow_id" value="<?php echo esc_attr( (string) ( $values['tvshow_id'] ?? '' ) ); ?>">
				<input type="hidden" name="expected_tmdb_id" value="<?php echo esc_attr( (string) ( $values['expected_tmdb_id'] ?? '' ) ); ?>">
				<input type="hidden" name="series_directory" value="<?php echo esc_attr( (string) ( $values['series_directory'] ?? '' ) ); ?>">
				<input type="hidden" name="<?php echo esc_attr( Movies_WP_Series_Media_Admin::ACTION_FIELD ); ?>" value="<?php echo esc_attr( Movies_WP_Series_Media_Admin::IMPORT_ACTION ); ?>">
				<p class="submit">
					<button type="submit" class="button button-primary"><?php esc_html_e( 'Import Series Media', 'movies-wp' ); ?></button>
				</p>
			</form>
		<?php endif; ?>
	<?php endif; ?>

	<?php if ( is_array( $import_result ) ) : ?>
		<div class="movies-wp-panel movies-wp-import-result <?php echo ! empty( $import_result['ok'] ) ? 'movies-wp-panel-success' : ( ! empty( $import_result['partial'] ) ? 'movies-wp-panel-warning' : 'movies-wp-panel-error' ); ?>">
			<h2><?php esc_html_e( 'Series media import result', 'movies-wp' ); ?></h2>
			<ul>
				<li><?php esc_html_e( 'Completed episodes:', 'movies-wp' ); ?> <?php echo esc_html( (string) ( $import_result['completed'] ?? 0 ) ); ?></li>
			</ul>
			<?php if ( ! empty( $import_result['errors'] ) && is_array( $import_result['errors'] ) ) : ?>
				<h3><?php esc_html_e( 'Errors', 'movies-wp' ); ?></h3>
				<ul>
					<?php foreach ( $import_result['errors'] as $error ) : ?>
						<li>
							<?php if ( is_array( $error ) && ! empty( $error['code'] ) ) : ?>
								<code dir="ltr"><?php echo esc_html( (string) $error['code'] ); ?></code>
							<?php endif; ?>
							<?php echo esc_html( Movies_WP_Series_Media_Admin::issue_message( $error ) ); ?>
						</li>
					<?php endforeach; ?>
				</ul>
			<?php endif; ?>
		</div>
	<?php endif; ?>
</div>
