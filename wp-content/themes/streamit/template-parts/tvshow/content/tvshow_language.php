<?php

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

$lunguages = $st_data->get_meta('_language');
$is_limit = isset($is_limit) ? $is_limit : false;

if (isset($lunguages['labels']) && is_array($lunguages['labels'])) {
    $total_languages = count($lunguages['labels']);
    $loop_limit = $is_limit ? 2 : $total_languages; // Show 2 if limit is true, otherwise show all
    $counter = 0;
    ?>

    <?php if (!empty($lunguages)) { ?>
        <div class="tvshow-language d-flex align-items-center gap-1">
            <?php echo st_get_icon('translate'); ?>
            <ul class="list-inline m-0 p-0 d-inline-flex align-items-center gap-2 flex-wrap">
                <?php foreach ($lunguages['labels'] as $lungugage) :
                    if ($counter >= $loop_limit) {
                        break;
                    }
                    ?>
                    <li>
                        <small><?php echo esc_html($lungugage); ?></small>
                    </li>
                    <?php $counter++; ?>
                <?php endforeach; ?>

                <?php if ($is_limit && $total_languages > 2) : ?>
                    <li>
                        <small><?php echo '+'.($total_languages - 2); ?></small>
                    </li>
                <?php endif; ?>
            </ul>
        </div>
    <?php } ?>

<?php
}
?>
