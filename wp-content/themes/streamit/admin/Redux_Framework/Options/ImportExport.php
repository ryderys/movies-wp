<?php

if (!defined('ABSPATH')) {
	exit; // Exit if accessed directly.
}


/**
 * streamit\streamit\Redux_Framework\Options\ImportExport class
 *
 * @package streamit
 */

Redux::set_section(
	$this->opt_name,
	array(
		'title' => esc_html__('Import / Export', 'streamit'),
		'id' => 'import-export',
		'icon' => 'custom-import-export',
		'customizer' => false,
		'fields' => array(

			array(
				'id' => 'redux_import_export',
				'type' => 'import_export',
				'full_width' => true,
			),
		)
	)
);
