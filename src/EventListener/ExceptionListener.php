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

namespace App\EventListener;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\KernelEvents;
use Twig\Environment;

/**
 * Handles error responses with appropriate format:
 * - XHR / JSON requests: JSON error body with correct HTTP status
 * - Non-HTML content types: empty body with correct HTTP status
 * - Dev HTML: exception details rendered in the application template
 * - Prod HTML: falls through to the Twig error template (error.html.twig)
 */
#[AsEventListener(event: KernelEvents::EXCEPTION, priority: -64)]
class ExceptionListener
{
    public function __construct(
        private readonly Environment $twig,
        #[Autowire('%kernel.environment%')]
        private readonly string $environment,
    ) {
    }

    public function __invoke(ExceptionEvent $event): void
    {
        $request = $event->getRequest();
        $exception = $event->getThrowable();

        $statusCode = $exception instanceof HttpExceptionInterface
            ? $exception->getStatusCode()
            : Response::HTTP_INTERNAL_SERVER_ERROR;

        $statusText = Response::$statusTexts[$statusCode] ?? 'Unknown Error';

        // XHR / JSON requests → JSON response
        if ($request->isXmlHttpRequest() || 'json' === $request->getPreferredFormat('html')) {
            $body = ['error' => $statusText, 'status' => $statusCode];

            if ('prod' !== $this->environment) {
                $body['message'] = $exception->getMessage();
                $body['trace'] = $exception->getTraceAsString();
            }

            $event->setResponse(new JsonResponse($body, $statusCode));

            return;
        }

        // Non-HTML content types (images, CSS, JS, fonts, etc.) → empty body
        $acceptableTypes = $request->getAcceptableContentTypes();

        if ([] !== $acceptableTypes && !$this->acceptsHtml($acceptableTypes)) {
            $event->setResponse(new Response('', $statusCode));

            return;
        }

        // Dev HTML → render exception details in the application template
        if ('prod' !== $this->environment) {
            $html = $this->twig->render('exception/dev.html.twig', [
                'status_code' => $statusCode,
                'status_text' => $statusText,
                'exception' => $exception,
                'class_name' => $exception::class,
                'previous_class_name' => $exception->getPrevious() instanceof \Throwable ? $exception->getPrevious()::class : null,
            ]);

            $event->setResponse(new Response($html, $statusCode));
        }

        // Prod HTML falls through to the default Twig error template (error.html.twig)
    }

    /**
     * @param string[] $acceptableTypes
     */
    private function acceptsHtml(array $acceptableTypes): bool
    {
        foreach ($acceptableTypes as $type) {
            if ('text/html' === $type || 'application/xhtml+xml' === $type || '*/*' === $type) {
                return true;
            }
        }

        return false;
    }
}
