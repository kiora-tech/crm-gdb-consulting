<?php

namespace App\Form;

use App\Data\ProjectSearchData;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class ProjectSearchType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', TextType::class, [
                'label' => 'Project Name',
                'required' => false,
                'attr' => ['placeholder' => 'Search by name'],
            ])
            ->add('status', ChoiceType::class, [
                'label' => 'Status',
                'required' => false,
                'choices' => [
                    'In Progress' => 'in_progress',
                    'On Hold' => 'on_hold',
                    'Completed' => 'completed',
                    'Waiting for Validation' => 'waiting_for_validation',
                ],
                'placeholder' => 'Select status',
            ])
            ->add('startDate', DateType::class, [
                'label' => 'Start Date',
                'required' => false,
                'widget' => 'single_text',
            ])
            ->add('deadline', DateType::class, [
                'label' => 'Deadline',
                'required' => false,
                'widget' => 'single_text',
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => ProjectSearchData::class,
            'method' => 'GET',
            'csrf_protection' => false,
        ]);
    }

    public function getBlockPrefix(): string
    {
        return ''; // Removes the form name prefix in query parameters
    }
}
