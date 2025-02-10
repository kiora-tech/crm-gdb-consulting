<?php

namespace App\Form;

use App\Entity\CanalSignature;
use App\Entity\Customer;
use App\Entity\ProspectOrigin;
use App\Entity\ProspectStatus;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class CustomerType extends AbstractType
{
    public function __construct(private readonly Security $security)
    {

    }
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name')
            ->add('leadOrigin')
            ->add('origin', EnumType::class, [
                'class' => ProspectOrigin::class,
                'choice_label' => fn(ProspectOrigin $origin) => $origin->value,
            ])
            ->add('status', EnumType::class, [
                'class' => ProspectStatus::class,
                'choice_label' => fn(ProspectStatus $status) => $status->value,
            ])
            ->add('canalSignature', EnumType::class, [
                'class' => CanalSignature::class,
                'choice_label' => fn(CanalSignature $status) => $status->value,
            ])
            ->add('action')
            ->add('worth')
            ->add('commision')
            ->add('margin')
            ->add('siret')
            ->add('companyGroup');

        if($this->security->isGranted('ROLE_ADMIN')){
            $builder->add('user');
        }
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Customer::class,
        ]);
    }
}