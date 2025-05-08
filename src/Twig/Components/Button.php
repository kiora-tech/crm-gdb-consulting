<?php

namespace App\Twig\Components;

use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;
use Symfony\UX\TwigComponent\Attribute\PreMount;

#[AsTwigComponent]
class Button
{
    public string $type = 'button';
    public string $label = '';
    public ?string $icon = null;
    public string $theme = 'primary';
    public string $size = '';
    public bool $outline = false;
    public bool $disabled = false;
    public ?string $link = null;
    /**
     * @var array<string, string|bool>
     */
    public array $attributes = [];

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
        $resolver->setDefaults([
            'type' => 'button',
            'label' => '',
            'icon' => null,
            'theme' => 'primary',
            'size' => '',
            'outline' => false,
            'disabled' => false,
            'link' => null,
            'attributes' => [],
        ]);

        $resolver->setAllowedTypes('type', 'string');
        $resolver->setAllowedTypes('label', 'string');
        $resolver->setAllowedTypes('icon', ['null', 'string']);
        $resolver->setAllowedTypes('theme', 'string');
        $resolver->setAllowedTypes('size', 'string');
        $resolver->setAllowedTypes('outline', 'bool');
        $resolver->setAllowedTypes('disabled', 'bool');
        $resolver->setAllowedTypes('link', ['null', 'string']);
        $resolver->setAllowedTypes('attributes', 'array');

        $resolver->setAllowedValues('type', ['button', 'submit', 'reset']);
        $resolver->setAllowedValues('theme', ['primary', 'secondary', 'success', 'danger', 'warning', 'info', 'light', 'dark']);
        $resolver->setAllowedValues('size', ['', 'sm', 'lg']);
    }

    public function getButtonClasses(): string
    {
        $classes = ['btn'];

        // Ajouter la classe de thème
        $classes[] = $this->outline
            ? 'btn-outline-'.$this->theme
            : 'btn-'.$this->theme;

        // Ajouter la classe de taille si définie
        if ($this->size) {
            $classes[] = 'btn-'.$this->size;
        }

        return implode(' ', $classes);
    }

    public function getAttributes(): string
    {
        $attributes = $this->attributes;

        // Ajouter la classe
        $attributes['class'] = isset($attributes['class'])
            ? $attributes['class'].' '.$this->getButtonClasses()
            : $this->getButtonClasses();

        // Ajouter disabled si nécessaire
        if ($this->disabled) {
            $attributes['disabled'] = 'disabled';
        }

        // Construire la chaîne d'attributs
        return array_reduce(array_keys($attributes), function ($carry, $key) use ($attributes) {
            $value = $attributes[$key];
            // Convertir en chaîne avant d'appliquer htmlspecialchars
            $stringValue = is_bool($value) ? ($value ? 'true' : 'false') : (string) $value;

            return $carry.' '.$key.'="'.htmlspecialchars($stringValue).'"';
        }, '');
    }
}
