<?php

namespace App\Validator;

use App\Entity\Energy;
use App\Repository\EnergyRepository;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;
use Symfony\Component\Validator\Exception\UnexpectedValueException;

/**
 * Validator for UniqueEnergyCode constraint.
 *
 * Checks if an Energy with the same code, type, and contractEnd already exists.
 * If found, generates an error message with a link to the existing customer.
 */
class UniqueEnergyCodeValidator extends ConstraintValidator
{
    public function __construct(
        private readonly EnergyRepository $energyRepository,
        private readonly UrlGeneratorInterface $urlGenerator,
    ) {
    }

    /**
     * @param Energy $value
     */
    public function validate(mixed $value, Constraint $constraint): void
    {
        if (!$constraint instanceof UniqueEnergyCode) {
            throw new UnexpectedTypeException($constraint, UniqueEnergyCode::class);
        }

        if (!$value instanceof Energy) {
            throw new UnexpectedValueException($value, Energy::class);
        }

        // Skip validation if required fields are empty
        if (null === $value->getCode() || null === $value->getType() || null === $value->getContractEnd()) {
            return;
        }

        $existingEnergy = $this->findExistingEnergy($value);

        if (null === $existingEnergy) {
            return;
        }

        // If editing an existing entity, don't flag it as duplicate of itself
        if (null !== $value->getId() && $existingEnergy->getId() === $value->getId()) {
            return;
        }

        $customer = $existingEnergy->getCustomer();

        if (null === $customer) {
            // Fallback message without link if no customer is associated
            $this->context->buildViolation('Ce code est déjà utilisé pour ce type d\'énergie avec cette date de fin de contrat.')
                ->atPath('code')
                ->addViolation();

            return;
        }

        $customerUrl = $this->urlGenerator->generate('app_customer_show', [
            'id' => $customer->getId(),
        ]);

        $customerName = $customer->getName() ?? 'Client #' . $customer->getId();

        $this->context->buildViolation($constraint->message)
            ->atPath('code')
            ->setParameter('{{ customerUrl }}', $customerUrl)
            ->setParameter('{{ customerName }}', $customerName)
            ->addViolation();
    }

    /**
     * Find an existing Energy with the same code, type, and contractEnd.
     */
    private function findExistingEnergy(Energy $energy): ?Energy
    {
        return $this->energyRepository->findOneBy([
            'code' => $energy->getCode(),
            'type' => $energy->getType(),
            'contractEnd' => $energy->getContractEnd(),
        ]);
    }
}
