<?php

namespace App\RemoteEvent\Yousign;

use App\RemoteEvent\Yousign\SignatureRequest\YousignSRActivatedWebhookConsumer;
use App\RemoteEvent\Yousign\SignatureRequest\YousignSRDeclinedWebhookConsumer;
use App\RemoteEvent\Yousign\SignatureRequest\YousignSRDoneWebhookConsumer;
use App\RemoteEvent\Yousign\Signer\YousignSDeclinedWebhookConsumer;
use App\RemoteEvent\Yousign\Signer\YousignSDoneWebhookConsumer;
use App\RemoteEvent\Yousign\Signer\YousignSNotifiedWebhookConsumer;
use App\RemoteEvent\Yousign\Signer\YousignSOpenedWebhookConsumer;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\ContainerInterface;
use Psr\Container\NotFoundExceptionInterface;
use Symfony\Component\RemoteEvent\Attribute\AsRemoteEventConsumer;
use Symfony\Component\RemoteEvent\Consumer\ConsumerInterface;
use Symfony\Component\RemoteEvent\RemoteEvent;
use Symfony\Contracts\Service\ServiceSubscriberInterface;

#[AsRemoteEventConsumer('yousign')]
class YousignWebhookConsumer implements ConsumerInterface, ServiceSubscriberInterface
{
    public function __construct(
        private readonly ContainerInterface $locator,
    ) {
    }

    public static function getSubscribedServices(): array
    {
        return [
            YousignSRActivatedWebhookConsumer::class,
            YousignSNotifiedWebhookConsumer::class,
            YousignSOpenedWebhookConsumer::class,
            YousignSDoneWebhookConsumer::class,
            YousignSRDoneWebhookConsumer::class,
            YousignSDeclinedWebhookConsumer::class,
            YousignSRDeclinedWebhookConsumer::class,
        ];
    }

    /**
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     */
    public function consume(RemoteEvent $event): void
    {
        $class = match ($event->getName()) {
            'signature_request.activated' => YousignSRActivatedWebhookConsumer::class,
            'signer.notified' => YousignSNotifiedWebhookConsumer::class,
            'signer.link_opened' => YousignSOpenedWebhookConsumer::class,
            'signer.done' => YousignSDoneWebhookConsumer::class,
            'signature_request.done' => YousignSRDoneWebhookConsumer::class,
            'signer.declined' => YousignSDeclinedWebhookConsumer::class,
            'signature_request.declined' => YousignSRDeclinedWebhookConsumer::class,
            default => null,
        };

        if (null === $class) {
            return;
        }

        $consumer = $this->locator->get($class);

        if (!$consumer instanceof ConsumerInterface) {
            return;
        }

        $consumer->consume($event);
    }
}
