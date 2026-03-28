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

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Test route to preview error pages in dev. Throws an HTTP exception with the given status code.
 */
class TestErrorController extends AbstractController
{
    #[Route('/test-error/{code}', name: 'app_test_error', requirements: ['code' => '\d+'], methods: ['GET'])]
    public function __invoke(int $code): never
    {
        throw new HttpException($code);
    }
}
