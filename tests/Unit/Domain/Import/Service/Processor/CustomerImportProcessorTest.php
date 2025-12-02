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

    /**
     * BUG 4 : Le numéro de téléphone n'est pas enregistré quand c'est un nombre.
     *
     * Comportement actuel :
     * - Le numéro de téléphone vient d'Excel comme un nombre (ex: 612345678)
     * - Dans processContact(), ligne 386 : $phone = isset($rowData['phone']) && is_string($rowData['phone']) ? $rowData['phone'] : null;
     * - is_string() retourne false pour un nombre → le téléphone est ignoré
     * - Résultat : le numéro de téléphone n'est jamais enregistré dans Contact->phone
     *
     * Comportement attendu :
     * - Le numéro de téléphone devrait être converti en string et enregistré
     *
     * Ce test DOIT PLANTER pour confirmer le bug.
     */
    #[Test]
    public function testPhoneNumberIsNotSavedWhenNumeric(): void
    {
        // Arrange : Créer un utilisateur qui fait l'import
        $importingUser = new User();
        $importingUser->setId(1);
        $importingUser->setEmail('importer@gdb-consulting.com');

        // Créer un import de type CUSTOMER
        $import = new Import();
        $import->setType(ImportType::CUSTOMER);
        $import->setUser($importingUser);
        $import->setOriginalFilename('test.xlsx');
        $import->setStoredFilename('test-stored.xlsx');

        // Simuler les données Excel avec le numéro de téléphone comme nombre (comportement réel d'Excel)
        $phoneNumber = 612345678; // Nombre, pas string - comme Excel le renvoie
        $rows = [
            [
                'Raison sociale' => 'Test Company Phone',
                'Contact' => 'Jean Dupont',
                'Email' => 'jean@test.fr',
                'Téléphone' => $phoneNumber, // Nombre Excel, pas string !
            ],
        ];

        // Mock : Le customer n'existe pas encore
        $this->customerRepository
            ->method('findOneBy')
            ->willReturn(null);

        // Mock : ContactRepository pour la recherche de contact existant
        $contactRepository = $this->createMock(\App\Repository\ContactRepository::class);
        $contactRepository
            ->method('findContactByCustomerAndEmailOrNumber')
            ->willReturn(null);

        $this->entityManager
            ->method('getRepository')
            ->willReturn($contactRepository);

        // Mock : EntityManager pour capturer le contact créé
        $capturedContact = null;
        $this->entityManager
            ->method('persist')
            ->willReturnCallback(function ($entity) use (&$capturedContact) {
                if ($entity instanceof \App\Entity\Contact) {
                    $capturedContact = $entity;
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

        // Assert : Un contact devrait être créé avec le téléphone
        $this->assertNotNull($capturedContact, 'Un contact aurait dû être créé');

        // BUG EXPOSÉ : Cette assertion DOIT PLANTER
        // Car is_string($phoneNumber) retourne false pour un nombre
        $this->assertNotNull(
            $capturedContact->getPhone(),
            'BUG CONFIRMÉ : Le numéro de téléphone n\'est pas enregistré car c\'est un nombre et non une string'
        );

        $this->assertEquals(
            (string) $phoneNumber,
            $capturedContact->getPhone(),
            'Le numéro de téléphone devrait être enregistré comme string'
        );
    }

    /**
     * BUG 5 : Plusieurs compteurs sur la même fiche client ne sont pas tous importés.
     *
     * Comportement actuel :
     * - Un client a plusieurs lignes dans le fichier avec des compteurs différents (PDL différents)
     * - À la ligne 516-525 de processEnergy(), si le client a exactement une énergie du type,
     *   elle est réutilisée au lieu d'en créer une nouvelle
     * - Résultat : seul le premier compteur est créé, les suivants mettent à jour le premier
     *
     * Comportement attendu :
     * - Chaque ligne avec un PDL différent devrait créer une nouvelle énergie
     *
     * Ce test DOIT PLANTER pour confirmer le bug.
     */
    #[Test]
    public function testMultipleEnergiesForSameCustomerAreNotAllCreated(): void
    {
        // Arrange : Créer un utilisateur qui fait l'import
        $importingUser = new User();
        $importingUser->setId(1);
        $importingUser->setEmail('importer@gdb-consulting.com');

        // Créer un import de type FULL
        $import = new Import();
        $import->setType(ImportType::FULL);
        $import->setUser($importingUser);
        $import->setOriginalFilename('test.xlsx');
        $import->setStoredFilename('test-stored.xlsx');

        // Créer un customer qui existera déjà après la première ligne
        $existingCustomer = new Customer();
        $existingCustomer->setId(1);
        $existingCustomer->setName('Test Company Multi-Energy');
        $existingCustomer->setSiret('12345678901234');

        // Simuler les données Excel avec DEUX lignes pour le MÊME client mais des PDL différents
        $pdl1 = '11111111111111';
        $pdl2 = '22222222222222';
        $rows = [
            [
                'Raison sociale' => 'Test Company Multi-Energy',
                'SIRET' => '12345678901234',
                'PDL' => $pdl1,
                'Fournisseur actuel' => 'EDF',
                'Elec/Gaz' => 'ELEC',
            ],
            [
                'Raison sociale' => 'Test Company Multi-Energy',
                'SIRET' => '12345678901234',
                'PDL' => $pdl2, // PDL différent !
                'Fournisseur actuel' => 'Engie',
                'Elec/Gaz' => 'ELEC',
            ],
        ];

        // Mock : Le customer existe après la première ligne
        $callCount = 0;
        $this->customerRepository
            ->method('findOneBy')
            ->willReturnCallback(function ($criteria) use ($existingCustomer, &$callCount) {
                ++$callCount;
                // Première ligne : customer n'existe pas
                if ($callCount <= 2) {
                    return 1 === $callCount ? null : $existingCustomer;
                }

                // Deuxième ligne : customer existe
                return $existingCustomer;
            });

        // Mock : EnergyRepository - simule le comportement réel
        /** @var array<int, Energy> $createdEnergies */
        $createdEnergies = [];
        $this->energyRepository
            ->method('findOneBy')
            ->willReturnCallback(function ($criteria) use (&$createdEnergies): ?Energy {
                // Cherche par code + type
                if (isset($criteria['code']) && [] !== $createdEnergies) {
                    foreach ($createdEnergies as $energy) {
                        if ($energy->getCode() === $criteria['code']) {
                            return $energy;
                        }
                    }
                }

                return null;
            });

        $this->energyRepository
            ->method('findBy')
            ->willReturnCallback(function ($criteria) use (&$createdEnergies) {
                // Retourne les énergies du customer du type donné
                // C'est ici le problème : après la création de la première énergie,
                // findBy retourne cette énergie, donc le code réutilise au lieu de créer
                return $createdEnergies;
            });

        // Mock : EntityManager pour capturer les énergies créées
        $this->entityManager
            ->method('persist')
            ->willReturnCallback(function ($entity) use (&$createdEnergies) {
                if ($entity instanceof Energy) {
                    $createdEnergies[] = $entity;
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

        // Assert : DEUX énergies devraient être créées
        // BUG EXPOSÉ : Cette assertion DOIT PLANTER
        // Car après la création de la première énergie, findBy retourne cette énergie
        // et le code à la ligne 522 réutilise cette énergie au lieu d'en créer une nouvelle
        $this->assertCount(
            2,
            $createdEnergies,
            'BUG CONFIRMÉ : Seulement 1 énergie créée au lieu de 2 car les lignes suivantes réutilisent la première énergie'
        );

        // Vérifier que les deux PDL différents sont présents
        $pdlCodes = array_map(fn (Energy $e) => $e->getCode(), $createdEnergies);
        $this->assertContains($pdl1, $pdlCodes, 'Le premier PDL devrait être enregistré');
        $this->assertContains($pdl2, $pdlCodes, 'Le deuxième PDL devrait être enregistré');
    }
}
