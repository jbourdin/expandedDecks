<?php

declare(strict_types=1);

$finder = (new PhpCsFixer\Finder())
    ->in(__DIR__)
    ->exclude('var')
    ->exclude('vendor')
    ->exclude('node_modules')
;

return (new PhpCsFixer\Config())
    ->setRiskyAllowed(true)
    ->setRules([
        '@Symfony' => true,
        '@Symfony:risky' => true,
        'declare_strict_types' => true,
        'void_return' => true,
        'header_comment' => [
            'header' => <<<'HEADER'
                This file is part of the Expanded Decks project.

                (c) Expanded Decks contributors

                For the full copyright and license information, please view the LICENSE
                file that was distributed with this source code.
                HEADER,
            'location' => 'after_declare_strict',
            'comment_type' => 'comment',
        ],
        'ordered_imports' => [
            'sort_algorithm' => 'alpha',
            'imports_order' => ['class', 'function', 'const'],
        ],
    ])
    ->setFinder($finder)
;
