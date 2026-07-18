<?php
/**
 * Movie play button — always shows "شروع تماشا"; locked plan opens subscribe modal.
 *
 * @package streamit-child
 */

defined( 'ABSPATH' ) || exit;

$post_id   = (int) $st_data->get_id();
$post_type = $st_data->get_post_type();
$user_id   = get_current_user_id();

$access_type = $st_data->get_meta( '_access_type' ) ?? '';
$pmp_levels  = $st_data->get_meta( '_pmp_level' ) ?? array();
if ( ! $access_type && ! empty( $pmp_levels ) ) {
	$access_type = 'plan';
}

$has_access = function_exists( 'streamit_user_has_stream_access' )
	? streamit_user_has_stream_access( $post_id, $post_type, $user_id )
	: true;

$play_label = esc_html__( 'شروع تماشا', 'streamit' );
$player_url = streamit_get_permalink( $post_type, $st_data->get_post_name() . '/player' );
?>
<div class="play-button-wrapper d-flex align-items-center gap-md-4 gap-3 flex-wrap">
	<?php if ( $has_access || empty( $access_type ) || 'free' === $access_type ) : ?>
		<a class="btn btn-primary" href="<?php echo esc_url( $player_url ); ?>">
			<span class="d-flex align-items-center justify-content-center gap-2">
				<span><?php echo st_get_icon( 'play' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
				<span><?php echo esc_html( $play_label ); ?></span>
			</span>
		</a>
	<?php elseif ( 'ppv' === $access_type ) : ?>
		<a class="btn btn-primary" href="#" data-bs-toggle="modal" data-bs-target="#PpvSubscriptionDataModal">
			<span class="d-flex align-items-center justify-content-center gap-2">
				<span><?php echo st_get_icon( 'play' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
				<span><?php echo esc_html( $play_label ); ?></span>
			</span>
		</a>
	<?php else : ?>
		<?php streamit_child_render_subscribe_required_modal( $st_data, $post_type, 'play' ); ?>
		<button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#subscribeRequiredModal">
			<span class="d-flex align-items-center justify-content-center gap-2">
				<span><?php echo st_get_icon( 'play' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
				<span><?php echo esc_html( $play_label ); ?></span>
			</span>
		</button>
	<?php endif; ?>
</div>
