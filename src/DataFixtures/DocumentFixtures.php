<?php

namespace App\DataFixtures;

use App\Entity\Customer;
use App\Entity\Document;
use App\Entity\DocumentType;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;

class DocumentFixtures extends Fixture implements DependentFixtureInterface
{
    private const DOCUMENTS = [
        // Documents pour le premier client de l'admin
        'document_admin_1_contract' => [
            'name' => 'Contrat BioEnergie SA 2023',
            'path' => 'uploads/documents/bioenergie_contract_2023.pdf',
            'customer' => 'customer_admin_1',
            'type' => 'document_type_contract',
        ],
        'document_admin_1_attestation' => [
            'name' => 'Attestation de fourniture BioEnergie',
            'path' => 'uploads/documents/bioenergie_attestation.pdf',
            'customer' => 'customer_admin_1',
            'type' => 'document_type_attestation',
        ],

        // Documents pour le deuxième client de l'admin
        'document_admin_2_contract' => [
            'name' => 'Contrat Industrie Moderne',
            'path' => 'uploads/documents/industrie_moderne_contract.pdf',
            'customer' => 'customer_admin_2',
            'type' => 'document_type_contract',
        ],
        'document_admin_2_invoice_1' => [
            'name' => 'Facture 2023-01 Industrie Moderne',
            'path' => 'uploads/documents/industrie_moderne_invoice_01.pdf',
            'customer' => 'customer_admin_2',
            'type' => 'document_type_facture',
        ],
        'document_admin_2_invoice_2' => [
            'name' => 'Facture 2023-02 Industrie Moderne',
            'path' => 'uploads/documents/industrie_moderne_invoice_02.pdf',
            'customer' => 'customer_admin_2',
            'type' => 'document_type_facture',
        ],
        'document_admin_2_consumption' => [
            'name' => 'Rapport consommation 2023 Industrie Moderne',
            'path' => 'uploads/documents/industrie_moderne_consumption.xlsx',
            'customer' => 'customer_admin_2',
            'type' => 'document_type_consumption',
        ],

        // Documents pour le client de l'utilisateur manager
        'document_manager_1_contract' => [
            'name' => 'Contrat SuperMarché Plus',
            'path' => 'uploads/documents/supermarche_contract.pdf',
            'customer' => 'customer_manager_1',
            'type' => 'document_type_contract',
        ],
        'document_manager_1_attestation' => [
            'name' => 'Attestation SuperMarché Plus',
            'path' => 'uploads/documents/supermarche_attestation.pdf',
            'customer' => 'customer_manager_1',
            'type' => 'document_type_attestation',
        ],
        'document_manager_1_audit' => [
            'name' => 'Audit énergétique SuperMarché Plus',
            'path' => 'uploads/documents/supermarche_audit.pdf',
            'customer' => 'customer_manager_1',
            'type' => 'document_type_audit',
        ],

        // Documents pour le premier client de l'utilisateur commercial
        'document_sales_1_quote' => [
            'name' => 'Devis Cabinet Medical Central',
            'path' => 'uploads/documents/cabinet_medical_quote.pdf',
            'customer' => 'customer_sales_1',
            'type' => 'document_type_devis',
        ],
        'document_sales_1_audit' => [
            'name' => 'Audit énergétique Cabinet Medical',
            'path' => 'uploads/documents/cabinet_medical_audit.pdf',
            'customer' => 'customer_sales_1',
            'type' => 'document_type_audit',
        ],

        // Documents pour le deuxième client de l'utilisateur commercial
        'document_sales_2_contract_old' => [
            'name' => 'Ancien contrat Hotel Luxe Palace',
            'path' => 'uploads/documents/hotel_contract_old.pdf',
            'customer' => 'customer_sales_2',
            'type' => 'document_type_contract',
        ],
        'document_sales_2_quote' => [
            'name' => 'Devis renouvellement Hotel Luxe Palace',
            'path' => 'uploads/documents/hotel_quote_renewal.pdf',
            'customer' => 'customer_sales_2',
            'type' => 'document_type_devis',
        ],
        'document_sales_2_consumption' => [
            'name' => 'Historique consommation Hotel Luxe Palace',
            'path' => 'uploads/documents/hotel_consumption.xlsx',
            'customer' => 'customer_sales_2',
            'type' => 'document_type_consumption',
        ],
    ];

    public function load(ObjectManager $manager): void
    {
        foreach (self::DOCUMENTS as $reference => $documentData) {
            $document = new Document();
            $document->setName($documentData['name']);
            $document->setPath($documentData['path']);
            $document->setCustomer($this->getReference($documentData['customer'], Customer::class));

            if (isset($documentData['type'])) {
                $document->setType($this->getReference($documentData['type'], DocumentType::class));
            }

            $manager->persist($document);

            // Ajouter une référence pour utilisation ultérieure
            $this->addReference($reference, $document);
        }

        $manager->flush();
    }

    public function getDependencies(): array
    {
        return [
            CustomerFixtures::class,
            DocumentTypeFixtures::class,
        ];
    }
}
