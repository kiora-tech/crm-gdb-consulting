<?php

namespace App\Notification;

use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Notifier\Message\EmailMessage;
use Symfony\Component\Notifier\Notification\EmailNotificationInterface;
use Symfony\Component\Notifier\Notification\Notification;
use Symfony\Component\Notifier\Recipient\EmailRecipientInterface;
use Symfony\Contracts\Service\Attribute\Required;

class NewCollaboratorNotification extends Notification implements EmailNotificationInterface
{


    public function asEmailMessage(EmailRecipientInterface $recipient, ?string $transport = null): ?EmailMessage
    {
        $email = (new TemplatedEmail())
            ->to($recipient->getEmail())
            ->subject($this->getSubject())

        ->htmlTemplate('mail/notify_new_collaborator.html.twig')
       ;


        return new EmailMessage($email);
    }
}