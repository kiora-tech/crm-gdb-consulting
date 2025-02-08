<?php

namespace App\Form;

use App\Entity\Comment;
use App\Entity\Contact;
use App\Entity\Customer;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Routing\RouterInterface;

class CommentType extends AbstractType
{
    public function __construct(private readonly RouterInterface $router)
    {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('note', TextareaType::class);

        if (!$options['customer'] instanceof Customer) {
            $builder->add('customer', EntityType::class, [
                'class' => Customer::class
            ]);
        }
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Comment::class,
            'customer' => null,
        ]);

        $resolver->setAllowedValues('customer', fn($value) => $value instanceof Customer || $value === null);
    }

}