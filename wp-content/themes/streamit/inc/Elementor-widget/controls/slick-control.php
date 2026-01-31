<?php

namespace Elementor;

$this->add_control(
    'desk_number',
    [
        'label' => __('Desktop view', 'streamit'),
        'type' => Controls_Manager::TEXT,
        'dynamic' => [
            'active' => true,
        ],
        'label_block' => true,
        'default' => '6',
    ]
);

$this->add_control(
    'lap_number',
    [
        'label' => __('Laptop view', 'streamit'),
        'type' => Controls_Manager::TEXT,
        'dynamic' => [
            'active' => true,
        ],
        'label_block' => true,
        'default' => '4',
    ]
);


$this->add_control(
    'tab_number',
    [
        'label' => __('Tablet view', 'streamit'),
        'type' => Controls_Manager::TEXT,
        'dynamic' => [
            'active' => true,
        ],
        'label_block' => true,
        'default' => '3',
    ]
);

$this->add_control(
    'mob_number',
    [
        'label' => __('Mobile view', 'streamit'),
        'type' => Controls_Manager::TEXT,
        'dynamic' => [
            'active' => true,
        ],
        'label_block' => true,
        'default' => '2',
    ]
);

$this->add_control(
    'autoplay',
    [
        'label' => __('Autoplay', 'streamit'),
        'type' => Controls_Manager::SELECT,
        'options' => [
            'true' => __('True', 'streamit'),
            'false' => __('False', 'streamit'),
        ],
        'default' => 'false',
    ]
);

$this->add_control(
    'autoplay_speed',
    [
        'label' => __('Autoplay Speed', 'streamit'),
        'type' => Controls_Manager::TEXT,
        'label_block' => false,
        'condition' => ['autoplay' => 'true'],
        'default' => '2000',
    ]
);

$this->add_control(
    'infinite',
    [
        'label' => __('Infinite', 'streamit'),
        'type' => Controls_Manager::SELECT,
        'options' => [
            'true' => __('True', 'streamit'),
            'false' => __('False', 'streamit'),
        ],
        'default' => 'false',
    ]
);

$this->add_control(
    'speed',
    [
        'label' => __('Speed', 'streamit'),
        'type' => Controls_Manager::TEXT,
        'label_block' => true,
        'default' => '300',
    ]
);

$this->add_control(
    'nav-arrow',
    [
        'label' => __('Arrow', 'streamit'),
        'type' => Controls_Manager::SELECT,
        'default' => 'true',
        'options' => [
            'true' => __('True', 'streamit'),
            'false' => __('False', 'streamit'),
        ],
    ]
);
