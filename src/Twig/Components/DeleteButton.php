<?php

namespace App\Twig\Components;

use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;
use Symfony\UX\TwigComponent\Attribute\PreMount;

#[AsTwigComponent]
class DeleteButton
{
    public string $deleteRoute;

    /**
     * @var array<string, mixed>
     */
    public array $deleteRouteParams = [];

    public ?string $entityId = null;
    public ?string $confirmationMessage = null;
    public bool $showLabel = false;
    public string $size = '';
    public bool $outline = false;

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
        $resolver->setRequired(['deleteRoute', 'entityId']);

        $resolver->setDefaults([
            'deleteRouteParams' => [],
            'confirmationMessage' => 'delete_confirmation.message',
            'showLabel' => false,
            'size' => '',
            'outline' => false,
            'attributes' => [],
        ]);

        $resolver->setAllowedTypes('deleteRoute', 'string');
        $resolver->setAllowedTypes('deleteRouteParams', 'array');
        $resolver->setAllowedTypes('entityId', ['string', 'int']);
        $resolver->setAllowedTypes('confirmationMessage', ['string', 'null']);
        $resolver->setAllowedTypes('showLabel', 'bool');
        $resolver->setAllowedTypes('size', 'string');
        $resolver->setAllowedTypes('outline', 'bool');

        $resolver->setAllowedValues('size', ['', 'sm', 'lg']);
    }

    public function getDeleteToken(): string
    {
        return 'delete'.$this->entityId;
    }
}
