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
use App\Entity\ArchetypeTranslationRevision;
use App\Entity\Deck;
use App\Entity\DeckTranslationRevision;
use App\Entity\MenuCategory;
use App\Entity\MenuCategoryTranslationRevision;
use App\Entity\Page;
use App\Entity\PageTranslationRevision;
use App\Entity\TranslationRevisionInterface;
use App\Entity\User;
use App\Security\TranslationTarget;
use App\Security\Voter\TranslationVoter;
use App\Service\Translation\StaleTranslationSourceException;
use App\Service\Translation\TranslationDraftFieldUpdater;
use App\Service\Translation\TranslationDraftProvider;
use App\Service\Translation\TranslationQueueProvider;
use App\Service\Translation\TranslationReviewService;
use App\Service\Translation\TranslationViewDataBuilder;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Workflow\Exception\NotEnabledTransitionException;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Translation workspace: queue (F9.12) and contextual translation view
 * (F9.10 / F9.11 / F9.13). All state changes go through the F9.9 services;
 * this controller only resolves content, checks access, and shapes JSON.
 *
 * @see docs/features.md F9.10 — Translator entry points & contextual translation view
 * @see docs/features.md F9.11 — Moderation review mode
 * @see docs/features.md F9.12 — Translation queue
 */
#[Route('/admin/translations')]
#[IsGranted('ROLE_USER')]
class TranslationController extends AbstractAppController
{
    use AjaxCsrfTrait;

    private const array CONTENT_TYPES = [
        'page' => Page::class,
        'archetype' => Archetype::class,
        'menu_category' => MenuCategory::class,
        'deck' => Deck::class,
    ];

    private const array REVISION_TYPES = [
        'page' => PageTranslationRevision::class,
        'archetype' => ArchetypeTranslationRevision::class,
        'menu_category' => MenuCategoryTranslationRevision::class,
        'deck' => DeckTranslationRevision::class,
    ];

    public function __construct(
        TranslatorInterface $translator,
        private readonly EntityManagerInterface $em,
        private readonly TranslationQueueProvider $queueProvider,
        private readonly TranslationViewDataBuilder $viewDataBuilder,
        private readonly TranslationDraftProvider $draftProvider,
        private readonly TranslationDraftFieldUpdater $fieldUpdater,
        private readonly TranslationReviewService $reviewService,
    ) {
        parent::__construct($translator);
    }

    #[Route('', name: 'app_admin_translation_queue', methods: ['GET'])]
    public function queue(): Response
    {
        $this->denyUnlessWorkspaceMember();

        return $this->render('admin/translation/queue.html.twig');
    }

    #[Route('/queue-data', name: 'app_admin_translation_queue_data', methods: ['GET'])]
    public function queueData(): JsonResponse
    {
        $this->denyUnlessWorkspaceMember();
        $user = $this->workspaceUser();

        $isTranslator = $this->isGranted('ROLE_TRANSLATION_EDITOR');
        $isModerator = $this->isGranted('ROLE_TRANSLATION_MODERATOR');

        return new JsonResponse([
            'contributor' => $isTranslator ? $this->queueProvider->contributorQueue($user) : null,
            'reviewer' => $isModerator ? $this->queueProvider->reviewerQueue($user) : null,
        ]);
    }

    /**
     * Contextual translation view. Archetypes open as a combined context
     * with one section per variant (F9.13); other types as a single section.
     */
    #[Route('/{type}/{id}/{locale}', name: 'app_admin_translation_edit', requirements: ['type' => 'page|archetype|menu_category', 'id' => '\d+', 'locale' => '[a-z]{2}'], methods: ['GET'])]
    public function edit(string $type, int $id, string $locale): Response
    {
        $this->denyUnlessWorkspaceMember();
        $content = $this->contentOr404($type, $id);

        $canTranslate = $this->isGranted(TranslationVoter::TRANSLATE, new TranslationTarget($content, $locale));
        $canModerate = $this->isGranted('ROLE_TRANSLATION_MODERATOR');
        if (!$canTranslate && !$canModerate) {
            throw $this->createAccessDeniedException('Neither translator of this locale nor moderator.');
        }

        $sections = $content instanceof Archetype
            ? $this->viewDataBuilder->buildArchetypeContext($content, $locale)
            : [$this->viewDataBuilder->buildSection($content, $locale)];

        return $this->render('admin/translation/edit.html.twig', [
            'sections' => $sections,
            'contentType' => $type,
            'contentId' => $id,
            'locale' => $locale,
            'canTranslate' => $canTranslate,
            'canModerate' => $canModerate,
        ]);
    }

    /**
     * Autosave: creates the draft on first write (US-T3/US-T4), then applies
     * the posted `#[Translatable]` fields.
     */
    #[Route('/{type}/{id}/{locale}/draft', name: 'app_admin_translation_draft', requirements: ['type' => 'page|archetype|menu_category|deck', 'id' => '\d+', 'locale' => '[a-z]{2}'], methods: ['POST'])]
    public function saveDraft(string $type, int $id, string $locale, Request $request): JsonResponse
    {
        if (null !== $csrfError = $this->invalidAjaxCsrfResponse($request)) {
            return $csrfError;
        }

        $content = $this->contentOr404($type, $id);
        $this->denyAccessUnlessGranted(TranslationVoter::TRANSLATE, new TranslationTarget($content, $locale));

        /** @var array<string, mixed> $payload */
        $payload = $request->toArray();
        /** @var array<string, mixed> $fields */
        $fields = \is_array($payload['fields'] ?? null) ? $payload['fields'] : [];

        $draft = $this->draftProvider->findOrCreateDraft($content, $locale);
        $applied = $this->fieldUpdater->apply($draft, $fields);
        $this->em->flush();

        return new JsonResponse([
            'revisionId' => $draft->getId(),
            'state' => $draft->getState()->value,
            'applied' => $applied,
        ]);
    }

    #[Route('/{type}/{id}/{locale}/submit', name: 'app_admin_translation_submit', requirements: ['type' => 'page|archetype|menu_category|deck', 'id' => '\d+', 'locale' => '[a-z]{2}'], methods: ['POST'])]
    public function submit(string $type, int $id, string $locale, Request $request): JsonResponse
    {
        if (null !== $csrfError = $this->invalidAjaxCsrfResponse($request)) {
            return $csrfError;
        }

        $content = $this->contentOr404($type, $id);
        $pending = $this->draftProvider->findPending($content, $locale);
        if (!$pending instanceof TranslationRevisionInterface) {
            return new JsonResponse(['error' => 'No pending revision.'], Response::HTTP_NOT_FOUND);
        }

        try {
            $this->reviewService->submit($pending);
        } catch (StaleTranslationSourceException $exception) {
            return new JsonResponse(
                ['error' => $this->translator->trans($exception->getMessage())],
                Response::HTTP_CONFLICT,
            );
        } catch (NotEnabledTransitionException) {
            return new JsonResponse(['error' => 'Invalid state.'], Response::HTTP_CONFLICT);
        }

        return new JsonResponse(['state' => $pending->getState()->value]);
    }

    #[Route('/{type}/revisions/{id}/approve', name: 'app_admin_translation_approve', requirements: ['type' => 'page|archetype|menu_category|deck', 'id' => '\d+'], methods: ['POST'])]
    public function approve(string $type, int $id, Request $request): JsonResponse
    {
        return $this->review($type, $id, $request, 'approve');
    }

    #[Route('/{type}/revisions/{id}/reject', name: 'app_admin_translation_reject', requirements: ['type' => 'page|archetype|menu_category|deck', 'id' => '\d+'], methods: ['POST'])]
    public function reject(string $type, int $id, Request $request): JsonResponse
    {
        return $this->review($type, $id, $request, 'reject');
    }

    #[Route('/{type}/revisions/{id}/rework', name: 'app_admin_translation_rework', requirements: ['type' => 'page|archetype|menu_category|deck', 'id' => '\d+'], methods: ['POST'])]
    public function rework(string $type, int $id, Request $request): JsonResponse
    {
        return $this->review($type, $id, $request, 'rework');
    }

    private function review(string $type, int $id, Request $request, string $action): JsonResponse
    {
        if (null !== $csrfError = $this->invalidAjaxCsrfResponse($request)) {
            return $csrfError;
        }

        $revision = $this->em->getRepository(self::REVISION_TYPES[$type])->find($id);
        if (!$revision instanceof TranslationRevisionInterface) {
            throw new NotFoundHttpException('Unknown revision.');
        }

        try {
            match ($action) {
                'approve' => $this->reviewService->approve($revision),
                'reject' => $this->reviewService->reject($revision, $this->rejectComment($request)),
                default => $this->reviewService->rework($revision),
            };
        } catch (NotEnabledTransitionException) {
            return new JsonResponse(['error' => 'Invalid state.'], Response::HTTP_CONFLICT);
        }

        return new JsonResponse(['state' => $revision->getState()->value]);
    }

    private function rejectComment(Request $request): string
    {
        /** @var array<string, mixed> $payload */
        $payload = $request->toArray();
        $comment = $payload['comment'] ?? '';

        return \is_string($comment) ? $comment : '';
    }

    private function contentOr404(string $type, int $id): Page|Archetype|MenuCategory|Deck
    {
        $content = $this->em->getRepository(self::CONTENT_TYPES[$type])->find($id);
        if (!$content instanceof Page && !$content instanceof Archetype && !$content instanceof MenuCategory && !$content instanceof Deck) {
            throw new NotFoundHttpException('Unknown content.');
        }

        return $content;
    }

    private function denyUnlessWorkspaceMember(): void
    {
        if (!$this->isGranted('ROLE_TRANSLATION_EDITOR') && !$this->isGranted('ROLE_TRANSLATION_MODERATOR')) {
            throw $this->createAccessDeniedException('Translation workspace requires a translation role.');
        }
    }

    private function workspaceUser(): User
    {
        $user = $this->getUser();
        \assert($user instanceof User);

        return $user;
    }
}
