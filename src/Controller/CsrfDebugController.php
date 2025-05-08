<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

/**
 * Controller pour déboguer les problèmes CSRF en production.
 * À utiliser uniquement temporairement et à supprimer après résolution des problèmes.
 */
#[Route('/debug', name: 'app_debug_')]
class CsrfDebugController extends AbstractController
{
    #[Route('/csrf-info', name: 'csrf_info')]
    public function csrfInfo(Request $request, CsrfTokenManagerInterface $csrfTokenManager): Response
    {
        // Limiter l'accès aux administrateurs uniquement
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        // Générer un token CSRF pour le test
        $token = $csrfTokenManager->getToken('debug');

        // Collecter des informations sur la requête et l'environnement
        $info = [
            'request' => [
                'client_ip' => $request->getClientIp(),
                'real_method' => $request->getRealMethod(),
                'method' => $request->getMethod(),
                'host' => $request->getHost(),
                'scheme' => $request->getScheme(),
                'port' => $request->getPort(),
                'secure' => $request->isSecure(),
                'ajax' => $request->isXmlHttpRequest(),
                'turbo' => $request->headers->has('Turbo-Frame'),
            ],
            'headers' => [],
            'server' => [],
            'cookies' => [],
            'trusted_proxies' => $_ENV['SYMFONY_TRUSTED_PROXIES'] ?? 'Non défini',
            'trusted_headers' => $_ENV['SYMFONY_TRUSTED_HEADERS'] ?? 'Non défini',
            'csrf_test_token' => $token->getValue(),
        ];

        // Collecter tous les en-têtes
        foreach ($request->headers->all() as $name => $value) {
            $info['headers'][$name] = $value;
        }

        // Collecter les variables serveur pertinentes
        $serverKeys = [
            'REMOTE_ADDR', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_FORWARDED_HOST',
            'HTTP_X_FORWARDED_PORT', 'HTTP_X_FORWARDED_PROTO', 'SERVER_NAME',
            'SERVER_PORT', 'SERVER_ADDR', 'HTTPS',
        ];

        foreach ($serverKeys as $key) {
            $info['server'][$key] = $_SERVER[$key] ?? 'Non défini';
        }

        // Collecter les cookies
        foreach ($request->cookies as $name => $value) {
            $info['cookies'][$name] = substr($value, 0, 10).'...'; // Tronquer pour la sécurité
        }

        return $this->render('debug/csrf_info.html.twig', [
            'info' => $info,
        ]);
    }

    #[Route('/csrf-test', name: 'csrf_test', methods: ['GET'])]
    public function csrfTestForm(CsrfTokenManagerInterface $csrfTokenManager): Response
    {
        // Limiter l'accès aux administrateurs uniquement
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        // Générer un token CSRF pour le test
        $token = $csrfTokenManager->getToken('csrf_test');

        return $this->render('debug/csrf_test_form.html.twig', [
            'token' => $token->getValue(),
        ]);
    }

    #[Route('/csrf-test', name: 'csrf_test_submit', methods: ['POST'])]
    public function csrfTestSubmit(Request $request, CsrfTokenManagerInterface $csrfTokenManager): Response
    {
        // Limiter l'accès aux administrateurs uniquement
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        // Vérifier le token CSRF
        $submittedToken = $request->request->get('_csrf_token');
        $headerToken = $request->headers->get('X-CSRF-TOKEN');
        $hasHeaderToken = $request->headers->has('X-CSRF-TOKEN');

        // Vérifier si le token est déjà stocké dans les attributs de requête par le subscriber
        $attributeToken = $request->attributes->get('_csrf_token_csrf_test');
        $isStoredInAttributes = null !== $attributeToken;

        // Créer un token pour validation directe
        $csrfToken = new \Symfony\Component\Security\Csrf\CsrfToken('csrf_test', $submittedToken);
        $isValid = $csrfTokenManager->isTokenValid($csrfToken);

        // Toujours collecter toutes les informations de débogage
        $debug = [
            'submitted_token' => $submittedToken,
            'header_token_exists' => $hasHeaderToken,
            'header_token' => $headerToken,
            'request_attributes' => array_keys($request->attributes->all()),
            'token_id' => 'csrf_test',
            'stored_in_attributes' => $isStoredInAttributes,
            'attribute_token' => $attributeToken,
            'validation_result' => $isValid,
            'cookies' => array_keys($request->cookies->all()),
            'session_id' => $request->getSession()->getId(),
            'method' => $request->getMethod(),
            'content_type' => $request->headers->get('Content-Type'),
            'all_headers' => $request->headers->all(),
        ];

        if (!$isValid) {
            // Forcer l'acceptation du token si les tokens correspondent
            if (($headerToken && $headerToken === $submittedToken)
                || ($attributeToken && $attributeToken === $submittedToken)) {
                return $this->json([
                    'success' => true,
                    'message' => 'CSRF token accepté (correspondance exacte)',
                    'debug' => $debug,
                ]);
            }

            return $this->json([
                'success' => false,
                'message' => 'CSRF token invalide',
                'debug' => $debug,
            ]);
        }

        return $this->json([
            'success' => true,
            'message' => 'CSRF token validé avec succès',
        ]);
    }
}
