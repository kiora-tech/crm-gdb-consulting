<?php

namespace App\DataFixtures;

use App\Entity\CanalSignature;
use App\Entity\Comment;
use App\Entity\Contact;
use App\Entity\Customer;
use App\Entity\Energy;
use App\Entity\EnergyProvider;
use App\Entity\ProspectOrigin;
use App\Entity\ProspectStatus;
use App\Entity\User;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;
use Faker\Factory as FakerFactory;

class MassCustomerFixtures extends Fixture implements DependentFixtureInterface
{
    private $faker;
    
    public function __construct()
    {
        $this->faker = FakerFactory::create('fr_FR');
    }
    
    public function load(ObjectManager $manager): void
    {
        // Récupérer les utilisateurs
        $users = [
            $this->getReference('admin_user', User::class),
            $this->getReference('regular_user', User::class),
            $this->getReference('manager_user', User::class),
            $this->getReference('sales_user', User::class),
        ];
        
        // Récupérer les fournisseurs d'énergie
        $energyProviders = [];
        $providerReferences = [
            'provider_edf',
            'provider_engie',
            'provider_total_energies',
            'provider_ekwateur',
            'provider_eni',
            'provider_grdf',
            'provider_vattenfall',
            'provider_iberdrola',
            'provider_endesa',
            'provider_direct_energie',
        ];
        
        foreach ($providerReferences as $ref) {
            $energyProviders[] = $this->getReference($ref, EnergyProvider::class);
        }
        
        // Types d'entreprises pour des noms réalistes
        $companyTypes = [
            'Industries', 'Services', 'Distribution', 'Logistique', 'Transport',
            'Bâtiment', 'Construction', 'Immobilier', 'Hôtellerie', 'Restauration',
            'Commerce', 'Retail', 'Manufacturing', 'Production', 'Energie',
            'Technologies', 'Informatique', 'Consulting', 'Finance', 'Assurance'
        ];
        
        $companySuffixes = ['SA', 'SARL', 'SAS', 'EURL', 'SCI', 'SASU'];
        
        // Créer 100 clients
        for ($i = 1; $i <= 100; $i++) {
            $customer = new Customer();
            
            // Nom de l'entreprise
            $companyName = $this->faker->company() . ' ' . 
                          $this->faker->randomElement($companyTypes) . ' ' . 
                          $this->faker->randomElement($companySuffixes);
            $customer->setName($companyName);
            
            // Assigner un utilisateur (20% de chance d'être non assigné)
            if ($this->faker->numberBetween(1, 100) > 20) {
                $customer->setUser($this->faker->randomElement($users));
            }
            
            // Origine et statut
            $customer->setOrigin($this->faker->randomElement(ProspectOrigin::cases()));
            $customer->setStatus($this->faker->randomElement(ProspectStatus::cases()));
            
            // SIRET (14 chiffres)
            $customer->setSiret($this->faker->numerify('##############'));
            
            // Adresse
            $customer->setAddress(
                $this->faker->streetAddress() . ', ' . 
                $this->faker->postcode() . ' ' . 
                $this->faker->city()
            );
            
            // Action commerciale
            $actions = [
                'Renouvellement contrat',
                'Optimisation consommation',
                'Audit énergétique',
                'Installation panneaux solaires',
                'Réduction facture',
                'Changement fournisseur',
                'Négociation tarifs',
                'Étude comparative',
                'Migration énergie verte',
                'Optimisation puissance souscrite'
            ];
            $customer->setAction($this->faker->randomElement($actions));
            
            // Valeur du contrat
            $worth = $this->faker->numberBetween(5000, 150000);
            $customer->setWorth((string)$worth);
            
            // Commission (entre 5% et 15% de la valeur)
            $commissionRate = $this->faker->numberBetween(5, 15) / 100;
            $customer->setCommision((string)round($worth * $commissionRate));
            
            // Marge
            $customer->setMargin($this->faker->numberBetween(5, 20) . '%');
            
            // Groupe d'entreprises (30% de chance)
            if ($this->faker->numberBetween(1, 100) <= 30) {
                $customer->setCompanyGroup('Groupe ' . $this->faker->company());
            }
            
            // Canal de signature (70% de chance)
            if ($this->faker->numberBetween(1, 100) <= 70) {
                $customer->setCanalSignature($this->faker->randomElement(CanalSignature::cases()));
            }
            
            // Ajouter 1 à 3 contacts
            $nbContacts = $this->faker->numberBetween(1, 3);
            for ($j = 0; $j < $nbContacts; $j++) {
                $contact = new Contact();
                $contact->setFirstName($this->faker->firstName());
                $contact->setLastName($this->faker->lastName());
                $contact->setEmail($this->faker->companyEmail());
                
                $positions = [
                    'Directeur Général', 'Directeur Financier', 'Responsable Achats',
                    'Directeur Technique', 'Responsable Énergie', 'Chef d\'entreprise',
                    'Gérant', 'Responsable Administratif', 'Directeur des Opérations',
                    'Responsable Maintenance', 'Directeur Commercial', 'PDG'
                ];
                $contact->setPosition($this->faker->randomElement($positions));
                
                $contact->setPhone($this->faker->phoneNumber());
                
                // 70% de chance d'avoir un mobile
                if ($this->faker->numberBetween(1, 100) <= 70) {
                    $contact->setMobilePhone($this->faker->mobileNumber());
                }
                
                // 20% de chance d'avoir une adresse
                if ($this->faker->numberBetween(1, 100) <= 20) {
                    $contact->setAddress($customer->getAddress());
                }
                
                $customer->addContact($contact);
                $manager->persist($contact);
            }
            
            // Ajouter 0 à 5 commentaires
            $nbComments = $this->faker->numberBetween(0, 5);
            for ($j = 0; $j < $nbComments; $j++) {
                $comment = new Comment();
                
                $notes = [
                    'Premier contact établi',
                    'Devis envoyé',
                    'En attente de retour client',
                    'Relance prévue',
                    'Négociation en cours',
                    'Client intéressé par l\'offre',
                    'Rendez-vous programmé',
                    'Visite technique effectuée',
                    'Contrat en cours de signature',
                    'Documents reçus',
                    'Analyse de consommation terminée',
                    'Proposition commerciale validée',
                    'Client demande des modifications',
                    'Concurrent contacté par le client',
                    'Budget validé en interne'
                ];
                
                $comment->setNote($this->faker->randomElement($notes));
                $comment->setCreatedAt($this->faker->dateTimeBetween('-6 months', 'now'));
                
                $customer->addComment($comment);
                $manager->persist($comment);
            }
            
            // Ajouter 1 à 3 contrats d'énergie
            $nbEnergies = $this->faker->numberBetween(1, 3);
            for ($j = 0; $j < $nbEnergies; $j++) {
                $energy = new Energy();
                
                // Code unique
                $energy->setCode('CTR-' . str_pad($i, 4, '0', STR_PAD_LEFT) . '-' . ($j + 1));
                
                // Fournisseur
                $energy->setEnergyProvider($this->faker->randomElement($energyProviders));
                
                // Dates de contrat
                // Pour créer des contrats variés (passés, actuels, futurs)
                $contractStart = $this->faker->dateTimeBetween('-2 years', '+6 months');
                $energy->setContractStart($contractStart);
                
                // Durée du contrat entre 1 et 3 ans
                $contractDuration = $this->faker->numberBetween(1, 3);
                $contractEnd = clone $contractStart;
                $contractEnd->modify('+' . $contractDuration . ' years');
                $energy->setContractEnd($contractEnd);
                
                // Consommation annuelle (en kWh)
                $energy->setAnnualConsumption($this->faker->numberBetween(10000, 1000000));
                
                // Prix unitaire (en centimes d'euro)
                $energy->setUnitPrice($this->faker->randomFloat(2, 8, 15));
                
                // Puissance souscrite (en kVA)
                $powers = [36, 42, 48, 54, 60, 72, 84, 96, 108, 120, 132, 144, 156, 168, 180];
                $energy->setSubscribedPower($this->faker->randomElement($powers));
                
                // Type d'énergie (70% électricité, 30% gaz)
                $energy->setEnergyType($this->faker->numberBetween(1, 100) <= 70 ? 'Électricité' : 'Gaz');
                
                $customer->addEnergy($energy);
                $manager->persist($energy);
            }
            
            $manager->persist($customer);
            
            // Flush par batch de 20 pour optimiser les performances
            if ($i % 20 === 0) {
                $manager->flush();
            }
        }
        
        // Flush final
        $manager->flush();
    }
    
    public function getDependencies(): array
    {
        return [
            UserFixtures::class,
            EnergyProviderFixtures::class,
        ];
    }
}