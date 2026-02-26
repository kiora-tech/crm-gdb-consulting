<?php

declare(strict_types=1);

namespace App\Tests\Functional\Validator;

use App\DataFixtures\CompanyFixtures;
use App\DataFixtures\CustomerFixtures;
use App\DataFixtures\EnergyFixtures;
use App\DataFixtures\EnergyProviderFixtures;
use App\DataFixtures\FtaFixtures;
use App\DataFixtures\UserFixtures;
use App\Entity\Customer;
use App\Entity\Energy;
use App\Entity\User;
use App\Repository\CustomerRepository;
use App\Repository\EnergyRepository;
use App\Repository\UserRepository;
use Liip\TestFixturesBundle\Services\DatabaseToolCollection;
use Liip\TestFixturesBundle\Services\DatabaseTools\AbstractDatabaseTool;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Functional test for UniqueEnergyCode validator.
 *
 * Tests that when creating an Energy with the same code, type, and contractEnd
 * as an existing Energy, the form displays an error message containing a link
 * to the existing customer.
 */
class UniqueEnergyCodeFunctionalTest extends WebTestCase
{
    private KernelBrowser $client;
    private AbstractDatabaseTool $databaseTool;
    private CustomerRepository $customerRepository;
    private EnergyRepository $energyRepository;
    private UserRepository $userRepository;

    protected function setUp(): void
    {
        parent::setUp();

        $this->client = static::createClient();
        $container = self::getContainer();

        $this->databaseTool = $container->get(DatabaseToolCollection::class)->get();
        $this->customerRepository = $container->get(CustomerRepository::class);
        $this->energyRepository = $container->get(EnergyRepository::class);
        $this->userRepository = $container->get(UserRepository::class);

        // Load all required fixtures
        $this->databaseTool->loadFixtures([
            CompanyFixtures::class,
            UserFixtures::class,
            CustomerFixtures::class,
            EnergyProviderFixtures::class,
            FtaFixtures::class,
            EnergyFixtures::class,
        ]);
    }

    /**
     * Test that creating an energy with duplicate code/type/contractEnd shows error with link to existing customer.
     */
    public function testDuplicateEnergyCodeShowsErrorWithCustomerLink(): void
    {
        // Arrange: Login as admin
        $this->loginAsAdmin();

        // Get the existing energy 'energy_admin_1_elec' which belongs to 'BioEnergie SA'
        $existingEnergy = $this->findExistingEnergy();
        $this->assertNotNull($existingEnergy, 'Existing energy fixture should be loaded');

        $existingCustomer = $existingEnergy->getCustomer();
        $this->assertNotNull($existingCustomer, 'Existing energy should have a customer');
        $this->assertSame('BioEnergie SA', $existingCustomer->getName());

        // Get a different customer to create the new energy for
        $differentCustomer = $this->findDifferentCustomer($existingCustomer);
        $this->assertNotNull($differentCustomer, 'Should find a different customer for the test');

        // Act: Navigate to the energy creation page for the different customer
        $crawler = $this->client->request(
            'GET',
            '/energy/new/' . $differentCustomer->getId() . '?energyType=ELEC'
        );

        $this->assertResponseIsSuccessful();

        // Submit the form with the same code, type, and contractEnd as the existing energy
        // The form name is 'energy' (derived from EnergyType by removing 'Type' suffix and lowercasing)
        $form = $crawler->filter('form[name="energy"]')->form();

        // Format the contract end date as expected by the form (Y-m-d)
        // The fixture uses '+180 days' relative to when fixtures were loaded, so get the actual value
        $contractEnd = $existingEnergy->getContractEnd();
        $this->assertNotNull($contractEnd, 'Existing energy should have a contract end date');
        $contractEndDate = $contractEnd->format('Y-m-d');

        $existingCode = $existingEnergy->getCode();
        $this->assertNotNull($existingCode, 'Existing energy should have a code');

        // Also need to set the type since the validator checks code/type/contractEnd
        // The type field is disabled for existing entities but for new ones we need to set it
        $form['energy[type]'] = 'ELEC';
        $form['energy[code]'] = $existingCode;
        $form['energy[contractEnd]'] = $contractEndDate;

        $crawler = $this->client->submit($form);

        // Assert: The form is re-rendered with validation error
        // Note: For non-Turbo requests, Symfony returns 200 when re-rendering form with errors
        // The 422 status is only returned for Turbo Stream requests
        $this->assertResponseIsSuccessful();

        // Verify we're still on the form page (not redirected to customer show)
        $this->assertSelectorExists('form[name="energy"]', 'Form should be re-rendered with errors');

        // Check that the validation error message is displayed
        $errorFeedback = $crawler->filter('.invalid-feedback');
        $this->assertGreaterThan(0, $errorFeedback->count(), 'Error feedback should be displayed');

        $errorMessage = $errorFeedback->text();

        // Verify the error message mentions the code is already used
        $this->assertStringContainsString(
            'Ce code est déjà utilisé',
            $errorMessage,
            'Error message should indicate the code is already used'
        );

        // Verify the error message contains a link to the existing customer
        $errorLink = $crawler->filter('.invalid-feedback a');
        $this->assertGreaterThan(0, $errorLink->count(), 'Error message should contain a link');

        // Verify the link points to the correct customer
        $linkHref = $errorLink->attr('href');
        $this->assertStringContainsString(
            '/customer/' . $existingCustomer->getId(),
            $linkHref,
            'Link should point to the existing customer page'
        );

        // Verify the link contains the customer name
        $linkText = $errorLink->text();
        $this->assertStringContainsString(
            'BioEnergie SA',
            $linkText,
            'Link text should contain the customer name'
        );

        // Verify the link has the expected CSS classes for styling
        $linkClass = $errorLink->attr('class');
        $this->assertStringContainsString('text-blue-600', $linkClass, 'Link should have blue text class');
        $this->assertStringContainsString('underline', $linkClass, 'Link should have underline class');
    }

    /**
     * Test that creating an energy with unique code/type/contractEnd succeeds.
     */
    public function testUniqueEnergyCodeAllowsCreation(): void
    {
        // Arrange: Login as admin
        $this->loginAsAdmin();

        // Get a customer to create the energy for
        $customer = $this->customerRepository->findOneBy(['name' => 'BioEnergie SA']);
        $this->assertNotNull($customer);

        // Act: Navigate to the energy creation page
        $crawler = $this->client->request(
            'GET',
            '/energy/new/' . $customer->getId() . '?energyType=ELEC'
        );

        $this->assertResponseIsSuccessful();

        // Submit the form with unique values
        // The form name is 'energy' (derived from EnergyType by removing 'Type' suffix and lowercasing)
        $form = $crawler->filter('form[name="energy"]')->form();

        $form['energy[code]'] = 'UNIQUE_PDL_12345';
        $form['energy[contractEnd]'] = '2030-12-31';

        $this->client->submit($form);

        // Assert: Should redirect to customer page on success
        $this->assertResponseRedirects(
            '/customer/' . $customer->getId(),
            null,
            'Should redirect to customer page after successful creation'
        );

        // Verify the energy was created
        $createdEnergy = $this->energyRepository->findOneBy(['code' => 'UNIQUE_PDL_12345']);
        $this->assertNotNull($createdEnergy, 'Energy should have been created');
        $this->assertSame($customer->getId(), $createdEnergy->getCustomer()->getId());
    }

    /**
     * Login as admin user using the loginUser helper.
     */
    private function loginAsAdmin(): void
    {
        $adminUser = $this->userRepository->findOneBy(['email' => 'admin@test.com']);
        $this->assertNotNull($adminUser, 'Admin user should exist in fixtures');

        $this->client->loginUser($adminUser);
    }

    /**
     * Find the existing energy 'energy_admin_1_elec' from fixtures.
     */
    private function findExistingEnergy(): ?Energy
    {
        return $this->energyRepository->findOneBy([
            'code' => 'PDL12345678901',
        ]);
    }

    /**
     * Find a customer different from the given one.
     */
    private function findDifferentCustomer(Customer $excludeCustomer): ?Customer
    {
        $customers = $this->customerRepository->findAll();

        foreach ($customers as $customer) {
            if ($customer->getId() !== $excludeCustomer->getId()) {
                return $customer;
            }
        }

        return null;
    }
}
