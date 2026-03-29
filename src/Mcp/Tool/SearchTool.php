<?php

namespace App\Mcp\Tool;

use App\Data\CustomerSearchData;
use App\Entity\Customer;
use App\Entity\Energy;
use App\Entity\EnergyProvider;
use App\Entity\ProspectStatus;
use App\Entity\User;
use App\Repository\CustomerRepository;
use Doctrine\ORM\EntityManagerInterface;
use Mcp\Capability\Attribute\McpTool;
use Mcp\Schema\Content\TextContent;
use Mcp\Schema\Result\CallToolResult;
use Symfony\Bundle\SecurityBundle\Security;

class SearchTool
{
    public function __construct(
        private readonly CustomerRepository $customerRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly Security $security,
    ) {
    }

    /**
     * Rechercher des clients dans le CRM par nom, statut, ville, fournisseur d'énergie ou date de fin de contrat.
     *
     * @param string|null $name             Nom du client (recherche partielle)
     * @param string|null $status           Statut: in_progress, won, lost
     * @param string|null $contactName      Nom du contact (recherche partielle)
     * @param string|null $energyProviderName Nom du fournisseur d'énergie
     * @param string|null $contractEndBefore Contrats expirant avant (YYYY-MM-DD)
     * @param string|null $contractEndAfter  Contrats expirant après (YYYY-MM-DD)
     * @param string      $sort             Tri: newest (plus recents), oldest, name (alphabetique)
     * @param int         $limit            Nombre max de résultats (max 50)
     *
     * @return CallToolResult
     */
    #[McpTool(name: 'search_customers', description: 'Rechercher des clients dans le CRM par nom, statut, fournisseur d\'energie ou date de fin de contrat. Supporte le tri par date d\'ajout (newest/oldest) ou par nom.')]
    public function searchCustomers(
        ?string $name = null,
        ?string $status = null,
        ?string $contactName = null,
        ?string $energyProviderName = null,
        ?string $contractEndBefore = null,
        ?string $contractEndAfter = null,
        string $sort = 'newest',
        int $limit = 20,
    ): CallToolResult {
        $search = new CustomerSearchData();
        $search->name = $name;
        $search->contactName = $contactName;

        if ($contractEndBefore) {
            $search->contractEndBefore = new \DateTime($contractEndBefore);
        }
        if ($contractEndAfter) {
            $search->contractEndAfter = new \DateTime($contractEndAfter);
        }
        if ($status) {
            $search->status = ProspectStatus::tryFrom($status);
        }
        if ($energyProviderName) {
            $provider = $this->entityManager->getRepository(EnergyProvider::class)
                ->findOneBy(['name' => $energyProviderName]);
            if ($provider) {
                $search->energyProvider = $provider;
            }
        }

        $limit = min($limit, 50);

        // Récupérer plus de résultats pour trier côté PHP
        /** @var Customer[] $results */
        $results = $this->customerRepository->search($search)
            ->setMaxResults($limit)
            ->getResult();

        // Appliquer le tri
        usort($results, match ($sort) {
            'oldest' => fn (Customer $a, Customer $b) => $a->getId() <=> $b->getId(),
            'name' => fn (Customer $a, Customer $b) => ($a->getName() ?? '') <=> ($b->getName() ?? ''),
            default => fn (Customer $a, Customer $b) => $b->getId() <=> $a->getId(), // newest
        });

        $data = array_map(fn (Customer $c) => [
            'id' => $c->getId(),
            'nom' => $c->getName(),
            'statut' => $c->getStatus()?->value,
            'ville' => $c->getAddressCity(),
            'nb_energies' => $c->getEnergies()->count(),
            'commercial' => $c->getUser() ? $c->getUser()->getFirstName() . ' ' . $c->getUser()->getLastName() : 'Non assigné',
        ], $results);

        return CallToolResult::success([new TextContent(text: json_encode([
            'total' => count($data),
            'clients' => $data,
        ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT))]);
    }

    /**
     * Trouver les contrats énergie qui expirent dans une période donnée.
     *
     * @param string $fromDate Date de début (YYYY-MM-DD)
     * @param string $toDate   Date de fin (YYYY-MM-DD)
     * @param int    $limit    Nombre max de résultats (max 50)
     *
     * @return CallToolResult
     */
    #[McpTool(name: 'search_expiring_contracts', description: 'Trouver les contrats energie qui expirent dans une periode donnee. Utile pour preparer les renouvellements.')]
    public function searchExpiringContracts(string $fromDate, string $toDate, int $limit = 30): CallToolResult
    {
        $limit = min($limit, 50);
        $isAdmin = $this->security->isGranted('ROLE_ADMIN');
        $user = $this->security->getUser();

        $qb = $this->entityManager->createQueryBuilder()
            ->select('e', 'c')
            ->from(Energy::class, 'e')
            ->join('e.customer', 'c')
            ->where('e.contractEnd >= :from')
            ->andWhere('e.contractEnd <= :to')
            ->setParameter('from', new \DateTime($fromDate))
            ->setParameter('to', new \DateTime($toDate))
            ->orderBy('e.contractEnd', 'ASC')
            ->setMaxResults($limit);

        if (!$isAdmin && $user instanceof User) {
            $qb->andWhere('c.user = :user OR c.user IS NULL')
                ->setParameter('user', $user);
        }

        /** @var Energy[] $results */
        $results = $qb->getQuery()->getResult();

        $data = array_map(fn (Energy $energy) => [
            'client' => $energy->getCustomer()->getName(),
            'client_id' => $energy->getCustomer()->getId(),
            'type_energie' => $energy->getType()?->value,
            'fournisseur' => $energy->getEnergyProvider()?->getName(),
            'fin_contrat' => $energy->getContractEnd()?->format('Y-m-d'),
            'consommation_totale' => $energy->getTotalConsumption(),
        ], $results);

        return CallToolResult::success([new TextContent(text: json_encode([
            'periode' => ['du' => $fromDate, 'au' => $toDate],
            'total' => count($data),
            'contrats' => $data,
        ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT))]);
    }

    /**
     * Obtenir un résumé du pipeline commercial.
     *
     * @return CallToolResult
     */
    #[McpTool(name: 'pipeline_summary', description: 'Resume du pipeline commercial : nombre de clients par statut et contrats expirant bientot.')]
    public function pipelineSummary(): CallToolResult
    {
        $isAdmin = $this->security->isGranted('ROLE_ADMIN');
        $user = $this->security->getUser();

        $qb = $this->entityManager->createQueryBuilder()
            ->select(
                'COUNT(c.id) AS total',
                "SUM(CASE WHEN c.status = 'in_progress' THEN 1 ELSE 0 END) AS en_cours",
                "SUM(CASE WHEN c.status = 'won' THEN 1 ELSE 0 END) AS gagnes",
                "SUM(CASE WHEN c.status = 'lost' THEN 1 ELSE 0 END) AS perdus",
            )
            ->from(Customer::class, 'c');

        if (!$isAdmin && $user instanceof User) {
            $qb->where('c.user = :user OR c.user IS NULL')
                ->setParameter('user', $user);
        }

        /** @var array{total: string, en_cours: string, gagnes: string, perdus: string} $stats */
        $stats = $qb->getQuery()->getSingleResult();

        $expiringQb = $this->entityManager->createQueryBuilder()
            ->select('COUNT(e.id)')
            ->from(Energy::class, 'e')
            ->join('e.customer', 'c2')
            ->where('e.contractEnd >= :now')
            ->andWhere('e.contractEnd <= :threeMonths')
            ->setParameter('now', new \DateTime())
            ->setParameter('threeMonths', new \DateTime('+3 months'));

        if (!$isAdmin && $user instanceof User) {
            $expiringQb->andWhere('c2.user = :user OR c2.user IS NULL')
                ->setParameter('user', $user);
        }

        $expiringCount = $expiringQb->getQuery()->getSingleScalarResult();

        $data = [
            'clients' => [
                'total' => (int) $stats['total'],
                'en_cours' => (int) $stats['en_cours'],
                'gagnes' => (int) $stats['gagnes'],
                'perdus' => (int) $stats['perdus'],
            ],
            'contrats_expirant_3_mois' => (int) $expiringCount,
        ];

        return CallToolResult::success([new TextContent(text: json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT))]);
    }
}
