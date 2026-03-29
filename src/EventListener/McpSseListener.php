<?php

namespace App\EventListener;

use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpKernel\Event\RequestEvent;

/**
 * Handle GET requests on /_mcp for SSE (Server-Sent Events).
 *
 * The MCP SDK only handles POST/DELETE/OPTIONS, but Claude's browser client
 * opens a GET SSE connection to receive streaming responses.
 * This listener returns an empty SSE stream to prevent 405 errors.
 */
#[AsEventListener(event: 'kernel.request', priority: 100)]
class McpSseListener
{
    public function __invoke(RequestEvent $event): void
    {
        $request = $event->getRequest();

        if ('GET' !== $request->getMethod() || '/_mcp' !== $request->getPathInfo()) {
            return;
        }

        $response = new StreamedResponse(function (): void {
            echo ": keepalive\n\n";
            @ob_flush();
            flush();
        });

        $response->headers->set('Content-Type', 'text/event-stream');
        $response->headers->set('Cache-Control', 'no-cache');
        $response->headers->set('Connection', 'keep-alive');
        $response->headers->set('Access-Control-Allow-Origin', '*');
        $response->headers->set('Access-Control-Allow-Methods', 'GET, POST, DELETE, OPTIONS');
        $response->headers->set('Access-Control-Allow-Headers', 'Accept,Authorization,Content-Type,Last-Event-ID,Mcp-Protocol-Version,Mcp-Session-Id');
        $response->headers->set('Access-Control-Expose-Headers', 'Mcp-Session-Id');

        $event->setResponse($response);
    }
}
