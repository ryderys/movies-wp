<?php
$file = '/var/www/html/wp-content/plugins/streamit/admin/content-import/streamit-tmdb_movie-function.php';
$content = file_get_contents( $file );

$content = preg_replace(
	'/    \/\/\/Update the GUID with the original image URL.*?return absint\(\$attachment_id\);/s',
	"    update_post_meta(\$attachment_id, '_streamit_tmdb_source_url', esc_url(\$image_url));\n\n    return absint(\$attachment_id);",
	$content,
	1,
	$guid_count
);

$content = preg_replace(
	'/    \/\/Strictly Check if Image Exists in Media Library by GUID \(URL\).*?return absint\(\$existing_attachment_id\); \/\/ Image exists, return attachment ID/s',
	"    \$existing_attachment_id = \$wpdb->get_var(\$wpdb->prepare(\n        \"SELECT post_id FROM {\$wpdb->postmeta} WHERE meta_key = '_streamit_tmdb_source_url' AND meta_value = %s LIMIT 1\",\n        esc_url(\$image_url)\n    ));\n\n    if (!empty(\$existing_attachment_id)) {\n        return absint(\$existing_attachment_id);",
	$content,
	1,
	$dedup_count
);

if ( $guid_count || $dedup_count ) {
	file_put_contents( $file, $content );
	echo "movie guid={$guid_count} dedup={$dedup_count}\n";
} else {
	echo "no changes\n";
}

// Backdrop import for movies.
$poster_block = "        if (!is_wp_error(\$thumbnail_id)) {\n            streamit_add_movie_meta(\$movie_id, 'thumbnail_id', \$thumbnail_id);\n            streamit_add_movie_meta(\$movie_id, '_portrait_thumbmail', \$thumbnail_id);\n        }\n    }";

$backdrop_block = "        if (!is_wp_error(\$thumbnail_id)) {\n            streamit_add_movie_meta(\$movie_id, '_portrait_thumbmail', \$thumbnail_id);\n        }\n    }\n\n    if (!empty(\$movie_data['backdrop_path'])) {\n        \$backdrop_url = streamit_get_tmdb_image_url(\$movie_data['backdrop_path'], 'original');\n        \$backdrop_id = streamit_download_and_attach_movie_image(\$backdrop_url);\n        if (!is_wp_error(\$backdrop_id)) {\n            streamit_add_movie_meta(\$movie_id, 'thumbnail_id', \$backdrop_id);\n        } elseif (!empty(\$thumbnail_id) && !is_wp_error(\$thumbnail_id)) {\n            streamit_add_movie_meta(\$movie_id, 'thumbnail_id', \$thumbnail_id);\n        }\n    } elseif (!empty(\$thumbnail_id) && !is_wp_error(\$thumbnail_id)) {\n        streamit_add_movie_meta(\$movie_id, 'thumbnail_id', \$thumbnail_id);\n    }";

$content = file_get_contents( $file );
if ( false !== strpos( $content, $poster_block ) ) {
	$content = str_replace( $poster_block, $backdrop_block, $content );
	file_put_contents( $file, $content );
	echo "movie backdrop import patched\n";
}
