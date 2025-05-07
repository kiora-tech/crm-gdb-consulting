<?php

namespace App\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

/**
 * This subscriber handles CSRF token extraction from custom headers
 * which is especially useful in proxy environments and with Turbo.
 */
class CsrfRequestSubscriber implements EventSubscriberInterface
{
    private RequestStack $requestStack;
    private CsrfTokenManagerInterface $csrfTokenManager;

    public function __construct(
        RequestStack $requestStack,
        CsrfTokenManagerInterface $csrfTokenManager
    ) {
        $this->requestStack = $requestStack;
        $this->csrfTokenManager = $csrfTokenManager;
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => ['onKernelRequest', 20], // Higher priority
        ];
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        
        if (!$this->isCsrfProtectedMethod($request)) {
            return;
        }
        
        // Handle CSRF token from header
        if ($request->headers->has('X-CSRF-TOKEN')) {
            $csrfToken = $request->headers->get('X-CSRF-TOKEN');
            $tokenId = $this->extractTokenId($request);
            
            if ($tokenId && $csrfToken) {
                // Toujours stocker le token dans les attributs de requête pour le debug CSRF
                $request->attributes->set('_csrf_token_' . $tokenId, $csrfToken);
                
                // Pour debug, également valider manuellement
                $token = new CsrfToken($tokenId, $csrfToken);
                $isValid = $this->csrfTokenManager->isTokenValid($token);
            }
        }
        
        // Handle debug CSRF test specifically
        if ($request->getPathInfo() === '/debug/csrf-test' && $request->isMethod('POST')) {
            $submittedToken = $request->request->get('_csrf_token');
            if ($submittedToken) {
                // Toujours stocker le token pour le debug
                $request->attributes->set('_csrf_token_csrf_test', $submittedToken);
                
                // Pour debug, également valider manuellement
                $token = new CsrfToken('csrf_test', $submittedToken);
                $isValid = $this->csrfTokenManager->isTokenValid($token);
            }
        }
    }

    private function isCsrfProtectedMethod(Request $request): bool
    {
        // CSRF protection typically applies to state-changing methods
        return in_array($request->getMethod(), ['POST', 'PUT', 'PATCH', 'DELETE']);
    }

    private function extractTokenId(Request $request): ?string
    {
        // Try to extract from cookies which could have been set by the JavaScript
        foreach ($request->cookies as $name => $value) {
            if (strpos($name, '_csrf_token') === 0) {
                return substr($name, strlen('_csrf_token_'));
            }
        }
        
        // Check for form submission in the debug controller
        if ($request->getPathInfo() === '/debug/csrf-test' && $request->isMethod('POST')) {
            return 'csrf_test';
        }
        
        // Alternative approach: use a standard token ID for all XHR/Turbo requests
        if ($request->isXmlHttpRequest() || $request->headers->has('Turbo-Frame')) {
            return 'turbo';
        }
        
        return null;
    }
}