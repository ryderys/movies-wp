<?php


if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}


Redux::set_section($this->opt_name, array(
    'title'         => esc_html__('Starring/Genres/Tags', 'streamit'),
    'id'            => 'custom-text-custom-options',
    'icon'          => 'custom-Information',
    'subsection'    => true,
    'fields'        => array(
        array(
            'id'        => 'streamit_starring_title',
            'type'      => 'text',
            'title'     => esc_html__('Starring title', 'streamit'),
            'default'   => esc_html__('Starring', 'streamit')
        ),
        array(
            'id'        => 'streamit_genres_title',
            'type'      => 'text',
            'title'     => esc_html__('Genres title', 'streamit'),
            'default'   => esc_html__('Genres', 'streamit')
        ),
        array(
            'id'        => 'streamit_tag_title',
            'type'      => 'text',
            'title'     => esc_html__('Tag title', 'streamit'),
            'default'   => esc_html__('Tags', 'streamit')
        ),

        array(
            'id'        => 'streamit_genere_tag_category_item',
            'type'      => 'button_set',
            'title'     => esc_html__('Genres, Tags , Categories Page Setting', 'streamit'),
            'subtitle'  => esc_html__('Turn on to display the Post ', 'streamit'),
            'options'   => array(
                'load_more'         => esc_html__('Load More', 'streamit'),
                'infinite_scroll'   => esc_html__('Infinite Scroll', 'streamit')
            ),
            'default' => esc_html__('load_more', 'streamit'),
        ),
        array(
            'id'        => 'streamit_genere_tag_category_display_loadmore_text',
            'type'      => 'text',
            'title'     => esc_html__('Load More button text', 'streamit'),
            'default'   => esc_html__('Load More', 'streamit'),
            'required'  => array('streamit_genere_tag_category_item', '=', 'load_more'),
        ),
        array(
            'id'        => 'streamit_genere_tag_category_loadmore_text_2',
            'type'      => 'text',
            'title'     => esc_html__('Load More button text', 'streamit'),
            'default'   => esc_html__('Loading...', 'streamit'),
            'required'  => array('streamit_genere_tag_category_item', '=', 'load_more'),
        ),
        array(
            'id'        => 'streamit_genere_tag_category_post_per_page',
            'type'      => 'text',
            'title'     => esc_html__('Item Per Page', 'streamit'),
            'default'   => '10',
        ),
    )
));
