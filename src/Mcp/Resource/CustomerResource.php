<?php

namespace App\Mcp\Resource;

use App\Entity\Customer;
use App\Entity\User;
use App\Repository\CustomerRepository;
use Mcp\Capability\Attribute\McpResource;
use Mcp\Capability\Attribute\McpResourceTemplate;
use Mcp\Schema\Content\TextContent;
use Symfony\Bundle\SecurityBundle\Security;

class CustomerResource
{
    public function __construct(
        private readonly CustomerRepository $customerRepository,
        private readonly Security $security,
    ) {
    }

    /** @return list<TextContent> */
    #[McpResource(
        uri: 'crm://customers',
        name: 'customers-list',
        description: 'Liste paginee des clients du CRM. Filtree par utilisateur (un commercial ne voit que ses clients, un admin voit tout).',
        mimeType: 'application/json'
    )]
    public function listCustomers(): array
    {
        $customers = $this->customerRepository->getQueryBuilder()
            ->orderBy('c.name', 'ASC')
            ->setMaxResults(50)
            ->getQuery()
            ->getResult();

        $data = array_map(fn (Customer $c) => $this->serializeCustomerSummary($c), $customers);

        return [new TextContent(text: json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT))];
    }

    /** @return list<TextContent> */
    #[McpResourceTemplate(
        uriTemplate: 'crm://customers/{customerId}',
        name: 'customer-detail',
        description: 'Fiche detaillee d\'un client avec resume des energies, contacts et commentaires.',
        mimeType: 'application/json'
    )]
    public function getCustomer(string $customerId): array
    {
        $customer = $this->customerRepository->find((int) $customerId);
        if (!$customer) {
            return [new TextContent(text: json_encode(['error' => 'Client non trouvé']))];
        }

        if (!$this->canAccess($customer)) {
            return [new TextContent(text: json_encode(['error' => 'Accès refusé']))];
        }

        $data = $this->serializeCustomerDetail($customer);

        return [new TextContent(text: json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT))];
    }

    /** @return list<TextContent> */
    #[McpResourceTemplate(
        uriTemplate: 'crm://customers/{customerId}/energies',
        name: 'customer-energies',
        description: 'Liste des contrats energie (electricite/gaz) d\'un client. Les codes PDL/PCE sont masques par defaut.',
        mimeType: 'application/json'
    )]
    public function getCustomerEnergies(string $customerId): array
    {
        $customer = $this->customerRepository->find((int) $customerId);
        if (!$customer || !$this->canAccess($customer)) {
            return [new TextContent(text: json_encode(['error' => 'Accès refusé']))];
        }

        $isAdmin = $this->security->isGranted('ROLE_ADMIN');

        $data = [];
        foreach ($customer->getEnergies() as $energy) {
            $code = $energy->getCode();
            $maskedCode = $code ? substr($code, 0, 4).'**********' : null;

            $data[] = [
                'id' => $energy->getId(),
                'type' => $energy->getType()?->value,
                'code_masque' => $maskedCode,
                'code_complet' => $isAdmin ? $code : null,
                'fin_contrat' => $energy->getContractEnd()?->format('Y-m-d'),
                'puissance_kva' => $energy->getPowerKva(),
                'segment' => $energy->getSegment()?->value,
                'consommation_totale' => $energy->getTotalConsumption(),
                'fournisseur' => $energy->getEnergyProvider()?->getName(),
                'fta' => $energy->getFta()?->getLabel(),
                'profil' => $energy->getProfile(),
            ];
        }

        return [new TextContent(text: json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT))];
    }

    /** @return list<TextContent> */
    #[McpResourceTemplate(
        uriTemplate: 'crm://customers/{customerId}/contacts',
        name: 'customer-contacts',
        description: 'Liste des contacts d\'un client. Les donnees personnelles (email, telephone) sont masquees par defaut (RGPD).',
        mimeType: 'application/json'
    )]
    public function getCustomerContacts(string $customerId): array
    {
        $customer = $this->customerRepository->find((int) $customerId);
        if (!$customer || !$this->canAccess($customer)) {
            return [new TextContent(text: json_encode(['error' => 'Accès refusé']))];
        }

        $data = [];
        foreach ($customer->getContacts() as $contact) {
            $data[] = [
                'id' => $contact->getId(),
                'nom' => mb_substr($contact->getFirstName(), 0, 1).'. '.$contact->getLastName(),
                'poste' => $contact->getPosition(),
                'contact_principal' => $contact->isPrimary(),
            ];
        }

        return [new TextContent(text: json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT))];
    }

    /** @return list<TextContent> */
    #[McpResourceTemplate(
        uriTemplate: 'crm://customers/{customerId}/comments',
        name: 'customer-comments',
        description: 'Historique des notes et commentaires sur un client, tries du plus recent au plus ancien.',
        mimeType: 'application/json'
    )]
    public function getCustomerComments(string $customerId): array
    {
        $customer = $this->customerRepository->find((int) $customerId);
        if (!$customer || !$this->canAccess($customer)) {
            return [new TextContent(text: json_encode(['error' => 'Accès refusé']))];
        }

        $data = [];
        foreach ($customer->getComments() as $comment) {
            $data[] = [
                'date' => $comment->getCreatedAt()->format('Y-m-d H:i'),
                'note' => $comment->getNote(),
            ];
        }

        usort($data, fn ($a, $b) => $b['date'] <=> $a['date']);

        return [new TextContent(text: json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT))];
    }

    /** @return list<TextContent> */
    #[McpResourceTemplate(
        uriTemplate: 'crm://customers/{customerId}/documents',
        name: 'customer-documents',
        description: 'Liste des documents associes a un client (metadonnees uniquement, pas de telechargement).',
        mimeType: 'application/json'
    )]
    public function getCustomerDocuments(string $customerId): array
    {
        $customer = $this->customerRepository->find((int) $customerId);
        if (!$customer || !$this->canAccess($customer)) {
            return [new TextContent(text: json_encode(['error' => 'Accès refusé']))];
        }

        $data = [];
        foreach ($customer->getDocuments() as $document) {
            $data[] = [
                'id' => $document->getId(),
                'nom' => $document->getName(),
                'type' => $document->getType()?->getLabel(),
            ];
        }

        return [new TextContent(text: json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT))];
    }

    /** @return array<string, mixed> */
    private function serializeCustomerSummary(Customer $customer): array
    {
        return [
            'id' => $customer->getId(),
            'nom' => $customer->getName(),
            'statut' => $customer->getStatus()?->value,
            'origine' => $customer->getOrigin()?->value,
            'ville' => $customer->getAddressCity(),
            'code_postal' => $customer->getAddressPostalCode(),
            'groupe' => $customer->getCompanyGroup(),
            'forme_juridique' => $customer->getLegalForm(),
            'nb_energies' => $customer->getEnergies()->count(),
            'nb_contacts' => $customer->getContacts()->count(),
            'commercial' => $customer->getUser() ? $customer->getUser()->getFirstName().' '.$customer->getUser()->getLastName() : null,
        ];
    }

    /** @return array<string, mixed> */
    private function serializeCustomerDetail(Customer $customer): array
    {
        $isAdmin = $this->security->isGranted('ROLE_ADMIN');
        $siret = $customer->getSiret();

        return [
            'id' => $customer->getId(),
            'nom' => $customer->getName(),
            'statut' => $customer->getStatus()?->value,
            'origine' => $customer->getOrigin()?->value,
            'origine_lead' => $customer->getLeadOrigin(),
            'adresse' => trim(($customer->getAddressNumber() ?? '').' '.($customer->getAddressStreet() ?? '')),
            'code_postal' => $customer->getAddressPostalCode(),
            'ville' => $customer->getAddressCity(),
            'siret' => $isAdmin ? $siret : ($siret ? substr($siret, 0, 9).'*****' : null),
            'forme_juridique' => $customer->getLegalForm(),
            'groupe' => $customer->getCompanyGroup(),
            'canal_signature' => $customer->getCanalSignature()?->value,
            'valeur' => $customer->getWorth(),
            'action' => $customer->getAction(),
            'commercial' => $customer->getUser() ? $customer->getUser()->getFirstName().' '.$customer->getUser()->getLastName() : null,
            'nb_energies' => $customer->getEnergies()->count(),
            'nb_contacts' => $customer->getContacts()->count(),
            'nb_documents' => $customer->getDocuments()->count(),
            'nb_commentaires' => $customer->getComments()->count(),
        ];
    }

    private function canAccess(Customer $customer): bool
    {
        if ($this->security->isGranted('ROLE_ADMIN')) {
            return true;
        }

        $user = $this->security->getUser();
        if (!$user instanceof User) {
            return false;
        }

        return $customer->getUser() === $user || null === $customer->getUser();
    }
}
