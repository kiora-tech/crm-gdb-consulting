<?php

namespace App\EventListener;

use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Event\ResponseEvent;

#[AsEventListener(event: 'kernel.request', priority: 200)]
#[AsEventListener(event: 'kernel.response', priority: -200)]
#[AsEventListener(event: 'kernel.exception', priority: 200)]
class McpDebugListener
{
    public function __construct(
        private readonly LoggerInterface $logger,
    ) {
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        $request = $event->getRequest();
        if (!str_starts_with($request->getPathInfo(), '/_mcp')) {
            return;
        }

        $this->logger->error('[MCP] {method} {path} Auth={auth} Body={body}', [
            'method' => $request->getMethod(),
            'path' => $request->getPathInfo(),
            'auth' => $request->headers->has('Authorization') ? 'Bearer ...' . substr($request->headers->get('Authorization', ''), -20) : 'NONE',
            'body' => substr($request->getContent(), 0, 500),
        ]);
    }

    public function onKernelResponse(ResponseEvent $event): void
    {
        $request = $event->getRequest();
        if (!str_starts_with($request->getPathInfo(), '/_mcp')) {
            return;
        }

        $response = $event->getResponse();
        $this->logger->error('[MCP] Response {status} Content-Type={ct} Body={body}', [
            'status' => $response->getStatusCode(),
            'ct' => $response->headers->get('Content-Type'),
            'body' => substr($response->getContent(), 0, 500),
        ]);
    }

    public function onKernelException(ExceptionEvent $event): void
    {
        $request = $event->getRequest();
        if (!str_starts_with($request->getPathInfo(), '/_mcp')) {
            return;
        }

        $this->logger->critical('[MCP] EXCEPTION: {message} in {file}:{line}', [
            'message' => $event->getThrowable()->getMessage(),
            'file' => $event->getThrowable()->getFile(),
            'line' => $event->getThrowable()->getLine(),
        ]);
    }
}
