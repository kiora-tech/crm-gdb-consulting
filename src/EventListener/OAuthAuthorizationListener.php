<?php

namespace App\EventListener;

use App\Entity\User;
use League\Bundle\OAuth2ServerBundle\Event\AuthorizationRequestResolveEvent;
use League\Bundle\OAuth2ServerBundle\OAuth2Events;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

/**
 * Auto-approve OAuth2 authorization requests for authenticated users.
 * Since MCP clients are trusted (Claude), we skip the consent screen.
 */
#[AsEventListener(event: OAuth2Events::AUTHORIZATION_REQUEST_RESOLVE)]
class OAuthAuthorizationListener
{
    public function __construct(
        private readonly Security $security,
    ) {
    }

    public function __invoke(AuthorizationRequestResolveEvent $event): void
    {
        $user = $this->security->getUser();

        if (!$user instanceof User) {
            return;
        }

        $event->setUser($user);
        $event->resolveAuthorization(AuthorizationRequestResolveEvent::AUTHORIZATION_APPROVED);
    }
}
