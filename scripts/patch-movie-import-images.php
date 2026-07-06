<?php
/**
 * Patch movie TMDB import: backdrop banner + attachment GUID handling.
 */
$files = array(
	'/var/www/html/wp-content/plugins/streamit/admin/content-import/streamit-tmdb_movie-function.php',
	'/var/www/html/wp-content/plugins/streamit/admin/content-import/streamit-tmdb_tvshow-function.php',
);

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

$guid_search = "    ///Update the GUID with the original image URL (ensures future duplicate checks work)
    \$wpdb->update(
        \$wpdb->posts,
        ['guid' => esc_url(\$image_url)],
        ['ID' => \$attachment_id]
    );

    return absint(\$attachment_id);";

$guid_replace = "    update_post_meta(\$attachment_id, '_streamit_tmdb_source_url', esc_url(\$image_url));

    return absint(\$attachment_id);";

$backdrop_search = "        if (!is_wp_error(\$thumbnail_id)) {
            streamit_add_movie_meta(\$movie_id, 'thumbnail_id', \$thumbnail_id);
            streamit_add_movie_meta(\$movie_id, '_portrait_thumbmail', \$thumbnail_id);
        }
    }";

$backdrop_replace = "        if (!is_wp_error(\$thumbnail_id)) {
            streamit_add_movie_meta(\$movie_id, '_portrait_thumbmail', \$thumbnail_id);
        }
    }

    // Hero banner: use TMDB backdrop (landscape) when available.
    if (!empty(\$movie_data['backdrop_path'])) {
        \$backdrop_url = streamit_get_tmdb_image_url(\$movie_data['backdrop_path'], 'original');
        \$backdrop_id = streamit_download_and_attach_movie_image(\$backdrop_url);

        if (!is_wp_error(\$backdrop_id)) {
            streamit_add_movie_meta(\$movie_id, 'thumbnail_id', \$backdrop_id);
        } elseif (!empty(\$thumbnail_id) && !is_wp_error(\$thumbnail_id)) {
            streamit_add_movie_meta(\$movie_id, 'thumbnail_id', \$thumbnail_id);
        }
    } elseif (!empty(\$thumbnail_id) && !is_wp_error(\$thumbnail_id)) {
        streamit_add_movie_meta(\$movie_id, 'thumbnail_id', \$thumbnail_id);
    }";

foreach ( $files as $file ) {
	if ( ! is_readable( $file ) ) {
		continue;
	}

	$content = file_get_contents( $file );
	$orig    = $content;

	if ( false !== strpos( $content, $dedup_search ) ) {
		$content = str_replace( $dedup_search, $dedup_replace, $content );
		echo basename( $file ) . ": patched dedup\n";
	}

	if ( false !== strpos( $content, $guid_search ) ) {
		$content = str_replace( $guid_search, $guid_replace, $content );
		echo basename( $file ) . ": patched guid\n";
	}

	if ( false !== strpos( $file, 'movie-function' ) && false !== strpos( $content, $backdrop_search ) ) {
		$content = str_replace( $backdrop_search, $backdrop_replace, $content );
		echo basename( $file ) . ": patched backdrop import\n";
	}

	if ( $content !== $orig ) {
		file_put_contents( $file, $content );
	}
}

// TV show import: separate portrait + backdrop like movies.
$tv_file = '/var/www/html/wp-content/plugins/streamit/admin/content-import/streamit-tmdb_tvshow-function.php';
if ( is_readable( $tv_file ) ) {
	$content = file_get_contents( $tv_file );
	$tv_search = "        if (!is_wp_error(\$thumbnail_id)) {
            streamit_add_tvshow_meta(\$tvshow_id, 'thumbnail_id', \$thumbnail_id);
            streamit_add_tvshow_meta(\$tvshow_id, '_portrait_thumbmail', \$thumbnail_id);
        }
    }";

	$tv_replace = "        if (!is_wp_error(\$thumbnail_id)) {
            streamit_add_tvshow_meta(\$tvshow_id, '_portrait_thumbmail', \$thumbnail_id);
        }
    }

    if (!empty(\$tvshow_data['backdrop_path'])) {
        \$backdrop_url = streamit_get_tmdb_image_url(\$tvshow_data['backdrop_path'], 'original');
        \$backdrop_id = streamit_download_and_attach_tvshow_image(\$backdrop_url);

        if (!is_wp_error(\$backdrop_id)) {
            streamit_add_tvshow_meta(\$tvshow_id, 'thumbnail_id', \$backdrop_id);
        } elseif (!empty(\$thumbnail_id) && !is_wp_error(\$thumbnail_id)) {
            streamit_add_tvshow_meta(\$tvshow_id, 'thumbnail_id', \$thumbnail_id);
        }
    } elseif (!empty(\$thumbnail_id) && !is_wp_error(\$thumbnail_id)) {
        streamit_add_tvshow_meta(\$tvshow_id, 'thumbnail_id', \$thumbnail_id);
    }";

	if ( false !== strpos( $content, $tv_search ) ) {
		$content = str_replace( $tv_search, $tv_replace, $content );
		file_put_contents( $tv_file, $content );
		echo "streamit-tmdb_tvshow-function.php: patched backdrop import\n";
	}
}

echo "Import patches complete.\n";
