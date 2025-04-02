<?php

namespace App\Form;

use App\Entity\Template;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\File;
use App\Entity\TemplateType as TemplateTypeEnum;

class TemplateType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('label', TextType::class, [
                'label' => 'template.label',
                'required' => true,
            ])
            ->add('file', FileType::class, [
                'label' => 'template.file',
                'mapped' => false,
                'required' => true,
                'constraints' => [
                    new File([
                        'mimeTypes' => array_keys(TemplateTypeEnum::MIME_TYPE_MAPPING),
                        'mimeTypesMessage' => 'template.invalid_mime_type',
                    ])
                ],
            ])
            ->add('documentType', EntityType::class, [
                'label' => 'template.documentType',
                'class' => \App\Entity\DocumentType::class
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Template::class,
            'csrf_protection' => false,
        ]);
    }
}
