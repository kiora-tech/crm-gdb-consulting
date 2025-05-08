<?php

namespace App\Twig\Components;

use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;
use Symfony\UX\TwigComponent\Attribute\PreMount;

#[AsTwigComponent]
class FormActions
{
    public string $backRoute;

    /**
     * @var array<string, mixed>
     */
    public array $backRouteParams = [];

    public bool $showDelete = false;
    public ?string $deleteRoute = null;

    /**
     * @var array<string, mixed>
     */
    public array $deleteRouteParams = [];

    public ?string $entityId = null;
    public bool $isAdmin = false;

    /**
     * @param array<string, mixed> $data
     *
     * @return array<string, mixed>
     */
    #[PreMount]
    public function preMount(array $data): array
    {
        $resolver = new OptionsResolver();
        $this->configureOptions($resolver);

        return $resolver->resolve($data);
    }

    private function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setRequired(['backRoute']);

        $resolver->setDefaults([
            'backRouteParams' => [],
            'showDelete' => false,
            'deleteRoute' => null,
            'deleteRouteParams' => [],
            'entityId' => null,
            'isAdmin' => false,
        ]);

        $resolver->setAllowedTypes('backRoute', 'string');
        $resolver->setAllowedTypes('backRouteParams', 'array');
        $resolver->setAllowedTypes('showDelete', 'bool');
        $resolver->setAllowedTypes('deleteRoute', ['string', 'null']);
        $resolver->setAllowedTypes('deleteRouteParams', 'array');
        $resolver->setAllowedTypes('entityId', ['string', 'null', 'int']);
        $resolver->setAllowedTypes('isAdmin', 'bool');
    }

    public function getDeleteToken(): string
    {
        return $this->entityId ? 'delete'.$this->entityId : '';
    }
}
