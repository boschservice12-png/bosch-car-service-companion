<?php

declare(strict_types=1);

namespace App\Shared\Presentation;

use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Uid\Uuid;

/**
 * Transformă orice excepție de pe rutele /api într-un răspuns standardizat
 * `application/problem+json` cu traceId. Nu expune detalii interne.
 */
final class ApiExceptionListener
{
    public function __construct(private readonly LoggerInterface $logger)
    {
    }

    public function onKernelException(ExceptionEvent $event): void
    {
        $request = $event->getRequest();
        if (!str_starts_with($request->getPathInfo(), '/api')) {
            return;
        }

        $throwable = $event->getThrowable();
        $traceId = (string) Uuid::v7();

        $problem = match (true) {
            $throwable instanceof ValidationFailedException =>
                ApiProblem::validation($traceId, $throwable->errors),
            $throwable instanceof \InvalidArgumentException =>
                ApiProblem::validation($traceId, ['_' => [$throwable->getMessage()]]),
            $throwable instanceof AccessDeniedException =>
                ApiProblem::forbidden($traceId),
            $throwable instanceof \DomainException =>
                ApiProblem::conflict($traceId),
            $throwable instanceof HttpExceptionInterface =>
                $this->fromHttp($throwable, $traceId),
            default => new ApiProblem('server_error', 'Eroare internă', 500, $traceId),
        };

        if ($problem->status >= 500) {
            $this->logger->error('api.unhandled_exception', [
                'traceId' => $traceId,
                'exception' => $throwable::class,
                'message' => $throwable->getMessage(),
            ]);
        }

        $event->setResponse(new JsonResponse(
            $problem->toArray(),
            $problem->status,
            ['Content-Type' => 'application/problem+json'],
        ));
    }

    private function fromHttp(HttpExceptionInterface $e, string $traceId): ApiProblem
    {
        return match ($e->getStatusCode()) {
            403 => ApiProblem::forbidden($traceId),
            404 => ApiProblem::notFound($traceId),
            409 => ApiProblem::conflict($traceId),
            default => new ApiProblem('http_error', 'Cerere invalidă', $e->getStatusCode(), $traceId),
        };
    }
}
