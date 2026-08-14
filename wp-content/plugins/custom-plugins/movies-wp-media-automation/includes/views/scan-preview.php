<?php
/**
 * Scan & Preview admin view.
 *
 * @var array{tmdb_id: int|string, title: string, summary: string, media_directory: string} $values
 * @var array<string, mixed>|null $preview
 * @var array<string, mixed>|null $plan
 * @var array{type: string, message: string, details?: array}|null $notice
 * @var array<string, mixed>|null $import_result
 */

defined( 'ABSPATH' ) || exit;

$tmdb    = ( is_array( $preview ) && isset( $preview['tmdb'] ) && is_array( $preview['tmdb'] ) ) ? $preview['tmdb'] : array();
$media   = ( is_array( $preview ) && isset( $preview['media'] ) && is_array( $preview['media'] ) ) ? $preview['media'] : array();
$files   = ( isset( $media['files'] ) && is_array( $media['files'] ) ) ? $media['files'] : array();
$errors  = ( is_array( $preview ) && ! empty( $preview['validation']['errors'] ) && is_array( $preview['validation']['errors'] ) )
	? $preview['validation']['errors']
	: array();

/*
 * Single UI warning source:
 * Prefer Import Plan warnings (already includes Preview aggregation + association).
 * Do not merge plan + preview (that duplicated every warning).
 */
if ( is_array( $plan ) && isset( $plan['warnings'] ) && is_array( $plan['warnings'] ) ) {
	$warns = $plan['warnings'];
} elseif ( is_array( $preview ) && ! empty( $preview['validation']['warnings'] ) && is_array( $preview['validation']['warnings'] ) ) {
	$warns = $preview['validation']['warnings'];
} else {
	$warns = array();
}

// Import button uses Import Plan readiness only — not browser/preview heuristics.
$plan_ready = is_array( $plan ) && ! empty( $plan['ready_to_import'] );
$ready      = $plan_ready;

if ( is_array( $plan ) && ! empty( $plan['errors'] ) && is_array( $plan['errors'] ) ) {
	// Prefer plan errors when present; avoid doubling Preview errors.
	$errors = $plan['errors'];
}

$videos    = array();
$subtitles = array();
foreach ( $files as $file ) {
	if ( ! is_array( $file ) ) {
		continue;
	}
	if ( ( $file['kind'] ?? '' ) === 'video' ) {
		$videos[] = $file;
	} elseif ( ( $file['kind'] ?? '' ) === 'subtitle' ) {
		$subtitles[] = $file;
	}
}

$import_details = ( is_array( $notice ) && isset( $notice['details']['import_result'] ) && is_array( $notice['details']['import_result'] ) )
	? $notice['details']['import_result']
	: ( is_array( $import_result ) ? $import_result : null );
?>
<div class="wrap movies-wp-scan-preview">
	<h1><?php esc_html_e( 'Movie Import Automation', 'movies-wp' ); ?></h1>
	<p class="description">
		<?php esc_html_e( 'Scan TMDb and the media directory, then import only after explicit confirmation. Subtitle rows store relative media paths; signed URLs are minted at render time.', 'movies-wp' ); ?>
	</p>

	<?php if ( is_array( $notice ) ) : ?>
		<div class="notice notice-<?php echo esc_attr( $notice['type'] ); ?> is-dismissible">
			<p><?php echo esc_html( $notice['message'] ); ?></p>
		</div>
	<?php endif; ?>

	<?php if ( is_array( $import_details ) ) : ?>
		<div class="movies-wp-panel movies-wp-import-result <?php echo ! empty( $import_details['ok'] ) ? 'movies-wp-panel-success' : ( ! empty( $import_details['partial'] ) ? 'movies-wp-panel-warning' : 'movies-wp-panel-error' ); ?>">
			<h2>
				<?php
				if ( ! empty( $import_details['ok'] ) ) {
					echo esc_html( (string) $import_details['message'] );
				} elseif ( ! empty( $import_details['partial'] ) ) {
					esc_html_e( 'Import partially completed.', 'movies-wp' );
				} else {
					esc_html_e( 'Import failed.', 'movies-wp' );
				}
				?>
			</h2>
			<ul class="movies-wp-import-result-list">
				<?php if ( ! empty( $import_details['movie_id'] ) ) : ?>
					<li>
						<?php
						printf(
							/* translators: %d: Streamit movie ID */
							esc_html__( 'Movie ID: %d', 'movies-wp' ),
							(int) $import_details['movie_id']
						);
						?>
					</li>
				<?php endif; ?>
				<?php if ( ! empty( $import_details['identity_action'] ) ) : ?>
					<li>
						<?php
						printf(
							/* translators: %s: create|update */
							esc_html__( 'Identity action: %s', 'movies-wp' ),
							esc_html( (string) $import_details['identity_action'] )
						);
						?>
					</li>
				<?php endif; ?>
				<?php if ( ! empty( $import_details['media_directory'] ) ) : ?>
					<li>
						<?php esc_html_e( 'Media directory:', 'movies-wp' ); ?>
						<code><?php echo esc_html( (string) $import_details['media_directory'] ); ?></code>
					</li>
				<?php endif; ?>
				<?php
				$stats = isset( $import_details['source_stats'] ) && is_array( $import_details['source_stats'] )
					? $import_details['source_stats']
					: array();
				?>
				<li>
					<?php
					printf(
						/* translators: 1: added count 2: updated count 3: kept count */
						esc_html__( 'Sources — added: %1$d, updated: %2$d, kept existing: %3$d', 'movies-wp' ),
						isset( $stats['added'] ) ? (int) $stats['added'] : 0,
						isset( $stats['updated'] ) ? (int) $stats['updated'] : 0,
						isset( $stats['kept'] ) ? (int) $stats['kept'] : 0
					);
					?>
				</li>
				<?php
				$sub_stats = isset( $import_details['subtitle_stats'] ) && is_array( $import_details['subtitle_stats'] )
					? $import_details['subtitle_stats']
					: array();
				$subtitles_completed = ! empty( $import_details['completed'] ) && is_array( $import_details['completed'] )
					&& in_array( 'subtitles', $import_details['completed'], true );
				$subtitles_failed = ( $import_details['failed_step'] ?? null ) === 'subtitles';
				?>
				<li>
					<?php if ( $subtitles_failed ) : ?>
						<strong><?php esc_html_e( 'Subtitles import failed.', 'movies-wp' ); ?></strong>
					<?php elseif ( $subtitles_completed ) : ?>
						<?php
						printf(
							/* translators: 1: added count 2: updated count 3: kept count */
							esc_html__( 'Subtitles — added: %1$d, updated: %2$d, kept existing: %3$d (relative paths; signed at render time).', 'movies-wp' ),
							isset( $sub_stats['added'] ) ? (int) $sub_stats['added'] : 0,
							isset( $sub_stats['updated'] ) ? (int) $sub_stats['updated'] : 0,
							isset( $sub_stats['kept'] ) ? (int) $sub_stats['kept'] : 0
						);
						?>
					<?php else : ?>
						<?php esc_html_e( 'Subtitles were not imported in this run.', 'movies-wp' ); ?>
					<?php endif; ?>
				</li>
				<?php if ( ! empty( $import_details['completed'] ) && is_array( $import_details['completed'] ) ) : ?>
					<li>
						<?php esc_html_e( 'Completed steps:', 'movies-wp' ); ?>
						<code><?php echo esc_html( implode( ', ', array_map( 'strval', $import_details['completed'] ) ) ); ?></code>
					</li>
				<?php endif; ?>
				<?php if ( ! empty( $import_details['failed_step'] ) ) : ?>
					<li>
						<?php esc_html_e( 'Failed step:', 'movies-wp' ); ?>
						<code><?php echo esc_html( (string) $import_details['failed_step'] ); ?></code>
					</li>
				<?php endif; ?>
				<?php if ( ! empty( $import_details['error']['code'] ) || ! empty( $import_details['error']['message'] ) ) : ?>
					<li>
						<?php esc_html_e( 'Error:', 'movies-wp' ); ?>
						<code><?php echo esc_html( (string) ( $import_details['error']['code'] ?? '' ) ); ?></code>
						<?php echo esc_html( Movies_WP_Media_Import_Service::safe_text( (string) ( $import_details['error']['message'] ?? '' ) ) ); ?>
					</li>
				<?php endif; ?>
			</ul>
			<?php if ( ! empty( $import_details['partial'] ) ) : ?>
				<p class="description">
					<?php esc_html_e( 'This import did not roll back earlier steps. Inspect the Streamit movie before retrying. Do not import again until you understand the current state.', 'movies-wp' ); ?>
				</p>
			<?php endif; ?>
			<?php if ( ! empty( $import_details['warnings'] ) && is_array( $import_details['warnings'] ) ) : ?>
				<ul>
					<?php foreach ( $import_details['warnings'] as $w ) : ?>
						<li><?php echo esc_html( is_array( $w ) ? (string) ( $w['message'] ?? '' ) : (string) $w ); ?></li>
					<?php endforeach; ?>
				</ul>
			<?php endif; ?>
		</div>
	<?php endif; ?>

	<form method="post" action="">
		<?php wp_nonce_field( Movies_WP_Media_Admin::NONCE ); ?>
		<input type="hidden" name="movies_wp_media_action" value="<?php echo esc_attr( Movies_WP_Media_Admin::ACTION ); ?>" />

		<table class="form-table" role="presentation">
			<tr>
				<th scope="row">
					<label for="movies-wp-tmdb-id"><?php esc_html_e( 'TMDb Movie ID', 'movies-wp' ); ?></label>
				</th>
				<td>
					<input type="number" min="1" step="1" class="regular-text" id="movies-wp-tmdb-id" name="tmdb_id" value="<?php echo esc_attr( (string) $values['tmdb_id'] ); ?>" required />
				</td>
			</tr>
			<tr>
				<th scope="row">
					<label for="movies-wp-title"><?php esc_html_e( 'Persian / Local Title', 'movies-wp' ); ?></label>
				</th>
				<td>
					<input type="text" class="regular-text" id="movies-wp-title" name="title" dir="auto" value="<?php echo esc_attr( $values['title'] ); ?>" required />
				</td>
			</tr>
			<tr>
				<th scope="row">
					<label for="movies-wp-summary"><?php esc_html_e( 'Summary', 'movies-wp' ); ?></label>
				</th>
				<td>
					<textarea class="large-text" rows="5" id="movies-wp-summary" name="summary" dir="auto"><?php echo esc_textarea( $values['summary'] ); ?></textarea>
					<p class="description"><?php esc_html_e( 'Optional. Kept separate from the TMDb overview.', 'movies-wp' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row">
					<label for="movies-wp-media-directory"><?php esc_html_e( 'Media Directory', 'movies-wp' ); ?></label>
				</th>
				<td>
					<input type="text" class="large-text code" id="movies-wp-media-directory" name="media_directory" value="<?php echo esc_attr( $values['media_directory'] ); ?>" placeholder="Movie/Korea/2016/Bounty.Hunters" required />
					<p class="description"><?php esc_html_e( 'Relative path under Movie/. Directory browser will be added later.', 'movies-wp' ); ?></p>
				</td>
			</tr>
		</table>

		<?php submit_button( __( 'Scan & Preview', 'movies-wp' ), 'primary', 'submit', false ); ?>
	</form>

	<?php if ( is_array( $preview ) ) : ?>
		<hr />

		<div class="movies-wp-import-status <?php echo $plan_ready ? 'is-ready' : 'is-blocked'; ?>">
			<strong><?php esc_html_e( 'Import status:', 'movies-wp' ); ?></strong>
			<?php if ( $plan_ready ) : ?>
				<span><?php esc_html_e( 'Ready to import', 'movies-wp' ); ?></span>
			<?php else : ?>
				<span><?php esc_html_e( 'Cannot import yet', 'movies-wp' ); ?></span>
			<?php endif; ?>
			<p class="description">
				<?php esc_html_e( 'Import uses a freshly rebuilt Import Plan on the server. The browser never submits source rows or plan decisions.', 'movies-wp' ); ?>
			</p>

			<?php if ( $plan_ready ) : ?>
				<form method="post" action="" class="movies-wp-import-form">
					<?php wp_nonce_field( Movies_WP_Media_Admin::IMPORT_NONCE ); ?>
					<input type="hidden" name="movies_wp_media_action" value="<?php echo esc_attr( Movies_WP_Media_Admin::IMPORT_ACTION ); ?>" />
					<input type="hidden" name="tmdb_id" value="<?php echo esc_attr( (string) $values['tmdb_id'] ); ?>" />
					<input type="hidden" name="title" value="<?php echo esc_attr( $values['title'] ); ?>" />
					<textarea name="summary" class="screen-reader-text" tabindex="-1" aria-hidden="true"><?php echo esc_textarea( $values['summary'] ); ?></textarea>
					<input type="hidden" name="media_directory" value="<?php echo esc_attr( $values['media_directory'] ); ?>" />

					<p>
						<label for="movies-wp-confirm-import">
							<input type="checkbox" name="confirm_import" id="movies-wp-confirm-import" value="1" required />
							<?php esc_html_e( 'I understand this will create/update the Streamit movie and add the scanned media sources.', 'movies-wp' ); ?>
						</label>
					</p>
					<?php submit_button( __( 'Import Movie', 'movies-wp' ), 'primary', 'movies_wp_import_submit', false ); ?>
				</form>
			<?php else : ?>
				<button type="button" class="button button-primary" disabled>
					<?php esc_html_e( 'Import Movie', 'movies-wp' ); ?>
				</button>
			<?php endif; ?>
		</div>

		<?php if ( $errors ) : ?>
			<div class="movies-wp-panel movies-wp-panel-error">
				<h2><?php esc_html_e( 'Errors', 'movies-wp' ); ?></h2>
				<ul>
					<?php foreach ( $errors as $issue ) : ?>
						<li>
							<?php echo esc_html( $issue['message'] ?? '' ); ?>
							<?php if ( ! empty( $issue['file'] ) ) : ?>
								<code><?php echo esc_html( (string) $issue['file'] ); ?></code>
							<?php endif; ?>
						</li>
					<?php endforeach; ?>
				</ul>
			</div>
		<?php endif; ?>

		<?php if ( $warns ) : ?>
			<div class="movies-wp-panel movies-wp-panel-warning">
				<h2><?php esc_html_e( 'Warnings', 'movies-wp' ); ?></h2>
				<ul>
					<?php foreach ( $warns as $issue ) : ?>
						<li>
							<?php echo esc_html( $issue['message'] ?? '' ); ?>
							<?php if ( ! empty( $issue['file'] ) ) : ?>
								<code><?php echo esc_html( (string) $issue['file'] ); ?></code>
							<?php endif; ?>
						</li>
					<?php endforeach; ?>
				</ul>
			</div>
		<?php endif; ?>

		<div class="movies-wp-preview-grid">
			<section class="movies-wp-panel">
				<h2><?php esc_html_e( 'Movie information', 'movies-wp' ); ?></h2>

				<?php if ( ! empty( $tmdb['poster_url'] ) || ! empty( $tmdb['backdrop_url'] ) ) : ?>
					<div class="movies-wp-preview-images">
						<?php if ( ! empty( $tmdb['poster_url'] ) ) : ?>
							<img src="<?php echo esc_url( $tmdb['poster_url'] ); ?>" alt="" class="movies-wp-poster" />
						<?php endif; ?>
						<?php if ( ! empty( $tmdb['backdrop_url'] ) ) : ?>
							<img src="<?php echo esc_url( $tmdb['backdrop_url'] ); ?>" alt="" class="movies-wp-backdrop" />
						<?php endif; ?>
					</div>
				<?php endif; ?>

				<table class="widefat striped">
					<tbody>
						<tr>
							<th><?php esc_html_e( 'Admin title', 'movies-wp' ); ?></th>
							<td dir="auto"><?php echo esc_html( (string) ( $preview['input']['title'] ?? $values['title'] ) ); ?></td>
						</tr>
						<tr>
							<th><?php esc_html_e( 'Admin summary', 'movies-wp' ); ?></th>
							<td dir="auto"><?php echo esc_html( (string) ( $preview['input']['summary'] ?? $values['summary'] ) ); ?></td>
						</tr>
						<tr>
							<th><?php esc_html_e( 'TMDb ID', 'movies-wp' ); ?></th>
							<td><?php echo esc_html( (string) ( $tmdb['id'] ?? '' ) ); ?></td>
						</tr>
						<tr>
							<th><?php esc_html_e( 'TMDb title', 'movies-wp' ); ?></th>
							<td><?php echo esc_html( (string) ( $tmdb['title'] ?? '' ) ); ?></td>
						</tr>
						<tr>
							<th><?php esc_html_e( 'Original title', 'movies-wp' ); ?></th>
							<td><?php echo esc_html( (string) ( $tmdb['original_title'] ?? '' ) ); ?></td>
						</tr>
						<tr>
							<th><?php esc_html_e( 'Release year', 'movies-wp' ); ?></th>
							<td><?php echo esc_html( Movies_WP_Media_Admin::dash( $tmdb['year'] ?? null ) ); ?></td>
						</tr>
						<tr>
							<th><?php esc_html_e( 'Runtime', 'movies-wp' ); ?></th>
							<td>
								<?php
								$runtime = isset( $tmdb['runtime'] ) ? (int) $tmdb['runtime'] : 0;
								echo $runtime > 0
									? esc_html( sprintf( /* translators: %d: minutes */ __( '%d minutes', 'movies-wp' ), $runtime ) )
									: '—';
								?>
							</td>
						</tr>
						<tr>
							<th><?php esc_html_e( 'Original language', 'movies-wp' ); ?></th>
							<td><?php echo esc_html( Movies_WP_Media_Admin::dash( $tmdb['original_language'] ?? null ) ); ?></td>
						</tr>
						<tr>
							<th><?php esc_html_e( 'Genres', 'movies-wp' ); ?></th>
							<td>
								<?php
								$genre_names = array();
								if ( ! empty( $tmdb['genres'] ) && is_array( $tmdb['genres'] ) ) {
									foreach ( $tmdb['genres'] as $genre ) {
										if ( is_array( $genre ) && ! empty( $genre['name'] ) ) {
											$genre_names[] = (string) $genre['name'];
										}
									}
								}
								echo $genre_names ? esc_html( implode( ', ', $genre_names ) ) : '—';
								?>
							</td>
						</tr>
						<tr>
							<th><?php esc_html_e( 'Countries', 'movies-wp' ); ?></th>
							<td>
								<?php
								$country_names = array();
								if ( ! empty( $tmdb['countries'] ) && is_array( $tmdb['countries'] ) ) {
									foreach ( $tmdb['countries'] as $country ) {
										if ( is_array( $country ) && ! empty( $country['name'] ) ) {
											$country_names[] = (string) $country['name'];
										}
									}
								}
								echo $country_names ? esc_html( implode( ', ', $country_names ) ) : '—';
								?>
							</td>
						</tr>
					</tbody>
				</table>
			</section>

			<section class="movies-wp-panel">
				<h2><?php esc_html_e( 'Media directory', 'movies-wp' ); ?></h2>
				<table class="widefat striped">
					<tbody>
						<tr>
							<th><?php esc_html_e( 'Directory', 'movies-wp' ); ?></th>
							<td><code><?php echo esc_html( (string) ( $media['directory'] ?? '' ) ); ?></code></td>
						</tr>
						<tr>
							<th><?php esc_html_e( 'Country', 'movies-wp' ); ?></th>
							<td><?php echo esc_html( Movies_WP_Media_Admin::dash( $media['country'] ?? null ) ); ?></td>
						</tr>
						<tr>
							<th><?php esc_html_e( 'Directory year', 'movies-wp' ); ?></th>
							<td><?php echo esc_html( Movies_WP_Media_Admin::dash( $media['year'] ?? null ) ); ?></td>
						</tr>
						<tr>
							<th><?php esc_html_e( 'Directory name', 'movies-wp' ); ?></th>
							<td><?php echo esc_html( Movies_WP_Media_Admin::dash( $media['movie_name'] ?? null ) ); ?></td>
						</tr>
					</tbody>
				</table>
			</section>
		</div>

		<section class="movies-wp-panel">
			<h2><?php esc_html_e( 'Video files', 'movies-wp' ); ?></h2>
			<?php if ( ! $videos ) : ?>
				<p><?php esc_html_e( 'No video files were found in this directory.', 'movies-wp' ); ?></p>
			<?php else : ?>
				<table class="widefat striped movies-wp-files">
					<thead>
						<tr>
							<th><?php esc_html_e( 'File', 'movies-wp' ); ?></th>
							<th><?php esc_html_e( 'Quality', 'movies-wp' ); ?></th>
							<th><?php esc_html_e( 'Source', 'movies-wp' ); ?></th>
							<th><?php esc_html_e( 'Provider', 'movies-wp' ); ?></th>
							<th><?php esc_html_e( 'Audio', 'movies-wp' ); ?></th>
							<th><?php esc_html_e( 'Codec', 'movies-wp' ); ?></th>
							<th><?php esc_html_e( 'Encoder', 'movies-wp' ); ?></th>
							<th><?php esc_html_e( 'Release group', 'movies-wp' ); ?></th>
							<th><?php esc_html_e( 'Size', 'movies-wp' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $videos as $file ) : ?>
							<?php
							$audio = ! empty( $file['audio_label'] ) ? (string) $file['audio_label'] : __( 'Unknown', 'movies-wp' );
							$vc    = isset( $file['video_codec'] ) ? trim( (string) $file['video_codec'] ) : '';
							$ac    = isset( $file['audio_codec'] ) ? trim( (string) $file['audio_codec'] ) : '';
							$codec = trim( implode( ' / ', array_filter( array( $vc, $ac ) ) ) );
							?>
							<tr>
								<td><code><?php echo esc_html( (string) ( $file['name'] ?? '' ) ); ?></code></td>
								<td><?php echo esc_html( Movies_WP_Media_Admin::dash( $file['quality'] ?? null ) ); ?></td>
								<td><?php echo esc_html( Movies_WP_Media_Admin::dash( $file['source_type'] ?? null ) ); ?></td>
								<td><?php echo esc_html( Movies_WP_Media_Admin::dash( $file['provider'] ?? null ) ); ?></td>
								<td><?php echo esc_html( $audio ); ?></td>
								<td><?php echo esc_html( '' !== $codec ? $codec : '—' ); ?></td>
								<td><?php echo esc_html( Movies_WP_Media_Admin::dash( $file['encoder'] ?? null ) ); ?></td>
								<td><?php echo esc_html( Movies_WP_Media_Admin::dash( $file['release_group'] ?? null ) ); ?></td>
								<td><?php echo esc_html( Movies_WP_Media_Admin::dash( $file['size_label'] ?? null ) ); ?></td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			<?php endif; ?>
		</section>

		<section class="movies-wp-panel">
			<h2><?php esc_html_e( 'Subtitles', 'movies-wp' ); ?></h2>
			<?php if ( ! $subtitles ) : ?>
				<p><?php esc_html_e( 'No subtitle files were found.', 'movies-wp' ); ?></p>
			<?php else : ?>
				<table class="widefat striped movies-wp-files">
					<thead>
						<tr>
							<th><?php esc_html_e( 'File', 'movies-wp' ); ?></th>
							<th><?php esc_html_e( 'Language', 'movies-wp' ); ?></th>
							<th><?php esc_html_e( 'Format', 'movies-wp' ); ?></th>
							<th><?php esc_html_e( 'Quality', 'movies-wp' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $subtitles as $file ) : ?>
							<tr>
								<td><code><?php echo esc_html( (string) ( $file['name'] ?? '' ) ); ?></code></td>
								<td><?php echo esc_html( Movies_WP_Media_Admin::language_label( $file['subtitle_lang'] ?? null ) ); ?></td>
								<td><?php echo esc_html( strtoupper( Movies_WP_Media_Admin::dash( $file['extension'] ?? $file['format'] ?? null ) ) ); ?></td>
								<td><?php echo esc_html( Movies_WP_Media_Admin::dash( $file['quality'] ?? null ) ); ?></td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			<?php endif; ?>
		</section>
	<?php endif; ?>
</div>
