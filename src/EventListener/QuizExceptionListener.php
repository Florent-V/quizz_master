<?php

declare(strict_types=1);

namespace App\EventListener;

use App\Quiz\Exception\QuizBadRequestException;
use App\Quiz\Exception\QuizConflictException;
use App\Quiz\Exception\QuizException;
use App\Quiz\Exception\QuizForbiddenException;
use App\Quiz\Exception\QuizNotFoundException;
use App\Quiz\Exception\QuizUnprocessable;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\KernelEvents;

#[AsEventListener(event: KernelEvents::EXCEPTION, priority: 10)]
readonly class QuizExceptionListener
{
    public function __construct(
        private string $environment = 'prod',
    ) {
    }

    public function onKernelException(ExceptionEvent $event): void
    {
        $exception = $event->getThrowable();

        // Ne traiter que nos erreurs métier
        if (!$exception instanceof QuizException) {
            return;
        }

        $request = $event->getRequest();

        // On ne traite que les routes API/JSON
        if (!$this->shouldReturnJson($request)) {
            return;
        }

        $statusCode = $this->getHttpStatusCode($exception);
        $response   = $this->createJsonResponse($exception, $statusCode);
        $event->setResponse($response);
    }

    private function shouldReturnJson(Request $request): bool
    {
        // 1. Route commence par /api/
        $route = $request->getPathInfo();
        if (str_starts_with($route, '/api/')) {
            return true;
        }

        // 2. Header Accept contient application/json
        $acceptHeader = $request->headers->get('Accept', '');
        if (str_contains($acceptHeader, 'application/json')) {
            return true;
        }

        // 3. Header Content-Type est application/json
        if ('json' === $request->getContentTypeFormat()) {
            return true;
        }

        // 4. Paramètre _format=json
        if ('json' === $request->get('_format')) {
            return true;
        }

        // 5. Header X-Requested-With: XMLHttpRequest (requête AJAX)
        if ($request->isXmlHttpRequest()) {
            return true;
        }

        return false;
    }

    private function createJsonResponse(QuizException $exception, int $statusCode): JsonResponse
    {
        $data = [
            'error' => [
                'message' => $exception->getMessage(),
                'code'    => $statusCode,
                'type'    => (new \ReflectionClass($exception))->getShortName(),
            ],
        ];

        // En développement, ajouter plus d'informations
        if ('dev' === $this->environment) {
            $data['error']['file']  = $exception->getFile();
            $data['error']['line']  = $exception->getLine();
            $data['error']['trace'] = $exception->getTraceAsString();
        }

        return new JsonResponse($data, $statusCode);
    }

    private function getHttpStatusCode(\Throwable $exception): int
    {
        return match (get_class($exception)) {
            QuizBadRequestException::class => 400,
            QuizConflictException::class   => 409,
            QuizForbiddenException::class  => 403,
            QuizNotFoundException::class   => 404,
            QuizUnprocessable::class       => 422,
            default                        => 500,
        };
    }
}
