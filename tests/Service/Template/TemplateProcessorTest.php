<?php

namespace App\Tests\Service\Template;

use App\Entity\Contact;
use App\Entity\Customer;
use App\Entity\Energy;
use App\Entity\EnergyType;
use App\Entity\ProspectStatus;
use App\Entity\Template;
use App\Service\Template\TemplateProcessor;
use PHPUnit\Framework\TestCase;
use PhpOffice\PhpWord\TemplateProcessor as PhpWordTemplateProcessor;

class TemplateProcessorTest extends TestCase
{
    private TemplateProcessor $templateProcessor;
    private string $projectDir;
    private Template $template;
    private Customer $customer;
    private string $templatePath;

    protected function setUp(): void
    {
        // Crée un vrai fichier template de test
        $this->templatePath = sys_get_temp_dir() . '/test-template.docx';
        copy(__DIR__ . '/fixtures/template.docx', $this->templatePath);

        $this->projectDir = sys_get_temp_dir();
        $this->templateProcessor = new TemplateProcessor($this->projectDir);

        // Configure le template
        $this->template = new Template();
        $this->template->setPath($this->templatePath);
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

        // Ajoute une énergie
        $energy = new Energy();
        $energy->setType(EnergyType::ELEC);
        $energy->setCode('123456');
        $energy->setProvider('EDF');
        $this->customer->addEnergy($energy);
    }

    public function testProcessTemplateWithBasicFields(): void
    {
        $mockProcessor = $this->getMockBuilder(PhpWordTemplateProcessor::class)
            ->disableOriginalConstructor()
            ->getMock();

        // Configure les attentes individuelles
        $mockProcessor->expects($this->exactly(3))
            ->method('setValue')
            ->willReturnMap([
                ['name', 'Test Company', $mockProcessor],
                ['siret', '12345678901234', $mockProcessor],
                ['status', 'in_progress', $mockProcessor]
            ]);

        // Injecte le contenu de test
        $this->setTemplateContent($mockProcessor, '${name} ${siret} ${status}');

        $result = $this->templateProcessor->processTemplate($this->template, $this->customer);
        $this->assertFileExists($result);
        unlink($result);
    }

    public function testProcessTemplateWithCollections(): void
    {
        $mockProcessor = $this->getMockBuilder(PhpWordTemplateProcessor::class)
            ->disableOriginalConstructor()
            ->getMock();

        // Configure les attentes individuelles
        $mockProcessor->expects($this->exactly(4))
            ->method('setValue')
            ->willReturnMap([
                ['contacts[0].firstName', 'John', $mockProcessor],
                ['contacts[0].lastName', 'Doe', $mockProcessor],
                ['energies[0].type', 'ELEC', $mockProcessor],
                ['energies[0].code', '123456', $mockProcessor]
            ]);

        $this->setTemplateContent($mockProcessor,
            '${contacts[0].firstName} ${contacts[0].lastName} ${energies[0].type} ${energies[0].code}');

        $result = $this->templateProcessor->processTemplate($this->template, $this->customer);
        $this->assertFileExists($result);
        unlink($result);
    }

    public function testProcessTemplateWithInvalidPath(): void
    {
        // Crée un vrai fichier template pour ce test
        $tempFile = tempnam(sys_get_temp_dir(), 'template') . '.docx';
        copy($this->templatePath, $tempFile);

        $template = new Template();
        $template->setPath($tempFile);

        $result = $this->templateProcessor->processTemplate($template, $this->customer);

        $this->assertFileExists($result);
        $content = file_get_contents($result);
        $this->assertStringContainsString('', $content); // Le champ invalide devrait être vide

        unlink($tempFile);
        unlink($result);
    }

    public function testProcessTemplateWithDates(): void
    {
        $date = new \DateTime('2024-01-01');
        $energy = $this->customer->getEnergies()->first();
        $energy->setContractEnd($date);

        // Crée un vrai fichier template pour ce test
        $tempFile = tempnam(sys_get_temp_dir(), 'template') . '.docx';
        copy($this->templatePath, $tempFile);

        $template = new Template();
        $template->setPath($tempFile);

        $result = $this->templateProcessor->processTemplate($template, $this->customer);

        $this->assertFileExists($result);
        unlink($tempFile);
        unlink($result);
    }

    public function testProcessTemplateCreatesTemporaryFile(): void
    {
        // Crée un vrai fichier template pour ce test
        $tempFile = tempnam(sys_get_temp_dir(), 'template') . '.docx';
        copy($this->templatePath, $tempFile);

        $template = new Template();
        $template->setPath($tempFile);

        $result = $this->templateProcessor->processTemplate($template, $this->customer);

        $this->assertFileExists($result);
        $this->assertNotEquals($tempFile, $result);

        unlink($tempFile);
        unlink($result);
    }

    private function setTemplateContent(PhpWordTemplateProcessor $processor, string $content): void
    {
        $reflection = new \ReflectionClass(PhpWordTemplateProcessor::class);
        $property = $reflection->getProperty('tempDocumentMainPart');
        $property->setAccessible(true);
        $property->setValue($processor, $content);
    }

    protected function tearDown(): void
    {
        if (file_exists($this->templatePath)) {
            unlink($this->templatePath);
        }
    }
}
