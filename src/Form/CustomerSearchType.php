<?php

namespace App\Form;

use App\Data\CustomerSearchData;
use App\Entity\EnergyProvider;
use App\Entity\ProspectOrigin;
use App\Entity\ProspectStatus;
use App\Entity\User;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\DateIntervalType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Routing\RouterInterface;

class CustomerSearchType extends AbstractType
{
    public function __construct(private readonly RouterInterface $router)
    {

    }

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
            ->add(
                'origin',
                EnumType::class,
                [
                    'label' => false,
                    'required' => false,
                    'class' => ProspectOrigin::class,
                    'placeholder' => 'customer.prospect_origin',
                ]
            )
            ->add('leadOrigin', TextType::class, [
                'label' => false,
                'required' => false,
                'attr' => [
                    'placeholder' => 'customer.lead_origin'
                ]
            ])
            ->add('contactName', TextType::class, [
                'label' => false,
                'required' => false,

                'attr' => [
                    'placeholder' => 'contact.name'
                ]
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
            ])
            ->add('code', TextType::class, [
                'label' => false,
                'required' => false,

                'attr' => [
                    'placeholder' => 'energy.code'
                ]
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
            ->add('unassigned', CheckboxType::class, [
                'label' => 'customer.unassigned',
                'required' => false,
            ])
            ->add('energyProvider', EnergyProviderAutocompleteType::class, [
                'label' => 'energy.provider',
                'required' => false,
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => 'energy.provider',
                    'data-controller' => 'energy-provider-autocomplete',
                    'data-energy-provider-autocomplete-url-value' => $this->router->generate('app_energy_provider_new_ajax'),
                ],
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
