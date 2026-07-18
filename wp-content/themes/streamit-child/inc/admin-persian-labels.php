<?php
/**
 * Persian labels for Streamit movie / TV show / episode admin edit screens.
 *
 * @package streamit-child
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Admin screens where field labels should be Persian.
 *
 * @return bool
 */
function streamit_child_is_streamit_content_admin() {
	if ( ! is_admin() ) {
		return false;
	}

	$page = isset( $_GET['page'] ) ? sanitize_text_field( wp_unslash( $_GET['page'] ) ) : '';

	return in_array(
		$page,
		array(
			'streamit-edit-movie',
			'streamit-add-movie',
			'streamit-edit-tvshow',
			'streamit-add-tvshow',
			'streamit-edit-tvshow-episode',
			'streamit-add-tvshow-episode',
		),
		true
	);
}

/**
 * English → Persian map for streamit-core admin strings.
 *
 * @return array<string, string>
 */
function streamit_child_get_admin_persian_labels() {
	return array(
		// Page titles & actions.
		'Edit Movie'                    => 'ویرایش فیلم',
		'Add New Movie'                 => 'افزودن فیلم جدید',
		'View Movie'                    => 'مشاهده فیلم',
		'Update Movie'                  => 'به‌روزرسانی فیلم',
		'Add Movie'                     => 'افزودن فیلم',
		'Edit TV Show'                  => 'ویرایش سریال',
		'Add New TV Show'               => 'افزودن سریال جدید',
		'View TV Show'                  => 'مشاهده سریال',
		'Update TV Show'                => 'به‌روزرسانی سریال',
		'Add TV Show'                   => 'افزودن سریال',
		'Edit Episode'                  => 'ویرایش قسمت',
		'Add New Episode'               => 'افزودن قسمت جدید',
		'View Episode'                  => 'مشاهده قسمت',
		'Update Episode'                => 'به‌روزرسانی قسمت',
		'Add Episode'                   => 'افزودن قسمت',

		// Tabs.
		'General'                       => 'عمومی',
		'Recommended Movies'            => 'فیلم‌های پیشنهادی',
		'Related Videos'                => 'ویدیوهای مرتبط',
		'Cast'                          => 'بازیگران',
		'Crew'                          => 'عوامل',
		'Sources'                       => 'منابع',
		'Genres'                        => 'ژانرها',
		'Tags'                          => 'برچسب‌ها',
		'Additional Data'               => 'اطلاعات تکمیلی',
		'Membership'                    => 'عضویت',
		'Seasons & Episodes'            => 'فصل‌ها و قسمت‌ها',

		// Common fields.
		'Title'                         => 'عنوان',
		'Movie Title'                   => 'عنوان فیلم',
		'TV Show Title'                 => 'عنوان سریال',
		'Episode Title'                 => 'عنوان قسمت',
		'Author'                        => 'نویسنده',
		'Status'                        => 'وضعیت',
		'Published'                     => 'منتشر شده',
		'Draft'                         => 'پیش‌نویس',
		'Slug'                          => 'نامک',
		'Content'                       => 'محتوا',
		'Thumbnail'                     => 'تصویر شاخص',
		'Excerpt'                       => 'خلاصه',
		'Name'                          => 'نام',
		'Description'                   => 'توضیحات',
		'Remove'                        => 'حذف',
		'Add'                           => 'افزودن',
		'Featured'                      => 'ویژه',
		'Upcoming'                      => 'به‌زودی',
		'Free'                          => 'رایگان',
		'Notification'                  => 'اعلان',

		// Placeholders — titles.
		'Enter title of the movie.'     => 'عنوان فیلم را وارد کنید.',
		'Enter title of the TVShow.'    => 'عنوان سریال را وارد کنید.',
		'Enter title of the episode.'   => 'عنوان قسمت را وارد کنید.',
		'Enter slug of the movie.'      => 'نامک فیلم را وارد کنید.',
		'Enter slug of the tvshow.'     => 'نامک سریال را وارد کنید.',
		'Enter excerpt content of the movie.' => 'خلاصه فیلم را وارد کنید.',
		'Enter excerpt content of the TVshow.' => 'خلاصه سریال را وارد کنید.',
		'Enter excerpt of the episode.' => 'خلاصه قسمت را وارد کنید.',
		'TV Show Excerpt Content'       => 'خلاصه سریال',

		// Upload.
		'Upload movie Thumbnail'        => 'بارگذاری تصویر شاخص فیلم',
		'Upload tvshow Thumbnail'       => 'بارگذاری تصویر شاخص سریال',
		'Upload Episode Thumbnail'      => 'بارگذاری تصویر شاخص قسمت',
		'This is a featured Episode'    => 'قسمت ویژه',
		'This setting determines which catalog pages the episode will be listed on.' => 'این گزینه مشخص می‌کند قسمت در کدام صفحات فهرست نمایش داده شود.',
		'Upload movie'                  => 'بارگذاری فیلم',
		'Upload Movie Thumbnail'        => 'بارگذاری تصویر شاخص فیلم',
		'Upload a file.'                => 'یک فایل بارگذاری کنید.',
		'Upload the movie logo.'        => 'لوگوی فیلم را بارگذاری کنید.',
		'Upload the TV show logo.'      => 'لوگوی سریال را بارگذاری کنید.',
		'Upload the trailer image.'     => 'تصویر تریلر را بارگذاری کنید.',
		'Upload Potrait Image'          => 'بارگذاری تصویر پرتره',
		'Portrait image File'           => 'فایل تصویر پرتره',

		// Movie / TV show general meta.
		'Choose movie Method'           => 'روش افزودن فیلم',
		'Choose Episode Method'         => 'روش افزودن قسمت',
		'Embed movie'                   => 'جاسازی فیلم',
		'Embed Movie Content'           => 'محتوای جاسازی فیلم',
		'Embed Episode'                 => 'جاسازی قسمت',
		'Embed Episode Content'         => 'محتوای جاسازی قسمت',
		'Upload Episode'                => 'بارگذاری قسمت',
		'Movie URL'                     => 'آدرس فیلم',
		'Episode URL'                   => 'آدرس قسمت',
		'Movie Time Duration'           => 'مدت زمان فیلم',
		'Episode Time Duration'         => 'مدت زمان قسمت',
		'Movie Censor Rating'           => 'رده سنی فیلم',
		'TV Show Censor Rating'         => 'رده سنی سریال',
		'Episode Censor Rating'         => 'رده سنی قسمت',
		'Episode Number'                => 'شماره قسمت',
		'IMDb ID'                       => 'شناسه IMDb',
		'TMDb ID'                       => 'شناسه TMDb',
		'Enter IMDb ID of the movie.'   => 'شناسه IMDb فیلم را وارد کنید.',
		'Enter IMDb ID of the TV Show.' => 'شناسه IMDb سریال را وارد کنید.',
		'Enter IMDb ID of the episode.' => 'شناسه IMDb قسمت را وارد کنید.',
		'IMDb Episode ID'               => 'شناسه IMDb قسمت',
		'TMDb Episode ID'               => 'شناسه TMDb قسمت',
		'Enter TMDb ID of the episode.' => 'شناسه TMDb قسمت را وارد کنید.',
		'Enter TMDb ID of the movie.'   => 'شناسه TMDb فیلم را وارد کنید.',
		'Enter TMDb ID of the TV Show.' => 'شناسه TMDb سریال را وارد کنید.',
		'Enter the embed content to the movie.' => 'کد جاسازی فیلم را وارد کنید.',
		'Enter the embed content for the episode.' => 'کد جاسازی قسمت را وارد کنید.',
		'Enter the external URL to the video.' => 'آدرس خارجی ویدیو را وارد کنید.',
		'Enter the external URL for the episode.' => 'آدرس خارجی قسمت را وارد کنید.',
		'Enter the movie censor rating.' => 'رده سنی فیلم را وارد کنید.',
		'Enter the TV show censor rating.' => 'رده سنی سریال را وارد کنید.',
		'Enter the episode censor rating.' => 'رده سنی قسمت را وارد کنید.',
		'Enter the episode number.'     => 'شماره قسمت را وارد کنید.',
		'Origin Country'                => 'کشور مبدا',
		'Production Country'            => 'کشور تولید',
		'Catalog Visibility'            => 'نمایش در فهرست',
		'Catalog visibility'              => 'نمایش در فهرست',
		'This setting determines which catalog pages movie will be listed on.' => 'این گزینه مشخص می‌کند فیلم در کدام صفحات فهرست نمایش داده شود.',
		'This setting determines which catalog pages the TV Show will be listed on.' => 'این گزینه مشخص می‌کند سریال در کدام صفحات فهرست نمایش داده شود.',
		'This is a featured movie'      => 'فیلم ویژه',
		'This is a featured movie.'     => 'این فیلم ویژه است.',
		'This is a featured TV Show.'   => 'این سریال ویژه است.',
		'Is Affiliate URL ?'            => 'لینک وابسته است؟',

		// Sources tab.
		'Add Source'                    => 'افزودن منبع',
		'Quality'                       => 'کیفیت',
		'Language'                      => 'زبان',
		'Name'                          => 'انکودر (Encoder)',
		'Download URL'                  => 'آدرس دانلود',
		'Date Added'                    => 'تاریخ افزودن',
		'Enter the source name of the movie.' => 'نام انکودر را وارد کنید (مثلاً YIFY).',
		'Enter the source name of the episode.' => 'نام انکودر را وارد کنید (مثلاً YIFY).',
		'Enter the source quality of the movie.' => 'کیفیت را وارد کنید (مثلاً BluRay 1080p).',
		'Enter the source quality of the episode.' => 'کیفیت را وارد کنید (مثلاً BluRay 1080p).',
		'Enter the source language of the movie.' => 'زبان منبع را وارد کنید.',
		'Enter the source added date of the movie.' => 'تاریخ افزودن منبع را وارد کنید.',
		'Enter the download link.'      => 'لینک دانلود را وارد کنید.',

		// Cast & crew.
		'Choose movie Cast'             => 'انتخاب بازیگران فیلم',
		'Choose tvshow cast'            => 'انتخاب بازیگران سریال',
		'Choose Cast'                   => 'انتخاب بازیگر',
		'Choose Cast '                  => 'انتخاب بازیگر',
		'Choose crew member'            => 'انتخاب عضو عوامل',
		'Choose crew member '           => 'انتخاب عضو عوامل',
		'Department'                    => 'بخش',
		'Role'                          => 'نقش',

		// Genres & tags.
		'Choose Movie Genres'           => 'انتخاب ژانرهای فیلم',
		'Choose TV Show Genres'         => 'انتخاب ژانرهای سریال',
		'Choose Movie Tags'             => 'انتخاب برچسب‌های فیلم',
		'Choose TV Show Tags'           => 'انتخاب برچسب‌های سریال',
		'Add Genre'                     => 'افزودن ژانر',
		'Add Tag'                       => 'افزودن برچسب',

		// Seasons.
		'Add Season'                    => 'افزودن فصل',
		'Season Year'                   => 'سال فصل',
		'Season Description'            => 'توضیحات فصل',
		'Episode(s)'                    => 'قسمت‌ها',
		'Enter season name'             => 'نام فصل را وارد کنید.',
		'Enter a brief description of this season' => 'توضیح کوتاه این فصل را وارد کنید.',
		'e.g. 2025'                     => 'مثلاً ۱۴۰۴',
		'Is Upcoming Season'            => 'فصل به‌زودی',
		'This Is Upcoming Season'       => 'این فصل به‌زودی است',
		'Select the date and time when this upcoming season will be released.' => 'تاریخ و ساعت انتشار این فصل را انتخاب کنید.',

		// Additional data.
		'Movie Release Date'            => 'تاریخ انتشار فیلم',
		'Episode Release Date'          => 'تاریخ انتشار قسمت',
		'Movie Views Count'             => 'تعداد بازدید فیلم',
		'Tv Show Views Count'           => 'تعداد بازدید سریال',
		'Episode Views Count'           => 'تعداد بازدید قسمت',
		'Enter the release date of the movie.' => 'تاریخ انتشار فیلم را وارد کنید.',
		'Enter the release date of the episode.' => 'تاریخ انتشار قسمت را وارد کنید.',
		'Enter the views count of the movie.' => 'تعداد بازدید فیلم را وارد کنید.',
		'Enter the views count of the tvshow.' => 'تعداد بازدید سریال را وارد کنید.',
		'Enter IMDb rating.'            => 'امتیاز IMDb را وارد کنید.',
		'Enter IMDb rating manually'    => 'امتیاز IMDb را دستی وارد کنید.',
		'Trailer Link'                  => 'لینک تریلر',
		'Trailer Image'                 => 'تصویر تریلر',
		'Trailer Logo'                  => 'لوگوی تریلر',
		'Enter the trailer link.'       => 'لینک تریلر را وارد کنید.',
		'Select the recommended movies.' => 'فیلم‌های پیشنهادی را انتخاب کنید.',
		'Select Related Product'        => 'انتخاب محصول مرتبط',
		'SEO discription'               => 'توضیحات سئو',
		'Enter SEO description.'        => 'توضیحات سئو را وارد کنید.',

		// Upcoming.
		'Is Upcoming Movie'             => 'فیلم به‌زودی',
		'Is Upcoming TV Show'           => 'سریال به‌زودی',
		'This Is Upcoming Movie'        => 'این فیلم به‌زودی است',
		'This Is Upcoming Movie.'       => 'این فیلم به‌زودی است.',
		'This Is Upcoming TV Show'      => 'این سریال به‌زودی است',
		'This Is Upcoming TV Show.'     => 'این سریال به‌زودی است.',
		'Upcoming Release Date & Time'  => 'تاریخ و ساعت انتشار',
		'Select release date and time'  => 'انتخاب تاریخ و ساعت انتشار',
		'Select the date and time when this upcoming movie will be released.' => 'تاریخ و ساعت انتشار فیلم را انتخاب کنید.',
		'Select the date and time when this upcoming TV show will be released.' => 'تاریخ و ساعت انتشار سریال را انتخاب کنید.',
		'Notify User'                   => 'اطلاع‌رسانی به کاربر',
		'Notify User.'                  => 'به کاربر اطلاع داده شود.',

		// Membership / PPV.
		'Movie Access'                  => 'دسترسی فیلم',
		'TV Show Access'                => 'دسترسی سریال',
		'Choose PMP Levels'             => 'انتخاب سطح عضویت',
		'PPV Price'                     => 'قیمت پرداخت‌به‌ازای‌هر‌بازدید',
		'Enter price'                   => 'قیمت را وارد کنید.',
		'Enter discount percent'        => 'درصد تخفیف را وارد کنید.',
		'Enter Discount Percent'        => 'درصد تخفیف را وارد کنید.',
		'Enter duration in days'        => 'مدت (روز) را وارد کنید.',

		// Episode-specific labels.
		'Episode Label'                 => 'برچسب قسمت',
		'Enter the episode label.'      => 'برچسب قسمت را وارد کنید.',
		'Enter content of the episode.' => 'محتوای قسمت را وارد کنید.',
		'Enter slug of the episode.'    => 'نامک قسمت را وارد کنید.',

		// Validation & toast messages.
		'Success'                       => 'موفق',
		'Error'                         => 'خطا',
		'Please fill all required fields' => 'لطفاً همه فیلدهای الزامی را پر کنید.',
		'The title is required.'        => 'عنوان الزامی است.',
		'The name is required.'         => 'نام الزامی است.',
		'The slug is required.'         => 'نامک الزامی است.',
		'The thumbnail is required.'    => 'تصویر شاخص الزامی است.',
		'The attachment is required.'   => 'فایل پیوست الزامی است.',
		'The embed code is required.'   => 'کد جاسازی الزامی است.',
		'The link is required.'         => 'لینک الزامی است.',
		'The quality is required.'      => 'کیفیت الزامی است.',
		'The language is required.'     => 'زبان الزامی است.',
		'The date is required.'         => 'تاریخ الزامی است.',
		'The download URL is required.' => 'آدرس دانلود الزامی است.',
		'The source name is required.'  => 'نام منبع الزامی است.',
		'The source URL is required.'   => 'آدرس منبع الزامی است.',
		'Please enter a rating between 1 and 10.' => 'امتیاز باید بین ۱ تا ۱۰ باشد.',
		'Please select at least one option.' => 'حداقل یک گزینه را انتخاب کنید.',
		'Please Enter Positive Digit'   => 'لطفاً عدد مثبت وارد کنید.',
		'Only Image files are allowed!' => 'فقط فایل تصویر مجاز است!',
		'Only Video files are allowed!' => 'فقط فایل ویدیو مجاز است!',
		'Select or Upload Image'        => 'انتخاب یا بارگذاری تصویر',
		'Select or Upload Video'        => 'انتخاب یا بارگذاری ویدیو',
		'Use this image'                => 'استفاده از این تصویر',
		'Use this video'                => 'استفاده از این ویدیو',
		'Are you sure you want to delete the selected items?' => 'آیا از حذف موارد انتخاب‌شده مطمئن هستید؟',
		'HLS streaming URL (.m3u8) requires the Live Streaming plugin to be active' => 'برای آدرس HLS (.m3u8) افزونه پخش زنده باید فعال باشد.',
		'Release date and time is required for upcoming content.' => 'برای محتوای به‌زودی، تاریخ و ساعت انتشار الزامی است.',
		'Movie updated successfully.'   => 'فیلم با موفقیت به‌روزرسانی شد.',
		'Movie added successfully.'     => 'فیلم با موفقیت افزوده شد.',
		'TV Show updated successfully.' => 'سریال با موفقیت به‌روزرسانی شد.',
		'TV Show added successfully.'   => 'سریال با موفقیت افزوده شد.',
		'Episode updated successfully.' => 'قسمت با موفقیت به‌روزرسانی شد.',
		'Episode added successfully.'   => 'قسمت با موفقیت افزوده شد.',
		'An unexpected error occurred. Please try again.' => 'خطای غیرمنتظره رخ داد. لطفاً دوباره تلاش کنید.',
	);
}

/**
 * Whether current request is Streamit wp-admin (any streamit-* page or admin-post).
 *
 * @return bool
 */
function streamit_child_is_streamit_admin_screen() {
	if ( ! is_admin() ) {
		return false;
	}

	$page = isset( $_GET['page'] ) ? sanitize_text_field( wp_unslash( $_GET['page'] ) ) : '';
	if ( '' !== $page && 0 === strpos( $page, 'streamit' ) ) {
		return true;
	}

	$action = isset( $_REQUEST['action'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['action'] ) ) : '';
	if ( '' !== $action && 0 === strpos( $action, 'handle_' ) ) {
		return true;
	}

	global $hook_suffix;
	if ( ! empty( $hook_suffix ) && false !== strpos( $hook_suffix, 'streamit' ) ) {
		return true;
	}

	return streamit_child_is_streamit_content_admin();
}

/**
 * Translate streamit-core strings on content admin screens.
 *
 * @param string $translated Translated text.
 * @param string $text       Original text.
 * @param string $domain     Text domain.
 * @return string
 */
function streamit_child_translate_admin_labels( $translated, $text, $domain ) {
	if ( 'streamit-core' !== $domain || ! is_admin() || ! is_rtl() ) {
		return $translated;
	}

	static $map = null;
	if ( null === $map ) {
		$map = streamit_child_get_admin_persian_labels();
	}

	return $map[ $text ] ?? $translated;
}
add_filter( 'gettext', 'streamit_child_translate_admin_labels', 20, 3 );

/**
 * Inject RTL layout styles on Streamit content edit screens (Persian admin only).
 *
 * Skipped when the admin user locale is LTR (e.g. English profile) so WP chrome
 * and Streamit panels do not fight direction and overlay each other.
 *
 * @param string $hook Current admin page hook.
 */
function streamit_child_admin_persian_labels_styles( $hook ) {
	if ( ! is_rtl() ) {
		return;
	}

	$screens = array(
		'admin_page_streamit-edit-movie',
		'admin_page_streamit-add-movie',
		'admin_page_streamit-edit-tvshow',
		'admin_page_streamit-add-tvshow',
		'admin_page_streamit-edit-tvshow-episode',
		'admin_page_streamit-add-tvshow-episode',
	);

	if ( ! in_array( $hook, $screens, true ) ) {
		return;
	}

	$css_file = get_stylesheet_directory() . '/assets/css/admin-persian-labels.css';
	if ( ! file_exists( $css_file ) ) {
		return;
	}

	wp_enqueue_style(
		'streamit-child-admin-persian-labels',
		get_stylesheet_directory_uri() . '/assets/css/admin-persian-labels.css',
		array(),
		(string) filemtime( $css_file )
	);

}
add_action( 'admin_enqueue_scripts', 'streamit_child_admin_persian_labels_styles' );

/**
 * Center admin toasts, RTL layout, and Persian toast titles on Streamit screens.
 *
 * Only for RTL (Persian) admin locales — English admin keeps default LTR toasts.
 *
 * @param string $hook Current admin page hook.
 */
function streamit_child_enqueue_admin_notifications_fa( $hook ) {
	if ( ! is_rtl() ) {
		return;
	}

	if ( ! streamit_child_is_streamit_admin_screen() && false === strpos( $hook, 'streamit' ) ) {
		return;
	}

	$css_file = get_stylesheet_directory() . '/assets/css/admin-notifications-rtl.css';
	if ( file_exists( $css_file ) ) {
		wp_enqueue_style(
			'streamit-child-admin-notifications-rtl',
			get_stylesheet_directory_uri() . '/assets/css/admin-notifications-rtl.css',
			array(),
			(string) filemtime( $css_file )
		);
	}

	$js_file = get_stylesheet_directory() . '/assets/js/admin-notifications-fa.js';
	if ( file_exists( $js_file ) ) {
		wp_enqueue_script(
			'streamit-child-admin-notifications-fa',
			get_stylesheet_directory_uri() . '/assets/js/admin-notifications-fa.js',
			array(),
			(string) filemtime( $js_file ),
			true
		);
	}
}
add_action( 'admin_enqueue_scripts', 'streamit_child_enqueue_admin_notifications_fa', 100 );
