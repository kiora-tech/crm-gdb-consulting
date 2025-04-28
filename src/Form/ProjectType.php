<?php

namespace App\Form;

use App\Entity\Customer;
use App\Entity\Project;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\MoneyType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class ProjectType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', TextType::class, [
                'label' => 'Project Name',
            ])
            ->add('status', ChoiceType::class, [
                'label' => 'Status',
                'choices' => [
                    'In Progress' => 'in_progress',
                    'On Hold' => 'on_hold',
                    'Completed' => 'completed',
                    'Waiting for Validation' => 'waiting_for_validation',
                ],
            ])
            ->add('startDate', DateType::class, [
                'label' => 'Start Date',
                'widget' => 'single_text',
                'required' => false,
            ])
            ->add('deadline', DateType::class, [
                'label' => 'Deadline',
                'widget' => 'single_text',
                'required' => false,
            ])
            ->add('budget', MoneyType::class, [
                'label' => 'Budget',
                'currency' => 'USD',
                'required' => false,
            ])
            ->add('customer', EntityType::class, [
                'class' => Customer::class,
                'choice_label' => 'name',
                'label' => 'Customer',
                'placeholder' => 'Select a customer',
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Project::class,
        ]);
    }
}
