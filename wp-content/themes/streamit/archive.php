<?php
/**
 * The template for displaying archive pages
 *
 * @link https://developer.wordpress.org/themes/basics/template-hierarchy/
 *
 * @package Streamit
 */

get_header(); ?>

<div class="page-content">
	<div id="content" class="site-content">
		<div id="primary" class="content-area">
            
            <?php streamit_get_template('content/archive.php'); ?>

        </div>
    </div>
</div>
<?php
get_footer();
