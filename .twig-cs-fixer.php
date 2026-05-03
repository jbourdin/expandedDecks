<?php

declare(strict_types=1);

/*
 * This file is part of the Expanded Decks project.
 *
 * (c) Expanded Decks contributors
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

$finder = new TwigCsFixer\File\Finder();
$finder->in(__DIR__.'/templates');

$config = new TwigCsFixer\Config\Config();
$config->setFinder($finder);

return $config;
