<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Message\RefreshMicrosoftCalendarsMessage;
use App\Repository\UserRepository;
use App\Service\MicrosoftGraphService;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Contracts\Cache\CacheInterface;

#[AsMessageHandler]
class RefreshMicrosoftCalendarsHandler
{
    private const int TTL_CALENDARS = 3600; // 1 hour

    public function __construct(
        private readonly UserRepository $userRepository,
        private readonly MicrosoftGraphService $microsoftGraphService,
        private readonly CacheInterface $microsoftGraphCache,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function __invoke(RefreshMicrosoftCalendarsMessage $message): void
    {
        $userId = $message->getUserId();

        $this->logger->info('Refreshing Microsoft calendars in background', [
            'user_id' => $userId,
        ]);

        try {
            $user = $this->userRepository->find($userId);
            if (!$user) {
                $this->logger->error('User not found for refresh', ['user_id' => $userId]);

                return;
            }

            // Fetch fresh data from Microsoft
            $calendars = $this->microsoftGraphService->getUserCalendars($user);

            // Update cache
            $cacheKey = sprintf('ms_graph:%d:calendars', $userId);
            $cacheItem = $this->microsoftGraphCache->getItem($cacheKey);
            $cacheItem->set([
                'data' => $calendars,
                'timestamp' => time(),
            ]);
            $cacheItem->expiresAfter(self::TTL_CALENDARS);
            $this->microsoftGraphCache->save($cacheItem);

            $this->logger->info('Microsoft calendars refreshed successfully', [
                'user_id' => $userId,
                'count' => count($calendars),
            ]);
        } catch (\Exception $e) {
            $this->logger->error('Error refreshing Microsoft calendars', [
                'user_id' => $userId,
                'error' => $e->getMessage(),
            ]);
            // Ne pas rethrow - le message sera retryé automatiquement
            throw $e;
        }
    }
}
