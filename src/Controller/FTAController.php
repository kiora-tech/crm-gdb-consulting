<?php

namespace App\Controller;

use App\Entity\Fta;
use App\Form\FtaType;
use App\Repository\FTARepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/fta')]
final class FtaController extends AbstractController
{
    #[Route(name: 'app_fta_index', methods: ['GET'])]
    public function index(FTARepository $fTARepository): Response
    {
        return $this->render('fta/index.html.twig', [
            'ftas' => $fTARepository->findAll(),
        ]);
    }

    #[Route('/new', name: 'app_fta_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager): Response
    {
        $fta = new Fta();
        $form = $this->createForm(FtaType::class, $fta);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->persist($fta);
            $entityManager->flush();

            return $this->redirectToRoute('app_fta_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('fta/new.html.twig', [
            'fta' => $fta,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_fta_show', methods: ['GET'])]
    public function show(Fta $fta): Response
    {
        return $this->render('fta/show.html.twig', [
            'fta' => $fta,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_fta_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Fta $fta, EntityManagerInterface $entityManager): Response
    {
        $form = $this->createForm(FtaType::class, $fta);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();

            return $this->redirectToRoute('app_fta_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('fta/edit.html.twig', [
            'fta' => $fta,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_fta_delete', methods: ['POST'])]
    public function delete(Request $request, Fta $fta, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('delete'.$fta->getId(), $request->getPayload()->getString('_token'))) {
            $entityManager->remove($fta);
            $entityManager->flush();
        }

        return $this->redirectToRoute('app_fta_index', [], Response::HTTP_SEE_OTHER);
    }
}
