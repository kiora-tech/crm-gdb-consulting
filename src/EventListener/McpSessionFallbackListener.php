<?php

namespace App\EventListener;

use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Event\ResponseEvent;

/**
 * Convert 404 "Session not found" responses on /_mcp to JSON-RPC error with 200 status.
 *
 * Claude sometimes calls tools/call before initialize, getting a 404.
 * Returning a proper JSON-RPC error with 200 status lets Claude handle it gracefully
 * and retry with a proper initialize flow.
 */
#[AsEventListener(event: 'kernel.response', priority: 100)]
class McpSessionFallbackListener
{
    public function __invoke(ResponseEvent $event): void
    {
        $request = $event->getRequest();
        $response = $event->getResponse();

        if ('/_mcp' !== $request->getPathInfo()) {
            return;
        }

        if (404 !== $response->getStatusCode()) {
            return;
        }

        $content = $response->getContent();
        if (false !== $content && str_contains($content, 'Session not found')) {
            $body = json_decode($request->getContent(), true);
            $id = $body['id'] ?? '';

            $event->setResponse(new JsonResponse([
                'jsonrpc' => '2.0',
                'id' => $id,
                'error' => [
                    'code' => -32600,
                    'message' => 'Session expired. Please reinitialize.',
                ],
            ], 200, [
                'Access-Control-Allow-Origin' => '*',
                'Access-Control-Allow-Methods' => 'GET, POST, DELETE, OPTIONS',
                'Access-Control-Allow-Headers' => 'Accept,Authorization,Content-Type,Last-Event-ID,Mcp-Protocol-Version,Mcp-Session-Id',
                'Access-Control-Expose-Headers' => 'Mcp-Session-Id',
            ]));
        }
    }
}
