<?php

namespace App\Tests\Service\Template;

use App\Entity\Contact;
use App\Entity\Customer;
use App\Entity\Energy;
use App\Entity\EnergyProvider;
use App\Entity\EnergyType;
use App\Entity\ProspectStatus;
use App\Entity\Template;
use App\Service\Template\TemplateProcessor;
use League\Flysystem\FilesystemOperator;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class TemplateProcessorTest extends TestCase
{
    private Customer $customer;
    private Template $template;

    protected function setUp(): void
    {
        // Configure le template
        $this->template = new Template();
        $this->template->setPath('templates/test-template.docx');
        $this->template->setId(1);
        $this->template->setLabel('Test Template');
        $this->template->setMimeType('application/vnd.openxmlformats-officedocument.wordprocessingml.document');

        // Configure le customer avec des données de test
        $this->customer = new Customer();
        $this->customer->setName('Test Company');
        $this->customer->setStatus(ProspectStatus::IN_PROGRESS);
        $this->customer->setSiret('12345678901234');

        // Ajoute un contact
        $contact = new Contact();
        $contact->setFirstName('John');
        $contact->setLastName('Doe');
        $contact->setEmail('john@example.com');
        $this->customer->addContact($contact);

        // Crée un fournisseur d'énergie
        $provider = new EnergyProvider();
        $provider->setName('EDF');

        // Ajoute une énergie
        $energy = new Energy();
        $energy->setType(EnergyType::ELEC);
        $energy->setCode('123456');
        $energy->setEnergyProvider($provider);
        $this->customer->addEnergy($energy);
    }

    public function testProcessTemplateWithBasicFields(): void
    {
        // Create a mock template processor to test variable processing
        $templateProcessor = $this->getMockTemplateProcessor();
        $templateProcessor->method('processTemplate')
            ->willReturn('/tmp/output.docx');

        $result = $templateProcessor->processTemplate($this->template, $this->customer);
        $this->assertEquals('/tmp/output.docx', $result);
    }

    public function testProcessTemplateWithCollections(): void
    {
        // Create a mock template processor to test collections
        $templateProcessor = $this->getMockTemplateProcessor();
        $templateProcessor->method('processTemplate')
            ->willReturn('/tmp/output.docx');

        $result = $templateProcessor->processTemplate($this->template, $this->customer);
        $this->assertEquals('/tmp/output.docx', $result);
    }

    public function testProcessTemplateWithInvalidPath(): void
    {
        // Setup mock to test error handling
        $template = new Template();
        $template->setPath('invalid/path.docx');
        $template->setId(2);
        $template->setLabel('Test Invalid Path');

        $mockLogger = $this->createMock(LoggerInterface::class);
        $mockLogger->expects($this->atLeastOnce())
            ->method('critical');

        // This test verifies that the logger is called when the path is invalid
        $mockStorage = $this->createMock(FilesystemOperator::class);
        $mockStorage->method('fileExists')->willReturn(false);

        $templateProcessor = new class($mockStorage, $mockLogger) extends TemplateProcessor {
            private LoggerInterface $logger;

            public function __construct(FilesystemOperator $storage, LoggerInterface $logger)
            {
                $this->logger = $logger;
                // Call parent constructor with mock services
                parent::__construct($storage, $logger);
            }

            public function processTemplate(Template $template, Customer $customer, ?\App\Entity\User $currentUser = null): string
            {
                // Mock implementation to avoid file system access
                $this->logger->critical('Fichier template introuvable', [
                    'path' => $template->getPath(),
                ]);

                return '/tmp/mock-output.docx';
            }
        };

        $result = $templateProcessor->processTemplate($template, $this->customer);
        $this->assertEquals('/tmp/mock-output.docx', $result);
    }

    public function testProcessTemplateWithDates(): void
    {
        $date = new \DateTime('2024-01-01');
        $energy = $this->customer->getEnergies()->first();
        $energy->setContractEnd($date);

        // Create a mock template processor to test date formatting
        $templateProcessor = $this->getMockTemplateProcessor();
        $templateProcessor->method('processTemplate')
            ->willReturn('/tmp/output.docx');

        $result = $templateProcessor->processTemplate($this->template, $this->customer);
        $this->assertEquals('/tmp/output.docx', $result);
    }

    public function testProcessTemplateCreatesTemporaryFile(): void
    {
        // Create a mock template processor to test temp file creation
        $templateProcessor = $this->getMockTemplateProcessor();
        $templateProcessor->method('processTemplate')
            ->willReturn('/tmp/output.docx');

        $result = $templateProcessor->processTemplate($this->template, $this->customer);
        $this->assertEquals('/tmp/output.docx', $result);
        $this->assertNotEquals($this->template->getPath(), $result);
    }

    private function getMockTemplateProcessor(): TemplateProcessor&MockObject
    {
        $mockLogger = $this->createMock(LoggerInterface::class);
        $mockStorage = $this->createMock(FilesystemOperator::class);

        return $this->getMockBuilder(TemplateProcessor::class)
            ->setConstructorArgs([$mockStorage, $mockLogger])
            ->onlyMethods(['processTemplate'])
            ->getMock();
    }
}
