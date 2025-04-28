<?php

namespace App\Controller;

use App\Data\ContactSearchData;
use App\Entity\Contact;
use App\Form\ContactSearchType;
use App\Form\ContactType;
use App\Repository\ContactRepository;
use App\Service\PaginationService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

#[Route('/contact', name: 'app_contact')]
final class ContactController extends AbstractController
{
    #[Route('/', name: '_index', methods: ['GET'])]
    public function index(ContactRepository $contactRepository, PaginationService $paginationService, Request $request): Response
    {
        $searchData = new ContactSearchData();
        $form = $this->createForm(ContactSearchType::class, $searchData);
        $form->handleRequest($request);

        $query = $contactRepository->search($searchData);
        $contacts = $paginationService->paginate($query, $request);

        return $this->render('contact/index.html.twig', [
            'contacts' => $contacts,
            'form' => $form->createView(),
        ]);
    }

    #[Route('/contact/new', name: '_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager): Response
    {
        $contact = new Contact();
        $form = $this->createForm(ContactType::class, $contact);
        $form->handleRequest($request);

        if ($form->isSubmitted()) {
            $entityManager->persist($contact);
            $entityManager->flush();

            $this->addFlash('success', 'Contact created successfully.');

            return $this->redirectToRoute('app_contact_index');
        }

        return $this->render('contact/new.html.twig', [
            'form' => $form->createView(),
        ]);
    }

    #[Route('/contact/{id}/edit', name: '_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Contact $contact, EntityManagerInterface $entityManager): Response
    {
        $form = $this->createForm(ContactType::class, $contact);
        $form->handleRequest($request);

        if ($form->isSubmitted()) {
            $entityManager->flush();

            $this->addFlash('success', 'Contact updated successfully.');

            return $this->redirectToRoute('app_contact_index');
        }

        return $this->render('contact/edit.html.twig', [
            'contact' => $contact,
            'form' => $form->createView(),
        ]);
    }

    #[Route('/contact/{id}', name: '_show', methods: ['GET'])]
    public function show(Contact $contact): Response
    {
        return $this->render('contact/show.html.twig', [
            'contact' => $contact,
        ]);
    }

    #[Route('/contact/{id}', name: '_delete', methods: ['POST'])]
    public function delete(Request $request, Contact $contact, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('delete' . $contact->getId(), $request->request->get('_token'))) {
            $entityManager->remove($contact);
            $entityManager->flush();

            $this->addFlash('success', 'Contact deleted successfully.');
        }

        return $this->redirectToRoute('app_contact_index');
    }
}
