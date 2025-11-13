<?php

declare(strict_types=1);

namespace App\Tests\Functional\Import;

use App\Entity\Contact;
use App\Entity\Customer;
use App\Entity\Import;
use App\Entity\ImportStatus;
use App\Entity\ImportType;
use App\Repository\ContactRepository;
use App\Repository\CustomerRepository;
use App\Repository\ImportRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Messenger\Transport\InMemory\InMemoryTransport;

/**
 * Test fonctionnel pour l'import complet (clients + contacts + énergies).
 */
class FullImportTest extends KernelTestCase
{
    private EntityManagerInterface $entityManager;
    private ImportRepository $importRepository;
    private CustomerRepository $customerRepository;
    private ContactRepository $contactRepository;

    protected function setUp(): void
    {
        parent::setUp();
        self::bootKernel();

        $container = static::getContainer();
        $this->entityManager = $container->get(EntityManagerInterface::class);
        $this->importRepository = $container->get(ImportRepository::class);
        $this->customerRepository = $container->get(CustomerRepository::class);
        $this->contactRepository = $container->get(ContactRepository::class);
    }

    public function testFullImportWithSeparatedContactColumns(): void
    {
        // 1. Générer le fichier exemple
        $this->generateExampleFile();

        // 2. Créer une entreprise de test
        $company = new \App\Entity\Company();
        $company->setName('Test Company');
        $this->entityManager->persist($company);
        $this->entityManager->flush();

        // 3. Créer un utilisateur de test
        $user = new \App\Entity\User();
        $user->setEmail('test-'.uniqid().'@example.com');
        $user->setPassword('test');
        $user->setRoles(['ROLE_USER']);
        $user->setCompany($company);
        $this->entityManager->persist($user);
        $this->entityManager->flush();

        $exampleFilePath = self::getContainer()->getParameter('kernel.project_dir').'/public/examples/import_complet_exemple.xlsx';
        $this->assertFileExists($exampleFilePath, 'Le fichier exemple doit exister');

        // Copier le fichier dans var/import
        $storedFilename = 'test-import-'.uniqid().'.xlsx';
        $importDir = self::getContainer()->getParameter('kernel.project_dir').'/var/import';
        if (!is_dir($importDir)) {
            mkdir($importDir, 0755, true);
        }
        copy($exampleFilePath, $importDir.'/'.$storedFilename);

        $import = new Import();
        $import->setType(ImportType::FULL);
        $import->setUser($user);
        $import->setOriginalFilename('import_complet_exemple.xlsx');
        $import->setStoredFilename($storedFilename);
        $import->setStatus(ImportStatus::PENDING);
        $this->entityManager->persist($import);
        $this->entityManager->flush();

        // 4. Marquer l'import comme en cours d'analyse
        $import->markAsAnalyzing();
        $this->entityManager->flush();

        // 5. Lancer l'analyse
        $analyzeMessage = new \App\Domain\Import\Message\AnalyzeImportMessage($import->getId());
        $handler = self::getContainer()->get(\App\Domain\Import\MessageHandler\AnalyzeImportMessageHandler::class);

        try {
            $handler($analyzeMessage);
        } catch (\Exception $e) {
            $this->fail('L\'analyse a échoué: '.$e->getMessage());
        }

        // 6. Vérifier que l'analyse a réussi
        $this->entityManager->refresh($import);
        $errors = $import->getErrors();
        $firstError = $errors->count() > 0 ? $errors->first()->getMessage() : 'none';

        $this->assertSame(
            ImportStatus::AWAITING_CONFIRMATION,
            $import->getStatus(),
            sprintf(
                'L\'import devrait être en attente de confirmation. Status: %s, Total rows: %d, First error: %s',
                $import->getStatus()->value,
                $import->getTotalRows(),
                $firstError
            )
        );
        $this->assertGreaterThan(0, $import->getTotalRows(), 'L\'import doit contenir au moins 1 ligne');

        // 7. Confirmer et traiter l'import
        $import->markAsProcessing();
        $this->entityManager->flush();

        // Traiter tous les lots
        $totalRows = $import->getTotalRows();
        $batchSize = 100;

        for ($startRow = 2; $startRow <= $totalRows + 1; $startRow += $batchSize) {
            $endRow = min($startRow + $batchSize - 1, $totalRows + 1);

            $batchMessage = new \App\Domain\Import\Message\ProcessImportBatchMessage(
                $import->getId(),
                $startRow,
                $endRow
            );

            $batchHandler = self::getContainer()->get(\App\Domain\Import\MessageHandler\ProcessImportBatchMessageHandler::class);

            try {
                $batchHandler($batchMessage);
            } catch (\Exception $e) {
                $this->fail(sprintf('Le traitement du lot %d-%d a échoué: %s', $startRow, $endRow, $e->getMessage()));
            }
        }

        // 8. Vérifier que l'import est terminé
        $this->entityManager->refresh($import);
        $this->assertSame(
            ImportStatus::COMPLETED,
            $import->getStatus(),
            sprintf(
                'L\'import devrait être terminé. Status: %s, Success: %d, Errors: %d',
                $import->getStatus()->value,
                $import->getSuccessRows(),
                $import->getErrorRows()
            )
        );
        $this->assertSame($import->getTotalRows(), $import->getProcessedRows(), 'Toutes les lignes devraient être traitées');
        $this->assertGreaterThan(0, $import->getSuccessRows(), 'Au moins une ligne devrait être importée avec succès');

        // 9. Vérifier que les clients ont été créés
        // Clear the entity manager to force fresh queries from the database
        $this->entityManager->clear();

        $allCustomers = $this->customerRepository->findAll();
        $customerDebug = array_map(fn($c) => sprintf('%s (SIRET: %s)', $c->getName(), $c->getSiret() ?? 'null'), $allCustomers);

        $customerBoulangerie = $this->customerRepository->findOneBy(['siret' => '12345678901234']);
        $this->assertNotNull(
            $customerBoulangerie,
            sprintf('Le client BOULANGERIE MARTIN devrait exister. Found %d customers: %s', count($allCustomers), implode(', ', $customerDebug))
        );
        $this->assertSame('BOULANGERIE MARTIN', $customerBoulangerie->getName());

        $customerGarage = $this->customerRepository->findOneBy(['siret' => '98765432109876']);
        $this->assertNotNull($customerGarage, 'Le client GARAGE DUPONT SARL devrait exister');
        $this->assertSame('GARAGE DUPONT SARL', $customerGarage->getName());

        $customerRestaurant = $this->customerRepository->findOneBy(['siret' => '11122233344455']);
        $this->assertNotNull($customerRestaurant, 'Le client RESTAURANT LE BON COIN devrait exister');
        $this->assertSame('RESTAURANT LE BON COIN', $customerRestaurant->getName());

        // 10. Vérifier que les contacts ont été créés
        $contactsMartin = $this->contactRepository->findBy(['customer' => $customerBoulangerie]);
        $this->assertCount(1, $contactsMartin, 'Le client BOULANGERIE MARTIN devrait avoir 1 contact');
        $this->assertSame('Jean', $contactsMartin[0]->getFirstName());
        $this->assertSame('Martin', $contactsMartin[0]->getLastName());
        $this->assertSame('j.martin@boulangerie-martin.fr', $contactsMartin[0]->getEmail());

        $contactsDupont = $this->contactRepository->findBy(['customer' => $customerGarage]);
        $this->assertCount(1, $contactsDupont, 'Le client GARAGE DUPONT SARL devrait avoir 1 contact');
        $this->assertSame('Marie', $contactsDupont[0]->getFirstName());
        $this->assertSame('Dupont', $contactsDupont[0]->getLastName());
        $this->assertSame('contact@garage-dupont.com', $contactsDupont[0]->getEmail());

        $contactsLeblanc = $this->contactRepository->findBy(['customer' => $customerRestaurant]);
        $this->assertCount(1, $contactsLeblanc, 'Le client RESTAURANT LE BON COIN devrait avoir 1 contact');
        $this->assertSame('Pierre', $contactsLeblanc[0]->getFirstName());
        $this->assertSame('Leblanc', $contactsLeblanc[0]->getLastName());
        $this->assertSame('pierre@leboncoin-resto.fr', $contactsLeblanc[0]->getEmail());
    }

    private function generateExampleFile(): void
    {
        $projectDir = self::getContainer()->getParameter('kernel.project_dir');
        exec("cd $projectDir && bin/console app:import:generate-examples", $output, $returnCode);

        if (0 !== $returnCode) {
            throw new \RuntimeException('Failed to generate example files');
        }
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        // Nettoyer les données de test
        $this->entityManager->clear();
    }
}
