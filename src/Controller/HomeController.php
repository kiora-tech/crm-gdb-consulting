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
use Symfony\Component\Mailer\MailerInterface;
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
            $this->addFlash('error', 'Erreur lors de l\'envoi : ' . $e->getMessage());
        }

        return $this->redirectToRoute('homepage');
    }
}
