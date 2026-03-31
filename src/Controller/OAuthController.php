<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class OAuthController extends AbstractController
{
    /**
     * OAuth2 Authorization Server Metadata (RFC 8414).
     * Discovered by MCP clients to find OAuth endpoints.
     */
    #[Route('/.well-known/oauth-authorization-server', name: 'oauth_metadata', methods: ['GET'])]
    public function metadata(UrlGeneratorInterface $urlGenerator): JsonResponse
    {
        $baseUrl = $urlGenerator->generate('homepage', [], UrlGeneratorInterface::ABSOLUTE_URL);
        $baseUrl = rtrim($baseUrl, '/');

        return new JsonResponse([
            'issuer' => $baseUrl,
            'authorization_endpoint' => $baseUrl.'/authorize',
            'token_endpoint' => $baseUrl.'/token',
            'registration_endpoint' => $baseUrl.'/oauth/register',
            'response_types_supported' => ['code'],
            'grant_types_supported' => ['authorization_code', 'refresh_token'],
            'code_challenge_methods_supported' => ['S256'],
            'token_endpoint_auth_methods_supported' => ['client_secret_post', 'none'],
            'scopes_supported' => ['crm:read'],
        ]);
    }
}
