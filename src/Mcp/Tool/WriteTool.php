<?php

namespace App\Mcp\Tool;

use App\Entity\Comment;
use App\Entity\Contact;
use App\Entity\Customer;
use App\Entity\ProspectOrigin;
use App\Entity\ProspectStatus;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Mcp\Capability\Attribute\McpTool;
use Mcp\Schema\Content\TextContent;
use Mcp\Schema\Result\CallToolResult;
use Symfony\Bundle\SecurityBundle\Security;

class WriteTool
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly Security $security,
        private readonly AuditLogger $auditLogger,
    ) {
    }

    /**
     * Creer un nouveau client dans le CRM.
     *
     * @param string      $name              Nom du client (raison sociale)
     * @param string      $origin            Origine: acquisition ou renouvellement
     * @param string|null $status            Statut: in_progress, won, lost
     * @param string|null $siret             Numero SIRET (14 chiffres)
     * @param string|null $leadOrigin        Origine du lead (ex: Apporteur, Prospection)
     * @param string|null $addressCity       Ville
     * @param string|null $addressPostalCode Code postal
     * @param string|null $addressStreet     Rue
     * @param string|null $legalForm         Forme juridique (SAS, SARL, etc.)
     * @param string|null $companyGroup      Groupe d'entreprises
     * @param string|null $comment           Commentaire initial sur le client
     */
    #[McpTool(name: 'create_customer', description: 'Creer un nouveau client dans le CRM. Necessite au minimum un nom et une origine (acquisition ou renouvellement).')]
    public function createCustomer(
        string $name,
        string $origin = 'acquisition',
        ?string $status = null,
        ?string $siret = null,
        ?string $leadOrigin = null,
        ?string $addressCity = null,
        ?string $addressPostalCode = null,
        ?string $addressStreet = null,
        ?string $legalForm = null,
        ?string $companyGroup = null,
        ?string $comment = null,
    ): CallToolResult {
        $prospectOrigin = ProspectOrigin::tryFrom($origin);
        if (!$prospectOrigin) {
            return CallToolResult::error([new TextContent(text: "Origine invalide: '$origin'. Valeurs possibles: acquisition, renouvellement")]);
        }

        // Vérifier le SIRET s'il est fourni
        if ($siret) {
            $siret = preg_replace('/\s/', '', $siret);
            if (14 !== strlen($siret) || !ctype_digit($siret)) {
                return CallToolResult::error([new TextContent(text: "SIRET invalide: '$siret'. Doit contenir exactement 14 chiffres.")]);
            }

            $existing = $this->entityManager->getRepository(Customer::class)->findOneBy(['siret' => $siret]);
            if ($existing) {
                return CallToolResult::error([new TextContent(text: "Un client avec le SIRET $siret existe deja: {$existing->getName()} (ID: {$existing->getId()})")]);
            }
        }

        $customer = new Customer();
        $customer->setName($name);
        $customer->setOrigin($prospectOrigin);

        if ($status) {
            $prospectStatus = ProspectStatus::tryFrom($status);
            if ($prospectStatus) {
                $customer->setStatus($prospectStatus);
            }
        }

        if ($siret) {
            $customer->setSiret($siret);
        }
        if ($leadOrigin) {
            $customer->setLeadOrigin($leadOrigin);
        }
        if ($addressCity) {
            $customer->setAddressCity($addressCity);
        }
        if ($addressPostalCode) {
            $customer->setAddressPostalCode($addressPostalCode);
        }
        if ($addressStreet) {
            $customer->setAddressStreet($addressStreet);
        }
        if ($legalForm) {
            $customer->setLegalForm($legalForm);
        }
        if ($companyGroup) {
            $customer->setCompanyGroup($companyGroup);
        }

        // Assigner au commercial connecte
        $user = $this->security->getUser();
        if ($user instanceof User) {
            $customer->setUser($user);
        }

        $this->entityManager->persist($customer);

        // Ajouter un commentaire initial si fourni
        if ($comment) {
            $commentEntity = new Comment();
            $commentEntity->setNote($comment);
            $commentEntity->setCustomer($customer);
            $this->entityManager->persist($commentEntity);
        }

        $this->entityManager->flush();

        $this->auditLogger->log(
            'create_customer',
            $user instanceof User ? $user->getEmail() : null,
            $customer->getId(),
            $customer->getName(),
        );

        return CallToolResult::success([new TextContent(text: json_encode([
            'success' => true,
            'message' => "Client '$name' cree avec succes",
            'client' => [
                'id' => $customer->getId(),
                'nom' => $customer->getName(),
                'origine' => $customer->getOrigin()->value,
                'statut' => $customer->getStatus()?->value,
                'siret' => $customer->getSiret(),
                'ville' => $customer->getAddressCity(),
                'commercial' => $user instanceof User ? $user->getFirstName().' '.$user->getLastName() : null,
            ],
        ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT))]);
    }

    /**
     * Ajouter un commentaire sur un client existant.
     *
     * @param int    $customerId ID du client
     * @param string $note       Contenu du commentaire
     */
    #[McpTool(name: 'add_comment', description: 'Ajouter un commentaire/note sur un client existant dans le CRM.')]
    public function addComment(int $customerId, string $note): CallToolResult
    {
        $customer = $this->entityManager->getRepository(Customer::class)->find($customerId);
        if (!$customer) {
            return CallToolResult::error([new TextContent(text: "Client ID $customerId non trouve")]);
        }

        if (!$this->canAccess($customer)) {
            return CallToolResult::error([new TextContent(text: 'Acces refuse')]);
        }

        $comment = new Comment();
        $comment->setNote($note);
        $comment->setCustomer($customer);
        $this->entityManager->persist($comment);
        $this->entityManager->flush();

        $user = $this->security->getUser();
        $this->auditLogger->log(
            'add_comment',
            $user instanceof User ? $user->getEmail() : null,
            $customerId,
            $customer->getName(),
        );

        return CallToolResult::success([new TextContent(text: json_encode([
            'success' => true,
            'message' => "Commentaire ajoute sur le client '{$customer->getName()}'",
            'client_id' => $customerId,
        ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT))]);
    }

    /**
     * Mettre a jour le statut d'un client.
     *
     * @param int    $customerId ID du client
     * @param string $status     Nouveau statut: in_progress, won, lost
     */
    #[McpTool(name: 'update_customer_status', description: 'Mettre a jour le statut d\'un client (in_progress, won, lost).')]
    public function updateCustomerStatus(int $customerId, string $status): CallToolResult
    {
        $customer = $this->entityManager->getRepository(Customer::class)->find($customerId);
        if (!$customer) {
            return CallToolResult::error([new TextContent(text: "Client ID $customerId non trouve")]);
        }

        if (!$this->canAccess($customer)) {
            return CallToolResult::error([new TextContent(text: 'Acces refuse')]);
        }

        $newStatus = ProspectStatus::tryFrom($status);
        if (!$newStatus) {
            return CallToolResult::error([new TextContent(text: "Statut invalide: '$status'. Valeurs possibles: in_progress, won, lost")]);
        }

        $oldStatus = $customer->getStatus() ? $customer->getStatus()->value : 'aucun';
        $customer->setStatus($newStatus);
        $this->entityManager->flush();

        $user = $this->security->getUser();
        $this->auditLogger->log(
            'update_customer_status',
            $user instanceof User ? $user->getEmail() : null,
            $customerId,
            $customer->getName(),
        );

        return CallToolResult::success([new TextContent(text: json_encode([
            'success' => true,
            'message' => "Statut du client '{$customer->getName()}' mis a jour: $oldStatus → $status",
            'client_id' => $customerId,
            'ancien_statut' => $oldStatus,
            'nouveau_statut' => $status,
        ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT))]);
    }

    /**
     * Creer un contact pour un client existant.
     *
     * @param int         $customerId ID du client
     * @param string      $firstName  Prenom du contact
     * @param string      $lastName   Nom du contact
     * @param string|null $email      Adresse email
     * @param string|null $phone      Numero de telephone
     * @param string|null $position   Poste/fonction du contact
     * @param bool        $isPrimary  Contact principal (defaut: false)
     */
    #[McpTool(name: 'create_contact', description: 'Creer un contact pour un client existant dans le CRM.')]
    public function createContact(
        int $customerId,
        string $firstName,
        string $lastName,
        ?string $email = null,
        ?string $phone = null,
        ?string $position = null,
        bool $isPrimary = false,
    ): CallToolResult {
        $customer = $this->entityManager->getRepository(Customer::class)->find($customerId);
        if (!$customer) {
            return CallToolResult::error([new TextContent(text: "Client ID $customerId non trouve")]);
        }

        if (!$this->canAccess($customer)) {
            return CallToolResult::error([new TextContent(text: 'Acces refuse')]);
        }

        $contact = new Contact();
        $contact->setCustomer($customer);
        $contact->setFirstName($firstName);
        $contact->setLastName($lastName);

        if ($email) {
            $contact->setEmail($email);
        }
        if ($phone) {
            $contact->setPhone($phone);
        }
        if ($position) {
            $contact->setPosition($position);
        }
        $contact->setIsPrimary($isPrimary);

        $this->entityManager->persist($contact);
        $this->entityManager->flush();

        $user = $this->security->getUser();
        $this->auditLogger->log(
            'create_contact',
            $user instanceof User ? $user->getEmail() : null,
            $customerId,
            $customer->getName(),
        );

        return CallToolResult::success([new TextContent(text: json_encode([
            'success' => true,
            'message' => "Contact '$firstName $lastName' cree pour le client '{$customer->getName()}'",
            'contact' => [
                'id' => $contact->getId(),
                'prenom' => $contact->getFirstName(),
                'nom' => $contact->getLastName(),
                'email' => $contact->getEmail(),
                'telephone' => $contact->getPhone(),
                'poste' => $contact->getPosition(),
                'principal' => $contact->isPrimary(),
            ],
            'client_id' => $customerId,
        ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT))]);
    }

    /**
     * Mettre a jour les informations d'un client.
     *
     * @param int         $customerId        ID du client
     * @param string|null $name              Nom du client (raison sociale)
     * @param string|null $addressCity       Ville
     * @param string|null $addressPostalCode Code postal
     * @param string|null $addressStreet     Rue
     * @param string|null $legalForm         Forme juridique (SAS, SARL, etc.)
     * @param string|null $companyGroup      Groupe d'entreprises
     * @param string|null $leadOrigin        Origine du lead
     * @param string|null $worth             Valeur estimee du client
     */
    #[McpTool(name: 'update_customer', description: 'Mettre a jour les informations d\'un client existant (nom, adresse, forme juridique, groupe, origine du lead, valeur).')]
    public function updateCustomer(
        int $customerId,
        ?string $name = null,
        ?string $addressCity = null,
        ?string $addressPostalCode = null,
        ?string $addressStreet = null,
        ?string $legalForm = null,
        ?string $companyGroup = null,
        ?string $leadOrigin = null,
        ?string $worth = null,
    ): CallToolResult {
        $customer = $this->entityManager->getRepository(Customer::class)->find($customerId);
        if (!$customer) {
            return CallToolResult::error([new TextContent(text: "Client ID $customerId non trouve")]);
        }

        if (!$this->canAccess($customer)) {
            return CallToolResult::error([new TextContent(text: 'Acces refuse')]);
        }

        $updated = [];

        if (null !== $name) {
            $customer->setName($name);
            $updated[] = 'nom';
        }
        if (null !== $addressCity) {
            $customer->setAddressCity($addressCity);
            $updated[] = 'ville';
        }
        if (null !== $addressPostalCode) {
            $customer->setAddressPostalCode($addressPostalCode);
            $updated[] = 'code_postal';
        }
        if (null !== $addressStreet) {
            $customer->setAddressStreet($addressStreet);
            $updated[] = 'rue';
        }
        if (null !== $legalForm) {
            $customer->setLegalForm($legalForm);
            $updated[] = 'forme_juridique';
        }
        if (null !== $companyGroup) {
            $customer->setCompanyGroup($companyGroup);
            $updated[] = 'groupe';
        }
        if (null !== $leadOrigin) {
            $customer->setLeadOrigin($leadOrigin);
            $updated[] = 'origine_lead';
        }
        if (null !== $worth) {
            $customer->setWorth($worth);
            $updated[] = 'valeur';
        }

        if (empty($updated)) {
            return CallToolResult::error([new TextContent(text: 'Aucun champ a mettre a jour. Fournissez au moins un parametre.')]);
        }

        $this->entityManager->flush();

        $user = $this->security->getUser();
        $this->auditLogger->log(
            'update_customer',
            $user instanceof User ? $user->getEmail() : null,
            $customerId,
            $customer->getName(),
        );

        return CallToolResult::success([new TextContent(text: json_encode([
            'success' => true,
            'message' => "Client '{$customer->getName()}' mis a jour",
            'champs_modifies' => $updated,
            'client_id' => $customerId,
        ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT))]);
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
