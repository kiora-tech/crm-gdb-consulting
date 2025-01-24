<?php

namespace App\Form;

use App\Entity\customer;
use App\Entity\Energy;
use App\Entity\Segment;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Routing\RouterInterface;

class EnergyType extends AbstractType
{
    public function __construct(private readonly RouterInterface $router)
    {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('type', EnumType::class, [
                'class' => \App\Entity\EnergyType::class,
                'choice_label' => fn(\App\Entity\EnergyType $energy) => $energy->value,
            ])
            ->add('code')
            ->add('provider')
            ->add('contractEnd', null, [
                'widget' => 'single_text',
            ])
            ->add('power')
            ->add('basePrice')
            ->add('segment', EnumType::class, [
                'class' => Segment::class,
                'choice_label' => fn(Segment $segment) => $segment->value,
                ])
            ->add('peakHour')
            ->add('offPeakHour')
            ->add('horoSeason')
            ->add('peakHourWinter')
            ->add('peakHourSummer')
            ->add('offPeakHourWinter')
            ->add('offPeakHourSummer')
            ->add('total');
        if (!$options['customer'] instanceof Customer) {
            $builder->add('customer', EntityType::class, [
                'class' => Customer::class
            ]);
        }
        $builder->setAction($this->router->generate('app_energy_new', ['customer' => $options['customer']?->getId()]));
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Energy::class,
            'customer' => null,
        ]);

        $resolver->setAllowedValues('customer', fn($value) => $value instanceof Customer || $value === null);
    }
}
