<?php

declare(strict_types=1);

namespace App\Security\Voter;

use App\Entity\Import;
use App\Entity\User;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * Voter for Import entity authorization.
 *
 * @extends Voter<string, Import>
 */
class ImportVoter extends Voter
{
    public const VIEW = 'view';
    public const EDIT = 'edit';

    /**
     * @param Import $subject
     */
    protected function supports(string $attribute, mixed $subject): bool
    {
        return in_array($attribute, [self::VIEW, self::EDIT], true)
            && $subject instanceof Import;
    }

    /**
     * @param Import $subject
     */
    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token, ?Vote $vote = null): bool
    {
        $user = $token->getUser();

        // User must be authenticated
        if (!$user instanceof User) {
            return false;
        }

        // Admins can do everything
        if (in_array('ROLE_ADMIN', $user->getRoles(), true)) {
            return true;
        }

        // Users can only access their own imports
        return match ($attribute) {
            self::VIEW, self::EDIT => $this->canAccessImport($subject, $user),
            default => false,
        };
    }

    private function canAccessImport(Import $import, User $user): bool
    {
        return $import->getUser()->getId() === $user->getId();
    }
}
