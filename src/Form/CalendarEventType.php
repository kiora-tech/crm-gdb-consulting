<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\CalendarEvent;
use App\Entity\Contact;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateTimeType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

class CalendarEventType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('contact', EntityType::class, [
                'label' => 'Contact associé',
                'class' => Contact::class,
                'required' => false,
                'placeholder' => 'Sélectionner un contact (optionnel)',
                'attr' => [
                    'class' => 'form-control',
                ],
                'choice_label' => function (Contact $contact): string {
                    return sprintf('%s %s (%s)', $contact->getFirstName(), $contact->getLastName(), $contact->getPosition() ?? 'N/A');
                },
                'choice_attr' => function (Contact $contact): array {
                    $customer = $contact->getCustomer();

                    return [
                        'data-first-name' => $contact->getFirstName(),
                        'data-last-name' => $contact->getLastName(),
                        'data-phone' => $contact->getPhone() ?: '',
                        'data-mobile-phone' => $contact->getMobilePhone() ?: '',
                        'data-customer-name' => $customer ? $customer->getName() : '',
                        'data-contact-id' => (string) $contact->getId(),
                    ];
                },
                'query_builder' => function ($repository) use ($options) {
                    $qb = $repository->createQueryBuilder('c');

                    // Filter contacts by customer if the CalendarEvent has a customer
                    if (isset($options['data']) && $options['data'] instanceof CalendarEvent && $options['data']->getCustomer()) {
                        $qb->where('c.customer = :customer')
                           ->setParameter('customer', $options['data']->getCustomer());
                    }

                    return $qb->orderBy('c.lastName', 'ASC');
                },
            ])
            ->add('title', TextType::class, [
                'label' => 'Titre',
                'attr' => [
                    'placeholder' => 'Titre de l\'événement',
                    'class' => 'form-control',
                ],
                'constraints' => [
                    new Assert\NotBlank(
                        message: 'Le titre est obligatoire',
                    ),
                    new Assert\Length(
                        max: 255,
                        maxMessage: 'Le titre ne peut pas dépasser {{ limit }} caractères',
                    ),
                ],
            ])
            ->add('description', TextareaType::class, [
                'label' => 'Description',
                'required' => false,
                'attr' => [
                    'placeholder' => 'Description de l\'événement (optionnel)',
                    'class' => 'form-control',
                    'rows' => 4,
                ],
            ])
            ->add('startDateTime', DateTimeType::class, [
                'label' => 'Date et heure de début',
                'widget' => 'single_text',
                'attr' => [
                    'class' => 'form-control',
                ],
                'constraints' => [
                    new Assert\NotBlank(
                        message: 'La date de début est obligatoire',
                    ),
                ],
            ])
            ->add('endDateTime', DateTimeType::class, [
                'label' => 'Date et heure de fin',
                'widget' => 'single_text',
                'attr' => [
                    'class' => 'form-control',
                ],
                'constraints' => [
                    new Assert\NotBlank(
                        message: 'La date de fin est obligatoire',
                    ),
                    new Assert\GreaterThan(
                        propertyPath: 'parent.all[startDateTime].data',
                        message: 'La date de fin doit être après la date de début',
                    ),
                ],
            ])
            ->add('location', TextType::class, [
                'label' => 'Lieu',
                'required' => false,
                'attr' => [
                    'placeholder' => 'Lieu de l\'événement (optionnel)',
                    'class' => 'form-control',
                ],
            ])
            ->add('category', ChoiceType::class, [
                'label' => 'Catégorie',
                'required' => false,
                'placeholder' => 'Sélectionner une catégorie (optionnel)',
                'attr' => [
                    'class' => 'form-control category-select',
                ],
                'choices' => $options['user_categories'] ?? [],
                'choice_attr' => function ($choice, $key, $value) use ($options) {
                    $categoryColors = $options['category_colors'] ?? [];
                    $color = $categoryColors[$value] ?? '#808080';

                    return [
                        'data-color' => $color,
                    ];
                },
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => CalendarEvent::class,
            'user_categories' => [],
            'category_colors' => [],
        ]);

        $resolver->setAllowedTypes('user_categories', 'array');
        $resolver->setAllowedTypes('category_colors', 'array');
    }
}
