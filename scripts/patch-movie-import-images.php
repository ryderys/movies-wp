<?php
/**
 * Patch the gitignored Streamit movie importer with tracked image behavior.
 *
 * Usage inside WordPress:
 *   php /tmp/patch-movie-import-images.php
 */

/**
 * Apply the movie image patch to importer source.
 *
 * Kept as a pure transformation so the deployment patch can be tested without
 * loading WordPress or making network requests.
 *
 * @param string   $content Importer source.
 * @param string[] $changes Applied change labels.
 * @return string
 */
function streamit_patch_movie_import_images_content( $content, &$changes = array() ) {
	$eol     = false !== strpos( $content, "\r\n" ) ? "\r\n" : "\n";
	$content = str_replace( "\r\n", "\n", $content );

	$dedup_search = "    //Strictly Check if Image Exists in Media Library by GUID (URL)
    \$existing_attachment_id = \$wpdb->get_var(\$wpdb->prepare(
        \"SELECT ID FROM {\$wpdb->posts} WHERE guid = %s AND post_type = 'attachment' LIMIT 1\",
        esc_url(\$image_url)
    ));

    if (!empty(\$existing_attachment_id)) {
        return absint(\$existing_attachment_id); // Image exists, return attachment ID
    }";

	$dedup_v1 = "    // Use canonical TMDB source metadata as the primary attachment identity.
    \$source_url = remove_query_arg('_streamit_image_role', \$image_url);
    \$existing_attachment_id = \$wpdb->get_var(\$wpdb->prepare(
        \"SELECT post_id FROM {\$wpdb->postmeta} WHERE meta_key = '_streamit_tmdb_source_url' AND meta_value = %s LIMIT 1\",
        esc_url(\$source_url)
    ));

    if (!empty(\$existing_attachment_id)) {
        return absint(\$existing_attachment_id);
    }

    // Adopt legacy GUID-identified imports once, then use source metadata.
    \$legacy_attachment_id = \$wpdb->get_var(\$wpdb->prepare(
        \"SELECT ID FROM {\$wpdb->posts} WHERE guid = %s AND post_type = 'attachment' LIMIT 1\",
        esc_url(\$source_url)
    ));
    if (!empty(\$legacy_attachment_id)) {
        update_post_meta(\$legacy_attachment_id, '_streamit_tmdb_source_url', esc_url(\$source_url));
        return absint(\$legacy_attachment_id);
    }";

	$dedup_replace = "    // Use canonical TMDB source metadata as the primary attachment identity.
    \$source_url = remove_query_arg('_streamit_image_role', \$image_url);
    \$existing_attachment_id = \$wpdb->get_var(\$wpdb->prepare(
        \"SELECT post_id FROM {\$wpdb->postmeta} WHERE meta_key = '_streamit_tmdb_source_url' AND meta_value = %s LIMIT 1\",
        esc_url(\$source_url)
    ));

    if (!empty(\$existing_attachment_id)) {
        return absint(\$existing_attachment_id);
    }

    // A TMDB file path is stable when the requested size changes (original -> w780).
    \$source_path = wp_basename(parse_url(\$source_url, PHP_URL_PATH));
    \$source_like = '%/' . \$wpdb->esc_like(\$source_path);
    \$existing_attachment_id = \$wpdb->get_var(\$wpdb->prepare(
        \"SELECT post_id FROM {\$wpdb->postmeta} WHERE meta_key = '_streamit_tmdb_source_url' AND meta_value LIKE %s LIMIT 1\",
        \$source_like
    ));
    if (!empty(\$existing_attachment_id)) {
        update_post_meta(\$existing_attachment_id, '_streamit_tmdb_source_url', esc_url(\$source_url));
        return absint(\$existing_attachment_id);
    }

    // Adopt legacy GUID-identified imports once, then use source metadata.
    \$legacy_attachment_id = \$wpdb->get_var(\$wpdb->prepare(
        \"SELECT ID FROM {\$wpdb->posts} WHERE guid = %s AND post_type = 'attachment' LIMIT 1\",
        esc_url(\$source_url)
    ));
    if (empty(\$legacy_attachment_id)) {
        \$legacy_attachment_id = \$wpdb->get_var(\$wpdb->prepare(
            \"SELECT ID FROM {\$wpdb->posts} WHERE guid LIKE %s AND post_type = 'attachment' LIMIT 1\",
            \$source_like
        ));
    }
    if (!empty(\$legacy_attachment_id)) {
        update_post_meta(\$legacy_attachment_id, '_streamit_tmdb_source_url', esc_url(\$source_url));
        return absint(\$legacy_attachment_id);
    }";

	$guid_search = "    ///Update the GUID with the original image URL (ensures future duplicate checks work)
    \$wpdb->update(
        \$wpdb->posts,
        ['guid' => esc_url(\$image_url)],
        ['ID' => \$attachment_id]
    );

    return absint(\$attachment_id);";

	$guid_replace = "    update_post_meta(\$attachment_id, '_streamit_tmdb_source_url', esc_url(\$source_url));

    return absint(\$attachment_id);";

	$image_search = "    // Handle Thumbnail (Poster)
    if (!empty(\$movie_data['poster_path'])) {
        \$poster_url = streamit_get_tmdb_image_url(\$movie_data['poster_path'], 'original'); // TMDb image URL via Cloudflare Worker
        \$thumbnail_id = streamit_download_and_attach_movie_image(\$poster_url);

        if (!is_wp_error(\$thumbnail_id)) {
            streamit_add_movie_meta(\$movie_id, 'thumbnail_id', \$thumbnail_id);
            streamit_add_movie_meta(\$movie_id, '_portrait_thumbmail', \$thumbnail_id);
        }
    }";

	$image_replace = "    // Portrait poster used by movie cards.
    \$poster_id = 0;
    if (!empty(\$movie_data['poster_path'])) {
        \$poster_url = streamit_get_tmdb_image_url(\$movie_data['poster_path'], 'w780');
        \$poster_id = streamit_download_and_attach_movie_image(\$poster_url);

        if (!is_wp_error(\$poster_id)) {
            streamit_add_movie_meta(\$movie_id, '_portrait_thumbmail', \$poster_id);
        }
    }

    // Landscape backdrop used by the movie detail hero.
    if (!empty(\$movie_data['backdrop_path'])) {
        \$backdrop_url = add_query_arg(
            '_streamit_image_role',
            'backdrop',
            streamit_get_tmdb_image_url(\$movie_data['backdrop_path'], 'original')
        );
        \$backdrop_id = streamit_download_and_attach_movie_image(\$backdrop_url);

        if (!is_wp_error(\$backdrop_id)) {
            streamit_add_movie_meta(\$movie_id, 'thumbnail_id', \$backdrop_id);
        } elseif (!empty(\$poster_id) && !is_wp_error(\$poster_id)) {
            streamit_add_movie_meta(\$movie_id, 'thumbnail_id', \$poster_id);
        }
    } elseif (!empty(\$poster_id) && !is_wp_error(\$poster_id)) {
        streamit_add_movie_meta(\$movie_id, 'thumbnail_id', \$poster_id);
    }";

	$replacements = array(
		'dedup'         => array( $dedup_search, $dedup_replace ),
		'dedup upgrade' => array( $dedup_v1, $dedup_replace ),
		'guid'          => array( $guid_search, $guid_replace ),
		'image split'   => array( $image_search, $image_replace ),
	);

	foreach ( $replacements as $label => $replacement ) {
		$search  = str_replace( "\r\n", "\n", $replacement[0] );
		$replace = str_replace( "\r\n", "\n", $replacement[1] );
		if ( false !== strpos( $content, $search ) ) {
			$content   = str_replace( $search, $replace, $content );
			$changes[] = $label;
		}
	}

	return "\r\n" === $eol ? str_replace( "\n", "\r\n", $content ) : $content;
}

/**
 * Patch the deployed importer.
 *
 * @param string $file Importer path.
 * @return int Process exit code.
 */
function streamit_patch_movie_import_images_file( $file ) {
	if ( ! is_readable( $file ) || ! is_writable( $file ) ) {
		fwrite( STDERR, "Importer is not readable/writable: {$file}\n" );
		return 1;
	}

	$original = file_get_contents( $file );
	if ( false === $original ) {
		fwrite( STDERR, "Could not read importer: {$file}\n" );
		return 1;
	}

	$changes = array();
	$patched = streamit_patch_movie_import_images_content( $original, $changes );
	$required_markers = array(
		"'w780'",
		"\$movie_data['backdrop_path']",
		"'_streamit_tmdb_source_url'",
		'$source_path',
		"'_portrait_thumbmail', \$poster_id",
		"'thumbnail_id', \$backdrop_id",
	);

	foreach ( $required_markers as $marker ) {
		if ( false === strpos( $patched, $marker ) ) {
			fwrite( STDERR, "Patch verification failed; missing marker: {$marker}\n" );
			return 1;
		}
	}

	if ( $patched === $original ) {
		echo basename( $file ) . ": already patched\n";
		return 0;
	}

	if ( false === file_put_contents( $file, $patched ) ) {
		fwrite( STDERR, "Could not write importer: {$file}\n" );
		return 1;
	}

	echo basename( $file ) . ': patched ' . implode( ', ', $changes ) . "\n";
	return 0;
}

if ( isset( $_SERVER['SCRIPT_FILENAME'] ) && realpath( $_SERVER['SCRIPT_FILENAME'] ) === __FILE__ ) {
	$file = getenv( 'STREAMIT_MOVIE_IMPORTER_FILE' );
	if ( ! is_string( $file ) || '' === $file ) {
		$file = '/var/www/html/wp-content/plugins/streamit/admin/content-import/streamit-tmdb_movie-function.php';
	}
	exit( streamit_patch_movie_import_images_file( $file ) );
}
