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
        'document_admin_1_invoice' => [
            'name' => 'Facture BioEnergie SA - Janvier 2023',
            'path' => 'uploads/documents/bioenergie_invoice_01_2023.pdf',
            'customer' => 'customer_admin_1',
            'type' => 'document_type_facture',
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
        'document_admin_2_invoice_3' => [
            'name' => 'Facture 2023-03 Industrie Moderne',
            'path' => 'uploads/documents/industrie_moderne_invoice_03.pdf',
            'customer' => 'customer_admin_2',
            'type' => 'document_type_facture',
        ],

        // Documents pour le client de l'utilisateur manager
        'document_manager_1_contract' => [
            'name' => 'Contrat SuperMarché Plus',
            'path' => 'uploads/documents/supermarche_contract.pdf',
            'customer' => 'customer_manager_1',
            'type' => 'document_type_contract',
        ],
        'document_manager_1_invoice_1' => [
            'name' => 'Facture SuperMarché Plus - Janvier 2023',
            'path' => 'uploads/documents/supermarche_invoice_01_2023.pdf',
            'customer' => 'customer_manager_1',
            'type' => 'document_type_facture',
        ],
        'document_manager_1_invoice_2' => [
            'name' => 'Facture SuperMarché Plus - Février 2023',
            'path' => 'uploads/documents/supermarche_invoice_02_2023.pdf',
            'customer' => 'customer_manager_1',
            'type' => 'document_type_facture',
        ],

        // Documents pour le premier client de l'utilisateur commercial
        'document_sales_1_contract' => [
            'name' => 'Contrat Cabinet Medical Central',
            'path' => 'uploads/documents/cabinet_medical_contract.pdf',
            'customer' => 'customer_sales_1',
            'type' => 'document_type_contract',
        ],
        'document_sales_1_invoice' => [
            'name' => 'Facture Cabinet Medical - Mars 2023',
            'path' => 'uploads/documents/cabinet_medical_invoice_03_2023.pdf',
            'customer' => 'customer_sales_1',
            'type' => 'document_type_facture',
        ],

        // Documents pour le deuxième client de l'utilisateur commercial
        'document_sales_2_contract_old' => [
            'name' => 'Ancien contrat Hotel Luxe Palace',
            'path' => 'uploads/documents/hotel_contract_old.pdf',
            'customer' => 'customer_sales_2',
            'type' => 'document_type_contract',
        ],
        'document_sales_2_contract_new' => [
            'name' => 'Nouveau contrat Hotel Luxe Palace',
            'path' => 'uploads/documents/hotel_contract_new.pdf',
            'customer' => 'customer_sales_2',
            'type' => 'document_type_contract',
        ],
        'document_sales_2_invoice' => [
            'name' => 'Facture Hotel Luxe Palace - Avril 2023',
            'path' => 'uploads/documents/hotel_invoice_04_2023.pdf',
            'customer' => 'customer_sales_2',
            'type' => 'document_type_facture',
        ],
    ];

    public function load(ObjectManager $manager): void
    {
        foreach (self::DOCUMENTS as $reference => $documentData) {
            $document = new Document();
            $document->setName($documentData['name']);
            $document->setPath($documentData['path']);
            $document->setCustomer($this->getReference($documentData['customer'], Customer::class));
            $document->setType($this->getReference($documentData['type'], DocumentType::class));

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
