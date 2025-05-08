<?php

namespace App\Notification;

use App\Entity\User;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Notifier\Message\EmailMessage;
use Symfony\Component\Notifier\Notification\EmailNotificationInterface;
use Symfony\Component\Notifier\Notification\Notification;
use Symfony\Component\Notifier\Recipient\EmailRecipientInterface;

class NewCollaboratorNotification extends Notification implements EmailNotificationInterface
{
    public function __construct(
        private readonly User $user,
        private readonly User $creator,
        private readonly string $crmUrl,
    ) {
        parent::__construct();
    }

    public function asEmailMessage(EmailRecipientInterface $recipient, ?string $transport = null): ?EmailMessage
    {
        $email = (new TemplatedEmail())
            ->to($recipient->getEmail())
            ->subject('Bienvenue chez GDB CONSULTING - Création de votre compte')
            ->htmlTemplate('mail/notify_new_collaborator.html.twig')
            ->context([
                'user' => $this->user,
                'creator' => $this->creator,
                'crm_url' => $this->crmUrl,
            ]);

        return new EmailMessage($email);
    }
}
