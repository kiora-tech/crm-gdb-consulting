<?php

namespace App\Service\Template;

use App\Entity\Customer;
use App\Entity\User;
use Doctrine\Common\Collections\Collection;
use Psr\Log\LoggerInterface;
use Symfony\Component\PropertyAccess\PropertyAccess;
use Symfony\Component\PropertyAccess\PropertyAccessor;

class VariableResolver
{
    private PropertyAccessor $propertyAccessor;

    public function __construct(
        private readonly LoggerInterface $logger,
    ) {
        $this->propertyAccessor = PropertyAccess::createPropertyAccessor();
    }

    /**
     * Résout une variable en fonction du contexte.
     */
    public function resolve(string $variable, Customer $customer, ?User $currentUser = null): mixed
    {
        // Vérifier si c'est une variable spéciale
        if (str_starts_with($variable, 'date.') || str_starts_with($variable, 'time.')) {
            return $this->resolveDateTimeVariable($variable);
        } elseif (str_starts_with($variable, 'user.') && null !== $currentUser) {
            return $this->resolveUserVariable($variable, $currentUser);
        } elseif (str_starts_with($variable, 'customer.')) {
            // Variables spéciales du customer
            $customerVar = substr($variable, 9); // Enlever "customer."
            if ('addressFull' === $customerVar) {
                return $customer->getAddressFull();
            } elseif ('addressMultiline' === $customerVar) {
                return $customer->getAddressMultiline();
            } elseif (str_starts_with($customerVar, 'primaryContact.')) {
                // Gérer les variables du contact principal
                $contactVar = substr($customerVar, 15); // Enlever "primaryContact."
                $primaryContact = $customer->getPrimaryContact();
                if ($primaryContact) {
                    return $this->resolveValue($primaryContact, $contactVar);
                }

                return '';
            } elseif (str_starts_with($customerVar, 'energiesElec[')) {
                // Filtre pour les énergies électriques
                return $this->resolveFilteredEnergies($customer, $customerVar, 'ELEC');
            } elseif (str_starts_with($customerVar, 'energiesGaz[')) {
                // Filtre pour les énergies gaz
                return $this->resolveFilteredEnergies($customer, $customerVar, 'GAZ');
            } elseif (str_starts_with($customerVar, 'energiesFuture[')) {
                // Filtre pour les énergies avec contrats futurs
                return $this->resolveFilteredEnergies($customer, $customerVar, null, true);
            } elseif (str_starts_with($customerVar, 'energiesFutureElec[')) {
                // Filtre pour les énergies électriques avec contrats futurs
                return $this->resolveFilteredEnergies($customer, $customerVar, 'ELEC', true);
            } elseif (str_starts_with($customerVar, 'energiesFutureGaz[')) {
                // Filtre pour les énergies gaz avec contrats futurs
                return $this->resolveFilteredEnergies($customer, $customerVar, 'GAZ', true);
            } else {
                return $this->resolveValue($customer, $customerVar);
            }
        } else {
            // Variables normales du customer
            return $this->resolveValue($customer, $variable);
        }
    }

    /**
     * Formate une valeur en chaîne de caractères pour l'insertion dans un template.
     */
    public function format(mixed $value): string
    {
        if (null === $value) {
            return '';
        }

        if ($value instanceof \DateTimeInterface) {
            return $value->format('d/m/Y');
        }

        if ($value instanceof Collection) {
            return (string) $value->count();
        }

        if ($value instanceof \BackedEnum) {
            return (string) $value->value;
        }

        if (is_bool($value)) {
            return $value ? 'Oui' : 'Non';
        }

        if (is_array($value)) {
            $stringArray = array_map(function ($item) {
                return (string) $item;
            }, $value);

            return implode(', ', $stringArray);
        }

        if (is_object($value) && method_exists($value, '__toString')) {
            return $value->__toString();
        }

        return (string) $value;
    }

    /**
     * Résout les variables de date et heure.
     */
    private function resolveDateTimeVariable(string $variable): string
    {
        $now = new \DateTime();

        return match ($variable) {
            'date.today' => $now->format('d/m/Y'),
            'date.todayLong' => $this->formatLongDate($now),
            'time.now' => $now->format('H:i'),
            'date.dayName' => $this->getDayName($now),
            'date.month' => $this->getMonthName($now),
            'date.year' => $now->format('Y'),
            default => '',
        };
    }

    /**
     * Résout les variables utilisateur.
     */
    private function resolveUserVariable(string $variable, User $user): mixed
    {
        $userVar = substr($variable, 5); // Enlever "user."

        return match ($userVar) {
            'name' => trim(($user->getFirstName() ?? '').' '.($user->getLastName() ?? $user->getName() ?? '')),
            'firstName' => $user->getFirstName() ?? '',
            'lastName' => $user->getLastName() ?? $user->getName() ?? '',
            'email' => $user->getEmail(),
            'phone' => $user->getPhone() ?? '',
            'title' => $user->getTitle() ?? '',
            'signature' => $user->getSignature() ?? '',
            default => $this->resolveValue($user, $userVar),
        };
    }

    /**
     * Résout les variables d'énergies filtrées.
     */
    private function resolveFilteredEnergies(Customer $customer, string $variable, ?string $type = null, bool $futureOnly = false): mixed
    {
        // Extraire l'index et la propriété
        preg_match('/energies(?:Elec|Gaz|Future|FutureElec|FutureGaz)?\[(\d+)\]\.(.+)/', $variable, $matches);
        if (count($matches) < 3) {
            return '';
        }

        $index = (int) $matches[1];
        $property = $matches[2];

        // Filtrer les énergies
        $energies = [];
        $now = new \DateTime();

        foreach ($customer->getEnergies() as $energy) {
            // Filtre par type
            if (null !== $type && $energy->getType()->value !== $type) {
                continue;
            }

            // Filtre par date future
            if ($futureOnly) {
                $contractEnd = $energy->getContractEnd();
                if (!$contractEnd || $contractEnd <= $now) {
                    continue;
                }
            }

            $energies[] = $energy;
        }

        // Retourner l'énergie à l'index demandé
        if (isset($energies[$index])) {
            return $this->resolveValue($energies[$index], $property);
        }

        return '';
    }

    private function resolveValue(object $object, string $path): mixed
    {
        try {
            $trimmedPath = trim($path);
            $this->logger->debug('Tentative de résolution de valeur', [
                'path' => $trimmedPath,
                'object_class' => get_class($object),
            ]);

            // Vérifier si le chemin est accessible
            if (!$this->propertyAccessor->isReadable($object, $trimmedPath)) {
                $this->logger->warning('Propriété non accessible', [
                    'path' => $trimmedPath,
                    'object_class' => get_class($object),
                    'available_properties' => $this->getObjectProperties($object),
                ]);

                return '';
            }

            $value = $this->propertyAccessor->getValue($object, $trimmedPath);
            $this->logger->debug('Valeur résolue avec succès', [
                'path' => $trimmedPath,
                'value_type' => is_object($value) ? get_class($value) : gettype($value),
            ]);

            return $value;
        } catch (\Exception $e) {
            $this->logger->error('Échec de résolution de valeur', [
                'path' => $path,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'object_properties' => $this->getObjectProperties($object),
            ]);

            return '';
        }
    }

    /**
     * Récupère les propriétés disponibles d'un objet pour le débogage.
     *
     * @return array<int, string>
     */
    private function getObjectProperties(object $object): array
    {
        $properties = [];

        try {
            $reflection = new \ReflectionObject($object);
            foreach ($reflection->getProperties() as $property) {
                $property->setAccessible(true);
                $properties[] = $property->getName();
            }

            // Ajouter les méthodes getters
            foreach ($reflection->getMethods(\ReflectionMethod::IS_PUBLIC) as $method) {
                $methodName = $method->getName();
                if (0 === strpos($methodName, 'get') && 0 === $method->getNumberOfRequiredParameters()) {
                    $properties[] = lcfirst(substr($methodName, 3));
                }
            }
        } catch (\Exception $e) {
            $this->logger->warning('Échec de récupération des propriétés de l\'objet', [
                'error' => $e->getMessage(),
            ]);
        }

        return $properties;
    }

    /**
     * Formate une date en français long.
     */
    private function formatLongDate(\DateTimeInterface $date): string
    {
        $months = [
            1 => 'janvier', 2 => 'février', 3 => 'mars', 4 => 'avril',
            5 => 'mai', 6 => 'juin', 7 => 'juillet', 8 => 'août',
            9 => 'septembre', 10 => 'octobre', 11 => 'novembre', 12 => 'décembre',
        ];

        $day = $date->format('j');
        $month = $months[(int) $date->format('n')];
        $year = $date->format('Y');

        return sprintf('%d %s %d', $day, $month, $year);
    }

    /**
     * Retourne le nom du jour en français.
     */
    private function getDayName(\DateTimeInterface $date): string
    {
        $days = [
            1 => 'Lundi', 2 => 'Mardi', 3 => 'Mercredi', 4 => 'Jeudi',
            5 => 'Vendredi', 6 => 'Samedi', 7 => 'Dimanche',
        ];

        return $days[(int) $date->format('N')];
    }

    /**
     * Retourne le nom du mois en français.
     */
    private function getMonthName(\DateTimeInterface $date): string
    {
        $months = [
            1 => 'Janvier', 2 => 'Février', 3 => 'Mars', 4 => 'Avril',
            5 => 'Mai', 6 => 'Juin', 7 => 'Juillet', 8 => 'Août',
            9 => 'Septembre', 10 => 'Octobre', 11 => 'Novembre', 12 => 'Décembre',
        ];

        return $months[(int) $date->format('n')];
    }
}
