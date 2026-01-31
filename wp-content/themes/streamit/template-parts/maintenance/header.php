<!DOCTYPE html>
<html <?php language_attributes(); ?> class="no-js no-svg">
<head>
  <!-- Required meta tags -->
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
  <?php
  /**
   * This single function now loads BOTH the compiled CSS and JavaScript for the maintenance page.
   * It replaces the old, incorrect wp_enqueue_script and wp_enqueue_style calls for your custom assets.
   */
  \Kucrut\Vite\enqueue_asset(
    get_template_directory() . '/static/dist',
    'static/assets/utilities/maintenance/maintance-custom.js',
    [
      'handle' => 'streamit-maintenance',
      'dependencies' => ['jquery'],
      'in-footer' => true,
    ]
  );

  ?>
</head>

<body data-spy="scroll" data-offset="80">
  <div id="page" class="site">
    <a class="skip-link screen-reader-text" href="#content"><?php esc_html__('Skip to content', 'streamit'); ?></a>

    <div class="site-content-contain">
      <div id="content" class="site-content">