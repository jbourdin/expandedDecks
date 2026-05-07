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

use App\Entity\Archetype;
use App\Entity\ArchetypeTranslation;
use App\Entity\Deck;
use App\Entity\DeckCard;
use App\Entity\DeckVersion;
use App\Enum\DeckFormat;
use App\Enum\DeckStatus;
use App\Form\ArchetypeFormType;
use App\Form\ArchetypeTranslationFormType;
use App\Form\ArchetypeVariantFormType;
use App\Message\EnrichDeckVersionMessage;
use App\Repository\ArchetypeRepository;
use App\Repository\DeckRepository;
use App\Repository\DeckVersionRepository;
use App\Service\DeckListParser;
use App\Service\DeckVersionDiffer;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * @see docs/features.md F2.6 — Archetype management
 * @see docs/features.md F2.18 — Admin archetype create/edit form
 * @see docs/features.md F9.6 — Archetype localization
 */
#[Route('/admin/archetypes')]
#[IsGranted('ROLE_ARCHETYPE_EDITOR')]
class AdminArchetypeController extends AbstractAppController
{
    private const array SUPPORTED_LOCALES = ['en', 'fr'];

    public function __construct(
        TranslatorInterface $translator,
        private readonly EntityManagerInterface $em,
    ) {
        parent::__construct($translator);
    }

    #[Route('', name: 'app_admin_archetype_list', methods: ['GET'])]
    public function list(ArchetypeRepository $archetypeRepository, DeckRepository $deckRepository): Response
    {
        $archetypes = $archetypeRepository->findBy(['deletedAt' => null], ['position' => 'ASC', 'name' => 'ASC']);

        $deckCounts = [];
        foreach ($archetypes as $archetype) {
            /** @var int $id */
            $id = $archetype->getId();
            $deckCounts[$id] = $deckRepository->countAllByArchetype($archetype);
        }

        return $this->render('admin/archetype/list.html.twig', [
            'archetypes' => $archetypes,
            'deckCounts' => $deckCounts,
        ]);
    }

    /**
     * @see docs/features.md F2.18 — Admin archetype create/edit form
     */
    #[Route('/new', name: 'app_admin_archetype_new', methods: ['GET', 'POST'])]
    public function new(Request $request, ArchetypeRepository $archetypeRepository): Response
    {
        $archetype = new Archetype();
        $form = $this->createForm(ArchetypeFormType::class, $archetype);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->handlePokemonSlugs($form, $archetype);
            $this->handlePlaystyleTags($form, $archetype);
            $this->em->persist($archetype);
            $this->em->flush();

            $this->addFlash('success', 'app.archetype.created', ['%name%' => $archetype->getName()]);

            return $this->redirectToRoute('app_admin_archetype_edit', ['id' => $archetype->getId()]);
        }

        return $this->render('admin/archetype/new.html.twig', [
            'form' => $form,
            'existingTags' => $this->collectExistingTags($archetypeRepository),
        ]);
    }

    #[Route('/{id}', name: 'app_admin_archetype_edit', methods: ['GET', 'POST'], requirements: ['id' => '\d+'])]
    public function edit(Request $request, Archetype $archetype, ArchetypeRepository $archetypeRepository, DeckRepository $deckRepository): Response
    {
        $form = $this->createForm(ArchetypeFormType::class, $archetype);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->handlePokemonSlugs($form, $archetype);
            $this->handlePlaystyleTags($form, $archetype);
            $this->em->flush();

            $this->addFlash('success', 'app.archetype.updated', ['%name%' => $archetype->getName()]);

            return $this->redirectToRoute('app_admin_archetype_edit', ['id' => $archetype->getId()]);
        }

        $translationForms = $this->buildTranslationForms($archetype, $request);
        $variants = $deckRepository->findVariantsByArchetype($archetype);

        return $this->render('admin/archetype/edit.html.twig', [
            'archetype' => $archetype,
            'form' => $form,
            'existingTags' => $this->collectExistingTags($archetypeRepository),
            'translationForms' => $translationForms,
            'supportedLocales' => self::SUPPORTED_LOCALES,
            'deckCount' => $deckRepository->countAllByArchetype($archetype),
            'variants' => $variants,
        ]);
    }

    /**
     * @see docs/models/deck.md — Archetype soft-delete rules
     */
    #[Route('/{id}/delete', name: 'app_admin_archetype_delete', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function delete(Request $request, Archetype $archetype, DeckRepository $deckRepository): Response
    {
        if (!$this->isCsrfTokenValid('archetype-delete-'.$archetype->getId(), $request->getPayload()->getString('_token'))) {
            $this->addFlash('danger', 'app.common.invalid_csrf');

            return $this->redirectToRoute('app_admin_archetype_edit', ['id' => $archetype->getId()]);
        }

        if ($deckRepository->countAllByArchetype($archetype) > 0) {
            $this->addFlash('danger', 'app.admin.archetype.delete_has_decks');

            return $this->redirectToRoute('app_admin_archetype_edit', ['id' => $archetype->getId()]);
        }

        $archetype->setDeletedAt(new \DateTimeImmutable());
        $this->em->flush();

        $this->addFlash('success', 'app.flash.archetype.deleted');

        return $this->redirectToRoute('app_admin_archetype_list');
    }

    /**
     * Persist archetype ordering from drag-and-drop.
     * Expects a JSON array of archetype IDs in display order.
     *
     * @see docs/features.md F18.12 — Admin drag-and-drop archetype ordering
     */
    #[Route('/reorder', name: 'app_admin_archetype_reorder', methods: ['POST'])]
    public function reorder(Request $request, ArchetypeRepository $archetypeRepository): JsonResponse
    {
        /** @var list<int> $ids */
        $ids = json_decode($request->getContent(), true) ?? [];

        foreach ($ids as $position => $id) {
            $archetype = $archetypeRepository->find($id);
            if ($archetype instanceof Archetype) {
                $archetype->setPosition($position);
            }
        }
        $this->em->flush();

        return new JsonResponse(['ok' => true]);
    }

    /**
     * Persist variant ordering from drag-and-drop within an archetype.
     * Expects a JSON array of deck IDs in display order.
     *
     * @see docs/features.md F18.19 — Archetype variant ordering
     */
    #[Route('/{id}/variants/reorder', name: 'app_admin_archetype_variant_reorder', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function reorderVariants(Request $request, Archetype $archetype, DeckRepository $deckRepository): JsonResponse
    {
        /** @var list<int> $ids */
        $ids = json_decode($request->getContent(), true) ?? [];

        $nonCanonicalPosition = 1;
        foreach ($ids as $id) {
            $deck = $deckRepository->find($id);
            if (!$deck instanceof Deck || !$deck->isArchetypeVariant() || $deck->getArchetype()?->getId() !== $archetype->getId()) {
                continue;
            }

            if ($deck->isCanonical()) {
                $deck->setPosition(0);
            } else {
                $deck->setPosition($nonCanonicalPosition);
                ++$nonCanonicalPosition;
            }
        }
        $this->em->flush();

        return new JsonResponse(['ok' => true]);
    }

    /**
     * @see docs/features.md F9.6 — Archetype localization
     */
    #[Route('/{id}/translation/{locale}', name: 'app_admin_archetype_translation', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function saveTranslation(Request $request, Archetype $archetype, string $locale): Response
    {
        if (!\in_array($locale, self::SUPPORTED_LOCALES, true)) {
            throw $this->createNotFoundException();
        }

        $translation = $archetype->getTranslation($locale);
        $isNew = false;

        if (!$translation instanceof ArchetypeTranslation || $translation->getLocale() !== $locale) {
            $translation = new ArchetypeTranslation();
            $translation->setLocale($locale);
            $translation->setArchetype($archetype);
            $isNew = true;
        }

        $form = $this->container->get('form.factory')->createNamed(
            'archetype_translation_form_'.$locale,
            ArchetypeTranslationFormType::class,
            $translation,
        );
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            if ($isNew) {
                $archetype->addTranslation($translation);
                $this->em->persist($translation);
            }
            $this->em->flush();

            $this->addFlash('success', 'app.cms.translation_saved', ['%locale%' => strtoupper($locale)]);
        } else {
            $this->addFlash('danger', 'app.cms.translation_invalid');
        }

        return $this->redirect(
            $this->generateUrl('app_admin_archetype_edit', ['id' => $archetype->getId()]).'#pane-'.$locale
        );
    }

    /**
     * @see docs/features.md F18.15 — Admin archetype variant management
     */
    #[Route('/{id}/variants/new', name: 'app_admin_archetype_variant_new', methods: ['GET', 'POST'], requirements: ['id' => '\d+'])]
    public function newVariant(
        Request $request,
        Archetype $archetype,
        DeckListParser $parser,
        DeckVersionRepository $versionRepository,
        MessageBusInterface $messageBus,
    ): Response {
        $deck = new Deck();
        $deck->setArchetype($archetype);
        $deck->setFormat(DeckFormat::Expanded);

        $form = $this->createForm(ArchetypeVariantFormType::class, $deck, [
            'action' => $this->generateUrl('app_admin_archetype_variant_new', ['id' => $archetype->getId()]),
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->handleVariantPokemonSlugs($form, $deck);
            $this->handleCanonicalToggle($deck, $archetype);
            $this->handleOutdatedToggle($form, $deck);
            $this->em->persist($deck);
            $this->em->flush();

            $this->handleVariantRawList($form, $deck, $parser, $versionRepository, $messageBus);

            $this->addFlash('success', 'app.archetype.variant.created', ['%name%' => $deck->getName()]);

            return $this->redirectToRoute('app_admin_archetype_edit', ['id' => $archetype->getId()]);
        }

        return $this->render('admin/archetype/variant_form.html.twig', [
            'archetype' => $archetype,
            'form' => $form,
            'deck' => $deck,
            'isNew' => true,
        ]);
    }

    /**
     * @see docs/features.md F18.15 — Admin archetype variant management
     */
    #[Route('/{id}/variants/{deckId}', name: 'app_admin_archetype_variant_edit', methods: ['GET', 'POST'], requirements: ['id' => '\d+', 'deckId' => '\d+'])]
    public function editVariant(
        Request $request,
        Archetype $archetype,
        int $deckId,
        DeckRepository $deckRepository,
        DeckListParser $parser,
        DeckVersionRepository $versionRepository,
        MessageBusInterface $messageBus,
    ): Response {
        $deck = $deckRepository->find($deckId);
        if (!$deck instanceof Deck || !$deck->isArchetypeVariant() || $deck->getArchetype()?->getId() !== $archetype->getId()) {
            throw $this->createNotFoundException();
        }

        $form = $this->createForm(ArchetypeVariantFormType::class, $deck, [
            'action' => $this->generateUrl('app_admin_archetype_variant_edit', [
                'id' => $archetype->getId(),
                'deckId' => $deckId,
            ]),
        ]);

        // Pre-fill the unmapped outdated checkbox from the entity status
        $form->get('outdated')->setData($deck->isOutdated());

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->handleVariantPokemonSlugs($form, $deck);
            $this->handleCanonicalToggle($deck, $archetype);
            $this->handleOutdatedToggle($form, $deck);
            $this->em->flush();

            $this->handleVariantRawList($form, $deck, $parser, $versionRepository, $messageBus);

            $this->addFlash('success', 'app.archetype.variant.updated', ['%name%' => $deck->getName()]);

            return $this->redirectToRoute('app_admin_archetype_edit', ['id' => $archetype->getId()]);
        }

        return $this->render('admin/archetype/variant_form.html.twig', [
            'archetype' => $archetype,
            'form' => $form,
            'deck' => $deck,
            'isNew' => false,
        ]);
    }

    /**
     * @see docs/features.md F18.15 — Admin archetype variant management
     */
    #[Route('/{id}/variants/{deckId}/delete', name: 'app_admin_archetype_variant_delete', methods: ['POST'], requirements: ['id' => '\d+', 'deckId' => '\d+'])]
    public function deleteVariant(Request $request, Archetype $archetype, int $deckId, DeckRepository $deckRepository): Response
    {
        $deck = $deckRepository->find($deckId);
        if (!$deck instanceof Deck || !$deck->isArchetypeVariant() || $deck->getArchetype()?->getId() !== $archetype->getId()) {
            throw $this->createNotFoundException();
        }

        if (!$this->isCsrfTokenValid('variant-delete-'.$deckId, $request->getPayload()->getString('_token'))) {
            $this->addFlash('danger', 'app.common.invalid_csrf');

            return $this->redirectToRoute('app_admin_archetype_edit', ['id' => $archetype->getId()]);
        }

        $deck->setDeletedAt(new \DateTimeImmutable());
        $this->em->flush();

        $this->addFlash('success', 'app.archetype.variant.deleted', ['%name%' => $deck->getName()]);

        return $this->redirectToRoute('app_admin_archetype_edit', ['id' => $archetype->getId()]);
    }

    /**
     * Re-parse and re-enrich a variant's current deck version.
     */
    #[Route('/{id}/variants/{deckId}/reenrich', name: 'app_admin_archetype_variant_reenrich', methods: ['POST'], requirements: ['id' => '\d+', 'deckId' => '\d+'])]
    #[IsGranted('ROLE_TECHNICAL_ADMIN')]
    public function reenrichVariant(
        Request $request,
        Archetype $archetype,
        int $deckId,
        DeckRepository $deckRepository,
        DeckListParser $parser,
        MessageBusInterface $messageBus,
    ): Response {
        $deck = $deckRepository->find($deckId);
        if (!$deck instanceof Deck || !$deck->isArchetypeVariant() || $deck->getArchetype()?->getId() !== $archetype->getId()) {
            throw $this->createNotFoundException();
        }

        if (!$this->isCsrfTokenValid('variant-reenrich-'.$deckId, $request->getPayload()->getString('_token'))) {
            $this->addFlash('danger', 'app.common.invalid_csrf');

            return $this->redirectToRoute('app_admin_archetype_variant_edit', ['id' => $archetype->getId(), 'deckId' => $deckId]);
        }

        $version = $deck->getCurrentVersion();
        if (null === $version || null === $version->getRawList() || '' === trim($version->getRawList())) {
            $this->addFlash('warning', 'app.deck.reenrich.no_raw_list');

            return $this->redirectToRoute('app_admin_archetype_variant_edit', ['id' => $archetype->getId(), 'deckId' => $deckId]);
        }

        foreach ($version->getCards() as $card) {
            $version->removeCard($card);
            $this->em->remove($card);
        }

        $this->em->flush();

        $result = $parser->parse($version->getRawList());

        foreach ($result->cards as $parsedCard) {
            $card = new DeckCard();
            $card->setCardName($parsedCard->cardName);
            $card->setSetCode($parsedCard->setCode);
            $card->setCardNumber($parsedCard->cardNumber);
            $card->setQuantity($parsedCard->quantity);
            $card->setCardType($parsedCard->cardType);
            $version->addCard($card);
        }

        $version->setEnrichmentStatus('pending');
        $version->setMosaicImageUrl(null);
        $version->setMinifiedList(null);
        $version->setMinifiedCardViews(null);
        $version->setMinifiedMosaicImageUrl(null);

        $this->em->flush();

        /** @var int $versionId */
        $versionId = $version->getId();
        $messageBus->dispatch(new EnrichDeckVersionMessage($versionId));

        $this->addFlash('success', 'app.deck.reenrich.dispatched');

        return $this->redirectToRoute('app_admin_archetype_variant_edit', ['id' => $archetype->getId(), 'deckId' => $deckId]);
    }

    /**
     * Duplicate an archetype variant: copies name (prefixed), notes, sprites,
     * latest set, and re-parses the raw list to create a fresh DeckVersion.
     *
     * @see docs/features.md F2.24 — Expansion set boundary & outdated variant flag
     */
    #[Route('/{id}/variants/{deckId}/duplicate', name: 'app_admin_archetype_variant_duplicate', methods: ['POST'], requirements: ['id' => '\d+', 'deckId' => '\d+'])]
    public function duplicateVariant(
        Request $request,
        Archetype $archetype,
        int $deckId,
        DeckRepository $deckRepository,
        DeckListParser $parser,
        DeckVersionRepository $versionRepository,
        MessageBusInterface $messageBus,
    ): Response {
        $source = $deckRepository->find($deckId);
        if (!$source instanceof Deck || !$source->isArchetypeVariant() || $source->getArchetype()?->getId() !== $archetype->getId()) {
            throw $this->createNotFoundException();
        }

        if (!$this->isCsrfTokenValid('variant-duplicate-'.$deckId, $request->getPayload()->getString('_token'))) {
            $this->addFlash('danger', 'app.common.invalid_csrf');

            return $this->redirectToRoute('app_admin_archetype_edit', ['id' => $archetype->getId()]);
        }

        $copy = new Deck();
        $copy->setArchetype($archetype);
        $copy->setFormat($source->getFormat());
        $copy->setName($this->translator->trans('app.archetype.variant.copy_prefix', ['%name%' => $source->getName()]));
        $copy->setNotes($source->getNotes());
        $copy->setPokemonSlugs($source->getPokemonSlugs());
        $copy->setLatestSet($source->getLatestSet());

        $this->em->persist($copy);
        $this->em->flush();

        // Re-parse the raw list to create a fresh DeckVersion with enrichment
        $rawList = $source->getCurrentVersion()?->getRawList();
        if (null !== $rawList && '' !== trim($rawList)) {
            $nextVersion = $versionRepository->findMaxVersionNumber($copy) + 1;
            $result = $parser->parse($rawList);

            $version = new DeckVersion();
            $version->setDeck($copy);
            $version->setVersionNumber($nextVersion);
            $version->setRawList($rawList);

            foreach ($result->cards as $parsedCard) {
                $card = new DeckCard();
                $card->setCardName($parsedCard->cardName);
                $card->setSetCode($parsedCard->setCode);
                $card->setCardNumber($parsedCard->cardNumber);
                $card->setQuantity($parsedCard->quantity);
                $card->setCardType($parsedCard->cardType);
                $version->addCard($card);
            }

            $this->em->persist($version);
            $copy->setCurrentVersion($version);
            $this->em->flush();

            /** @var int $versionId */
            $versionId = $version->getId();
            $messageBus->dispatch(new EnrichDeckVersionMessage($versionId));
        }

        $this->addFlash('success', 'app.archetype.variant.duplicated', ['%name%' => $copy->getName()]);

        return $this->redirectToRoute('app_admin_archetype_variant_edit', [
            'id' => $archetype->getId(),
            'deckId' => $copy->getId(),
        ]);
    }

    /**
     * Version history for an archetype variant.
     *
     * @see docs/features.md F2.9 — Deck version history
     */
    #[Route('/{id}/variants/{deckId}/versions', name: 'app_admin_archetype_variant_versions', methods: ['GET'], requirements: ['id' => '\d+', 'deckId' => '\d+'])]
    public function variantVersions(
        Archetype $archetype,
        int $deckId,
        DeckRepository $deckRepository,
        DeckVersionRepository $versionRepository,
    ): Response {
        $variant = $this->findVariantOrThrow($deckRepository, $deckId, $archetype);

        return $this->render('admin/archetype/variant_versions.html.twig', [
            'archetype' => $archetype,
            'deck' => $variant,
            'versions' => $versionRepository->findByDeckOrderedByVersion($variant),
        ]);
    }

    /**
     * Compare two versions of a variant (JSON API for the React island).
     *
     * @see docs/features.md F2.9 — Deck version history
     */
    #[Route('/{id}/variants/{deckId}/versions/compare', name: 'app_admin_archetype_variant_version_compare', methods: ['GET'], requirements: ['id' => '\d+', 'deckId' => '\d+'])]
    public function variantVersionCompare(
        Archetype $archetype,
        int $deckId,
        Request $request,
        DeckRepository $deckRepository,
        DeckVersionRepository $versionRepository,
        DeckVersionDiffer $differ,
    ): JsonResponse {
        $variant = $this->findVariantOrThrow($deckRepository, $deckId, $archetype);

        $fromNumber = $request->query->getInt('from');
        $toNumber = $request->query->getInt('to');

        if ($fromNumber < 1 || $toNumber < 1) {
            throw $this->createNotFoundException('Invalid version numbers.');
        }

        $versions = $versionRepository->findByDeckOrderedByVersion($variant);
        $fromVersion = null;
        $toVersion = null;

        foreach ($versions as $version) {
            if ($version->getVersionNumber() === $fromNumber) {
                $fromVersion = $version;
            }
            if ($version->getVersionNumber() === $toNumber) {
                $toVersion = $version;
            }
        }

        if (null === $fromVersion || null === $toVersion) {
            throw $this->createNotFoundException('Version not found.');
        }

        return $this->json($differ->diff($fromVersion, $toVersion));
    }

    /**
     * Export a variant version's raw deck list as a text file.
     *
     * @see docs/features.md F2.9 — Deck version history
     */
    #[Route('/{id}/variants/{deckId}/versions/{versionNumber}/export', name: 'app_admin_archetype_variant_version_export', methods: ['GET'], requirements: ['id' => '\d+', 'deckId' => '\d+', 'versionNumber' => '\d+'])]
    public function variantVersionExport(
        Archetype $archetype,
        int $deckId,
        int $versionNumber,
        DeckRepository $deckRepository,
        DeckVersionRepository $versionRepository,
    ): Response {
        $variant = $this->findVariantOrThrow($deckRepository, $deckId, $archetype);

        $version = $versionRepository->findOneByDeckAndVersion($variant, $versionNumber);

        if (null === $version || null === $version->getRawList()) {
            throw $this->createNotFoundException();
        }

        return new Response($version->getRawList(), 200, [
            'Content-Type' => 'text/plain; charset=utf-8',
            'Content-Disposition' => \sprintf('attachment; filename="variant-%s-v%d.txt"', $variant->getShortTag(), $versionNumber),
        ]);
    }

    /**
     * Restore a previous version as the active variant version.
     *
     * @see docs/features.md F2.9 — Deck version history
     */
    #[Route('/{id}/variants/{deckId}/versions/{versionNumber}/restore', name: 'app_admin_archetype_variant_version_restore', methods: ['POST'], requirements: ['id' => '\d+', 'deckId' => '\d+', 'versionNumber' => '\d+'])]
    public function variantVersionRestore(
        Archetype $archetype,
        int $deckId,
        int $versionNumber,
        Request $request,
        DeckRepository $deckRepository,
        DeckVersionRepository $versionRepository,
        MessageBusInterface $messageBus,
    ): RedirectResponse {
        $variant = $this->findVariantOrThrow($deckRepository, $deckId, $archetype);

        if (!$this->isCsrfTokenValid('variant-version-restore-'.$deckId.'-'.$versionNumber, $request->request->getString('_token'))) {
            throw $this->createAccessDeniedException();
        }

        $version = $versionRepository->findOneByDeckAndVersion($variant, $versionNumber);

        if (null === $version || null !== $version->getDeletedAt()) {
            throw $this->createNotFoundException();
        }

        if ($variant->getCurrentVersion()?->getId() === $version->getId()) {
            $this->addFlash('warning', 'app.deck.version.already_current');

            return $this->redirectToRoute('app_admin_archetype_variant_versions', ['id' => $archetype->getId(), 'deckId' => $deckId]);
        }

        $variant->setCurrentVersion($version);
        $this->em->flush();

        if ('done' !== $version->getEnrichmentStatus()) {
            /** @var int $versionId */
            $versionId = $version->getId();
            $messageBus->dispatch(new EnrichDeckVersionMessage($versionId));
        }

        $this->addFlash('success', 'app.deck.version.restored');

        return $this->redirectToRoute('app_admin_archetype_variant_versions', ['id' => $archetype->getId(), 'deckId' => $deckId]);
    }

    /**
     * Soft-delete a previous variant version (not the current one).
     *
     * @see docs/features.md F2.9 — Deck version history
     */
    #[Route('/{id}/variants/{deckId}/versions/{versionNumber}/delete', name: 'app_admin_archetype_variant_version_delete', methods: ['POST'], requirements: ['id' => '\d+', 'deckId' => '\d+', 'versionNumber' => '\d+'])]
    public function variantVersionDelete(
        Archetype $archetype,
        int $deckId,
        int $versionNumber,
        Request $request,
        DeckRepository $deckRepository,
        DeckVersionRepository $versionRepository,
    ): RedirectResponse {
        $variant = $this->findVariantOrThrow($deckRepository, $deckId, $archetype);

        if (!$this->isCsrfTokenValid('variant-version-delete-'.$deckId.'-'.$versionNumber, $request->request->getString('_token'))) {
            throw $this->createAccessDeniedException();
        }

        $version = $versionRepository->findOneByDeckAndVersion($variant, $versionNumber);

        if (null === $version) {
            throw $this->createNotFoundException();
        }

        if ($variant->getCurrentVersion()?->getId() === $version->getId()) {
            $this->addFlash('warning', 'app.deck.version.cannot_delete_current');

            return $this->redirectToRoute('app_admin_archetype_variant_versions', ['id' => $archetype->getId(), 'deckId' => $deckId]);
        }

        $version->setDeletedAt(new \DateTimeImmutable());
        $this->em->flush();

        $this->addFlash('success', 'app.deck.version.deleted');

        return $this->redirectToRoute('app_admin_archetype_variant_versions', ['id' => $archetype->getId(), 'deckId' => $deckId]);
    }

    /**
     * Find a variant deck or throw 404.
     */
    private function findVariantOrThrow(DeckRepository $deckRepository, int $deckId, Archetype $archetype): Deck
    {
        $deck = $deckRepository->find($deckId);
        if (!$deck instanceof Deck || !$deck->isArchetypeVariant() || $deck->getArchetype()?->getId() !== $archetype->getId()) {
            throw $this->createNotFoundException();
        }

        return $deck;
    }

    /**
     * @param FormInterface<Deck> $form
     */
    private function handleVariantPokemonSlugs(FormInterface $form, Deck $deck): void
    {
        /** @var string|null $slugsJson */
        $slugsJson = $form->get('pokemonSlugs')->getData();

        if (null !== $slugsJson && '' !== $slugsJson) {
            /** @var list<string> $slugs */
            $slugs = json_decode($slugsJson, true);
            $deck->setPokemonSlugs($slugs);
        } else {
            $deck->setPokemonSlugs([]);
        }
    }

    /**
     * If this variant is set as canonical, unset any other canonical variant for the same archetype.
     */
    private function handleCanonicalToggle(Deck $deck, Archetype $archetype): void
    {
        if (!$deck->isCanonical()) {
            return;
        }

        $queryBuilder = $this->em->createQueryBuilder();
        $queryBuilder
            ->update(Deck::class, 'd')
            ->set('d.canonical', ':false')
            ->where('d.archetype = :archetype')
            ->andWhere('d.owner IS NULL')
            ->andWhere('d.canonical = :true')
            ->setParameter('false', false)
            ->setParameter('archetype', $archetype)
            ->setParameter('true', true);

        if (null !== $deck->getId()) {
            $queryBuilder
                ->andWhere('d.id != :currentId')
                ->setParameter('currentId', $deck->getId());
        }

        $queryBuilder->getQuery()->execute();
    }

    /**
     * Sync the unmapped "outdated" checkbox with DeckStatus.
     *
     * @see docs/features.md F2.24 — Expansion set boundary & outdated variant flag
     *
     * @param FormInterface<Deck> $form
     */
    private function handleOutdatedToggle(FormInterface $form, Deck $deck): void
    {
        $isOutdated = (bool) $form->get('outdated')->getData();

        if ($isOutdated) {
            $deck->setStatus(DeckStatus::Outdated);
        } elseif ($deck->isOutdated()) {
            $deck->setStatus(DeckStatus::Available);
        }
    }

    /**
     * Create a DeckVersion from rawList if provided, and dispatch enrichment.
     *
     * @param FormInterface<Deck> $form
     */
    private function handleVariantRawList(
        FormInterface $form,
        Deck $deck,
        DeckListParser $parser,
        DeckVersionRepository $versionRepository,
        MessageBusInterface $messageBus,
    ): void {
        /** @var string|null $rawList */
        $rawList = $form->get('rawList')->getData();
        if (null === $rawList || '' === trim($rawList)) {
            return;
        }

        $nextVersion = $versionRepository->findMaxVersionNumber($deck) + 1;
        $result = $parser->parse($rawList);

        $version = new DeckVersion();
        $version->setDeck($deck);
        $version->setVersionNumber($nextVersion);
        $version->setRawList($rawList);

        foreach ($result->cards as $parsedCard) {
            $card = new DeckCard();
            $card->setCardName($parsedCard->cardName);
            $card->setSetCode($parsedCard->setCode);
            $card->setCardNumber($parsedCard->cardNumber);
            $card->setQuantity($parsedCard->quantity);
            $card->setCardType($parsedCard->cardType);
            $version->addCard($card);
        }

        $this->em->persist($version);
        $deck->setCurrentVersion($version);
        $this->em->flush();

        /** @var int $versionId */
        $versionId = $version->getId();
        $messageBus->dispatch(new EnrichDeckVersionMessage($versionId));
    }

    /**
     * @param FormInterface<Archetype> $form
     */
    private function handlePokemonSlugs(FormInterface $form, Archetype $archetype): void
    {
        /** @var string|null $slugsJson */
        $slugsJson = $form->get('pokemonSlugs')->getData();

        if (null !== $slugsJson && '' !== $slugsJson) {
            /** @var list<string> $slugs */
            $slugs = json_decode($slugsJson, true);
            $archetype->setPokemonSlugs($slugs);
        } else {
            $archetype->setPokemonSlugs([]);
        }
    }

    /**
     * @see docs/features.md F2.15 — Archetype playstyle tags
     *
     * @param FormInterface<Archetype> $form
     */
    private function handlePlaystyleTags(FormInterface $form, Archetype $archetype): void
    {
        /** @var string|null $tagsJson */
        $tagsJson = $form->get('playstyleTags')->getData();

        if (null !== $tagsJson && '' !== $tagsJson) {
            /** @var list<string> $rawTags */
            $rawTags = json_decode($tagsJson, true);
            $normalized = array_values(array_unique(array_filter(array_map(self::normalizeTag(...), $rawTags))));
            $archetype->setPlaystyleTags($normalized);
        } else {
            $archetype->setPlaystyleTags([]);
        }
    }

    /**
     * Normalize a tag: only alphanumeric and spaces, casing preserved verbatim.
     *
     * @see docs/features.md F2.15 — Archetype playstyle tags
     */
    private static function normalizeTag(string $tag): string
    {
        $cleaned = preg_replace('/[^a-zA-Z0-9 ]/', '', $tag) ?? '';

        return trim(preg_replace('/\s+/', ' ', $cleaned) ?? '');
    }

    /**
     * @return array<string, FormView>
     *
     * @see docs/features.md F9.6 — Archetype localization
     */
    private function buildTranslationForms(Archetype $archetype, Request $request): array
    {
        $forms = [];

        foreach (self::SUPPORTED_LOCALES as $locale) {
            $translation = $archetype->getTranslation($locale);

            if (!$translation instanceof ArchetypeTranslation || $translation->getLocale() !== $locale) {
                $translation = new ArchetypeTranslation();
                $translation->setLocale($locale);
            }

            $form = $this->container->get('form.factory')->createNamed(
                'archetype_translation_form_'.$locale,
                ArchetypeTranslationFormType::class,
                $translation,
                [
                    'action' => $this->generateUrl('app_admin_archetype_translation', [
                        'id' => $archetype->getId(),
                        'locale' => $locale,
                    ]),
                ],
            );

            $forms[$locale] = $form->createView();
        }

        return $forms;
    }

    /**
     * Collect playstyle tag suggestions from existing archetypes.
     *
     * Folds casing variants together: for each case-insensitive key, the most-frequent casing
     * wins (insertion-order tie-break) so editors see one canonical entry in the autocomplete.
     *
     * @return list<string>
     */
    private function collectExistingTags(ArchetypeRepository $archetypeRepository): array
    {
        /** @var array<string, array<string, int>> $buckets */
        $buckets = [];
        foreach ($archetypeRepository->findBy(['deletedAt' => null]) as $archetype) {
            foreach ($archetype->getPlaystyleTags() as $tag) {
                $key = mb_strtolower($tag);
                $buckets[$key][$tag] = ($buckets[$key][$tag] ?? 0) + 1;
            }
        }

        $tags = [];
        foreach ($buckets as $casings) {
            arsort($casings);
            $tags[] = (string) array_key_first($casings);
        }
        sort($tags);

        return $tags;
    }
}
