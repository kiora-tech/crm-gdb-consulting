<?php

namespace App\Controller;

use App\Entity\Customer;
use App\Entity\Document;
use App\Entity\Energy;
use App\Entity\ProspectStatus;
use App\Entity\User;
use App\Repository\CalendarEventRepository;
use App\Service\MicrosoftGraphService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class HomeController extends AbstractController
{
    public function __construct(
        private readonly MicrosoftGraphService $microsoftGraphService,
    ) {
    }

    #[Route('/', name: 'homepage')]
    public function index(EntityManagerInterface $entityManager, CalendarEventRepository $calendarEventRepository): Response
    {
        // Récupérer les données pour le tableau de bord
        /** @var User|null $user */
        $user = $this->getUser();
        $isAdmin = $user && in_array('ROLE_ADMIN', $user->getRoles());

        // Stats globales
        $customerRepo = $entityManager->getRepository(Customer::class);

        // Requête de base pour les clients
        $queryBuilder = $customerRepo->createQueryBuilder('c');

        // Si ce n'est pas un admin, filtrer par utilisateur
        if (!$isAdmin && $user) {
            $queryBuilder->where('c.user = :user')
                ->setParameter('user', $user);
        }

        // Nombre total de clients
        $totalCustomers = $queryBuilder->select('COUNT(c.id)')
            ->getQuery()
            ->getSingleScalarResult();

        // Clients en cours
        $inProgressCustomers = $customerRepo->createQueryBuilder('c')
            ->select('COUNT(c.id)')
            ->where('c.status = :status')
            ->setParameter('status', ProspectStatus::IN_PROGRESS)
            ->getQuery()
            ->getSingleScalarResult();

        // Clients gagnés
        $wonCustomers = $customerRepo->createQueryBuilder('c')
            ->select('COUNT(c.id)')
            ->where('c.status = :status')
            ->setParameter('status', ProspectStatus::WON)
            ->getQuery()
            ->getSingleScalarResult();

        // Clients perdus
        $lostCustomers = $customerRepo->createQueryBuilder('c')
            ->select('COUNT(c.id)')
            ->where('c.status = :status')
            ->setParameter('status', ProspectStatus::LOST)
            ->getQuery()
            ->getSingleScalarResult();

        // Clients récents
        $recentCustomers = $customerRepo->createQueryBuilder('c')
            ->orderBy('c.id', 'DESC')
            ->setMaxResults(5)
            ->getQuery()
            ->getResult();

        // Contrats d'énergie expirant bientôt
        $now = new \DateTime();
        $threeMonthsLater = (new \DateTime())->modify('+3 months');

        $expiringContracts = $entityManager->getRepository(Energy::class)
            ->createQueryBuilder('e')
            ->join('e.customer', 'c')
            ->where('e.contractEnd >= :now')
            ->andWhere('e.contractEnd <= :threeMonths')
            ->setParameter('now', $now)
            ->setParameter('threeMonths', $threeMonthsLater)
            ->orderBy('e.contractEnd', 'ASC')
            ->setMaxResults(10) // Doublé le nombre de contrats affichés
            ->getQuery()
            ->getResult();

        // Documents récents
        $recentDocuments = $entityManager->getRepository(Document::class)
            ->createQueryBuilder('d')
            ->join('d.customer', 'c')
            ->orderBy('d.id', 'DESC')
            ->setMaxResults(5)
            ->getQuery()
            ->getResult();

        // Calcul de la valeur totale des contrats
        $totalWorth = $customerRepo->createQueryBuilder('c')
            ->select('SUM(c.worth)')
            ->where('c.worth IS NOT NULL')
            ->getQuery()
            ->getSingleScalarResult() ?? 0;

        $monthlyWonCustomers = $customerRepo->createQueryBuilder('c')
            ->select('COUNT(c.id)')
            ->where('c.status = :status')
            // Dans un vrai système, vous auriez une date de modification à vérifier ici
            ->setParameter('status', ProspectStatus::WON)
            ->getQuery()
            ->getSingleScalarResult();

        // Récupérer les tâches Microsoft si l'utilisateur est connecté
        $microsoftTasks = [];
        $hasMicrosoftToken = false;
        if ($user instanceof User) {
            $hasMicrosoftToken = $this->microsoftGraphService->hasValidToken($user);
            if ($hasMicrosoftToken) {
                try {
                    $allTasks = $this->microsoftGraphService->getUserTasks($user);
                    // Prendre les 10 tâches les plus récentes (non terminées en priorité)
                    $microsoftTasks = array_slice(
                        array_filter($allTasks, fn ($task) => 'completed' !== $task['status']),
                        0,
                        10
                    );
                } catch (\Exception $e) {
                    // En cas d'erreur, on continue sans les tâches mais on log l'erreur
                    $microsoftTasks = [];
                    $errorMessage = $e->getMessage();

                    // Si c'est un problème de token, marquer que le token n'est plus valide
                    if (str_contains($errorMessage, 'refresh Microsoft token')
                        || str_contains($errorMessage, '401')
                        || str_contains($errorMessage, 'No refresh token available')) {
                        $hasMicrosoftToken = false;
                    }
                }
            }
        }

        // Récupérer les événements calendrier à venir
        $upcomingEvents = $calendarEventRepository->findUpcomingEvents(10);

        // Retourner les données au template
        return $this->render('home/index.html.twig', [
            'totalCustomers' => $totalCustomers,
            'inProgressCustomers' => $inProgressCustomers,
            'wonCustomers' => $wonCustomers,
            'lostCustomers' => $lostCustomers,
            'recentCustomers' => $recentCustomers,
            'expiringContracts' => $expiringContracts,
            'recentDocuments' => $recentDocuments,
            'totalWorth' => $totalWorth,
            'monthlyWonCustomers' => $monthlyWonCustomers,
            'microsoftTasks' => $microsoftTasks,
            'hasMicrosoftToken' => $hasMicrosoftToken,
            'upcomingEvents' => $upcomingEvents,
        ]);
    }
}
