<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\Import\Service\Processor;

use App\Domain\Import\Service\Processor\CustomerImportProcessor;
use App\Entity\Customer;
use App\Entity\Energy;
use App\Entity\Import;
use App\Entity\ImportType;
use App\Entity\User;
use App\Repository\CustomerRepository;
use App\Repository\EnergyRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

/**
 * Tests unitaires exposant les bugs du CustomerImportProcessor.
 *
 * Ces tests DOIVENT PLANTER pour confirmer les bugs identifiés :
 * - Bug 1 : Commercial non appliqué (email dans header "Commercial" ignoré)
 * - Bug 2 : PDL/PCE non enregistré (header "PDL/PCE" avec slash non mappé)
 * - Bug 3 : Échéance non enregistrée (header "Échéance" avec accent mal converti)
 */
final class CustomerImportProcessorTest extends TestCase
{
    /** @var CustomerRepository&\PHPUnit\Framework\MockObject\MockObject */
    private CustomerRepository $customerRepository;

    /** @var EntityManagerInterface&\PHPUnit\Framework\MockObject\MockObject */
    private EntityManagerInterface $entityManager;

    /** @var LoggerInterface&\PHPUnit\Framework\MockObject\MockObject */
    private LoggerInterface $logger;

    /** @var EnergyRepository&\PHPUnit\Framework\MockObject\MockObject */
    private EnergyRepository $energyRepository;

    /** @var UserRepository&\PHPUnit\Framework\MockObject\MockObject */
    private UserRepository $userRepository;

    private CustomerImportProcessor $processor;

    protected function setUp(): void
    {
        // Mock repositories and services
        $this->customerRepository = $this->createMock(CustomerRepository::class);
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->energyRepository = $this->createMock(EnergyRepository::class);
        $this->userRepository = $this->createMock(UserRepository::class);

        // Instantiate the processor
        $this->processor = new CustomerImportProcessor(
            $this->customerRepository,
            $this->entityManager,
            $this->logger,
            $this->energyRepository,
            $this->userRepository
        );
    }

    /**
     * BUG 1 : Le commercial spécifié par email dans la colonne "Commercial" n'est pas assigné.
     *
     * Comportement actuel :
     * - Le header "Commercial" contient un email (ex: "noam.benguigui@gdb-consulting.com")
     * - Le header est normalisé en 'commercial' (sans underscore)
     * - AUCUN mapping n'existe pour 'commercial' dans normalizeHeaderKey()
     * - Résultat : l'email n'est jamais lu, c'est TOUJOURS l'utilisateur qui fait l'import qui est assigné (ligne 346)
     *
     * Comportement attendu :
     * - Le customer devrait être assigné à l'utilisateur dont l'email correspond
     *
     * Ce test DOIT PLANTER pour confirmer le bug.
     */
    #[Test]
    public function testCommercialEmailIsNotAssignedToCustomer(): void
    {
        // Arrange : Créer un utilisateur qui fait l'import
        $importingUser = new User();
        $importingUser->setId(1);
        $importingUser->setEmail('importer@gdb-consulting.com');
        $importingUser->setName('Importer User');

        // Créer un autre utilisateur qui devrait être assigné selon l'email du header "Commercial"
        $commercialUser = new User();
        $commercialUser->setId(2);
        $commercialUser->setEmail('noam.benguigui@gdb-consulting.com');
        $commercialUser->setName('Noam Benguigui');

        // Créer un import de type CUSTOMER
        $import = new Import();
        $import->setType(ImportType::CUSTOMER);
        $import->setUser($importingUser);
        $import->setOriginalFilename('test.xlsx');
        $import->setStoredFilename('test-stored.xlsx');

        // Simuler les données Excel avec le header "Commercial" contenant un email
        $rows = [
            [
                'Raison sociale' => 'Test Company',
                'Commercial' => 'noam.benguigui@gdb-consulting.com', // Email du commercial à assigner
            ],
        ];

        // Mock : Le customer n'existe pas encore
        $this->customerRepository
            ->method('findOneBy')
            ->willReturn(null);

        // Mock : UserRepository doit trouver l'utilisateur par email
        $this->userRepository
            ->method('findOneBy')
            ->with(['email' => 'noam.benguigui@gdb-consulting.com'])
            ->willReturn($commercialUser);

        // Mock : EntityManager pour capturer le customer créé
        $capturedCustomer = null;
        $this->entityManager
            ->expects($this->atLeastOnce())
            ->method('persist')
            ->willReturnCallback(function ($entity) use (&$capturedCustomer) {
                if ($entity instanceof Customer) {
                    $capturedCustomer = $entity;
                }
            });

        // Mock : EntityManager->getReference pour créer une référence User
        $this->entityManager
            ->method('getReference')
            ->willReturnCallback(function ($class, $id) use ($importingUser, $commercialUser) {
                if (User::class === $class) {
                    return 1 === $id ? $importingUser : $commercialUser;
                }

                return null;
            });

        // Act : Traiter le batch
        $this->processor->processBatch($rows, $import);

        // Assert : Le customer devrait être assigné au commercial, PAS à l'importateur
        $this->assertNotNull($capturedCustomer, 'Un customer aurait dû être créé');

        $user = $capturedCustomer->getUser();
        $this->assertNotNull($user, 'Le customer devrait avoir un utilisateur assigné');

        // BUG EXPOSÉ : Cette assertion DOIT PLANTER
        // Car actuellement, c'est l'utilisateur de l'import (ID=1) qui est assigné, pas le commercial (ID=2)
        $this->assertSame(
            2,
            $user->getId(),
            'BUG CONFIRMÉ : Le customer est assigné à l\'utilisateur de l\'import au lieu du commercial spécifié'
        );
    }

    /**
     * BUG 2 : Le code PDL/PCE n'est pas enregistré quand le header contient un slash.
     *
     * Comportement actuel :
     * - Le header Excel "PDL/PCE" contient le code (ex: "50022538360866")
     * - Le "/" est supprimé par la regex → normalisé en 'pdlpce' (sans underscore)
     * - Les mappings existants sont : 'pdl', 'pce', 'pce_pdl', 'pdl_pce', 'pdl__pce'
     * - AUCUN mapping pour 'pdlpce' (sans underscore) → la valeur n'est jamais dans $rowData['pce_pdl']
     * - Résultat : le code PDL/PCE n'est jamais enregistré dans Energy->code
     *
     * Comportement attendu :
     * - Le code devrait être enregistré dans l'entité Energy
     *
     * Ce test DOIT PLANTER pour confirmer le bug.
     */
    #[Test]
    public function testPdlPceIsNotSavedDueToSlashInHeader(): void
    {
        // Arrange : Créer un utilisateur qui fait l'import
        $importingUser = new User();
        $importingUser->setId(1);
        $importingUser->setEmail('importer@gdb-consulting.com');

        // Créer un import de type FULL (nécessaire pour traiter les énergies)
        $import = new Import();
        $import->setType(ImportType::FULL);
        $import->setUser($importingUser);
        $import->setOriginalFilename('test.xlsx');
        $import->setStoredFilename('test-stored.xlsx');

        // Simuler les données Excel avec le header "PDL/PCE" contenant un code
        $pdlPceCode = '50022538360866';
        $rows = [
            [
                'Raison sociale' => 'Test Company PDL',
                'PDL/PCE' => $pdlPceCode, // Header avec slash qui sera mal normalisé
                'Fournisseur actuel' => 'EDF',
            ],
        ];

        // Mock : Le customer n'existe pas encore
        $this->customerRepository
            ->method('findOneBy')
            ->willReturn(null);

        // Mock : L'énergie n'existe pas encore
        $this->energyRepository
            ->method('findOneBy')
            ->willReturn(null);

        $this->energyRepository
            ->method('findBy')
            ->willReturn([]);

        // Mock : EntityManager pour capturer l'energy créée
        $capturedEnergy = null;
        $this->entityManager
            ->method('persist')
            ->willReturnCallback(function ($entity) use (&$capturedEnergy) {
                if ($entity instanceof Energy) {
                    $capturedEnergy = $entity;
                }
            });

        // Mock : EntityManager->getReference pour créer une référence User
        $this->entityManager
            ->method('getReference')
            ->willReturnCallback(function ($class, $id) use ($importingUser) {
                if (User::class === $class && 1 === $id) {
                    return $importingUser;
                }

                return null;
            });

        // Act : Traiter le batch
        $this->processor->processBatch($rows, $import);

        // Assert : Une énergie devrait être créée avec le code PDL/PCE
        $this->assertNotNull($capturedEnergy, 'Une énergie aurait dû être créée');

        // BUG EXPOSÉ : Cette assertion DOIT PLANTER
        // Car le header "PDL/PCE" est normalisé en 'pdlpce' qui n'a aucun mapping
        $this->assertSame(
            $pdlPceCode,
            $capturedEnergy->getCode(),
            'BUG CONFIRMÉ : Le code PDL/PCE n\'est pas enregistré car le header avec slash n\'est pas mappé correctement'
        );
    }

    /**
     * BUG 3 : La date d'échéance n'est pas enregistrée quand le header contient un caractère accentué.
     *
     * Comportement actuel :
     * - Le header Excel "Échéance" (avec É accentué) contient une date Excel (ex: 45078)
     * - Après strtolower() : "échéance"
     * - Après iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE') : "cheance" (le 'é' disparaît !)
     * - Le mapping existant attend 'echeance'
     * - AUCUN mapping pour 'cheance' → la valeur n'est jamais dans $rowData['contract_end']
     * - Résultat : l'échéance n'est jamais enregistrée dans Energy->contractEnd
     *
     * Comportement attendu :
     * - La date d'échéance devrait être convertie et enregistrée dans l'entité Energy
     *
     * Ce test DOIT PLANTER pour confirmer le bug.
     */
    #[Test]
    public function testEcheanceIsNotSavedDueToAccentedCharacter(): void
    {
        // Arrange : Créer un utilisateur qui fait l'import
        $importingUser = new User();
        $importingUser->setId(1);
        $importingUser->setEmail('importer@gdb-consulting.com');

        // Créer un import de type FULL (nécessaire pour traiter les énergies)
        $import = new Import();
        $import->setType(ImportType::FULL);
        $import->setUser($importingUser);
        $import->setOriginalFilename('test.xlsx');
        $import->setStoredFilename('test-stored.xlsx');

        // Simuler les données Excel avec le header "Échéance" contenant une date Excel
        // Date Excel : 45078 = 2023-05-31
        $excelDate = 45078;
        $rows = [
            [
                'Raison sociale' => 'Test Company Echeance',
                'Échéance' => $excelDate, // Header avec accent qui sera mal converti
                'Fournisseur actuel' => 'Engie',
                'PDL' => '12345678901234',
            ],
        ];

        // Mock : Le customer n'existe pas encore
        $this->customerRepository
            ->method('findOneBy')
            ->willReturn(null);

        // Mock : L'énergie n'existe pas encore
        $this->energyRepository
            ->method('findOneBy')
            ->willReturn(null);

        $this->energyRepository
            ->method('findBy')
            ->willReturn([]);

        // Mock : EntityManager pour capturer l'energy créée
        $capturedEnergy = null;
        $this->entityManager
            ->method('persist')
            ->willReturnCallback(function ($entity) use (&$capturedEnergy) {
                if ($entity instanceof Energy) {
                    $capturedEnergy = $entity;
                }
            });

        // Mock : EntityManager->getReference pour créer une référence User
        $this->entityManager
            ->method('getReference')
            ->willReturnCallback(function ($class, $id) use ($importingUser) {
                if (User::class === $class && 1 === $id) {
                    return $importingUser;
                }

                return null;
            });

        // Act : Traiter le batch
        $this->processor->processBatch($rows, $import);

        // Assert : Une énergie devrait être créée avec la date d'échéance
        $this->assertNotNull($capturedEnergy, 'Une énergie aurait dû être créée');

        // Calculer la date attendue à partir du nombre Excel
        $expectedDate = new \DateTime();
        $unixTimestamp = (int) round(($excelDate - 25569) * 86400);
        $expectedDate->setTimestamp($unixTimestamp);

        // BUG EXPOSÉ : Cette assertion DOIT PLANTER
        // Car le header "Échéance" est converti en "cheance" au lieu de "echeance"
        $contractEnd = $capturedEnergy->getContractEnd();
        $this->assertNotNull(
            $contractEnd,
            'BUG CONFIRMÉ : La date d\'échéance n\'est pas enregistrée car le header avec accent n\'est pas converti correctement'
        );

        // Vérification supplémentaire de la date exacte
        $this->assertEquals(
            $expectedDate->format('Y-m-d'),
            $contractEnd->format('Y-m-d'),
            'La date d\'échéance devrait correspondre à la date Excel convertie'
        );
    }
}
