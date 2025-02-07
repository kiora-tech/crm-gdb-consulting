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
            'contact' => $contact,
        ]);
    }
}
