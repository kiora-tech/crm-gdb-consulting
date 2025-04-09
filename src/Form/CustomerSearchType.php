<?php

namespace App\Form;

use App\Data\CustomerSearchData;
use App\Entity\ProspectStatus;
use App\Entity\User;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;

class CustomerSearchType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', TextType::class, [
                'label' => false,
                'required' => false,
                'attr' => [
                    'placeholder' => 'customer.name'
                ]
            ])
            ->add(
                'status',
                EnumType::class,
                [
                    'label' => false,
                    'required' => false,
                    'class' => ProspectStatus::class,
                ]
            )
            ->add('contactName', TextType::class, [
                'label' => false,
                'required' => false,

                'attr' => [
                    'placeholder' => 'contact.name'
                ]
            ])
            ->add('expiringContracts', CheckboxType::class, [
                'label' => 'customer.expiring_contracts',
                'required' => false,
            ])
            ->add('user', EntityType::class, [
                'class' => User::class,
                'choice_label' => function (User $user) {
                    return (string) $user;
                },
                'label' => false,
                'placeholder' => 'user.select',
                'required' => false,
            ])
        ;

    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => CustomerSearchData::class,
            'method' => 'GET',
            'csrf_protection' => false,
            'allow_extra_fields' => true,
        ]);
    }

    public function getBlockPrefix(): string
    {
        return '';
    }
}
