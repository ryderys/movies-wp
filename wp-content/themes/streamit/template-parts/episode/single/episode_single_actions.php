<?php

/**
 * The template for displaying actions
 *
 * @link https://codex.wordpress.org/Template_Hierarchy
 *
 * @package streamit
 */
if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

?>
<div class="d-flex align-items-center flex-wrap gap-3 gap-md-4 mt-5">

    <?php streamit_get_template('episode/content/episode_single_actions_model.php', ['st_data' =>  $st_data]); ?>

</div>