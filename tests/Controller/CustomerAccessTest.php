<?php

namespace App\Test\Controller;

use App\DataFixtures\CompanyFixtures;
use App\DataFixtures\CustomerFixtures;
use App\DataFixtures\UserFixtures;
use App\Entity\Customer;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Liip\TestFixturesBundle\Services\DatabaseToolCollection;
use Liip\TestFixturesBundle\Services\DatabaseTools\AbstractDatabaseTool;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class CustomerAccessTest extends WebTestCase
{
    protected AbstractDatabaseTool $databaseTool;
    private KernelBrowser $client;
    private EntityManagerInterface $entityManager;

    protected function setUp(): void
    {
        parent::setUp();

        $this->client = static::createClient();
        $this->entityManager = static::getContainer()->get('doctrine')->getManager();
        $this->databaseTool = self::getContainer()->get(DatabaseToolCollection::class)->get();

        // Chargement des fixtures nécessaires pour les tests
        $this->databaseTool->loadFixtures([
            CompanyFixtures::class,
            UserFixtures::class,
            CustomerFixtures::class,
        ]);
    }

    public function loginAsAdmin(): void
    {
        $crawler = $this->client->request('GET', '/login');
        $form = $crawler->filter('form')->form([
            'email' => 'admin@test.com',
            'password' => 'password',
        ]);
        $this->client->submit($form);
        $this->client->followRedirect();
    }

    public function loginAsUser(): void
    {
        $crawler = $this->client->request('GET', '/login');
        $form = $crawler->filter('form')->form([
            'email' => 'user@test.com',
            'password' => 'password',
        ]);
        $this->client->submit($form);
        $this->client->followRedirect();
    }

    public function testAdminCanAccessAllCustomers(): void
    {
        // Se connecter en tant qu'admin
        $this->loginAsAdmin();
        
        // Récupérer tous les clients de la base de données pour vérifier l'accès
        $allCustomers = $this->entityManager->getRepository(Customer::class)->findAll();
        
        // Vérifier que l'admin peut accéder à chaque client
        foreach ($allCustomers as $customer) {
            $this->client->request('GET', '/customer/' . $customer->getId());
            $this->assertResponseIsSuccessful("L'admin doit pouvoir accéder au client #" . $customer->getId());
        }
    }

    public function testRegularUserCanAccessOnlyAssociatedAndUnassignedCustomers(): void
    {
        // Se connecter en tant qu'utilisateur normal
        $this->loginAsUser();
        
        // Récupérer l'utilisateur connecté
        $user = $this->entityManager->getRepository(User::class)
            ->findOneBy(['email' => 'user@test.com']);
        
        // Récupérer tous les clients
        $allCustomers = $this->entityManager->getRepository(Customer::class)->findAll();
        
        foreach ($allCustomers as $customer) {
            $this->client->request('GET', '/customer/' . $customer->getId());
            
            // Si le client appartient à l'utilisateur ou n'est pas assigné, l'accès doit être autorisé
            if ($customer->getUser() === $user || $customer->getUser() === null) {
                $this->assertResponseIsSuccessful(
                    "L'utilisateur doit pouvoir accéder à son client ou à un client non assigné #" . $customer->getId()
                );
            } else {
                // Sinon, l'accès doit être refusé (soit 403 Forbidden, soit 404 Not Found selon l'implémentation)
                $this->assertResponseStatusCodeSame(
                    403, 
                    "L'utilisateur ne doit pas pouvoir accéder au client d'un autre utilisateur #" . $customer->getId()
                );
            }
        }
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        unset($this->databaseTool);
        unset($this->entityManager);
    }
}