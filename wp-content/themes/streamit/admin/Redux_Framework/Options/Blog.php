<?php

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}


/**
 * streamit\streamit\Redux_Framework\Options\Blog
 *
 * @package streamit
 */
Redux::set_section($this->opt_name, array(
	'title' => esc_html__('Blog', 'streamit'),
	'id'    => 'blog',
	'icon'  => 'custom-Blog',
	'has_group_title' => __('Settings', 'streamit'),
	'customizer_width' => '500px',
));

Redux::set_section($this->opt_name, array(
	'title' 	=> esc_html__('General Blogs', 'streamit'),
	'id'    	=> 'blog-section',
	'subsection' => true,
	'icon' 		=> 'custom-General-Blog',
	'desc'  	=> esc_html__('This section contains options for blog.', 'streamit'),
	'fields' 	=> array(

		array(
			'id'        => 'post_sidebar_setting',
			'type'      => 'image_select',
			'title'     => esc_html__('Blog page layout', 'streamit'),
			'subtitle'  => wp_kses(__('Choose among these structures (Right Sidebar, Left Sidebar, 1column, 2column and 3column) for your blog section. To filling these column sections you should go to appearance > widget.<br />And put every widget that you want in these sections.', 'streamit'), array('br' => array())),
			'options'   => array(
				'1' => array(
					'title' => esc_html__('No idebar', 'streamit'),
					'img' => get_template_directory_uri() . '/admin/assets/images/redux/one-column-dark.png',
					'class' => 'one-column'
				),
				'2' => array(
					'title' => esc_html__('Right sidebar', 'streamit'),
					'img' => get_template_directory_uri() . '/admin/assets/images/redux/right-sidebar-dark.png',
					'class' => 'right-sidebar'
				),
				'3' => array(
					'title' => esc_html__('Left sidebar', 'streamit'),
					'img' => get_template_directory_uri() . '/admin/assets/images/redux/left-sidebar-dark.png',
					'class' => 'left-sidebar'
				),
			),
			'default'   => '2',
		),
		array(
			'id'        => 'post_conent_col',
			'type'      => 'image_select',
			'title'     => esc_html__('Blog Content Column', 'streamit'),
			'subtitle'  => wp_kses(__('Choose among these structures (1column, 2column and 3column) for your blog section. To filling these column sections you should go to appearance > widget.<br />And put every widget that you want in these sections.', 'streamit'), array('br' => array())),
			'options'   => array(
				'1' => array(
					'title' => esc_html__('One column', 'streamit'),
					'img' => get_template_directory_uri() . '/admin/assets/images/redux/one-column-dark.png',
					'class' => 'one-column'
				),
				'2' => array(
					'title' => esc_html__('Two column', 'streamit'),
					'img' => get_template_directory_uri() . '/admin/assets/images/redux/two-column-dark.png',
					'class' => 'two-column'
				),
				'3' => array(
					'title' => esc_html__('Three column', 'streamit'),
					'img' => get_template_directory_uri() . '/admin/assets/images/redux/three-column-dark.png',
					'class' => 'three-column'
				),
			),
			'default'   => '1',
		),
		
		array(
			'id'        => 'display_pagination',
			'type'      => 'button_set',
			'title'     => esc_html__('Pagination', 'streamit'),
			'subtitle' => esc_html__('Turn on to display pagination for the blog page.', 'streamit'),
			'options'   => array(
				'yes' => esc_html__('On', 'streamit'),
				'no' => esc_html__('Off', 'streamit')
			),
			'default'   => 'yes'
		),

		array(
			'id'        => 'streamit_display_pagination',
			'type'      => 'button_set',
			'title'     => esc_html__('Post Settings', 'streamit'),
			'subtitle' 	=> esc_html__('Turn on to display the Post ', 'streamit'),
			'options'   => array(
				'pagination' 		=> esc_html__('Pagination', 'streamit'),
				'load_more' 		=> 'بارگذاری بیشتر',
				'infinite_scroll' 	=> esc_html__('Infinite Scroll', 'streamit')
			),
			'default'   => 'infinite_scroll'
		),
		array(
			'id'        => 'streamit_display_blog_loadmore_text',
			'type'      => 'text',
			'title'     => esc_html__('Load More button text', 'streamit'),
			'default'   => 'بارگذاری بیشتر',
			'required'  => array('streamit_display_pagination', '=', 'load_more'),
			'class'		=> 'css_prefix-sub-fields',
		),
		array(
			'id'        => 'streamit_display_blog_loadmore_text_2',
			'type'      => 'text',
			'title'     => esc_html__('Load More button text', 'streamit'),
			'default'   => 'در حال بارگذاری...',
			'required'  => array('streamit_display_pagination', '=', 'load_more'),
			'class'		=> 'css_prefix-sub-fields',
		),

		array(
			'id'        => 'streamit_display_image',
			'type'      => 'button_set',
			'title'     => esc_html__('Featured Image on Blog Page', 'streamit'),
			'subtitle' 	=> esc_html__('Turn on to display featured images on the blog pages.', 'streamit'),
			'options'   => array(
				'yes' 		=> esc_html__('On', 'streamit'),
				'no' 		=> esc_html__('Off', 'streamit')
			),
			'default'   => 'yes'
		),
	)
));

Redux::set_section($this->opt_name, array(
	'title'      => esc_html__('Blog Single Post', 'streamit'),
	'desc'  => esc_html__('This section contains options for single Post.', 'streamit'),
	'id'         => 'basic',
	'subsection' => true,
	'icon' => 'custom-Single-Blog',
	'fields'     => array(

		array(
			'id'        => 'post_single_sidebar_setting',
			'type'      => 'image_select',
			'title'     => esc_html__('Blog page Setting', 'streamit'),
			'subtitle'  => wp_kses(__('Choose among these structures (Right Sidebar, Left Sidebar, 1column, 2column and 3column) for your blog section. To filling these column sections you should go to appearance > widget.<br />And put every widget that you want in these sections.', 'streamit'), array('br' => array())),
			'options'   => array(
				'1' => array(
					'title' => esc_html__('Full width', 'streamit'),
					'img' => get_template_directory_uri() . '/admin/assets/images/redux/one-column-dark.png',
					'class' => 'one-column'
				),	
				'2' => array(
					'title' => esc_html__('Right sidebar', 'streamit'),
					'img' => get_template_directory_uri() . '/admin/assets/images/redux/right-sidebar-dark.png',
					'class' => 'right-sidebar'
				),
				'3' => array(
					'title' => esc_html__('Left sidebar', 'streamit'),
					'img' => get_template_directory_uri() . '/admin/assets/images/redux/left-sidebar-dark.png',
					'class' => 'left-sidebar'
				),
			),
			'default'   => '1',
		),

		array(
			'id'        => 'display_comment',
			'type'      => 'button_set',
			'title'     => esc_html__('Comments', 'streamit'),
			'subtitle' 	=> esc_html__('Turn on to display comments', 'streamit'),
			'options'   => array(
				'yes' 		=> esc_html__('On', 'streamit'),
				'no' 		=> esc_html__('Off', 'streamit')
			),
			'default'   => 'yes'
		),

		/* featured Image hide option */
		array(
			'id'       => 'posts_select',
			'type'     => 'select',
			'multi'    => true,
			'title'    => esc_html__( 'Select Posts for hide Feature Images', 'streamit' ),
			'options'  => [
				'video'   => 'Video Format',
				'quote'   => 'Quote Format',
				'link'    => 'Link Format',
				'audio'   => 'Audio Format',
				'gallery' => 'Gallery Format',
				'image'   => 'Image Format'
			],
		),
	)
));
