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
            ->add('name', null, [
                'label' => 'customer.name',
            ])
            ->add('leadOrigin', null, [
                'label' => 'customer.lead_origin',
            ])
            ->add('origin', EnumType::class, [
                'class' => ProspectOrigin::class,
                'choice_label' => fn(ProspectOrigin $origin) => $origin->value,
                'label' => 'customer.origin',
            ])
            ->add('status', EnumType::class, [
                'class' => ProspectStatus::class,
                'choice_label' => fn(ProspectStatus $status) => $status->value,
                'label' => 'customer.status',
            ])
            ->add('canalSignature', EnumType::class, [
                'class' => CanalSignature::class,
                'choice_label' => fn(CanalSignature $status) => $status->value,
                'label' => 'customer.canal_signature',
            ])
            ->add('action', null, [
                'label' => 'customer.action',
            ])
            ->add('worth',  null, [
                'label' => 'customer.worth',
            ])
            ->add('commision', null, [
                'label' => 'customer.commission',
            ])
            ->add('margin', null, [
                'label' => 'customer.margin',
            ])
            ->add('siret', null, [
                'label' => 'customer.siret',
            ])
            ->add('companyGroup', null, [
                'label' => 'customer.company_group',
            ]);

        if($this->security->isGranted('ROLE_ADMIN')){
            $builder->add('user', null, [
                'label' => 'customer.commercial',
            ]);
        }
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Customer::class,
        ]);
    }
}