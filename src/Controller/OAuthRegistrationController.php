<?php

namespace App\Controller;

use League\Bundle\OAuth2ServerBundle\Manager\ClientManagerInterface;
use League\Bundle\OAuth2ServerBundle\ValueObject\Grant;
use League\Bundle\OAuth2ServerBundle\ValueObject\RedirectUri;
use League\Bundle\OAuth2ServerBundle\ValueObject\Scope;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * OAuth2 Dynamic Client Registration (RFC 7591).
 * MCP clients use this to register themselves as OAuth clients automatically.
 */
class OAuthRegistrationController extends AbstractController
{
    #[Route('/oauth/register', name: 'oauth_register', methods: ['POST'])]
    public function register(Request $request, ClientManagerInterface $clientManager): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        if (!$data) {
            return new JsonResponse(['error' => 'invalid_request'], Response::HTTP_BAD_REQUEST);
        }

        $clientName = $data['client_name'] ?? 'mcp-client-'.bin2hex(random_bytes(8));
        $redirectUris = $data['redirect_uris'] ?? [];

        if (empty($redirectUris)) {
            return new JsonResponse(['error' => 'invalid_request', 'error_description' => 'redirect_uris is required'], Response::HTTP_BAD_REQUEST);
        }

        $clientId = bin2hex(random_bytes(16));

        $client = $clientManager->find($clientId);
        if (null !== $client) {
            $clientId = bin2hex(random_bytes(16));
        }

        $newClient = new \League\Bundle\OAuth2ServerBundle\Model\Client($clientName, $clientId, null);
        $newClient->setActive(true);
        $newClient->setAllowPlainTextPkce(false);

        foreach ($redirectUris as $uri) {
            $newClient->setRedirectUris(new RedirectUri($uri), ...$newClient->getRedirectUris());
        }

        $newClient->setGrants(new Grant('authorization_code'), new Grant('refresh_token'));
        $newClient->setScopes(new Scope('crm:read'));

        $clientManager->save($newClient);

        return new JsonResponse([
            'client_id' => $clientId,
            'client_name' => $clientName,
            'redirect_uris' => $redirectUris,
            'grant_types' => ['authorization_code', 'refresh_token'],
            'response_types' => ['code'],
            'scope' => 'crm:read',
            'token_endpoint_auth_method' => 'none',
        ], Response::HTTP_CREATED);
    }
}
