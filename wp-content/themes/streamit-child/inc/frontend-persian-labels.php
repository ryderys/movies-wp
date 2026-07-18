<?php
/**
 * Persian labels for Streamit frontend strings.
 *
 * @package streamit-child
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * English → Persian map for streamit frontend strings.
 *
 * @return array<string, string>
 */
function streamit_child_get_frontend_persian_labels() {
	return array(
		'View All'                           => 'نمایش همه',
		'Search'                             => 'جستجو',
		'Close'                              => 'بستن',
		'Search...'                          => 'جستجو...',
		'Search....'                         => 'جستجو...',
		'Sorry, Could not Find Your Search!' => 'متأسفیم، نتیجه‌ای یافت نشد!',
		'Try something new'                  => 'عبارت دیگری امتحان کنید',
		'No Data Found'                      => 'نتیجه‌ای یافت نشد',
		'Movies'                             => 'فیلم‌ها',
		'videos'                             => 'ویدیوها',
		'TvShows'                            => 'سریال‌ها',
		'Episodes'                           => 'قسمت‌ها',
		'Persons'                            => 'بازیگران',
		'Loading...'                         => 'در حال بارگذاری...',
		'All'                                => 'همه',
		'Movie'                              => 'فیلم',
		'Tvshow'                             => 'سریال',
		'Video'                              => 'ویدیو',
		'Person'                             => 'بازیگر',
		'Episode'                            => 'قسمت',
		'Person History'                     => 'آثار',
		'Awards :'                           => 'جوایز:',
		'TV Shows'                           => 'سریال‌ها',
		'No TV shows or Movies found.'       => 'فیلم یا سریالی یافت نشد.',
		'No movies or TV shows available in this category.' => 'در این دسته فیلم یا سریالی موجود نیست.',
	);
}

/**
 * Translate streamit strings on the public site.
 *
 * @param string $translated Translated text.
 * @param string $text       Original text.
 * @param string $domain     Text domain.
 * @return string
 */
function streamit_child_translate_frontend_labels( $translated, $text, $domain ) {
	if ( 'streamit' !== $domain || is_admin() ) {
		return $translated;
	}

	static $map = null;
	if ( null === $map ) {
		$map = streamit_child_get_frontend_persian_labels();
	}

	return $map[ $text ] ?? $translated;
}
add_filter( 'gettext', 'streamit_child_translate_frontend_labels', 20, 3 );
