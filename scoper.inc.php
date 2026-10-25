<?php
// @codingStandardsIgnoreFile -- PHP-Scoper build configuration, never loaded by WordPress.

declare(strict_types=1);

defined('ABSPATH') || exit;

use Isolated\Symfony\Component\Finder\Finder;

return [
    'prefix' => 'BerqUsedCSS\\Deps',

    'finders' => [
        Finder::create()
            ->files()
            ->ignoreVCS(true)
            ->notName('/LICENSE|.*\\.md|.*\\.dist|Makefile|composer\\.json|composer\\.lock/')
            ->exclude([
                'doc',
                'test',
                'test_old',
                'tests',
                'Tests',
                'vendor-bin',
            ])
            ->in('vendor'),
        Finder::create()->append([
            'composer.json',
        ]),
    ],

    'exclude-namespaces' => [],

    'exclude-classes' => [],

    'exclude-functions' => [],

    'exclude-constants' => [],

    'expose-global-constants' => true,
    'expose-global-classes'   => true,
    'expose-global-functions' => true,
];
