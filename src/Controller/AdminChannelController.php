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

use App\Entity\Channel;
use App\Form\ChannelFormType;
use App\Repository\ChannelRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * @see docs/features.md F18.6 — Admin: channel CRUD and assignment UI
 */
#[Route('/admin/channels')]
#[IsGranted('ROLE_ADMIN')]
class AdminChannelController extends AbstractAppController
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly ChannelRepository $channelRepository,
    ) {
    }

    #[Route('', name: 'app_admin_channel_list', methods: ['GET'])]
    public function list(): Response
    {
        return $this->render('admin/channel/list.html.twig', [
            'channels' => $this->channelRepository->findAll(),
        ]);
    }

    #[Route('/new', name: 'app_admin_channel_new', methods: ['GET', 'POST'])]
    public function new(Request $request): Response
    {
        $channel = new Channel();
        $form = $this->createForm(ChannelFormType::class, $channel);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->entityManager->persist($channel);
            $this->entityManager->flush();

            $this->addFlash('success', 'app.channel.created', ['%code%' => $channel->getCode()]);

            return $this->redirectToRoute('app_admin_channel_edit', ['id' => $channel->getId()]);
        }

        return $this->render('admin/channel/new.html.twig', [
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_admin_channel_edit', methods: ['GET', 'POST'], requirements: ['id' => '\d+'])]
    public function edit(Request $request, Channel $channel): Response
    {
        $form = $this->createForm(ChannelFormType::class, $channel);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->entityManager->flush();

            $this->addFlash('success', 'app.channel.updated', ['%code%' => $channel->getCode()]);

            return $this->redirectToRoute('app_admin_channel_edit', ['id' => $channel->getId()]);
        }

        return $this->render('admin/channel/edit.html.twig', [
            'channel' => $channel,
            'form' => $form,
        ]);
    }
}
