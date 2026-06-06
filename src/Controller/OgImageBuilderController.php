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

use App\Entity\CardPrinting;
use App\Service\CardIdentity\CardCodeResolver;
use App\Service\OgImage\CardFanImageGenerator;
use League\Flysystem\FilesystemException;
use League\Flysystem\FilesystemOperator;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\ExpressionLanguage\Expression;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Uid\Uuid;

/**
 * Admin tool that composites a few cards into an OG-ready "card fan" image.
 *
 * Editors paste card codes, the tool generates a 1200×630 PNG stored on the
 * editor upload storage, and the resulting URL is meant to be copied into the
 * ogImage field of a deck, archetype, or CMS page (F18.30/F18.31).
 *
 * @see docs/features.md F18.32 — Card-fan OG image builder
 * @see docs/technicalities/og_image_builder.md
 */
#[IsGranted(new Expression("is_granted('ROLE_CMS_EDITOR') or is_granted('ROLE_ARCHETYPE_EDITOR')"))]
class OgImageBuilderController extends AbstractController
{
    private const int MIN_CARDS = 2;
    private const int MAX_CARDS = 6;

    public function __construct(
        private readonly CardCodeResolver $cardCodeResolver,
        private readonly CardFanImageGenerator $cardFanImageGenerator,
        private readonly FilesystemOperator $editorUploadStorage,
    ) {
    }

    #[Route('/admin/og-image-builder', name: 'app_admin_og_image_builder', methods: ['GET'])]
    public function index(): Response
    {
        return $this->render('admin/og_image_builder/index.html.twig');
    }

    /**
     * Resolve the submitted card codes, composite the fan, store it and return its URL.
     *
     * Cards that fail to resolve are reported per-code; the image is generated
     * from the resolved subset as long as at least one code resolves.
     */
    #[Route('/admin/og-image-builder/generate', name: 'app_admin_og_image_builder_generate', methods: ['POST'])]
    public function generate(Request $request): JsonResponse
    {
        /** @var array{codes?: mixed} $payload */
        $payload = json_decode($request->getContent(), true) ?? [];
        $codes = $payload['codes'] ?? null;

        if (!\is_array($codes)) {
            return $this->json(['error' => 'invalid_payload'], Response::HTTP_BAD_REQUEST);
        }

        $codes = array_values(array_filter(
            array_map(static fn (mixed $code): string => \is_string($code) ? trim($code) : '', $codes),
            static fn (string $code): bool => '' !== $code,
        ));

        if (\count($codes) < self::MIN_CARDS || \count($codes) > self::MAX_CARDS) {
            return $this->json(['error' => 'invalid_card_count'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $printings = [];
        $cards = [];

        foreach ($codes as $code) {
            $printing = $this->cardCodeResolver->resolve($code);

            if ($printing instanceof CardPrinting) {
                $printings[] = $printing;
                $cards[] = [
                    'code' => $code,
                    'status' => 'resolved',
                    'name' => $printing->getCardIdentity()->getName(),
                ];
            } else {
                $cards[] = [
                    'code' => $code,
                    'status' => 'not_found',
                    'name' => null,
                ];
            }
        }

        if ([] === $printings) {
            return $this->json(['error' => 'no_card_resolved', 'cards' => $cards], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $imageData = $this->cardFanImageGenerator->generate($printings);
        $filename = Uuid::v4()->toRfc4122().'.png';

        try {
            $this->editorUploadStorage->write($filename, $imageData);
        } catch (FilesystemException) {
            return $this->json(['error' => 'storage_failure'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        $url = $this->generateUrl('app_editor_image_show', ['filename' => $filename]);

        return $this->json(['url' => $url, 'cards' => $cards]);
    }
}
