<?php

namespace App\Controller;

use App\Entity\User;
use App\Service\MicrosoftGraphService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/tasks')]
class TaskController extends AbstractController
{
    public function __construct(
        private readonly MicrosoftGraphService $microsoftGraphService,
    ) {
    }

    #[Route('/', name: 'app_tasks_index', methods: ['GET'])]
    public function index(): Response
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            throw new \LogicException('User must be logged in');
        }

        if (!$this->microsoftGraphService->hasValidToken($user)) {
            $this->addFlash('warning', 'Please connect your Microsoft account to view your tasks.');

            return $this->redirectToRoute('app_user_profile');
        }

        try {
            $tasks = $this->microsoftGraphService->getUserTasks($user);

            $tasksByStatus = [
                'notStarted' => [],
                'inProgress' => [],
                'completed' => [],
            ];

            foreach ($tasks as $task) {
                $tasksByStatus[$task['status']][] = $task;
            }

            return $this->render('task/index.html.twig', [
                'tasks' => $tasks,
                'tasksByStatus' => $tasksByStatus,
                'user' => $user,
            ]);
        } catch (\Exception $e) {
            $this->addFlash('error', 'Error loading Microsoft tasks: '.$e->getMessage());

            return $this->redirectToRoute('app_user_profile');
        }
    }
}
