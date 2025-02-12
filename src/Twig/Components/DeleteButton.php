<?php

namespace App\Twig\Components;

use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;
use Symfony\UX\TwigComponent\Attribute\PreMount;
use Symfony\Component\OptionsResolver\OptionsResolver;

#[AsTwigComponent]
class DeleteButton
{
    public string $deleteRoute;
    public array $deleteRouteParams = [];
    public ?string $entityId = null;
    public ?string $confirmationMessage = null;

    #[PreMount]
    public function preMount(array $data): array
    {
        $resolver = new OptionsResolver();
        $this->configureOptions($resolver);

        return $resolver->resolve($data);
    }

    private function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setRequired(['deleteRoute', 'entityId']);

        $resolver->setDefaults([
            'deleteRouteParams' => [],
            'confirmationMessage' => 'delete_confirmation.message'
        ]);

        $resolver->setAllowedTypes('deleteRoute', 'string');
        $resolver->setAllowedTypes('deleteRouteParams', 'array');
        $resolver->setAllowedTypes('entityId', ['string', 'int']);
        $resolver->setAllowedTypes('confirmationMessage', ['string', 'null']);
    }

    public function getDeleteToken(): string
    {
        return 'delete' . $this->entityId;
    }
}