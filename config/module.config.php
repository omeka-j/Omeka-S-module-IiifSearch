<?php declare(strict_types=1);

namespace IiifSearch;

return [
    'controllers' => [
        'invokables' => [
            'IiifSearch\Controller\Search' => Controller\SearchController::class,
        ],
    ],
    'controller_plugins' => [
        /*
        'invokables' => [
            'jsonLd' => Mvc\Controller\Plugin\JsonLd::class,
        ],
        */
    ],
    'translator' => [
        'translation_file_patterns' => [
            [
                'type' => 'gettext',
                'base_dir' => dirname(__DIR__) . '/language',
                'pattern' => '%s.mo',
                'text_domain' => null,
            ],
        ],
    ],
    /*
    "iiifsearch" => [
        "config" => [
            'iiifsearch_url' => "https://static.ldas.jp/viewer/iiif/downloader/?manifest=",
        ]
    ],
    */
    // 依存モジュール追加
    'dependencies' => [
        'IiifServer',
    ],
];
