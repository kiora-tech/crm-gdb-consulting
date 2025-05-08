<?php

namespace App\DataFixtures;

use App\Entity\DocumentType;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

class DocumentTypeFixtures extends Fixture
{
    private const DOCUMENT_TYPES = [
        'document_type_contract' => 'Contrat',
        'document_type_attestation' => 'Attestation',
        'document_type_facture' => 'Facture',
        'document_type_devis' => 'Devis',
        'document_type_audit' => 'Audit énergétique',
        'document_type_consumption' => 'Rapport de consommation',
        'document_type_identity' => 'Pièce d\'identité',
        'document_type_other' => 'Autre',
    ];

    public function load(ObjectManager $manager): void
    {
        foreach (self::DOCUMENT_TYPES as $reference => $label) {
            $documentType = new DocumentType();
            $documentType->setLabel($label);

            $manager->persist($documentType);

            // Ajouter une référence pour utilisation ultérieure
            $this->addReference($reference, $documentType);
        }

        $manager->flush();
    }
}
