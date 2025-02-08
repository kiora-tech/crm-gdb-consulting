<?php

namespace App\Controller;

use App\Entity\Contact;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/contact', name: 'app_contact')]
final class ContactController extends CustomerInfoController
{
   public function getEntityClass(): string
   {
       return Contact::class;
   }

   #[Route('/{id}', name: '_show', methods: ['GET'])]
    public function show(Contact $contact): Response
    {
        return $this->render('contact/show.html.twig', [
            'entity' => $contact,
        ]);
    }

    protected function getSortableFields(): array
    {
        return [
            'e.email' => 'e.email',
            'e.firstName' => 'e.firstName',
            'e.lastName' => 'e.lastName',
            'e.position' => 'e.position',
            'e.phone' => 'e.phone',
        ];
    }
}
