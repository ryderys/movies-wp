<?php
/**
 * TV show play button — always shows "شروع تماشا"; locked plan opens subscribe modal.
 *
 * @package streamit-child
 */

defined( 'ABSPATH' ) || exit;

$post      = isset( $st_data ) ? $st_data : null;
$post_type = 'tvshow';
$post_id   = $post ? (int) $post->get_id() : 0;
$user_id   = get_current_user_id();

$access_type = $post ? ( $post->get_meta( '_access_type' ) ?? '' ) : '';
$pmp_levels  = $post ? ( $post->get_meta( '_pmp_level' ) ?? array() ) : array();
if ( ! $access_type && ! empty( $pmp_levels ) ) {
	$access_type = 'plan';
}

$has_access = ( $post && function_exists( 'streamit_user_has_stream_access' ) )
	? streamit_user_has_stream_access( $post_id, $post_type, $user_id )
	: true;

$play_label    = esc_html__( 'شروع تماشا', 'streamit' );
$redirect_link = home_url( '/' );

if ( $post ) {
	$first_season  = $post->get_meta( '_seasons' )[0] ?? null;
	$first_episode = $first_season['episodes'][0] ?? null;
	if ( $first_episode ) {
		$episode = function_exists( 'streamit_get_episode' ) ? streamit_get_episode( (int) $first_episode ) : null;
		if ( ! empty( $episode ) && function_exists( 'streamit_get_permalink' ) ) {
			$redirect_link = streamit_get_permalink( $episode->get_post_type(), $episode->get_post_name() );
		}
	}
}
?>
<div class="play-button-wrapper">
	<?php if ( $has_access || empty( $access_type ) || 'free' === $access_type ) : ?>
		<a class="btn btn-primary" href="<?php echo esc_url( $redirect_link ); ?>">
			<span class="d-flex align-items-center justify-content-center gap-2">
				<?php echo st_get_icon( 'play' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				<span><?php echo esc_html( $play_label ); ?></span>
			</span>
		</a>
	<?php elseif ( 'ppv' === $access_type ) : ?>
		<a class="btn btn-primary" href="#" data-bs-toggle="modal" data-bs-target="#PpvSubscriptionDataModal">
			<span class="d-flex align-items-center justify-content-center gap-2">
				<?php echo st_get_icon( 'play' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				<span><?php echo esc_html( $play_label ); ?></span>
			</span>
		</a>
	<?php else : ?>
		<?php streamit_child_render_subscribe_required_modal( $post, $post_type, 'play' ); ?>
		<button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#subscribeRequiredModal">
			<span class="d-flex align-items-center justify-content-center gap-2">
				<?php echo st_get_icon( 'play' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				<span><?php echo esc_html( $play_label ); ?></span>
			</span>
		</button>
	<?php endif; ?>
</div>
