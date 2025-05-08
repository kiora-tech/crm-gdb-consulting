<?php

namespace App\DataFixtures;

use App\Entity\CanalSignature;
use App\Entity\Comment;
use App\Entity\Contact;
use App\Entity\Customer;
use App\Entity\ProspectOrigin;
use App\Entity\ProspectStatus;
use App\Entity\User;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;

class CustomerFixtures extends Fixture implements DependentFixtureInterface
{
    private const CUSTOMERS = [
        // Clients pour l'administrateur
        'customer_admin_1' => [
            'name' => 'BioEnergie SA',
            'user' => 'admin_user',
            'origin' => ProspectOrigin::ACQUISITION,
            'status' => ProspectStatus::IN_PROGRESS,
            'siret' => '12345678901234',
            'address' => '15 rue de la Paix, 75001 Paris',
            'action' => 'Renouvellement contrat',
            'worth' => '15000',
            'commision' => '1500',
            'margin' => '10%',
            'companyGroup' => 'Groupe Bio',
            'canalSignature' => CanalSignature::GDB,
            'contacts' => [
                [
                    'firstName' => 'François',
                    'lastName' => 'Durand',
                    'email' => 'f.durand@bio-energie.com',
                    'position' => 'Directeur',
                    'phone' => '01 23 45 67 89',
                    'mobilePhone' => '06 12 34 56 78',
                    'address' => null,
                ],
            ],
            'comments' => [
                ['note' => 'Premier contact très positif', 'createdAt' => '-30 days'],
                ['note' => 'Relance prévue semaine prochaine', 'createdAt' => '-15 days'],
            ],
        ],
        'customer_admin_2' => [
            'name' => 'Industrie Moderne',
            'user' => 'admin_user',
            'origin' => ProspectOrigin::RENOUVELLEMENT,
            'status' => ProspectStatus::WON,
            'siret' => '98765432109876',
            'address' => '78 avenue des Champs-Élysées, 75008 Paris',
            'action' => 'Optimisation consommation',
            'worth' => '32000',
            'commision' => '3200',
            'margin' => '12%',
            'companyGroup' => 'Groupe Industrie France',
            'canalSignature' => CanalSignature::FOURNISSEUR,
            'contacts' => [
                [
                    'firstName' => 'Sylvie',
                    'lastName' => 'Moreau',
                    'email' => 's.moreau@industrie-moderne.fr',
                    'position' => 'Responsable Achats',
                    'phone' => '01 45 67 89 01',
                    'mobilePhone' => '07 12 34 56 78',
                    'address' => null,
                ],
                [
                    'firstName' => 'Thomas',
                    'lastName' => 'Legrand',
                    'email' => 't.legrand@industrie-moderne.fr',
                    'position' => 'Directeur Technique',
                    'phone' => '01 45 67 89 02',
                    'mobilePhone' => null,
                    'address' => null,
                ],
            ],
            'comments' => [
                ['note' => 'Contrat signé le 15/12/2023', 'createdAt' => '-60 days'],
                ['note' => 'Installation prévue fin janvier', 'createdAt' => '-30 days'],
                ['note' => 'Installation terminée, client satisfait', 'createdAt' => '-5 days'],
            ],
        ],

        // Clients pour l'utilisateur régulier
        'customer_regular_1' => [
            'name' => 'EcoHabitat SARL',
            'user' => 'regular_user',
            'origin' => ProspectOrigin::ACQUISITION,
            'status' => ProspectStatus::IN_PROGRESS,
            'siret' => '45678912345678',
            'address' => '25 rue des Fleurs, 69001 Lyon',
            'action' => 'Étude consommation',
            'worth' => '8000',
            'commision' => '800',
            'margin' => '8%',
            'companyGroup' => null,
            'canalSignature' => null,
            'contacts' => [
                [
                    'firstName' => 'Martine',
                    'lastName' => 'Dubois',
                    'email' => 'martine@ecohabitat.fr',
                    'position' => 'Gérante',
                    'phone' => '04 78 12 34 56',
                    'mobilePhone' => '06 78 91 23 45',
                    'address' => null,
                ],
            ],
            'comments' => [
                ['note' => 'Rendez-vous initial programmé', 'createdAt' => '-10 days'],
            ],
        ],
        'customer_regular_2' => [
            'name' => 'Restaurant Le Gourmet',
            'user' => 'regular_user',
            'origin' => ProspectOrigin::ACQUISITION,
            'status' => ProspectStatus::LOST,
            'siret' => '65432198765432',
            'address' => '9 place Bellecour, 69002 Lyon',
            'action' => 'Audit énergétique',
            'worth' => '5000',
            'commision' => '500',
            'margin' => '9%',
            'companyGroup' => null,
            'canalSignature' => null,
            'contacts' => [
                [
                    'firstName' => 'Pierre',
                    'lastName' => 'Gagnaire',
                    'email' => 'contact@legourmet.fr',
                    'position' => 'Chef',
                    'phone' => '04 78 23 45 67',
                    'mobilePhone' => null,
                    'address' => null,
                ],
            ],
            'comments' => [
                ['note' => 'Client pas intéressé, concurrence déjà engagée', 'createdAt' => '-5 days'],
            ],
        ],

        // Clients pour l'utilisateur manager
        'customer_manager_1' => [
            'name' => 'SuperMarché Plus',
            'user' => 'manager_user',
            'origin' => ProspectOrigin::ACQUISITION,
            'status' => ProspectStatus::WON,
            'siret' => '13579246801234',
            'address' => '123 avenue de la République, 59000 Lille',
            'action' => 'Installation panneaux solaires',
            'worth' => '45000',
            'commision' => '4500',
            'margin' => '15%',
            'companyGroup' => 'Groupe Distribution Plus',
            'canalSignature' => CanalSignature::GDB,
            'contacts' => [
                [
                    'firstName' => 'Hélène',
                    'lastName' => 'Dupré',
                    'email' => 'h.dupre@supermarche-plus.com',
                    'position' => 'Directrice',
                    'phone' => '03 20 45 67 89',
                    'mobilePhone' => '06 45 67 89 01',
                    'address' => null,
                ],
            ],
            'comments' => [
                ['note' => 'Contrat signé pour 3 magasins', 'createdAt' => '-45 days'],
                ['note' => 'Première installation terminée', 'createdAt' => '-15 days'],
            ],
        ],

        // Clients pour l'utilisateur sales
        'customer_sales_1' => [
            'name' => 'Cabinet Medical Central',
            'user' => 'sales_user',
            'origin' => ProspectOrigin::ACQUISITION,
            'status' => ProspectStatus::IN_PROGRESS,
            'siret' => '24681357924680',
            'address' => '45 boulevard Haussmann, 75009 Paris',
            'action' => 'Réduction facture énergétique',
            'worth' => '12000',
            'commision' => '1200',
            'margin' => '11%',
            'companyGroup' => null,
            'canalSignature' => null,
            'contacts' => [
                [
                    'firstName' => 'Antoine',
                    'lastName' => 'Mercier',
                    'email' => 'dr.mercier@cabinet-medical.fr',
                    'position' => 'Médecin responsable',
                    'phone' => '01 56 78 90 12',
                    'mobilePhone' => '07 56 78 90 12',
                    'address' => '45 boulevard Haussmann, 75009 Paris',
                ],
            ],
            'comments' => [
                ['note' => 'Devis envoyé, en attente de retour', 'createdAt' => '-7 days'],
            ],
        ],
        'customer_sales_2' => [
            'name' => 'Hotel Luxe Palace',
            'user' => 'sales_user',
            'origin' => ProspectOrigin::RENOUVELLEMENT,
            'status' => ProspectStatus::IN_PROGRESS,
            'siret' => '36925814736925',
            'address' => '1 place Vendôme, 75001 Paris',
            'action' => 'Renouvellement contrat',
            'worth' => '75000',
            'commision' => '7500',
            'margin' => '14%',
            'companyGroup' => 'Groupe Hôtelier International',
            'canalSignature' => CanalSignature::GDB,
            'contacts' => [
                [
                    'firstName' => 'Isabelle',
                    'lastName' => 'Fontaine',
                    'email' => 'i.fontaine@luxepalace.com',
                    'position' => 'Directrice',
                    'phone' => '01 47 58 69 70',
                    'mobilePhone' => '06 47 58 69 70',
                    'address' => null,
                ],
                [
                    'firstName' => 'Laurent',
                    'lastName' => 'Blanchard',
                    'email' => 'l.blanchard@luxepalace.com',
                    'position' => 'Responsable technique',
                    'phone' => '01 47 58 69 71',
                    'mobilePhone' => null,
                    'address' => null,
                ],
            ],
            'comments' => [
                ['note' => 'Négociation en cours, veut une réduction de 5%', 'createdAt' => '-3 days'],
            ],
        ],

        // Client non attribué
        'customer_unassigned' => [
            'name' => 'Boulangerie Artisanale',
            'user' => null,
            'origin' => ProspectOrigin::ACQUISITION,
            'status' => ProspectStatus::IN_PROGRESS,
            'siret' => '14725836914725',
            'address' => '12 rue du Commerce, 33000 Bordeaux',
            'action' => 'Réduction consommation fours',
            'worth' => '7500',
            'commision' => '750',
            'margin' => '10%',
            'companyGroup' => null,
            'canalSignature' => null,
            'contacts' => [
                [
                    'firstName' => 'Michel',
                    'lastName' => 'Boulanger',
                    'email' => 'contact@boulangerie-artisanale.fr',
                    'position' => 'Propriétaire',
                    'phone' => '05 56 78 90 12',
                    'mobilePhone' => '06 56 78 90 12',
                    'address' => null,
                ],
            ],
            'comments' => [
                ['note' => 'Prospect à assigner à un commercial', 'createdAt' => '-1 days'],
            ],
        ],
    ];

    public function load(ObjectManager $manager): void
    {
        // Utiliser la constante CUSTOMERS pour itérer sur les données
        foreach (self::CUSTOMERS as $reference => $customerData) {
            $customer = new Customer();
            $customer->setName($customerData['name']);
            $customer->setOrigin($customerData['origin']);

            $customer->setStatus($customerData['status']);

            $customer->setSiret($customerData['siret']);

            $customer->setAddress($customerData['address']);

            $customer->setAction($customerData['action']);

            $customer->setWorth($customerData['worth']);

            $customer->setCommision($customerData['commision']);

            $customer->setMargin($customerData['margin']);

            if ($customerData['companyGroup']) {
                $customer->setCompanyGroup($customerData['companyGroup']);
            }

            if ($customerData['canalSignature']) {
                $customer->setCanalSignature($customerData['canalSignature']);
            }

            // Attribuer l'utilisateur s'il est défini
            if ($customerData['user']) {
                $customer->setUser($this->getReference($customerData['user'], User::class));
            }

            // Ajouter les contacts
            foreach ($customerData['contacts'] as $contactData) {
                $contact = new Contact();
                $contact->setFirstName($contactData['firstName']);
                $contact->setLastName($contactData['lastName']);
                $contact->setEmail($contactData['email']);

                $contact->setPosition($contactData['position']);

                $contact->setPhone($contactData['phone']);

                if ($contactData['mobilePhone']) {
                    $contact->setMobilePhone($contactData['mobilePhone']);
                }

                if ($contactData['address']) {
                    $contact->setAddress($contactData['address']);
                }

                $customer->addContact($contact);
                $manager->persist($contact);
            }

            // Ajouter les commentaires
            foreach ($customerData['comments'] as $commentData) {
                $comment = new Comment();
                $comment->setNote($commentData['note']);

                $createdAt = new \DateTime($commentData['createdAt']);
                $comment->setCreatedAt($createdAt);

                $customer->addComment($comment);
                $manager->persist($comment);
            }

            $manager->persist($customer);

            // Ajouter une référence pour utilisation ultérieure
            $this->addReference($reference, $customer);
        }

        $manager->flush();
    }

    public function getDependencies(): array
    {
        return [
            UserFixtures::class,
        ];
    }
}
