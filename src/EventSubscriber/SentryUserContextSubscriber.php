<?php

namespace App\EventSubscriber;

use App\Entity\User;
use App\EventProcessor\SentryFingerprintProcessor;
use Sentry\SentrySdk;
use Sentry\State\Scope;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

class SentryUserContextSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly TokenStorageInterface $tokenStorage,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => ['onKernelRequest', 0],
        ];
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $hub = SentrySdk::getCurrentHub();

        // Configure user context if authenticated
        $user = $this->tokenStorage->getToken()?->getUser();
        if ($user instanceof User) {
            $hub->configureScope(function (Scope $scope) use ($user): void {
                $scope->setUser([
                    'id' => (string) $user->getId(),
                    'email' => $user->getEmail(),
                    'username' => $user->getUserIdentifier(),
                ]);
            });
        }

        // Add fingerprint processor for better error grouping
        $hub->configureScope(function (Scope $scope): void {
            $scope->addEventProcessor(new SentryFingerprintProcessor());
        });
    }
}
