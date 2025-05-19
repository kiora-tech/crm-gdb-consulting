<?php

namespace App\Form;

use App\Entity\EnergyProvider;
use App\Repository\EnergyProviderRepository;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\UX\Autocomplete\Form\AsEntityAutocompleteField;
use Symfony\UX\Autocomplete\Form\BaseEntityAutocompleteType;

#[AsEntityAutocompleteField]
class EnergyProviderAutocompleteType extends AbstractType
{
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'class' => EnergyProvider::class,
            'placeholder' => 'energy.provider_placeholder',
            'choice_label' => 'name',
            'query_builder' => function (EnergyProviderRepository $repository) {
                return $repository->createQueryBuilder('p')
                    ->orderBy('p.name', 'ASC');
            },
            'tom_select_options' => ['create' => true],
        ]);
    }

    public function getParent(): string
    {
        return BaseEntityAutocompleteType::class;
    }
}
