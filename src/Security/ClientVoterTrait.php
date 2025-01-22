<?php

namespace App\Security;

use App\Entity\Client;
use App\Security\Voter\ClientVoter;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Contracts\Service\Attribute\Required;
use Symfony\Contracts\Translation\TranslatorInterface;

trait ClientVoterTrait
{
    private TranslatorInterface $tr;

    #[Required]
    public function setTr(TranslatorInterface $tr): void
    {
        $this->tr = $tr;
    }

    private function isClientGranted(Client $client): bool
    {
        return $this->isGranted(ClientVoter::EDIT, $client);
    }

    /**
     * @param Client[]|Client|null $client
     */
    protected function checkClientAccess(array|Client|null $client): ?Response
    {
        if (null === $client) {
            throw new \LogicException('The client is not valid.');
        }

        if (is_array($client)) {
            foreach ($client as $c) {
                if ($this->isClientGranted($c)) {
                    return null;
                }
            }
        } else {
            if ($this->isClientGranted($client)) {
                return null;
            }
        }

        $this->addFlash('danger', $this->tr->trans('client.error_message.right'));

        return $this->redirectToRoute('app_client_index');
    }
}
