<?php

/**
 * The template for displaying video download modal page
 *
 * @link https://codex.wordpress.org/Template_Hierarchy
 *
 * @package streamit
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

if (! is_user_logged_in()) return false;
$data_id = $st_data->get_id();
if (!function_exists('streamit_user_has_stream_access') || !streamit_user_has_stream_access($data_id, 'video', get_current_user_id())) return;
if ((method_exists($st_data, 'get_meta') && $st_data->get_meta('download_btn') == 'no')) return false;
if ($st_data->get_meta('download_btn') == 'link' && $st_data->get_meta('dwn_link') == null) return false;
if ($st_data->get_meta('download_btn') == 'upload' && wp_get_attachment_url($st_data->get_meta('upload_item')) == null) return false;

$st_download_link = ($st_data->get_meta('download_btn') == 'link') ? $st_data->get_meta('dwn_link') : wp_get_attachment_url($st_data->get_meta('upload_item'));
?>

<li>
    <a download href="<?php echo esc_url($st_download_link); ?>" class="action-btn btn btn-secondary border" data-bs-toggle="tooltip" data-bs-placement="top" data-bs-title="<?php esc_attr_e('Download', 'streamit'); ?>">
        <?php echo st_get_icon('download-2'); ?>
    </a>
</li>