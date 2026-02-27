<?php

declare(strict_types=1);

namespace App\Tests\Unit\Validator;

use App\Entity\Customer;
use App\Entity\Energy;
use App\Entity\EnergyType;
use App\Repository\EnergyRepository;
use App\Validator\UniqueEnergyCode;
use App\Validator\UniqueEnergyCodeValidator;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Validator\Context\ExecutionContextInterface;
use Symfony\Component\Validator\Violation\ConstraintViolationBuilderInterface;

class UniqueEnergyCodeValidatorTest extends TestCase
{
    private EnergyRepository&MockObject $energyRepository;
    private UrlGeneratorInterface&MockObject $urlGenerator;
    private ExecutionContextInterface&MockObject $context;
    private UniqueEnergyCodeValidator $validator;
    private UniqueEnergyCode $constraint;

    protected function setUp(): void
    {
        parent::setUp();

        $this->energyRepository = $this->createMock(EnergyRepository::class);
        $this->urlGenerator = $this->createMock(UrlGeneratorInterface::class);
        $this->context = $this->createMock(ExecutionContextInterface::class);
        $this->constraint = new UniqueEnergyCode();

        $this->validator = new UniqueEnergyCodeValidator(
            $this->energyRepository,
            $this->urlGenerator
        );
        $this->validator->initialize($this->context);
    }

    public function testValidationPassesWhenNoDuplicateExists(): void
    {
        // Arrange
        $energy = $this->createEnergy(null, 'PDL123456', EnergyType::ELEC, new \DateTime('2025-12-31'));

        $this->energyRepository
            ->expects($this->once())
            ->method('findOneBy')
            ->with([
                'code' => 'PDL123456',
                'type' => EnergyType::ELEC,
                'contractEnd' => $energy->getContractEnd(),
            ])
            ->willReturn(null);

        $this->context
            ->expects($this->never())
            ->method('buildViolation');

        // Act
        $this->validator->validate($energy, $this->constraint);
    }

    public function testValidationFailsWithLinkToCustomerWhenDuplicateExists(): void
    {
        // Arrange
        $existingCustomer = new Customer();
        $existingCustomer->setId(42);
        $existingCustomer->setName('ACME Corporation');

        $existingEnergy = $this->createEnergy(1, 'PDL123456', EnergyType::ELEC, new \DateTime('2025-12-31'));
        $existingEnergy->setCustomer($existingCustomer);

        $newEnergy = $this->createEnergy(null, 'PDL123456', EnergyType::ELEC, new \DateTime('2025-12-31'));

        $this->energyRepository
            ->expects($this->once())
            ->method('findOneBy')
            ->willReturn($existingEnergy);

        $this->urlGenerator
            ->expects($this->once())
            ->method('generate')
            ->with('app_customer_show', ['id' => 42])
            ->willReturn('/customer/42');

        $violationBuilder = $this->createMock(ConstraintViolationBuilderInterface::class);
        $violationBuilder
            ->expects($this->once())
            ->method('atPath')
            ->with('code')
            ->willReturnSelf();
        $violationBuilder
            ->expects($this->exactly(2))
            ->method('setParameter')
            ->willReturnCallback(function (string $key, string $value) use ($violationBuilder) {
                static $calls = 0;
                ++$calls;
                if (1 === $calls) {
                    $this->assertSame('{{ customerUrl }}', $key);
                    $this->assertSame('/customer/42', $value);
                } elseif (2 === $calls) {
                    $this->assertSame('{{ customerName }}', $key);
                    $this->assertSame('ACME Corporation', $value);
                }

                return $violationBuilder;
            });
        $violationBuilder
            ->expects($this->once())
            ->method('addViolation');

        $this->context
            ->expects($this->once())
            ->method('buildViolation')
            ->with($this->constraint->message)
            ->willReturn($violationBuilder);

        // Act
        $this->validator->validate($newEnergy, $this->constraint);
    }

    public function testValidationPassesWhenEditingSameEntity(): void
    {
        // Arrange
        $existingEnergy = $this->createEnergy(5, 'PDL123456', EnergyType::ELEC, new \DateTime('2025-12-31'));

        $this->energyRepository
            ->expects($this->once())
            ->method('findOneBy')
            ->willReturn($existingEnergy);

        $this->context
            ->expects($this->never())
            ->method('buildViolation');

        // Act - Validate the same entity (editing scenario)
        $this->validator->validate($existingEnergy, $this->constraint);
    }

    public function testValidationPassesWhenCodeIsNull(): void
    {
        // Arrange
        $energy = $this->createEnergy(null, null, EnergyType::ELEC, new \DateTime('2025-12-31'));

        $this->energyRepository
            ->expects($this->never())
            ->method('findOneBy');

        $this->context
            ->expects($this->never())
            ->method('buildViolation');

        // Act
        $this->validator->validate($energy, $this->constraint);
    }

    public function testValidationPassesWhenTypeIsNull(): void
    {
        // Arrange
        $energy = $this->createEnergy(null, 'PDL123456', null, new \DateTime('2025-12-31'));

        $this->energyRepository
            ->expects($this->never())
            ->method('findOneBy');

        $this->context
            ->expects($this->never())
            ->method('buildViolation');

        // Act
        $this->validator->validate($energy, $this->constraint);
    }

    public function testValidationPassesWhenContractEndIsNull(): void
    {
        // Arrange
        $energy = $this->createEnergy(null, 'PDL123456', EnergyType::ELEC, null);

        $this->energyRepository
            ->expects($this->never())
            ->method('findOneBy');

        $this->context
            ->expects($this->never())
            ->method('buildViolation');

        // Act
        $this->validator->validate($energy, $this->constraint);
    }

    public function testValidationFailsWithFallbackMessageWhenCustomerIsNull(): void
    {
        // Arrange
        $existingEnergy = $this->createEnergy(1, 'PDL123456', EnergyType::ELEC, new \DateTime('2025-12-31'));
        $existingEnergy->setCustomer(null);

        $newEnergy = $this->createEnergy(null, 'PDL123456', EnergyType::ELEC, new \DateTime('2025-12-31'));

        $this->energyRepository
            ->expects($this->once())
            ->method('findOneBy')
            ->willReturn($existingEnergy);

        $this->urlGenerator
            ->expects($this->never())
            ->method('generate');

        $violationBuilder = $this->createMock(ConstraintViolationBuilderInterface::class);
        $violationBuilder
            ->expects($this->once())
            ->method('atPath')
            ->with('code')
            ->willReturnSelf();
        $violationBuilder
            ->expects($this->once())
            ->method('addViolation');

        $this->context
            ->expects($this->once())
            ->method('buildViolation')
            ->with('Ce code est déjà utilisé pour ce type d\'énergie avec cette date de fin de contrat.')
            ->willReturn($violationBuilder);

        // Act
        $this->validator->validate($newEnergy, $this->constraint);
    }

    public function testValidationUsesCustomerIdAsFallbackNameWhenNameIsNull(): void
    {
        // Arrange
        $existingCustomer = new Customer();
        $existingCustomer->setId(99);
        // Name is not set (remains null)

        $existingEnergy = $this->createEnergy(1, 'PCE789', EnergyType::GAZ, new \DateTime('2026-06-30'));
        $existingEnergy->setCustomer($existingCustomer);

        $newEnergy = $this->createEnergy(null, 'PCE789', EnergyType::GAZ, new \DateTime('2026-06-30'));

        $this->energyRepository
            ->expects($this->once())
            ->method('findOneBy')
            ->willReturn($existingEnergy);

        $this->urlGenerator
            ->expects($this->once())
            ->method('generate')
            ->with('app_customer_show', ['id' => 99])
            ->willReturn('/customer/99');

        $violationBuilder = $this->createMock(ConstraintViolationBuilderInterface::class);
        $violationBuilder
            ->expects($this->once())
            ->method('atPath')
            ->with('code')
            ->willReturnSelf();
        $violationBuilder
            ->expects($this->exactly(2))
            ->method('setParameter')
            ->willReturnCallback(function (string $key, string $value) use ($violationBuilder) {
                static $calls = 0;
                ++$calls;
                if (2 === $calls) {
                    $this->assertSame('{{ customerName }}', $key);
                    $this->assertSame('Client #99', $value);
                }

                return $violationBuilder;
            });
        $violationBuilder
            ->expects($this->once())
            ->method('addViolation');

        $this->context
            ->expects($this->once())
            ->method('buildViolation')
            ->willReturn($violationBuilder);

        // Act
        $this->validator->validate($newEnergy, $this->constraint);
    }

    /**
     * Creates an Energy entity with the specified properties.
     */
    private function createEnergy(
        ?int $id,
        ?string $code,
        ?EnergyType $type,
        ?\DateTimeInterface $contractEnd,
    ): Energy {
        $energy = new Energy();
        $energy->setId($id);
        $energy->setCode($code);
        $energy->setType($type);
        $energy->setContractEnd($contractEnd);

        return $energy;
    }
}
