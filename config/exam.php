<?php

declare(strict_types=1);

return [
    'subjects' => [
        'default_icon' => '📘',
        'fallback_icon' => '📄',
    ],
    'slug' => [
        'random_suffix_length' => 5,
        'fallback_prefix' => 'exam',
    ],
    'scoring' => [
        'default_negative_mark_ratio' => 0.3333,
    ],
    'defaults' => [
        'status' => 'published',
        'price' => 0,
        'total_questions' => 0,
        'has_negative_marking' => false,
    ],
];
