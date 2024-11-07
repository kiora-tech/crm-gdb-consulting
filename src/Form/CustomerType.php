<?php

namespace App\Form;

use App\Entity\Customer;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class CustomerType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name')
            ->add('leadOrigin')

            // Ajout de la collection des Business Entities (liée au Customer)
            ->add('businessEntities', CollectionType::class, [
                'entry_type' => BusinessEntityType::class,  // Définition du sous-formulaire
                'entry_options' => ['label' => false],  // Pas de labels répétés pour chaque ligne
                'allow_add' => true,  // Permet l'ajout de nouvelles entités
                'allow_delete' => true,  // Permet de supprimer des entités
                'by_reference' => false,  // Important pour que Doctrine travaille bien avec l'association
            ])

            // Ajout de la collection des Contacts
            ->add('contacts', CollectionType::class, [
                'entry_type' => ContactType::class,  // Sous-formulaire Contact
                'entry_options' => ['label' => false],
                'allow_add' => true,
                'allow_delete' => true,
                'by_reference' => false,
            ])

            // Ajout de la collection des Energies
            ->add('energies', CollectionType::class, [
                'entry_type' => EnergyType::class,  // Sous-formulaire Energy
                'entry_options' => ['label' => false],
                'allow_add' => true,
                'allow_delete' => true,
                'by_reference' => false,
            ])

            // Ajout de la collection des Prospects
            ->add('prospects', CollectionType::class, [
                'entry_type' => ProspectType::class,  // Sous-formulaire Prospect
                'entry_options' => ['label' => false],
                'allow_add' => true,
                'allow_delete' => true,
                'by_reference' => false,
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Customer::class,
        ]);
    }
}