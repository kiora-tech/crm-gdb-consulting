<?php

namespace App\Test\Controller;

use App\DataFixtures\CustomerFixtures;
use App\Entity\Customer;
use Liip\TestFixturesBundle\Services\DatabaseToolCollection;
use Liip\TestFixturesBundle\Services\DatabaseTools\AbstractDatabaseTool;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class CustomerControllerTest extends WebTestCase
{
    protected AbstractDatabaseTool $databaseTool;

    private KernelBrowser $client;
    private string $path = '/customer/';

    protected function setUp(): void
    {
        parent::setUp();

        $this->client = static::createClient();
        $manager = static::getContainer()->get('doctrine')->getManager();
        $repository = $manager->getRepository(Customer::class);

        foreach ($repository->findAll() as $object) {
            $manager->remove($object);
        }

        $manager->flush();

        $this->databaseTool = self::getContainer()->get(DatabaseToolCollection::class)->get();

        $this->databaseTool->loadFixtures([
            CustomerFixtures::class
        ]);
    }

    public function testIndex(): void
    {

        $this->client->followRedirects();
        $this->client->request('GET', $this->path);

        self::assertResponseStatusCodeSame(200);

    }

    protected function tearDown(): void
    {
        parent::tearDown();
        unset($this->databaseTool);
    }
}
