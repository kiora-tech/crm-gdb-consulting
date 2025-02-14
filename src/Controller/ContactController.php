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

    protected function getIndexVars($pagination, array $columns = []): array
    {
        return $this->getIndexVarsTrait($pagination, [
            ['field' => 'email', 'label' => 'contact.email', 'sortable' => true],
            ['field' => 'lastName', 'label' => 'contact.last_name', 'sortable' => true],
            ['field' => 'position', 'label' => 'contact.position'],
            ['field' => 'phone', 'label' => 'contact.phone'],
            ['field' => 'mobilePhone', 'label' => 'contact.mobile_phone'],
        ]);
    }

    protected function getRoute(): array
    {
        $route = parent::getRoute();
        $route['show'] = 'app_contact_show';

        return $route;
    }

}
