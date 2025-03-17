<?php

namespace App\Form;

use App\Entity\Customer;
use App\Entity\Energy;
use App\Entity\Fta;
use App\Entity\GasTransportRate;
use App\Entity\Segment;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Form;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use App\Entity\EnergyType as EnergyTypeEnum;
use Symfony\Component\Form\FormError;

class EnergyType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        // Capture l'entité originale pour une vérification ultérieure
        $originalEnergy = $options['data'] ?? null;
        $originalType = $originalEnergy && $originalEnergy->getId() ? $originalEnergy->getType() : null;

        $builder
            ->add('type', EnumType::class, [
                'class' => EnergyTypeEnum::class,
                'choice_label' => fn(EnergyTypeEnum $type) => $type->value,
                'label' => 'energy.type',
                'placeholder' => 'placeholder.choose_option',
                // Rendre le champ en lecture seule si on édite une entité existante
                'disabled' => $originalEnergy && $originalEnergy->getId() !== null,
                // Ajouter une classe CSS pour mettre en évidence le statut en lecture seule
                'attr' => [
                    'class' => $originalEnergy && $originalEnergy->getId() ? 'bg-light' : ''
                ],
                'help' => $originalEnergy && $originalEnergy->getId() ? 'Le type d\'énergie ne peut pas être modifié après création' : null,
            ]);

        // Protection supplémentaire : vérifier que le type n'a pas été modifié après soumission du formulaire
        $builder->addEventListener(FormEvents::POST_SUBMIT, function (FormEvent $event) use ($originalType) {
            $energy = $event->getData();
            $form = $event->getForm();

            // Si l'entité existait déjà et que le type a été modifié, ajout d'une erreur
            if ($originalType !== null && $energy->getType() !== $originalType) {
                $form->get('type')->addError(new FormError('Le type d\'énergie ne peut pas être modifié après création.'));
            }
        });

        // Utilisation de FormEvents::PRE_SUBMIT pour gérer la dynamique du formulaire
        $builder->addEventListener(FormEvents::PRE_SUBMIT, function (FormEvent $event) use ($originalType) {
            $data = $event->getData();
            $form = $event->getForm();

            // Si on édite une entité existante, forcer le type original dans les données
            if ($originalType !== null) {
                $data['type'] = $originalType->value;
                $event->setData($data);
            }

            // Champs communs
            $form
                ->add('code', TextType::class, [
                    'label' => isset($data['type']) && $data['type'] === 'ELEC' ? 'energy.pdl' : 'energy.pce',
                    'required' => false,
                ])
                ->add('provider', TextType::class, [
                    'label' => 'energy.provider',
                    'required' => false,
                ])
                ->add('contractEnd', DateType::class, [
                    'widget' => 'single_text',
                    'label' => 'energy.contract_end',
                    'required' => false,
                ]);

            $typeToUse = $originalType ? $originalType->value : ($data['type'] ?? null);

            if ($typeToUse) {
                if ($typeToUse === 'ELEC') {
                    $this->addElectricityFields($form);
                } elseif ($typeToUse === 'GAZ') {
                    $this->addGasFields($form);
                }
            }
        });

        // Utilisation de FormEvents::PRE_SET_DATA pour l'affichage initial
        $builder->addEventListener(FormEvents::PRE_SET_DATA, function (FormEvent $event) {
            $energy = $event->getData();
            $form = $event->getForm();

            // Champs communs avec les valeurs initiales
            $form
                ->add('code', TextType::class, [
                    'label' => $energy && $energy->getType() === EnergyTypeEnum::ELEC ? 'energy.pdl' : 'energy.pce',
                    'required' => false,
                ])
                ->add('provider', TextType::class, [
                    'label' => 'energy.provider',
                    'required' => false,
                ])
                ->add('contractEnd', DateType::class, [
                    'widget' => 'single_text',
                    'label' => 'energy.contract_end',
                    'required' => false,
                ]);

            if ($energy && $energy->getType()) {
                if ($energy->getType() === EnergyTypeEnum::ELEC) {
                    $this->addElectricityFields($form);
                } elseif ($energy->getType() === EnergyTypeEnum::GAZ) {
                    $this->addGasFields($form);
                }
            }
        });

        if (!$options['customer'] instanceof Customer) {
            $builder->add('customer', EntityType::class, [
                'class' => Customer::class,
                'choice_label' => 'name',
                'label' => 'energy.customer',
            ]);
        }
    }

    private function addElectricityFields(FormInterface $form): void
    {
        $form
            ->add('powerKva', NumberType::class, [
                'label' => 'energy.power_kva',
                'required' => false,
            ])
            ->add('fta', EntityType::class, [
                'class' => Fta::class,
                'choice_label' => 'label',
                'label' => 'energy.fta',
                'required' => false,
            ])
            ->add('segment', EnumType::class, [
                'class' => Segment::class,
                'label' => 'energy.segment',
                'required' => false,
            ])
            ->add('peakConsumption', NumberType::class, [
                'label' => 'energy.peak_consumption',
                'help' => 'MWh/an',
                'required' => false,
            ])
            ->add('hphConsumption', NumberType::class, [
                'label' => 'energy.hph_consumption',
                'help' => 'MWh/an',
                'required' => false,
            ])
            ->add('hchConsumption', NumberType::class, [
                'label' => 'energy.hch_consumption',
                'help' => 'MWh/an',
                'required' => false,
            ])
            ->add('hpeConsumption', NumberType::class, [
                'label' => 'energy.hpe_consumption',
                'help' => 'MWh/an',
                'required' => false,
            ])
            ->add('hceConsumption', NumberType::class, [
                'label' => 'energy.hce_consumption',
                'help' => 'MWh/an',
                'required' => false,
            ])
            ->add('baseConsumption', NumberType::class, [
                'label' => 'energy.base_consumption',
                'help' => 'MWh/an',
                'required' => false,
            ])
            ->add('hpConsumption', NumberType::class, [
                'label' => 'energy.hp_consumption',
                'help' => 'MWh/an',
                'required' => false,
            ])
            ->add('hcConsumption', NumberType::class, [
                'label' => 'energy.hc_consumption',
                'help' => 'MWh/an',
                'required' => false,
            ])
            ->add('totalConsumption', NumberType::class, [
                'label' => 'energy.total_consumption',
                'help' => 'MWh/an',
                'required' => false,
            ]);
    }

    private function addGasFields(FormInterface $form): void
    {
        $form
            ->add('profile', TextType::class, [
                'label' => 'energy.profile',
                'required' => false,
            ])
            ->add('transportRate', EnumType::class, [
                'class' => GasTransportRate::class,
                'label' => 'energy.transport_rate',
                'required' => false,
            ])
            ->add('totalConsumption', NumberType::class, [
                'label' => 'energy.total_consumption',
                'help' => 'MWh/an',
                'required' => false,
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Energy::class,
            'customer' => null,
            'csrf_protection' => false,
        ]);

        $resolver->setAllowedValues('customer', fn($value) => $value instanceof Customer || $value === null);
    }
}