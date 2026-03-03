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

use App\Repository\EventRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Public event listing, accessible without authentication.
 *
 * @see docs/features.md F3.2 — Event listing
 */
class EventListController extends AbstractController
{
    #[Route('/event', name: 'app_event_list', methods: ['GET'], priority: 10)]
    public function list(EventRepository $eventRepository): Response
    {
        return $this->render('event/list.html.twig', [
            'events' => $eventRepository->findUpcoming(20),
        ]);
    }
}
