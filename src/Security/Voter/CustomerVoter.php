<?php

namespace App\Security\Voter;

use App\Entity\Customer;
use App\Entity\User;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * @extends Voter<string, Customer>
 */
class CustomerVoter extends Voter
{
    public const VIEW = 'view';
    public const EDIT = 'edit';

    protected function supports(string $attribute, mixed $subject): bool
    {
        return in_array($attribute, [self::VIEW, self::EDIT])
            && $subject instanceof Customer;
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token, ?Vote $vote = null): bool
    {
        $user = $token->getUser();
        // Si l'utilisateur n'est pas connecté, refuser l'accès
        if (!$user instanceof UserInterface) {
            return false;
        }

        /** @var Customer $customer */
        $customer = $subject;

        // Vérifier si l'utilisateur est un administrateur
        if (in_array('ROLE_ADMIN', $user->getRoles())) {
            return true;
        }

        // Si l'utilisateur n'est pas un type User, refuser l'accès
        if (!$user instanceof User) {
            return false;
        }

        // Les cas selon les attributs
        return match ($attribute) {
            self::VIEW => $this->canView($customer, $user),
            self::EDIT => $this->canEdit($customer, $user),
            default => false,
        };
    }

    private function canView(Customer $customer, User $user): bool
    {
        return $customer->getUser() === $user || null === $customer->getUser();
    }

    private function canEdit(Customer $customer, User $user): bool
    {
        return $customer->getUser() === $user || null === $customer->getUser();
    }
}
