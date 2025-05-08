<?php

namespace App\Test\Controller;

use App\DataFixtures\CompanyFixtures;
use App\DataFixtures\UserFixtures;
use Liip\TestFixturesBundle\Services\DatabaseToolCollection;
use Liip\TestFixturesBundle\Services\DatabaseTools\AbstractDatabaseTool;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class SecurityControllerTest extends WebTestCase
{
    protected AbstractDatabaseTool $databaseTool;
    private KernelBrowser $client;

    protected function setUp(): void
    {
        parent::setUp();

        $this->client = static::createClient();
        $this->databaseTool = self::getContainer()->get(DatabaseToolCollection::class)->get();

        // Chargement des fixtures nécessaires pour les tests
        $this->databaseTool->loadFixtures([
            CompanyFixtures::class,
            UserFixtures::class,
        ]);
    }

    public function testLoginPageIsAccessible(): void
    {
        $this->client->request('GET', '/login');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('.card-title', 'Login');
    }

    public function testLoginWithValidCredentials(): void
    {
        $crawler = $this->client->request('GET', '/login');

        // Récupère le formulaire de connexion
        $form = $crawler->filter('form.row')->form([
            'email' => 'admin@test.com',
            'password' => 'password',
        ]);

        $this->client->submit($form);

        // Vérifie que la redirection a bien lieu
        $this->assertResponseRedirects();

        // Suit la redirection
        $this->client->followRedirect();

        // Vérifie que nous sommes sur la page d'accueil après connexion
        $this->assertRouteSame('homepage');
    }

    public function testLoginWithInvalidCredentials(): void
    {
        $crawler = $this->client->request('GET', '/login');

        // Récupère le formulaire de connexion
        $form = $crawler->filter('form.row')->form([
            'email' => 'wrong@email.com',
            'password' => 'wrongpassword',
        ]);

        $this->client->submit($form);

        // Suit la redirection (en cas d'échec de connexion, on revient sur la page de login)
        $this->client->followRedirect();

        // Vérifie que nous sommes toujours sur la page de login
        $this->assertRouteSame('app_login');

        // Vérifie qu'un message d'erreur est affiché
        $this->assertSelectorExists('.alert-danger');
    }

    public function testAccessToCustomerPageRequiresLogin(): void
    {
        // Essaie d'accéder à la page client sans être connecté
        $this->client->request('GET', '/customer/');

        // Vérifie qu'on est redirigé vers la page de login
        $this->assertResponseRedirects('/login');
    }

    public function testAccessToCustomerPageAfterLogin(): void
    {
        // Se connecte d'abord
        $crawler = $this->client->request('GET', '/login');
        $form = $crawler->filter('form.row')->form([
            'email' => 'admin@test.com',
            'password' => 'password',
        ]);

        $this->client->submit($form);
        $this->client->followRedirect();

        // Maintenant accède à la page client
        $this->client->request('GET', '/customer/');

        // Vérifie que l'accès est réussi
        $this->assertResponseIsSuccessful();
        // Vérifie que nous sommes sur la page des clients
        $this->assertSelectorTextContains('.pagetitle h1', 'Liste des clients');
    }
}
