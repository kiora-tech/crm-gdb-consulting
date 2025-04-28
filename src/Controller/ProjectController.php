<?php

namespace App\Controller;

use App\Data\ProjectSearchData;
use App\Entity\Customer;
use App\Entity\Project;
use App\Form\ProjectSearchType;
use App\Form\ProjectType;
use App\Repository\ProjectRepository;
use App\Service\PaginationService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/project', name: 'app_project')]
class ProjectController extends AbstractController
{
    #[Route('/', name: '_index', methods: ['GET'])]
    public function index(ProjectRepository $projectRepository, PaginationService $paginationService, Request $request): Response
    {
        $data = new ProjectSearchData();
        $form = $this->createForm(ProjectSearchType::class, $data);
        $form->handleRequest($request);

        $query = $projectRepository->search($data);
        $projects = $paginationService->paginate($query, $request);

        return $this->render('project/index.html.twig', [
            'projects' => $projects,
            'form' => $form->createView(),
        ]);
    }

    #[Route('/new', name: '_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager): Response
    {
        $project = new Project();
        $form = $this->createForm(ProjectType::class, $project);
        $form->handleRequest($request);

        if ($form->isSubmitted()) {
            $entityManager->persist($project);
            $entityManager->flush();

            $this->addFlash('success', 'Project created successfully.');

            return $this->redirectToRoute('app_project_index');
        }

        return $this->render('project/_form.html.twig', [
            'form' => $form->createView(),
        ]);
    }

    #[Route('/{id}', name: '_show', methods: ['GET'])]
    public function show(Project $project): Response
    {
        return $this->render('project/show.html.twig', [
            'project' => $project,
        ]);
    }

    #[Route('/{id}/edit', name: '_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Project $project, EntityManagerInterface $entityManager): Response
    {
        $form = $this->createForm(ProjectType::class, $project);
        $form->handleRequest($request);

        if ($form->isSubmitted()) {
            dump($project);
            $entityManager->flush();

            $this->addFlash('success', 'Project updated successfully.');

            return $this->redirectToRoute('app_project_index');
        }

        return $this->render('project/_form.html.twig', [
            'form' => $form->createView(),
        ]);
    }

    #[Route('/{id}', name: '_delete', methods: ['POST'])]
    public function delete(Request $request, Project $project, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('delete' . $project->getId(), $request->request->get('_token'))) {
            $entityManager->remove($project);
            $entityManager->flush();

            $this->addFlash('success', 'Project deleted successfully.');
        }

        return $this->redirectToRoute('app_project_index');
    }
}
