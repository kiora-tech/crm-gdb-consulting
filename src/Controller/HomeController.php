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
use Symfony\Component\Mime\Email;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Http\Attribute\IsGranted;

class HomeController extends AbstractController
{
    public function __construct(
        private readonly MicrosoftGraphService $microsoftGraphService,
    ) {
    }

    #[Route('/', name: 'homepage')]
    public function index(EntityManagerInterface $entityManager, CalendarEventRepository $calendarEventRepository): Response
    {
        /** @var User|null $user */
        $user = $this->getUser();
        $isAdmin = $user && in_array('ROLE_ADMIN', $user->getRoles());

        $customerRepo = $entityManager->getRepository(Customer::class);

        // Single query for all status counts + total
        $statsQb = $customerRepo->createQueryBuilder('c')
            ->select(
                'COUNT(c.id) AS total',
                'SUM(CASE WHEN c.status = :in_progress THEN 1 ELSE 0 END) AS inProgress',
                'SUM(CASE WHEN c.status = :won THEN 1 ELSE 0 END) AS won',
                'SUM(CASE WHEN c.status = :lost THEN 1 ELSE 0 END) AS lost'
            )
            ->setParameter('in_progress', ProspectStatus::IN_PROGRESS)
            ->setParameter('won', ProspectStatus::WON)
            ->setParameter('lost', ProspectStatus::LOST);

        if (!$isAdmin && $user) {
            $statsQb->where('c.user = :user')
                ->setParameter('user', $user);
        }

        /** @var array{total: string, inProgress: string, won: string, lost: string} $stats */
        $stats = $statsQb->getQuery()->getSingleResult();

        // SUM(worth) via native SQL with CAST for PostgreSQL compatibility (worth is VARCHAR)
        $worthSql = 'SELECT COALESCE(SUM(CAST(NULLIF(REGEXP_REPLACE(worth, \'[^0-9.]\', \'\', \'g\'), \'\') AS NUMERIC)), 0) FROM customer';
        $worthParams = [];
        if (!$isAdmin && $user) {
            $worthSql .= ' WHERE user_id = :userId';
            $worthParams['userId'] = $user->getId();
        }
        $totalWorth = (float) $entityManager->getConnection()->executeQuery($worthSql, $worthParams)->fetchOne();

        // Clients récents
        $recentCustomers = $customerRepo->createQueryBuilder('c')
            ->orderBy('c.id', 'DESC')
            ->setMaxResults(5)
            ->getQuery()
            ->getResult();

        // Contrats expirants avec eager load du customer
        $now = new \DateTime();
        $threeMonthsLater = (new \DateTime())->modify('+3 months');

        $expiringContracts = $entityManager->getRepository(Energy::class)
            ->createQueryBuilder('e')
            ->addSelect('c')
            ->join('e.customer', 'c')
            ->where('e.contractEnd >= :now')
            ->andWhere('e.contractEnd <= :threeMonths')
            ->setParameter('now', $now)
            ->setParameter('threeMonths', $threeMonthsLater)
            ->orderBy('e.contractEnd', 'ASC')
            ->setMaxResults(10)
            ->getQuery()
            ->getResult();

        // Documents récents avec eager load du customer
        $recentDocuments = $entityManager->getRepository(Document::class)
            ->createQueryBuilder('d')
            ->addSelect('c')
            ->join('d.customer', 'c')
            ->orderBy('d.id', 'DESC')
            ->setMaxResults(5)
            ->getQuery()
            ->getResult();

        // Événements calendrier à venir
        $upcomingEvents = $calendarEventRepository->findUpcomingEvents(10);

        return $this->render('home/index.html.twig', [
            'totalCustomers' => (int) $stats['total'],
            'inProgressCustomers' => (int) $stats['inProgress'],
            'wonCustomers' => (int) $stats['won'],
            'lostCustomers' => (int) $stats['lost'],
            'recentCustomers' => $recentCustomers,
            'expiringContracts' => $expiringContracts,
            'recentDocuments' => $recentDocuments,
            'totalWorth' => $totalWorth,
            'monthlyWonCustomers' => (int) $stats['won'],
            'upcomingEvents' => $upcomingEvents,
        ]);
    }

    #[Route('/test-email', name: 'test_email')]
    #[IsGranted('ROLE_USER')]
    public function testEmail(): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        if (!$this->microsoftGraphService->hasValidToken($user)) {
            $this->addFlash('error', 'Vous devez connecter votre compte Microsoft pour envoyer des emails.');

            return $this->redirectToRoute('homepage');
        }

        try {
            $homeUrl = $this->generateUrl('homepage', [], UrlGeneratorInterface::ABSOLUTE_URL);

            $htmlContent = $this->renderView('emails/test_email.html.twig', [
                'homeUrl' => $homeUrl,
            ]);

            // Envoyer l'email via Microsoft Graph
            $this->microsoftGraphService->sendEmail(
                $user,
                'stephane@kiora.tech',
                'Test Email - CRM GDB Consulting',
                $htmlContent
            );

            $this->addFlash('success', 'Email de test envoyé à stephane@kiora.tech via Microsoft Graph');
        } catch (\Exception $e) {
            $this->addFlash('error', 'Erreur lors de l\'envoi : '.$e->getMessage());
        }

        return $this->redirectToRoute('homepage');
    }
}
