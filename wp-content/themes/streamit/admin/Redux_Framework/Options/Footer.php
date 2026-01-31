<?php

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}


/**
 * streamit\streamit\Redux_Framework\Options\Footer class
 *
 * @package streamit
 */
Redux::set_section(
	$this->opt_name,
	array(
		'title' => esc_html__('Footer', 'streamit'),
		'id' => 'footer',
		'icon' => 'custom-footer-main',
		'customizer_width' => '500px',
	)
);

Redux::set_section(
	$this->opt_name,
	array(
		'title' => esc_html__('Footer Background', 'streamit'),
		'id' => 'footer-logo',
		'subsection' => true,
		'icon' => 'custom-Footer-Layout',
		'desc' => esc_html__('This section contains options for footer background.', 'streamit'),
		'fields' => array(
			array(
				'id' => 'change_footer_background',
				'type' => 'button_set',
				'title' => esc_html__('Change footer color', 'streamit'),
				'subtitle' => esc_html__('Select option for the footer background', 'streamit'),
				'options' => array(
					'default' => esc_html__('Default', 'streamit'),
					'color' => esc_html__('Color', 'streamit'),
					'image' => esc_html__('Image', 'streamit')
				),
				'default' => 'default'
			),

			array(
				'id' => 'footer_bg_color',
				'type' => 'color',
				'title' => esc_html__('Background color', 'streamit'),
				'subtitle' => esc_html__('Choose footer background color', 'streamit'),
				'required' => array('change_footer_background', '=', 'color'),
				'class' => 'css_prefix-sub-fields',
				'mode' => 'background',
				'transparent' => false
			),

			array(
				'id' => 'footer_bg_image',
				'type' => 'media',
				'url' => false,
				'desc' => '<i class="custom-info"></i><span class="media-label">' . esc_html__(' Upload your footer background image here', 'streamit') . '</span>',
				'required' => array('change_footer_background', '=', 'image'),
				'read-only' => false,
				'class' => 'css_prefix-sub-fields',
				'title' => esc_html__('Footer Background image', 'streamit'),
				'subtitle' => esc_html__('Upload Footer image for your Website.', 'streamit'),
			),

		)
	)
);

Redux::set_section(
	$this->opt_name,
	array(
		'title' => esc_html__('Footer Option', 'streamit'),
		'id' => 'footer_section',
		'subsection' => true,
		'desc' => esc_html__('This section contains options for footer.', 'streamit'),
		'icon' => 'custom-Footer-Options',
		'fields' => array(
			array(
				'id' => 'footer_top',
				'type' => 'button_set',
				'title' => esc_html__('Display Footer Grid', 'streamit'),
				'subtitle' => esc_html__('Display Footer Grid On All page', 'streamit'),
				'options' => array(
					'yes' => esc_html__('Yes', 'streamit'),
					'no' => esc_html__('No', 'streamit')
				),
				'default' => 'yes'
			),

			array(
				'id' => 'streamit_footer_column_layout',
				'type' => 'image_select',
				'title' => esc_html__('Select Footer Layout', 'streamit'),
				'subtitle' => wp_kses(__('Choose among these structures (1-column, 2-column 3-column and 4-column) for your Footer section. To filling these column sections you should go to appearance > widget.<br />And add widgets there as per your design requirements.', 'streamit'), array('br' => array())),
				'required' => array('footer_top', '=', 'yes'),	
				'options' => array(
					1 => array(
						'title' => esc_html__('Layout 1', 'streamit'),
						'img' => get_template_directory_uri() . '/admin/assets/images/redux/footer-1-dark.png',
						'class' => 'footer-layout-1'
					),
					2 => array(
						'title' => esc_html__('Layout 2', 'streamit'),
						'img' => get_template_directory_uri() . '/admin/assets/images/redux/footer-2-dark.png',
						'class' => 'footer-layout-2'
					),
					3 => array(
						'title' => esc_html__('Layout 3', 'streamit'),
						'img' => get_template_directory_uri() . '/admin/assets/images/redux/footer-3-dark.png',
						'class' => 'footer-layout-3'
					),
					4 => array(
						'title' => esc_html__('Layout 4', 'streamit'),
						'img' => get_template_directory_uri() . '/admin/assets/images/redux/footer-4-dark.png',
						'class' => 'footer-layout-4'
					),
					5 => array(
						'title' => esc_html__('Layout 5', 'streamit'),
						'img' => get_template_directory_uri() . '/admin/assets/images/redux/footer-5-dark.png',
						'class' => 'footer-layout-5'
					),
				),
				'default' => 5,
			),

			array(
				'id' 	=> 'footer_widget_logo',
				'type' 	=> 'media',
				'title' => esc_html__('Display Footer Logo', 'streamit'),
				'subtitle' => esc_html__('Only available with "Streamit Footer Logo" widget', 'streamit'),
				'url' 	=> false,
				'read-only' => false,
				'default' => array('url' => get_template_directory_uri() . '/static/assets/images/logo.png'),
				'desc' 	=> '<i class="custom-info"></i><span class="media-label">' . esc_html__(' Upload your logo image', 'streamit') . '</span>',
			),

			array(
                'id'       => 'manage_footer_mobile_menu',
                'type'     => 'button_set',
                'title'    => esc_html__('Display Mobile Footer Menu', 'streamit'),
                'subtitle' => esc_html__('Enable or disable the mobile footer menu', 'streamit'),
                'options'  => array(
                    'yes'  => esc_html__('Yes', 'streamit'),
                    'no'   => esc_html__('No', 'streamit'),
                ),
                'default'  => 'yes',
            ),
		)
	)
);

Redux::set_section(
	$this->opt_name,
	array(
		'title' => esc_html__('Footer Copyright', 'streamit'),
		'id' => 'footer_copyright',
		'subsection' => true,
		'icon' => 'custom-CopyRight',
		'fields' => array(
			array(
				'id' => 'display_copyright',
				'type' => 'button_set',
				'title' => esc_html__('Display Copyrights', 'streamit'),
				'options' => array(
					'yes' => esc_html__('Yes', 'streamit'),
					'no' => esc_html__('No', 'streamit')
				),
				'default' => 'yes'
			),
				
		)
	),
);

Redux::set_section(
	$this->opt_name,
		array(
			'title' => esc_html__('Footer Contact', 'streamit'),
			'id' => 'footer_contact_form',
			'subsection' => true,
			'icon' => 'icon-footer-contact',
			'fields' => array(
				
				array(
					'id' => 'footer_contact_number',
					'type' => 'text',
					'title' => esc_html__('Phone Number', 'streamit'),
					'desc' => esc_html__('This options only works with streamit Footer widget', 'streamit')
				),
				array(
					'id' => 'footer_contact_address',
					'type' => 'editor',
					'title' => esc_html__('Location', 'streamit'),
					'desc' => esc_html__('This options only works with streamit Footer widget', 'streamit')
				),
				array(
					'id' => 'footer_contact_email',
					'type' => 'text',
					'title' => esc_html__('Email Address', 'streamit'),
					'desc' => esc_html__('This options only works with streamit Footer widget', 'streamit')
				),
			)
		)
);



