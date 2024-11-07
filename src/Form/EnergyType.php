<?php

namespace App\Form;

use App\Entity\Energy;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class EnergyType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('type')    // ELEC, GAS, etc.
            ->add('code')    // Code de l'énergie ou du contrat
            ->add('provider')  // Fournisseur d'énergie
            ->add('contractEnd')  // Date de fin de contrat
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Energy::class,
        ]);
    }
}