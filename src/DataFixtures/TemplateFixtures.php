<?php

namespace App\DataFixtures;

use App\Entity\DocumentType;
use App\Entity\Template;
use App\Entity\TemplateType;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;

class TemplateFixtures extends Fixture implements DependentFixtureInterface
{
    private const TEMPLATES = [
        'template_contract_elec' => [
            'label' => 'Contrat Électricité',
            'path' => 'templates/contract_elec.docx',
            'type' => TemplateType::DOCUMENT,
            'originalFilename' => 'contract_elec_template.docx',
            'mimeType' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'documentType' => 'document_type_contract',
        ],
        'template_contract_gas' => [
            'label' => 'Contrat Gaz',
            'path' => 'templates/contract_gas.docx',
            'type' => TemplateType::DOCUMENT,
            'originalFilename' => 'contract_gas_template.docx',
            'mimeType' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'documentType' => 'document_type_contract',
        ],
        'template_invoice' => [
            'label' => 'Facture standard',
            'path' => 'templates/invoice.docx',
            'type' => TemplateType::DOCUMENT,
            'originalFilename' => 'invoice_template.docx',
            'mimeType' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'documentType' => 'document_type_facture',
        ],
    ];

    public function load(ObjectManager $manager): void
    {
        foreach (self::TEMPLATES as $reference => $templateData) {
            $template = new Template();
            $template->setLabel($templateData['label']);
            $template->setPath($templateData['path']);
            $template->setType($templateData['type']);
            $template->setOriginalFilename($templateData['originalFilename']);
            $template->setMimeType($templateData['mimeType']);

            $template->setDocumentType($this->getReference($templateData['documentType'], DocumentType::class));

            $manager->persist($template);

            // Ajouter une référence pour utilisation ultérieure
            $this->addReference($reference, $template);
        }

        $manager->flush();
    }

    public function getDependencies(): array
    {
        return [
            DocumentTypeFixtures::class,
        ];
    }
}
