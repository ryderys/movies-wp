<?php
/**
 * Movie single trailer — public signed local Trailer/ paths + external embeds.
 *
 * @package streamit-child
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$thumbnail = ! empty( $st_data->get_meta( 'thumbnail_id' ) )
	? wp_get_attachment_image_url( $st_data->get_meta( 'thumbnail_id' ), 'full' )
	: streamit_placeholder_image();
?>

<div class="video-plus-image">
	<div class="video-banner-image-container">
		<img src="<?php echo esc_url( $thumbnail ); ?>" class="video-banner-image" alt="video-section-banner-image">
	</div>

	<?php
	$controllers               = streamit_media_player_controls();
	$controllers['autoplay']   = true;
	$controllers['muted']      = true;
	$controllers['fullscreen'] = array( 'enabled' => false );
	$controllers['tooltips']   = array(
		'controls' => false,
		'seek'     => false,
	);
	$controllers['loop']       = array( 'active' => true );

	$stored = ( method_exists( $st_data, 'get_meta' ) && null !== $st_data->get_meta( '_name_trailer_link' ) )
		? $st_data->get_meta( '_name_trailer_link' )
		: '';
	$st_trailer_details = function_exists( 'streamit_child_get_trailer_playback' )
		? streamit_child_get_trailer_playback( $stored )
		: ( function_exists( 'streamit_get_trailer_embed' ) ? streamit_get_trailer_embed( $stored ) : array() );

	$is_video = ! empty( $st_trailer_details['type'] ) && 'video' === $st_trailer_details['type'];
	$mime     = ! empty( $st_trailer_details['mime'] ) ? (string) $st_trailer_details['mime'] : 'video/mp4';

	if ( ! empty( $st_trailer_details['trailer_link'] ) ) :
		?>
		<div
			id="streamit-trailer-player-ctrl"
			class="streamit-trailer-player-ctrl"
			data-player_controls="<?php echo esc_attr( wp_json_encode( apply_filters( 'streamit_movie_trailer_player_controls', $controllers ) ) ); ?>"
		>
			<?php if ( $is_video ) : ?>
				<video class="streamit_trailer_player" autoplay muted playsinline loop preload="auto" webkit-playsinline>
					<source src="<?php echo esc_url( $st_trailer_details['trailer_link'] ); ?>" type="<?php echo esc_attr( $mime ); ?>">
				</video>
			<?php else : ?>
				<div class="streamit_trailer_player">
					<iframe
						id="trailerIframe"
						src="<?php echo esc_url( $st_trailer_details['trailer_link'] ); ?>"
						title="Video player"
						allowfullscreen
						allow="autoplay"
						loading="lazy"
					></iframe>
				</div>
			<?php endif; ?>
		</div>

		<button id="trailer-mute-toggle" class="streamit-trailer-mute-btn" style="display:none;">
			<i class="icon-volume-slash"></i>
		</button>

		<button id="trailer-fullscreen-toggle" class="streamit-trailer-fullscreen-btn" style="display:none;">
			<i class="icon-fullscreen"></i>
		</button>
	<?php endif; ?>
</div>
