<?php

namespace App\Service;

use App\Data\CustomerSearchData;
use App\Repository\EnergyProviderRepository;
use App\Repository\UserRepository;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Session\SessionInterface;

class SearchFilterService
{
    public function __construct(
        private LoggerInterface $logger,
        private UserRepository $userRepository,
        private EnergyProviderRepository $energyProviderRepository,
    ) {
    }

    public function saveSearchData(SessionInterface $session, CustomerSearchData $data): void
    {
        try {
            // Préparer les données à sauvegarder
            $searchData = [
                'name' => $data->name,
                'contactName' => $data->contactName,
                'unassigned' => $data->unassigned,
                'leadOrigin' => $data->leadOrigin,
                'code' => $data->code,
                'contractEndAfter' => $data->contractEndAfter?->format('Y-m-d'),
                'contractEndBefore' => $data->contractEndBefore?->format('Y-m-d'),
                'userId' => $data->user?->getId(),
                'originValue' => $data->origin?->value,
                'energyProviderId' => $data->energyProvider?->getId(),
                'statusValue' => $data->status?->value,
            ];

            $session->set('customer_search', json_encode($searchData));

            $this->logger->info('SearchFilterService: Search data saved to session', [
                'data' => $searchData,
            ]);
        } catch (\Exception $e) {
            $this->logger->error('SearchFilterService: Failed to save search data', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        }
    }

    public function loadSearchData(SessionInterface $session): CustomerSearchData
    {
        $data = new CustomerSearchData();

        try {
            if (!$session->has('customer_search')) {
                return $data;
            }

            $sessionData = json_decode($session->get('customer_search'), true);

            if (!is_array($sessionData)) {
                $this->logger->warning('SearchFilterService: Invalid session data format');

                return $data;
            }

            $this->logger->info('SearchFilterService: Loading search data from session', [
                'session_data' => $sessionData,
            ]);

            // Restaurer les données simples
            $data->name = $sessionData['name'] ?? '';
            $data->contactName = $sessionData['contactName'] ?? '';
            $data->unassigned = $sessionData['unassigned'] ?? false;
            $data->leadOrigin = $sessionData['leadOrigin'] ?? '';
            $data->code = $sessionData['code'] ?? '';

            // Restaurer les dates
            if (!empty($sessionData['contractEndAfter'])) {
                $data->contractEndAfter = new \DateTime($sessionData['contractEndAfter']);
            }
            if (!empty($sessionData['contractEndBefore'])) {
                $data->contractEndBefore = new \DateTime($sessionData['contractEndBefore']);
            }

            // Restaurer les entités
            if (!empty($sessionData['userId'])) {
                $data->user = $this->userRepository->find($sessionData['userId']);
            }
            if (!empty($sessionData['originValue'])) {
                $data->origin = \App\Entity\ProspectOrigin::from($sessionData['originValue']);
            }
            if (!empty($sessionData['energyProviderId'])) {
                $data->energyProvider = $this->energyProviderRepository->find($sessionData['energyProviderId']);
            }
            if (!empty($sessionData['statusValue'])) {
                $data->status = \App\Entity\ProspectStatus::from($sessionData['statusValue']);
            }

            $this->logger->info('SearchFilterService: Search data loaded successfully');
        } catch (\Exception $e) {
            $this->logger->error('SearchFilterService: Failed to load search data', [
                'error' => $e->getMessage(),
                'session_data' => $session->get('customer_search'),
                'trace' => $e->getTraceAsString(),
            ]);
        }

        return $data;
    }

    public function clearSearchData(SessionInterface $session): void
    {
        $session->remove('customer_search');
        $this->logger->info('SearchFilterService: Search data cleared from session');
    }
}
