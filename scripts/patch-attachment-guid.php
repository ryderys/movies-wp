<?php
$file = '/var/www/html/wp-content/plugins/streamit/admin/content-import/streamit-tmdb_tvshow-function.php';
$content = file_get_contents( $file );

$search = "    ///Update the GUID with the original image URL (ensures future duplicate checks work)
    \$wpdb->update(
        \$wpdb->posts,
        ['guid' => esc_url(\$image_url)],
        ['ID' => \$attachment_id]
    );

    return absint(\$attachment_id);";

$replace = "    update_post_meta(\$attachment_id, '_streamit_tmdb_source_url', esc_url(\$image_url));

    return absint(\$attachment_id);";

if ( false !== strpos( $content, $search ) ) {
	$content = str_replace( $search, $replace, $content );
	file_put_contents( $file, $content );
	echo "Patched TV show image GUID overwrite\n";
} else {
	echo "TV show GUID patch not needed\n";
}

$dedup_search = "    //Strictly Check if Image Exists in Media Library by GUID (URL)
    \$existing_attachment_id = \$wpdb->get_var(\$wpdb->prepare(
        \"SELECT ID FROM {\$wpdb->posts} WHERE guid = %s AND post_type = 'attachment' LIMIT 1\",
        esc_url(\$image_url)
    ));

    if (!empty(\$existing_attachment_id)) {
        return absint(\$existing_attachment_id); // Image exists, return attachment ID
    }";

$dedup_replace = "    \$existing_attachment_id = \$wpdb->get_var(\$wpdb->prepare(
        \"SELECT post_id FROM {\$wpdb->postmeta} WHERE meta_key = '_streamit_tmdb_source_url' AND meta_value = %s LIMIT 1\",
        esc_url(\$image_url)
    ));

    if (!empty(\$existing_attachment_id)) {
        return absint(\$existing_attachment_id);
    }";

$content = file_get_contents( $file );
if ( false !== strpos( $content, $dedup_search ) ) {
	$content = str_replace( $dedup_search, $dedup_replace, $content );
	file_put_contents( $file, $content );
	echo "Patched TV show image dedup lookup\n";
} else {
	echo "TV show dedup patch not needed\n";
}

$movie_file = '/var/www/html/wp-content/plugins/streamit/admin/content-import/streamit-tmdb_movie-function.php';
if ( is_readable( $movie_file ) ) {
	$movie = file_get_contents( $movie_file );
	$changed = false;
	if ( false !== strpos( $movie, $search ) ) {
		$movie = str_replace( $search, $replace, $movie );
		$changed = true;
	}
	if ( false !== strpos( $movie, $dedup_search ) ) {
		$movie = str_replace( $dedup_search, $dedup_replace, $movie );
		$changed = true;
	}
	if ( $changed ) {
		file_put_contents( $movie_file, $movie );
		echo "Patched movie image import\n";
	}
}
