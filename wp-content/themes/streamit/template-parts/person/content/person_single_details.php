<?php

/**
 * The template for displaying details.
 *
 * @package Streamit
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}
streamit_get_template('person/content/person_single_title.php', ['st_data' => $st_data]);

streamit_get_template('person/content/person_single_genre.php', ['st_data' => $st_data]);

streamit_get_template('person/content/person_single_content.php', ['st_data' => $st_data]);

streamit_get_template('person/content/person_single_actor_history.php', ['st_data' => $st_data]);
