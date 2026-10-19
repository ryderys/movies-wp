<?php
/**
 * Telegram administrator page.
 *
 * @var array<string, mixed> $state
 *
 * @package movies-wp
 */

defined( 'ABSPATH' ) || exit;

$notice    = isset( $state['notice'] ) && is_array( $state['notice'] ) ? $state['notice'] : null;
$counts    = isset( $state['counts'] ) && is_array( $state['counts'] ) ? $state['counts'] : array();
$failures  = isset( $state['failures'] ) && is_array( $state['failures'] ) ? $state['failures'] : array();
$paused    = ! empty( $state['paused'] );
$configured = ! empty( $state['configured'] );
$proxy_configured = ! empty( $state['proxy_configured'] );
$statuses  = array( 'pending', 'claimed', 'retry', 'sent', 'failed', 'ambiguous' );

$checked = static function ( $on ) {
	return ! empty( $on ) ? ' checked="checked"' : '';
};
?>
<div class="wrap">
	<h1><?php echo esc_html__( 'Telegram', 'movies-wp' ); ?></h1>
	<?php if ( is_array( $notice ) ) : ?>
		<div class="notice notice-<?php echo 'success' === (string) ( $notice['type'] ?? '' ) ? 'success' : 'error'; ?>">
			<p><?php echo esc_html( (string) ( $notice['message'] ?? '' ) ); ?></p>
		</div>
	<?php endif; ?>

	<?php if ( $paused ) : ?>
		<div class="notice notice-warning">
			<p><?php echo esc_html__( 'Delivery is paused. Movie, series, and episode imports continue, and queued announcements wait.', 'movies-wp' ); ?></p>
		</div>
	<?php endif; ?>

	<h2><?php echo esc_html__( 'Delivery', 'movies-wp' ); ?></h2>
	<form method="post">
		<?php wp_nonce_field( Movies_WP_Telegram_Admin::NONCE ); ?>
		<input type="hidden" name="<?php echo esc_attr( Movies_WP_Telegram_Admin::ACTION_FIELD ); ?>" value="<?php echo esc_attr( $paused ? Movies_WP_Telegram_Admin::RESUME_ACTION : Movies_WP_Telegram_Admin::PAUSE_ACTION ); ?>" />
		<p>
			<button type="submit" class="button button-secondary">
				<?php echo esc_html( $paused ? __( 'Resume delivery', 'movies-wp' ) : __( 'Pause delivery', 'movies-wp' ) ); ?>
			</button>
		</p>
	</form>

	<h2><?php echo esc_html__( 'Publishing', 'movies-wp' ); ?></h2>
	<form method="post">
		<?php wp_nonce_field( Movies_WP_Telegram_Admin::NONCE ); ?>
		<input type="hidden" name="<?php echo esc_attr( Movies_WP_Telegram_Admin::ACTION_FIELD ); ?>" value="<?php echo esc_attr( Movies_WP_Telegram_Admin::SAVE_ACTION ); ?>" />
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><?php echo esc_html__( 'Credentials', 'movies-wp' ); ?></th>
				<td><?php echo esc_html( $configured ? __( 'Configured', 'movies-wp' ) : __( 'Not configured', 'movies-wp' ) ); ?></td>
			</tr>
			<tr>
				<th scope="row"><?php echo esc_html__( 'Egress proxy', 'movies-wp' ); ?></th>
				<td><?php echo esc_html( $proxy_configured ? __( 'Configured', 'movies-wp' ) : __( 'Not configured', 'movies-wp' ) ); ?></td>
			</tr>
			<tr>
				<th scope="row"><?php echo esc_html__( 'Global publishing', 'movies-wp' ); ?></th>
				<td><label><input type="checkbox" name="telegram_global" value="1"<?php echo $checked( $state['global_enabled'] ?? false ); ?> /> <?php echo esc_html__( 'Allow Telegram publishing', 'movies-wp' ); ?></label></td>
			</tr>
			<tr>
				<th scope="row"><?php echo esc_html__( 'Movies', 'movies-wp' ); ?></th>
				<td><label><input type="checkbox" name="telegram_movies" value="1"<?php echo $checked( $state['movie_enabled'] ?? false ); ?> /> <?php echo esc_html__( 'Announce movies', 'movies-wp' ); ?></label></td>
			</tr>
			<tr>
				<th scope="row"><?php echo esc_html__( 'Series', 'movies-wp' ); ?></th>
				<td><label><input type="checkbox" name="telegram_series" value="1"<?php echo $checked( $state['series_enabled'] ?? false ); ?> /> <?php echo esc_html__( 'Announce series', 'movies-wp' ); ?></label></td>
			</tr>
			<tr>
				<th scope="row"><?php echo esc_html__( 'Episodes', 'movies-wp' ); ?></th>
				<td><label><input type="checkbox" name="telegram_episodes" value="1"<?php echo $checked( $state['episode_enabled'] ?? false ); ?> /> <?php echo esc_html__( 'Announce episodes', 'movies-wp' ); ?></label></td>
			</tr>
		</table>
		<?php submit_button( __( 'Save Telegram settings', 'movies-wp' ) ); ?>
	</form>

	<h2><?php echo esc_html__( 'Test message', 'movies-wp' ); ?></h2>
	<form method="post">
		<?php wp_nonce_field( Movies_WP_Telegram_Admin::NONCE ); ?>
		<input type="hidden" name="<?php echo esc_attr( Movies_WP_Telegram_Admin::ACTION_FIELD ); ?>" value="<?php echo esc_attr( Movies_WP_Telegram_Admin::TEST_ACTION ); ?>" />
		<p><?php echo esc_html__( 'Sends one labeled test message. It does not queue or publish a movie, series, or episode.', 'movies-wp' ); ?></p>
		<p><label><input type="checkbox" name="<?php echo esc_attr( Movies_WP_Telegram_Admin::CONFIRM_FIELD ); ?>" value="1" /> <?php echo esc_html__( 'I confirm this test send.', 'movies-wp' ); ?></label></p>
		<?php submit_button( __( 'Send test message', 'movies-wp' ), 'secondary', 'submit', false ); ?>
	</form>

	<h2><?php echo esc_html__( 'Queue', 'movies-wp' ); ?></h2>
	<ul>
		<?php foreach ( $statuses as $status ) : ?>
			<li><?php echo esc_html( $status ); ?>: <?php echo (int) ( $counts[ $status ] ?? 0 ); ?></li>
		<?php endforeach; ?>
	</ul>

	<h2><?php echo esc_html__( 'Recent failures', 'movies-wp' ); ?></h2>
	<?php if ( array() === $failures ) : ?>
		<p><?php echo esc_html__( 'No recent failures.', 'movies-wp' ); ?></p>
	<?php else : ?>
		<table class="widefat striped">
			<thead>
				<tr>
					<th><?php echo esc_html__( 'ID', 'movies-wp' ); ?></th>
					<th><?php echo esc_html__( 'Type', 'movies-wp' ); ?></th>
					<th><?php echo esc_html__( 'Post', 'movies-wp' ); ?></th>
					<th><?php echo esc_html__( 'Status', 'movies-wp' ); ?></th>
					<th><?php echo esc_html__( 'Error', 'movies-wp' ); ?></th>
					<th><?php echo esc_html__( 'Action', 'movies-wp' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $failures as $failure ) : ?>
					<?php if ( ! is_array( $failure ) ) { continue; } ?>
					<tr>
						<td><?php echo (int) ( $failure['id'] ?? 0 ); ?></td>
						<td><?php echo esc_html( (string) ( $failure['content_type'] ?? '' ) ); ?></td>
						<td><?php echo (int) ( $failure['post_id'] ?? 0 ); ?></td>
						<td><?php echo esc_html( (string) ( $failure['status'] ?? '' ) ); ?></td>
						<td><?php echo esc_html( (string) ( $failure['last_error'] ?? '' ) ); ?></td>
						<td>
							<?php if ( 'failed' === (string) ( $failure['status'] ?? '' ) && (int) ( $failure['id'] ?? 0 ) > 0 ) : ?>
								<form method="post">
									<?php wp_nonce_field( Movies_WP_Telegram_Admin::NONCE ); ?>
									<input type="hidden" name="<?php echo esc_attr( Movies_WP_Telegram_Admin::ACTION_FIELD ); ?>" value="<?php echo esc_attr( Movies_WP_Telegram_Admin::RETRY_ACTION ); ?>" />
									<input type="hidden" name="queue_id" value="<?php echo (int) $failure['id']; ?>" />
									<button type="submit" class="button button-small"><?php echo esc_html__( 'Retry', 'movies-wp' ); ?></button>
								</form>
							<?php endif; ?>
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
	<?php endif; ?>
</div>
