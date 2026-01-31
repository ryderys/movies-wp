<?php

if (!defined('ABSPATH')) {
	exit; // Exit if accessed directly.
}

/**
 * streamit\streamit\Redux_Framework\Options\SocialMedia class
 *
 * @package streamit
 */


Redux::set_section($this->opt_name, array(
	'title' => esc_html__('Social Media', 'streamit'),
	'id' => 'social_link',
	'icon' => 'custom-Social-Media',
	'fields' => array(

		array(
			'id' => 'social_media_options',
			'type' => 'sortable',
			'title' => esc_html__('Social Media Option', 'streamit'),
			'subtitle' => esc_html__('Enter social media url.', 'streamit'),
			'mode' => 'text',
			'label' => true,
			'options' => array(
				'facebook' 		=> '#',
				'x-twitter'		=> '#',
				'google-plus'	=> '',
				'github' 		=> '',
				'instagram' 	=> '#',
				'linkedin' 		=> '#',
				'tumblr'		=> '',
				'pinterest' 	=> '',
				'dribbble' 		=> '',
				'reddit' 		=> '',
				'flickr' 		=> '',
				'skype' 		=> '',
				'youtube' 	=> '',
				'vimeo' 		=> '',
				'soundcloud' 	=> '',
				'wechat' 		=> '',
				'renren' 		=> '',
				'weibo' 		=> '',
				'xing' 			=> '',
				'qq'			=> '',
				'rss'			=> '',
				'vk'			=> '',
				'behance'		=> '',
				'snapchat'		=> '',
			),
		),
	),
));
