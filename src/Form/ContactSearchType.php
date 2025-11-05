<?php

namespace App\Form;

use App\Data\ContactSearchData;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class ContactSearchType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('firstName', TextType::class, [
                'label' => false,
                'required' => false,
                'attr' => [
                    'placeholder' => 'contact.name',
                ],
            ])
            ->add('lastName', TextType::class, [
                'label' => false,
                'required' => false,
                'attr' => [
                    'placeholder' => 'contact.last_name',
                ],
            ])
            ->add('email', TextType::class, [
                'label' => false,
                'required' => false,
                'attr' => [
                    'placeholder' => 'contact.email',
                ],
            ])
            ->add('leadOrigin', TextType::class, [
                'label' => false,
                'required' => false,
                'attr' => [
                    'placeholder' => 'customer.lead_origin',
                ],
            ])
            ->add('contractEndAfter', DateType::class, [
                'widget' => 'single_text',
                'required' => false,
                'label' => 'Date de clôture après',
            ])
            ->add('contractEndBefore', DateType::class, [
                'widget' => 'single_text',
                'required' => false,
                'label' => 'Date de clôture avant',
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => ContactSearchData::class,
            'method' => 'GET',
            'allow_extra_fields' => true,
        ]);
    }

    public function getBlockPrefix(): string
    {
        return '';
    }
}
