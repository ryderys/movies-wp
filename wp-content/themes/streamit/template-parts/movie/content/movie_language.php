<?php

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

// Retrieve languages metadata
$languages = $st_data->get_meta('_language');

// Exit early if no languages are available or if labels are not in the expected format
if (empty($languages) || !isset($languages['labels']) || !is_array($languages['labels'])) {
    return;
}

// Determine if there is a limit on the number of languages to display
$is_limit = isset($is_limit) ? (bool) $is_limit : false;
$total_languages = count($languages['labels']);
$loop_limit = $is_limit ? 2 : $total_languages;

// Only proceed if there are languages to display
if ($total_languages > 0) : ?>
    <div class="movie-language d-flex align-items-center gap-1">
        <?php echo st_get_icon('translate'); ?>

        <ul class="list-inline m-0 p-0 d-inline-flex align-items-center gap-2 flex-wrap">

            <?php foreach (array_slice($languages['labels'], 0, $loop_limit) as $language) : ?>
                <li>
                    <small><?php echo esc_html($language); ?></small>
                </li>
            <?php endforeach; ?>

            <!-- Display additional languages count if applicable -->
            <?php if ($is_limit && $total_languages > 2) : ?>
                <li>
                    <small>+<?php echo esc_html($total_languages - 2); ?></small>
                </li>
            <?php endif; ?>
        </ul>
    </div>
<?php endif; ?>