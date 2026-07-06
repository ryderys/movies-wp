<?php
$file = '/var/www/html/wp-content/plugins/streamit/admin/content-import/streamit-tmdb_tvshow-function.php';
$content = file_get_contents( $file );
$original = $content;
$content = str_replace(
	"isset(\$response['data']['cast'])",
	"isset(\$cast_response['data']['cast'])",
	$content
);
$content = str_replace(
	"isset(\$response['data']['crew'])",
	"isset(\$cast_response['data']['crew'])",
	$content
);
if ( $content === $original ) {
	echo "No changes needed\n";
} else {
	file_put_contents( $file, $content );
	echo "Patched cast/crew bug\n";
}
